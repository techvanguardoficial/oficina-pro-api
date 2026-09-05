<?php

namespace App\Http\Controllers\ClientApp;

use App\Http\Controllers\Controller;
use App\Models\CarMileage;
use App\Models\ClientAppUser;
use App\Models\ClientVehiclePhoto;
use App\Models\OrderService;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
        ]);

        $data = $request->only(['name', 'email', 'phone', 'cpf']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

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

        $vehicles = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->with(['carModel.maker'])
            ->get()
            ->map(fn($v) => $this->vehicleResource($v, $photos->get($v->placa)));

        return response()->json($vehicles);
    }

    public function vehicle(string $placa)
    {
        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->with(['carModel.maker'])
            ->firstOrFail();

        $photo = ClientVehiclePhoto::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->first();

        return response()->json($this->vehicleResource($vehicle, $photo));
    }

    public function updateVehiclePhoto(Request $request, string $placa)
    {
        $request->validate(['photo' => 'required|image|max:5120']);

        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        Vehicle::withoutGlobalScopes()
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

    public function maintenanceSchedules(string $placa)
    {
        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        // Last mileage entry = KM + date at last workshop visit
        $lastMileage = CarMileage::withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->id)
            ->latest('created_at')
            ->first();

        $currentKm       = (int) ($vehicle->current_km ?? $vehicle->km ?? 0);
        $lastServiceKm   = $lastMileage ? (int) $lastMileage->mileage : null;
        $lastServiceDate = $lastMileage ? Carbon::parse($lastMileage->created_at) : null;
        $monthsSince     = $lastServiceDate ? (int) $lastServiceDate->diffInMonths(now()) : null;
        $kmSince         = ($lastServiceKm !== null) ? max(0, $currentKm - $lastServiceKm) : null;

        $schedules = VehicleMaintenanceSchedule::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->orderBy('description')
            ->get()
            ->map(function ($s) use ($kmSince, $monthsSince) {
                $status = 'no_data';

                if ($kmSince !== null || $monthsSince !== null) {
                    $overdue  = false;
                    $dueSoon  = false;

                    if ($s->interval_km && $kmSince !== null) {
                        $ratio = $kmSince / $s->interval_km;
                        if ($ratio >= 1)   $overdue = true;
                        elseif ($ratio >= 0.8) $dueSoon = true;
                    }

                    if ($s->interval_months && $monthsSince !== null) {
                        $ratio = $monthsSince / $s->interval_months;
                        if ($ratio >= 1)   $overdue = true;
                        elseif ($ratio >= 0.8) $dueSoon = true;
                    }

                    $status = $overdue ? 'overdue' : ($dueSoon ? 'due_soon' : 'ok');
                }

                return array_merge($s->toArray(), [
                    'status'       => $status,
                    'km_since'     => $kmSince,
                    'months_since' => $monthsSince,
                ]);
            });

        return response()->json($schedules);
    }

    public function storeMaintenanceSchedule(Request $request, string $placa)
    {
        $request->validate([
            'description'     => 'required|string|max:100',
            'interval_km'     => 'nullable|integer|min:1',
            'interval_months' => 'nullable|integer|min:1',
        ]);

        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        $schedule = VehicleMaintenanceSchedule::create([
            'client_app_user_id' => $user->id,
            'placa'              => strtoupper($placa),
            'description'        => $request->description,
            'interval_km'        => $request->interval_km,
            'interval_months'    => $request->interval_months,
        ]);

        return response()->json($schedule, 201);
    }

    public function updateMaintenanceSchedule(Request $request, string $placa, int $id)
    {
        $request->validate([
            'description'     => 'sometimes|string|max:100',
            'interval_km'     => 'nullable|integer|min:1',
            'interval_months' => 'nullable|integer|min:1',
        ]);

        $user = $this->appUser();

        $schedule = VehicleMaintenanceSchedule::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->findOrFail($id);

        $schedule->update($request->only(['description', 'interval_km', 'interval_months']));

        return response()->json($schedule->fresh());
    }

    public function deleteMaintenanceSchedule(string $placa, int $id)
    {
        $user = $this->appUser();

        $schedule = VehicleMaintenanceSchedule::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->findOrFail($id);

        $schedule->delete();

        return response()->json(['message' => 'Lembrete removido.']);
    }

    // ─── Oficinas / Histórico ────────────────────────────────────────────────

    public function vehicleWorkshops(string $placa)
    {
        $user      = $this->appUser();
        $clientIds = $user->clientIds();

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        $photo = ClientVehiclePhoto::where('client_app_user_id', $user->id)
            ->where('placa', strtoupper($placa))
            ->first();

        $workshops = OrderService::withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->id)
            ->with('company:id,fantasy_name,name')
            ->select('company_id')
            ->selectRaw('COUNT(*) as total_os')
            ->selectRaw('MAX(created_at) as last_visit')
            ->groupBy('company_id')
            ->orderByDesc('last_visit')
            ->get()
            ->map(fn($o) => [
                'company_id' => $o->company_id,
                'name'       => $o->company?->fantasy_name ?: $o->company?->name ?? 'Oficina',
                'total_os'   => $o->total_os,
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

        $vehicle = Vehicle::withoutGlobalScopes()
            ->whereIn('clients_id', $clientIds)
            ->where('placa', strtoupper($placa))
            ->firstOrFail();

        $perPage   = (int) $request->query('per_page', 15);
        $companyId = $request->query('company_id');

        $query = OrderService::withoutGlobalScopes()
            ->where('vehicle_id', $vehicle->id);

        if ($companyId) {
            $query->where('company_id', (int) $companyId);
        }

        $orders = $query
            ->with([
                'parts',
                'services' => fn($q) => $q->withoutGlobalScopes(),
                'status',
                'type',
                'mileages',
                'company:id,fantasy_name,name',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'vehicle'      => $this->vehicleResource($vehicle),
            'history'      => collect($orders->items())->map(fn($o) => $this->orderResource($o)),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'total'        => $orders->total(),
        ]);
    }

    // ─── Resources ───────────────────────────────────────────────────────────

    private function vehicleResource(Vehicle $v, ?ClientVehiclePhoto $photo = null): array
    {
        return [
            'placa'  => $v->placa,
            'make'   => $v->carModel?->maker?->manufacturer ?? '',
            'model'  => $v->carModel?->model ?? '',
            'year'   => $v->year,
            'color'  => $v->color,
            'km'     => $v->current_km,
            'vin'    => $v->chassis,
            'photo'  => $photo ? $this->storageUrl($photo->photo_path) : null,
        ];
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
            'km'       => $o->mileages->last()?->mileage ?? 0,
            'info'     => $o->info,
            'oficina'  => $o->company?->fantasy_name ?? $o->company?->name ?? '',
            'total'    => $totalParts + $totalServices,
            'parts'    => $o->parts->map(fn($p) => [
                'description' => $p->description,
                'quantity'    => (int) $p->quantity,
                'unit_value'  => (float) $p->unit_price,
                'total'       => (float) $p->quantity * (float) $p->unit_price,
            ]),
            'services' => $o->services->map(fn($s) => [
                'description' => $s->description,
                'value'       => $s->price,
            ]),
        ];
    }
}
