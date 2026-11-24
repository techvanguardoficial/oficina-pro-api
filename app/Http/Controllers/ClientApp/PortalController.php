<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\CarMileage;
use App\Models\ClientAppUser;
use App\Models\ClientVehiclePhoto;
use App\Models\Company;
use App\Models\OrderService;
use App\Models\Scopes\CompanyScope;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    private function appUser(): ClientAppUser
    {
        return auth('client')->user();
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('supabase');
    }

    private function storageUrl(string $path): string
    {
        return $this->disk()->url($path);
    }

    private function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'supabase');
    }

    private function deleteFile(string $path): void
    {
        $this->disk()->delete($path);
    }

    // ─── Perfil ──────────────────────────────────────────────────────────────

    public function me()
    {
        $user = $this->appUser();
        return response()->json([
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone,
            'cpf'                  => $user->cpf,
            'avatar'               => $user->avatar ? $this->storageUrl($user->avatar) : null,
            'onboarding_completed' => $user->onboarding_completed,
        ]);
    }

    public function updateMe(Request $request)
    {
        $user = $this->appUser();

        $request->validate([
            'name'     => 'sometimes|string|max:100',
            'email'    => 'sometimes|email|unique:client_app_users,email,' . $user->id,
            'phone'    => 'sometimes|string|max:20',
            'cpf'      => 'sometimes|nullable|string|max:14',
            'password' => 'sometimes|string|min:6|confirmed',
            'current_password' => 'nullable|string',
        ]);

        // Trocar a senha ou o e-mail (que recupera a senha) pede a senha atual:
        // sem isso, quem pegasse o celular desbloqueado tomava a conta.
        $changingPassword = $request->filled('password');
        $changingEmail    = $request->filled('email') && strcasecmp($request->email, (string) $user->email) !== 0;

        if (($changingPassword || $changingEmail)
            && !Hash::check((string) $request->current_password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual não confere.',
            ]);
        }

        $data = $request->only(['name', 'email', 'phone', 'cpf']);
        if ($changingPassword) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Senha nova encerra as sessões dos outros aparelhos; a atual continua
        if ($changingPassword) {
            $currentId = $user->currentAccessToken()?->getKey();
            $user->tokens()->when($currentId, fn($q) => $q->where('id', '!=', $currentId))->delete();
        }

        return response()->json(['message' => 'Dados atualizados.', 'user' => $user->fresh()]);
    }

    public function updateAvatar(Request $request)
    {
        $request->validate(['photo' => 'required|image|max:5120']);

        $user = $this->appUser();

        if ($user->avatar) {
            $this->deleteFile($user->avatar);
        }

        $path = $this->storeFile($request->file('photo'), 'client-avatars');
        $user->update(['avatar' => $path]);

        return response()->json(['avatar' => $this->storageUrl($path)]);
    }

    // ─── Veículos ────────────────────────────────────────────────────────────

    public function vehicles()
    {
        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        $photos = ClientVehiclePhoto::where('client_app_user_id', $user->id)
            ->get()
            ->keyBy('placa');

        $all = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->with(['carModel.maker'])
            ->get();

        $lastVisits = $this->lastVisitsByPlaca($all);

        $vehicles = $all
            ->groupBy(fn($v) => $this->normalizePlaca($v->placa))
            ->map(fn($group, $placa) => $this->vehicleResource(
                $group->first(),
                $photos->get($group->first()->placa),
                $lastVisits[$placa] ?? null
            ))
            ->values();

        return response()->json($vehicles);
    }

    public function vehicle(string $placa)
    {
        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        $vehicle = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->with(['carModel.maker'])
            ->firstOrFail();

        $photo = ClientVehiclePhoto::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->first();

        $samePlaca = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->whereRaw('UPPER(REPLACE(placa, " ", "")) = ?', [$this->normalizePlaca($placa)])
            ->get(['id', 'placa']);
        $lastVisit = $this->lastVisitsByPlaca($samePlaca)[$this->normalizePlaca($placa)] ?? null;

        return response()->json($this->vehicleResource($vehicle, $photo, $lastVisit));
    }

    public function updateVehiclePhoto(Request $request, string $placa)
    {
        $request->validate(['photo' => 'required|image|max:5120']);

        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        $record = ClientVehiclePhoto::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->first();

        if ($record) {
            $this->deleteFile($record->photo_path);
        }

        $path = $this->storeFile($request->file('photo'), 'vehicle-photos');

        ClientVehiclePhoto::updateOrCreate(
            ['client_app_user_id' => $user->id, 'placa' => strtoupper($placa)],
            ['photo_path' => $path]
        );

        return response()->json(['photo' => $this->storageUrl($path)]);
    }

    // ─── Lembretes de Manutenção ─────────────────────────────────────────────
    //
    // Cada lembrete conta a partir da última vez que o serviço foi feito (data e km
    // informados pelo cliente; padrão: hoje e o km atual). Vence no que chegar
    // primeiro: a data (last_done_at + interval_months) ou o km (last_done_km + interval_km).
    // O km "atual" é o último registrado por uma oficina; o Portal não sabe o km do dia.

    public function maintenanceSchedules(string $placa)
    {
        $user    = $this->appUser();
        $vehicle = $this->ownedVehicle($placa);
        [$currentKm, $kmDate] = $this->currentKmOf($vehicle);

        $schedules = VehicleMaintenanceSchedule::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->orderBy('description')
            ->get()
            ->map(fn($s) => $this->scheduleResource($s, $currentKm))
            ->sortBy(fn($s) => self::STATUS_ORDER[$s['status']] ?? 9)
            ->values();

        return response()->json([
            'current_km'      => $currentKm,
            'current_km_date' => $kmDate,
            'schedules'       => $schedules,
        ]);
    }

    public function storeMaintenanceSchedule(Request $request, string $placa)
    {
        $data = $request->validate($this->scheduleRules(creating: true));

        $user    = $this->appUser();
        $vehicle = $this->ownedVehicle($placa);
        [$currentKm] = $this->currentKmOf($vehicle);

        $schedule = VehicleMaintenanceSchedule::create([
            'client_app_user_id' => $user->id,
            'placa'              => strtoupper($placa),
            'description'        => $data['description'],
            'interval_km'        => $data['interval_km'] ?? null,
            'interval_months'    => $data['interval_months'] ?? null,
            // Sem informação, conta a partir de hoje: um lembrete novo nunca nasce vencido
            'last_done_at'       => $data['last_done_at'] ?? now()->toDateString(),
            'last_done_km'       => array_key_exists('last_done_km', $data) ? $data['last_done_km'] : $currentKm,
        ]);

        return response()->json($this->scheduleResource($schedule, $currentKm), 201);
    }

    public function updateMaintenanceSchedule(Request $request, string $placa, int $id)
    {
        $data = $request->validate($this->scheduleRules(creating: false));

        $schedule = $this->ownedSchedule($placa, $id);
        $schedule->fill($data);

        if (!$schedule->interval_km && !$schedule->interval_months) {
            throw ValidationException::withMessages([
                'interval_km' => 'Informe de quantos em quantos km ou meses.',
            ]);
        }

        $schedule->save();
        [$currentKm] = $this->currentKmOf($this->ownedVehicle($placa));

        return response()->json($this->scheduleResource($schedule->fresh(), $currentKm));
    }

    /** "Feito hoje": recomeça a contagem a partir de hoje e do km atual. */
    public function completeMaintenanceSchedule(string $placa, int $id)
    {
        $schedule = $this->ownedSchedule($placa, $id);
        [$currentKm] = $this->currentKmOf($this->ownedVehicle($placa));

        $schedule->update([
            'last_done_at' => now()->toDateString(),
            'last_done_km' => $currentKm,
        ]);

        return response()->json($this->scheduleResource($schedule->fresh(), $currentKm));
    }

    public function deleteMaintenanceSchedule(string $placa, int $id)
    {
        $this->ownedSchedule($placa, $id)->delete();

        return response()->json(['message' => 'Lembrete removido.']);
    }

    private const STATUS_ORDER = ['overdue' => 0, 'due_soon' => 1, 'ok' => 2, 'no_data' => 3];

    private function scheduleRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'description'     => "$required|string|max:100",
            'interval_km'     => ($creating ? 'required_without:interval_months|' : '') . 'nullable|integer|min:1|max:200000',
            'interval_months' => ($creating ? 'required_without:interval_km|' : '') . 'nullable|integer|min:1|max:120',
            'last_done_at'    => 'nullable|date|before_or_equal:today',
            'last_done_km'    => 'nullable|integer|min:0|max:2000000',
        ];
    }

    private function ownedVehicle(string $placa): Vehicle
    {
        return Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $this->appUser()->clientIds())
            ->where('placa', strtoupper($placa))
            ->firstOrFail();
    }

    private function ownedSchedule(string $placa, int $id): VehicleMaintenanceSchedule
    {
        return VehicleMaintenanceSchedule::where('client_app_user_id', $this->appUser()->id)
            ->where('placa', strtoupper($placa))
            ->findOrFail($id);
    }

    /** [km, 'Y-m-d' da leitura] do último km registrado por uma oficina (ou do cadastro). */
    private function currentKmOf(Vehicle $vehicle): array
    {
        $last = CarMileage::query()
            ->where('vehicle_id', $vehicle->id)
            ->latest('created_at')
            ->first(['mileage', 'created_at']);

        if ($last && is_numeric($last->mileage) && (int) $last->mileage > 0) {
            return [(int) $last->mileage, Carbon::parse($last->created_at)->toDateString()];
        }

        $km = is_numeric($vehicle->km) && (int) $vehicle->km > 0 ? (int) $vehicle->km : null;

        return [$km, null];
    }

    private function scheduleResource(VehicleMaintenanceSchedule $s, ?int $currentKm): array
    {
        $today = Carbon::today();
        $nextDate = $daysLeft = $nextKm = $kmLeft = null;
        $levels = [];

        if ($s->interval_months && $s->last_done_at) {
            $due      = Carbon::parse($s->last_done_at)->addMonthsNoOverflow($s->interval_months);
            $nextDate = $due->toDateString();
            $daysLeft = (int) $today->diffInDays($due, false);
            $window   = max(7, (int) round($s->interval_months * 30 * 0.2)); // últimos 20% do prazo
            $levels[] = $daysLeft <= 0 ? 'overdue' : ($daysLeft <= $window ? 'due_soon' : 'ok');
        }

        if ($s->interval_km && $s->last_done_km !== null) {
            $nextKm = $s->last_done_km + $s->interval_km;
            if ($currentKm !== null) {
                $kmLeft   = $nextKm - $currentKm;
                $levels[] = $kmLeft <= 0 ? 'overdue' : ($kmLeft <= $s->interval_km * 0.2 ? 'due_soon' : 'ok');
            }
        }

        $status = 'no_data';
        foreach (['overdue', 'due_soon', 'ok'] as $level) {
            if (in_array($level, $levels, true)) { $status = $level; break; }
        }

        return [
            'id'              => $s->id,
            'description'     => $s->description,
            'interval_km'     => $s->interval_km,
            'interval_months' => $s->interval_months,
            'last_done_at'    => $s->last_done_at?->toDateString(),
            'last_done_km'    => $s->last_done_km,
            'next_due_date'   => $nextDate,
            'next_due_km'     => $nextKm,
            'days_left'       => $daysLeft,
            'km_left'         => $kmLeft,
            'status'          => $status,
        ];
    }

    // ─── Oficinas / Histórico ────────────────────────────────────────────────

    public function vehicleWorkshops(string $placa)
    {
        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        $vehicleIds = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->whereRaw('UPPER(REPLACE(placa, " ", "")) = ?', [strtoupper(preg_replace('/\s+/', '', $placa))])
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            abort(404, 'Veículo não encontrado.');
        }

        $vehicle = Vehicle::withoutGlobalScope(CompanyScope::class)->find($vehicleIds->first());

        $photo = ClientVehiclePhoto::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->first();

        $workshops = OrderService::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->with('company:id,fantasy_name,name,logo')
            ->select('company_id')
            ->selectRaw('COUNT(*) as total_os')
            ->selectRaw('MAX(created_at) as last_visit')
            ->groupBy('company_id')
            ->orderByDesc('last_visit')
            ->get()
            ->map(fn($o) => [
                'company_id' => $o->company_id,
                'name'       => $o->company?->fantasy_name ?: $o->company?->name ?? 'Oficina',
                'logo_url'   => $o->company?->logo_url,
                'total_os'   => (int) $o->total_os,
                'last_visit' => $o->last_visit,
            ]);

        return response()->json([
            'vehicle'   => $this->vehicleResource($vehicle, $photo),
            'workshops' => $workshops,
        ]);
    }

    public function vehicleHistory(Request $request, string $placa)
    {
        $clientIds = $this->appUser()->clientIds();

        $vehicleIds = Vehicle::withoutGlobalScope(CompanyScope::class)
            ->whereIn('clients_id', $clientIds)
            ->whereRaw('UPPER(REPLACE(placa, " ", "")) = ?', [strtoupper(preg_replace('/\s+/', '', $placa))])
            ->pluck('id');

        if ($vehicleIds->isEmpty()) {
            abort(404, 'Veículo não encontrado.');
        }

        $vehicle   = Vehicle::withoutGlobalScope(CompanyScope::class)->with(['carModel.maker'])->find($vehicleIds->first());
        $perPage   = min(max((int) $request->query('per_page', 15), 1), 100);
        $companyId = $request->query('company_id');

        $query = OrderService::query()
            ->whereIn('vehicle_id', $vehicleIds);

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        $orders = $query
            ->with([
                'parts',
                'services' => fn($q) => $q->withoutGlobalScope(CompanyScope::class),
                'status',
                'type',
                'mileages',
                'payments',
                'company:id,fantasy_name,name',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $companyData = null;
        if ($companyId) {
            $company = Company::find((int) $companyId);
            if ($company) {
                $companyData = [
                    'id'           => $company->id,
                    'name'         => $company->name,
                    'fantasy_name' => $company->fantasy_name,
                    'address'      => $company->address ?? null,
                    'phone'        => $company->phone ?? null,
                    'email'        => $company->email ?? null,
                    'cnpj'         => $company->cnpj ?? null,
                    'logo_url'     => $company->logo_url,
                ];
            }
        }

        return response()->json([
            'vehicle'      => $this->vehicleResource($vehicle),
            'history'      => collect($orders->items())->map(fn($o) => $this->orderResource($o)),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'total'        => $orders->total(),
            'company'      => $companyData,
        ]);
    }

    // ─── Resources ───────────────────────────────────────────────────────────

    private function vehicleResource(Vehicle $v, ?ClientVehiclePhoto $photo = null, ?array $lastVisit = null): array
    {
        $km = $v->current_km;

        return [
            'placa'      => $v->placa,
            'make'       => $v->carModel?->maker?->manufacturer ?? '',
            'model'      => $v->carModel?->model ?? '',
            'year'       => $v->year ?: null,
            'color'      => $v->color ?: null,
            'km'         => is_numeric($km) ? (int) $km : null,
            'vin'        => $v->chassis,
            'photo'      => $photo ? $this->storageUrl($photo->photo_path) : null,
            'last_visit' => $lastVisit,
        ];
    }

    private function normalizePlaca(string $placa): string
    {
        return strtoupper(preg_replace('/\s+/', '', $placa));
    }

    /**
     * Última visita (OS mais recente) de cada placa, em qualquer oficina:
     * [placa => ['date' => 'Y-m-d', 'workshop' => nome, 'company_id' => id]].
     * Duas consultas no total, sem N+1.
     */
    private function lastVisitsByPlaca(Collection $vehicles): array
    {
        if ($vehicles->isEmpty()) {
            return [];
        }

        $placaById = $vehicles->mapWithKeys(fn($v) => [$v->id => $this->normalizePlaca($v->placa)]);

        $latestPerVehicle = OrderService::query()
            ->whereIn('vehicle_id', $placaById->keys())
            ->select('vehicle_id')
            ->selectRaw('MAX(created_at) as last_at')
            ->groupBy('vehicle_id');

        $orders = OrderService::query()
            ->joinSub($latestPerVehicle, 'latest', function ($join) {
                $join->on('order_services.vehicle_id', '=', 'latest.vehicle_id')
                     ->on('order_services.created_at', '=', 'latest.last_at');
            })
            ->with('company:id,fantasy_name,name')
            ->get(['order_services.id', 'order_services.vehicle_id', 'order_services.company_id', 'order_services.created_at']);

        $visits = [];
        foreach ($orders as $o) {
            $placa = $placaById[$o->vehicle_id] ?? null;
            if ($placa === null) {
                continue;
            }
            if (isset($visits[$placa]) && $visits[$placa]['at'] >= $o->created_at) {
                continue;
            }
            $visits[$placa] = [
                'at'         => $o->created_at,
                'date'       => $o->created_at?->toDateString(),
                'workshop'   => $o->company?->fantasy_name ?: ($o->company?->name ?? 'Oficina'),
                'company_id' => $o->company_id,
            ];
        }

        return array_map(fn($v) => array_diff_key($v, ['at' => true]), $visits);
    }

    private function orderResource(OrderService $o): array
    {
        $totalParts    = $o->parts->sum(fn($p) => (float)$p->quantity * (float)$p->unit_price);
        $totalServices = $o->services->sum('price');

        return [
            'id'       => $o->id,
            'date'     => $o->created_at?->toDateString(),
            'status'   => $o->status?->status ?? '',
            'type'     => $o->type?->type ?? '',
            'km'       => (int) ($o->mileages->last()?->mileage ?? 0),
            'info'     => $o->info,
            'oficina'  => $o->company?->fantasy_name ?? $o->company?->name ?? '',
            'total'    => $totalParts + $totalServices,
            'paid'     => $o->paid_total,
            'remaining' => max(0, round($totalParts + $totalServices - $o->paid_total, 2)),
            'payment_status' => $o->payment_status,
            'parts'    => $o->parts
                ->filter(fn($p) => trim((string) $p->description) !== '' && (float) $p->quantity > 0)
                ->values()
                ->map(fn($p) => [
                'description' => $p->description,
                'quantity'    => (int) $p->quantity,
                'unit_value'  => (float) $p->unit_price,
                'total'       => (float) $p->quantity * (float) $p->unit_price,
            ]),
            'services' => $o->services
                ->filter(fn($s) => trim((string) $s->description) !== '')
                ->values()
                ->map(fn($s) => [
                    'description' => $s->description,
                    'value'       => (float) $s->price,
                ]),
        ];
    }
}
