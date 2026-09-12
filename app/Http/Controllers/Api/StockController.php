<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\AuthorizesCompany;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StockController extends Controller
{
    use AuthorizesCompany;

    public function index(Request $request)
    {
        $query = Stock::with(['company', 'category', 'supplier', 'carModels.maker'])
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $stocks  = $query->paginate($perPage);

        return response()->json($stocks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                      => 'required|string|max:255|unique:stocks',
            'name'                      => 'required|string|max:255',
            'description'               => 'nullable|string',
            'unit_of_measurement'       => 'required|string|max:50',
            'current_stock'             => 'required|integer|min:0',
            'minimum_stock'             => 'required|integer|min:0',
            'purchase_price'            => 'required|numeric|min:0',
            'sale_price'                => 'required|numeric|min:0',
            'category_id'               => 'nullable|exists:categories,id',
            'supplier_id'               => 'nullable|exists:suppliers,id',
            'car_models'                => 'nullable|array',
            'car_models.*.id'           => 'required|integer|exists:car_models,id',
            'car_models.*.year_from'    => 'nullable|integer|min:1900|max:2100',
            'car_models.*.year_to'      => 'nullable|integer|min:1900|max:2100|gte:car_models.*.year_from',
            'image'                     => 'nullable|image|max:5120',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        if ($request->hasFile('image')) {
            $validated['image_url'] = $this->uploadImage($request->file('image'));
        }

        $carModels = $validated['car_models'] ?? [];
        unset($validated['car_models']);

        $stock = Stock::create($validated);
        $this->syncCarModels($stock, $carModels);

        $stock->load(['company', 'category', 'supplier', 'carModels.maker']);
        return response()->json($stock, 201);
    }

    public function show(Stock $stock)
    {
        $this->authorizeCompany($stock);
        return response()->json($stock->load(['company', 'category', 'supplier', 'carModels.maker']));
    }

    public function update(Request $request, Stock $stock)
    {
        $this->authorizeCompany($stock);

        $validated = $request->validate([
            'code'                      => 'sometimes|string|max:255|unique:stocks,code,' . $stock->id,
            'name'                      => 'sometimes|string|max:255',
            'description'               => 'sometimes|nullable|string',
            'unit_of_measurement'       => 'sometimes|string|max:50',
            'current_stock'             => 'sometimes|integer|min:0',
            'minimum_stock'             => 'sometimes|integer|min:0',
            'purchase_price'            => 'sometimes|numeric|min:0',
            'sale_price'                => 'sometimes|numeric|min:0',
            'category_id'               => 'sometimes|nullable|exists:categories,id',
            'supplier_id'               => 'sometimes|nullable|exists:suppliers,id',
            'car_models'                => 'sometimes|nullable|array',
            'car_models.*.id'           => 'required|integer|exists:car_models,id',
            'car_models.*.year_from'    => 'nullable|integer|min:1900|max:2100',
            'car_models.*.year_to'      => 'nullable|integer|min:1900|max:2100',
            'image'                     => 'sometimes|nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $this->deleteImage($stock->image_url);
            $validated['image_url'] = $this->uploadImage($request->file('image'));
        }

        if (array_key_exists('car_models', $validated)) {
            $this->syncCarModels($stock, $validated['car_models'] ?? []);
            unset($validated['car_models']);
        }

        $stock->update($validated);
        $stock->load(['company', 'category', 'supplier', 'carModels.maker']);
        return response()->json($stock);
    }

    public function destroy(Stock $stock)
    {
        $this->authorizeCompany($stock);
        $this->deleteImage($stock->image_url);
        $stock->delete();
        return response()->json(null, 204);
    }

    public function generateSku(): \Illuminate\Http\JsonResponse
    {
        $sku = 'SKU-' . strtoupper(Str::random(8));
        while (Stock::withoutGlobalScopes()->where('code', $sku)->exists()) {
            $sku = 'SKU-' . strtoupper(Str::random(8));
        }
        return response()->json(['sku' => $sku]);
    }

    private function syncCarModels(Stock $stock, array $carModels): void
    {
        $syncData = [];
        foreach ($carModels as $entry) {
            $syncData[$entry['id']] = [
                'year_from' => $entry['year_from'] ?? null,
                'year_to'   => $entry['year_to'] ?? null,
            ];
        }
        $stock->carModels()->sync($syncData);
    }

    private function uploadImage(\Illuminate\Http\UploadedFile $file): string
    {
        $path = $file->store('stocks', 'supabase');
        return Storage::disk('supabase')->url($path);
    }

    private function deleteImage(?string $imageUrl): void
    {
        if (!$imageUrl) return;
        $baseUrl = rtrim(config('filesystems.disks.supabase.url', ''), '/');
        if ($baseUrl && str_starts_with($imageUrl, $baseUrl)) {
            $relative = ltrim(substr($imageUrl, strlen($baseUrl)), '/');
            Storage::disk('supabase')->delete($relative);
        }
    }
}
