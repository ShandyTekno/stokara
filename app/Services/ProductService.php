<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductService
{
    public function create(array $data, ?UploadedFile $photo, User $user): Product
    {
        $stock = (int) ($data['initial_stock'] ?? 0);
        unset($data['initial_stock'], $data['photo'], $data['remove_photo']);
        $data = $this->normalizePackaging($data);

        $path = $photo?->store('products', 'public');

        try {
            return DB::transaction(function () use ($data, $stock, $path, $user) {
                $product = $user->store->products()->make($data);
                $product->stock = $stock; // stock tidak mass-assignable
                $product->photo = $path;
                $product->save();

                if ($stock > 0) {
                    $user->store->stockMovements()->create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'type' => StockMovementType::InitialStock,
                        'quantity_change' => $stock,
                        'stock_before' => 0,
                        'stock_after' => $stock,
                        'notes' => 'Stok awal saat barang dibuat',
                    ]);
                }

                return $product;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }

    public function update(Product $product, array $data, ?UploadedFile $photo, bool $removePhoto): Product
    {
        unset($data['photo'], $data['remove_photo']);
        $data = $this->normalizePackaging($data);

        $oldPhoto = $product->photo;
        $newPhoto = $photo?->store('products', 'public');

        $product->fill($data);

        if ($newPhoto) {
            $product->photo = $newPhoto;
        } elseif ($removePhoto) {
            $product->photo = null;
        }

        $product->save();

        if ($oldPhoto && ($newPhoto || $removePhoto)) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $product;
    }

    private function normalizePackaging(array $data): array
    {
        if (empty($data['pack_unit'])) {
            $data['pack_unit'] = null;
            $data['units_per_pack'] = 1;
        }

        return $data;
    }
}
