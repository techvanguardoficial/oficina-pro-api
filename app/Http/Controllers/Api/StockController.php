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

    public function index()
    {
        $stocks = Stock::with(['company', 'category', 'supplier', 'carModels.maker'])
            ->where('company_id', auth()->user()->company_id)
            ->paginate(15);
        return response()->json($stocks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'               => 'required|string|max:255|unique:stocks',
            'name'               => 'required|string|max:255',
            'unit_of_measurement'=> 'required|string|max:50',
            'current_stock'      => 'required|integer|min:0',
            'minimum_stock'      => 'required|integer|min:0',
            'purchase_price'     => 'required|numeric|min:0',
            'sale_price'         => 'required|numeric|min:0',
            'category_id'        => 'nullable|exists:categories,id',
            'supplier_id'        => 'nullable|exists:suppliers,id',
            'car_model_ids'      => 'nullable|array',
            'car_model_ids.*'    => 'integer|exists:car_models,id',
            'image'              => 'nullable|image|max:5120',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        if ($request->hasFile('image')) {
            $validated['image_url'] = $this->uploadImage($request->file('image'));
        }

        $carModelIds = $validated['car_model_ids'] ?? [];
        unset($validated['car_model_ids']);

        $stock = Stock::create($validated);
        if ($carModelIds) {
            $stock->carModels()->sync($carModelIds);
        }

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
            'code'               => 'sometimes|string|max:255|unique:stocks,code,' . $stock->id,
            'name'               => 'sometimes|string|max:255',
            'unit_of_measurement'=> 'sometimes|string|max:50',
            'current_stock'      => 'sometimes|integer|min:0',
            'minimum_stock'      => 'sometimes|integer|min:0',
            'purchase_price'     => 'sometimes|numeric|min:0',
            'sale_price'         => 'sometimes|numeric|min:0',
            'category_id'        => 'sometimes|nullable|exists:categories,id',
            'supplier_id'        => 'sometimes|nullable|exists:suppliers,id',
            'car_model_ids'      => 'sometimes|nullable|array',
            'car_model_ids.*'    => 'integer|exists:car_models,id',
            'image'              => 'sometimes|nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $this->deleteImage($stock->image_url);
            $validated['image_url'] = $this->uploadImage($request->file('image'));
        }

        if (array_key_exists('car_model_ids', $validated)) {
            $stock->carModels()->sync($validated['car_model_ids'] ?? []);
            unset($validated['car_model_ids']);
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

    private function uploadImage(\Illuminate\Http\UploadedFile $file): string
    {
        $path = $file->store('stocks', 'supabase');
        return Storage::disk('supabase')->url($path);
    }

    private function deleteImage(?string $imageUrl): void
    {
        if (!$imageUrl) return;
        $disk = Storage::disk('supabase');
        // Extract relative path from public URL
        $baseUrl = rtrim(config('filesystems.disks.supabase.url', ''), '/');
        if ($baseUrl && str_starts_with($imageUrl, $baseUrl)) {
            $relative = ltrim(substr($imageUrl, strlen($baseUrl)), '/');
            $disk->delete($relative);
        }
    }
}
