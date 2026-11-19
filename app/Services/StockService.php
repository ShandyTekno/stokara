<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockOutReason;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInDetail;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockOutDetail;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /** Batas stok per barang, jauh di bawah batas kolom INT UNSIGNED MySQL. */
    public const MAX_STOCK = 2_000_000_000;

    /**
     * Mencatat barang masuk. Atomik: gagal di satu baris membatalkan semuanya.
     *
     * @param  array{transaction_date: string, supplier_id?: ?int, notes?: ?string, items: array<int, array{product_id: int, quantity: int, is_package?: bool, price: numeric}>}  $data
     */
    public function recordStockIn(array $data, User $user): StockIn
    {
        return DB::transaction(function () use ($data, $user) {
            $storeId = $user->store_id;
            $date = Carbon::parse($data['transaction_date']);

            $this->lockStore($storeId);
            $products = $this->lockProducts($data['items'], $storeId);

            $stockIn = $this->persist(new StockIn([
                'user_id' => $user->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'transaction_number' => $this->nextNumber(StockIn::class, 'SI', $storeId, $date),
                'transaction_date' => $date->toDateString(),
                'total_cost' => 0,
                'notes' => $data['notes'] ?? null,
            ]), $storeId);

            $total = 0.0;

            foreach (array_values($data['items']) as $i => $item) {
                /** @var Product $product */
                $product = $products[(int) $item['product_id']];
                $inputQty = (int) $item['quantity'];
                $isPackage = filter_var($item['is_package'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($isPackage && ! $product->pack_unit) {
                    throw ValidationException::withMessages([
                        "items.$i.is_package" => "Barang “{$product->name}” tidak memiliki satuan kemasan.",
                    ]);
                }

                $perPack = $isPackage ? max(1, (int) $product->units_per_pack) : 1;
                $quantity = $inputQty * $perPack;
                $before = (int) $product->stock;
                $after = $before + $quantity;

                if ($after > self::MAX_STOCK) {
                    throw ValidationException::withMessages([
                        "items.$i.quantity" => "Stok “{$product->name}” akan melebihi batas maksimum ".number_format(self::MAX_STOCK, 0, ',', '.').'.',
                    ]);
                }

                $price = (float) $item['price'];          // harga per satuan yang dipilih (kemasan atau terkecil)
                $unitCost = round($price / $perPack, 2);  // harga per satuan terkecil
                $subtotal = round($price * $inputQty, 2);
                $total += $subtotal;

                $this->persist(new StockInDetail([
                    'stock_in_id' => $stockIn->id,
                    'product_id' => $product->id,
                    'input_quantity' => $inputQty,
                    'is_package' => $isPackage,
                    'units_per_pack' => $perPack,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                ]), $storeId);

                $product->stock = $after;
                if ($unitCost > 0) {
                    $product->purchase_price = $unitCost; // harga beli mengikuti pembelian terakhir
                }
                $product->save();

                $this->recordMovement(
                    $product, $user, StockMovementType::StockIn, $quantity, $before, $after,
                    $stockIn, "Barang masuk {$stockIn->transaction_number}",
                );
            }

            $stockIn->total_cost = round($total, 2);
            $stockIn->save();

            return $stockIn;
        });
    }

    /**
     * Mencatat barang keluar (rusak, hilang, dan sebagainya). Ditolak jika melebihi stok.
     *
     * @param  array{transaction_date: string, reason: string, notes?: ?string, items: array<int, array{product_id: int, quantity: int}>}  $data
     */
    public function recordStockOut(array $data, User $user): StockOut
    {
        return DB::transaction(function () use ($data, $user) {
            $storeId = $user->store_id;
            $date = Carbon::parse($data['transaction_date']);
            $reason = StockOutReason::from($data['reason']);

            $this->lockStore($storeId);
            $products = $this->lockProducts($data['items'], $storeId);

            $stockOut = $this->persist(new StockOut([
                'user_id' => $user->id,
                'transaction_number' => $this->nextNumber(StockOut::class, 'SO', $storeId, $date),
                'transaction_date' => $date->toDateString(),
                'reason' => $reason,
                'notes' => $data['notes'] ?? null,
            ]), $storeId);

            foreach (array_values($data['items']) as $i => $item) {
                /** @var Product $product */
                $product = $products[(int) $item['product_id']];
                $quantity = (int) $item['quantity'];
                $before = (int) $product->stock; // stok terkini, sudah terkunci

                if ($quantity > $before) {
                    throw ValidationException::withMessages([
                        "items.$i.quantity" => "Stok “{$product->name}” tidak mencukupi. Tersedia {$before} {$product->unit}, diminta {$quantity}.",
                    ]);
                }

                $after = $before - $quantity;

                $this->persist(new StockOutDetail([
                    'stock_out_id' => $stockOut->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]), $storeId);

                $product->stock = $after;
                $product->save();

                $this->recordMovement(
                    $product, $user, StockMovementType::StockOut, -$quantity, $before, $after,
                    $stockOut, "Barang keluar {$stockOut->transaction_number} ({$reason->label()})",
                );
            }

            return $stockOut;
        });
    }

    /** Kunci baris toko agar penomoran transaksi tidak bisa kembar. Selalu dipanggil sebelum lockProducts. */
    private function lockStore(int $storeId): void
    {
        Store::query()->whereKey($storeId)->lockForUpdate()->first();
    }

    /** Kunci baris barang (berurutan menurut ID) dan pastikan semuanya milik toko ini. */
    private function lockProducts(array $items, int $storeId): Collection
    {
        $ids = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $products = Product::query()
            ->where('products.store_id', $storeId)
            ->whereIn('products.id', $ids)
            ->orderBy('products.id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'items' => 'Ada barang yang tidak valid atau sudah dihapus.',
            ]);
        }

        return $products;
    }

    private function nextNumber(string $modelClass, string $prefix, int $storeId, Carbon $date): string
    {
        $base = $prefix.'-'.$date->format('Ymd').'-';

        $last = $modelClass::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->where('transaction_number', 'like', $base.'%')
            ->max('transaction_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function recordMovement(
        Product $product,
        User $user,
        StockMovementType $type,
        int $change,
        int $before,
        int $after,
        Model $reference,
        string $notes,
    ): void {
        $this->persist(new StockMovement([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'type' => $type,
            'quantity_change' => $change,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'notes' => $notes,
        ]), $user->store_id);
    }

    /** store_id diisi eksplisit (tidak mass-assignable) sehingga service tidak bergantung pada Auth. */
    private function persist(Model $model, int $storeId): Model
    {
        $model->store_id = $storeId;
        $model->save();

        return $model;
    }
}
