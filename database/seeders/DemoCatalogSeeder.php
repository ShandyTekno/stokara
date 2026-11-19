<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('name', 'Toko Sembako Demo')->firstOrFail();
        $admin = User::where('store_id', $store->id)
            ->where('role', Role::Admin->value)->first();

        DB::transaction(function () use ($store, $admin) {
            $categories = collect([
                'Beras & Tepung', 'Minyak & Bumbu', 'Minuman',
                'Mie & Makanan Instan', 'Kebutuhan Rumah Tangga',
            ])->mapWithKeys(fn ($name) => [$name => $store->categories()->create(['name' => $name])]);

            $suppliers = [
                $store->suppliers()->create([
                    'name' => 'Pak Haji Rahmat', 'phone' => '081234567890',
                    'address' => 'Pasar Induk, Bandar Lampung', 'company_name' => 'UD Rahmat Jaya',
                ]),
                $store->suppliers()->create([
                    'name' => 'Ibu Sinta', 'phone' => '082198765432',
                    'address' => 'Jl. Kartini No. 12, Bandar Lampung', 'company_name' => null,
                ]),
            ];

            // [kode, nama, kategori, pemasok, harga beli, harga jual, stok, satuan, kemasan, isi kemasan, stok min]
            $items = [
                ['BRS-001', 'Beras Premium', 'Beras & Tepung', 0, 12500, 14000, 120, 'kg', 'karung', 25, 50],
                ['TPG-001', 'Tepung Terigu 1 kg', 'Beras & Tepung', 0, 10500, 12000, 8, 'pcs', 'dus', 10, 15],
                ['GLA-001', 'Gula Pasir', 'Minyak & Bumbu', 0, 14500, 16000, 60, 'kg', 'karung', 50, 20],
                ['MNY-001', 'Minyak Goreng 1 L', 'Minyak & Bumbu', 0, 16000, 18000, 36, 'pcs', 'dus', 12, 24],
                ['GRM-001', 'Garam Halus', 'Minyak & Bumbu', 1, 2500, 3500, 0, 'pcs', 'dus', 40, 20],
                ['TLR-001', 'Telur Ayam', 'Minyak & Bumbu', 1, 2200, 2500, 180, 'butir', 'tray', 30, 60],
                ['AIR-001', 'Air Mineral 600 ml', 'Minuman', 1, 2000, 3000, 72, 'botol', 'dus', 24, 24],
                ['KOP-001', 'Kopi Sachet', 'Minuman', 1, 1200, 1500, 15, 'pcs', 'renceng', 10, 30],
                ['TEH-001', 'Teh Celup', 'Minuman', 1, 800, 1000, 100, 'pcs', 'dus', 24, 30],
                ['MIE-001', 'Mie Instan Goreng', 'Mie & Makanan Instan', 0, 2800, 3500, 200, 'pcs', 'dus', 40, 80],
                ['SBN-001', 'Sabun Mandi', 'Kebutuhan Rumah Tangga', 0, 3000, 4000, 4, 'pcs', 'dus', 36, 12],
                ['DTR-001', 'Deterjen 800 g', 'Kebutuhan Rumah Tangga', 0, 15000, 17500, 24, 'pcs', 'dus', 12, 10],
            ];

            foreach ($items as [$code, $name, $category, $supplier, $buy, $sell, $stock, $unit, $packUnit, $perPack, $min]) {
                $product = $store->products()->make([
                    'category_id' => $categories[$category]->id,
                    'supplier_id' => $suppliers[$supplier]->id,
                    'code' => $code,
                    'name' => $name,
                    'purchase_price' => $buy,
                    'selling_price' => $sell,
                    'unit' => $unit,
                    'pack_unit' => $packUnit,
                    'units_per_pack' => $perPack,
                    'min_stock' => $min,
                ]);
                $product->stock = $stock; // stock tidak mass-assignable
                $product->save();

                if ($stock > 0) {
                    $store->stockMovements()->create([
                        'product_id' => $product->id,
                        'user_id' => $admin?->id,
                        'type' => StockMovementType::InitialStock,
                        'quantity_change' => $stock,
                        'stock_before' => 0,
                        'stock_after' => $stock,
                        'notes' => 'Stok awal (data demo)',
                    ]);
                }
            }
        });
    }
}
