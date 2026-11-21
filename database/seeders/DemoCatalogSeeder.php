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
        $store = Store::first() ?? Store::create(['name' => 'Toko Sembako Demo']);
        $admin = User::where('store_id', $store->id)
            ->where('role', Role::Admin->value)->first();

        DB::transaction(function () use ($store, $admin) {
            $categoryDefs = [
                'Beras, Gula & Bahan Pokok' => 'Beras, gula pasir, tepung, garam, dan telur harian',
                'Minyak & Bumbu Dapur' => 'Minyak goreng, kecap, saus, bawang, dan rempah dapur',
                'Ikan, Ayam & Daging Segar' => 'Ikan segar/asin, ayam potong, daging sapi, udang, dan lauk pauk',
                'Sayur & Buah Segar' => 'Sayuran harian, cabai, tomat, kentang, dan buah-buahan',
                'Makanan Beku (Frozen Food)' => 'Nugget, sosis, bakso, otak-otak, dan olahan beku lainnya',
                'Makanan Instan, Kopi & Minuman' => 'Mie instan, kopi, teh, susu sachet, biskuit, dan air mineral',
                'Jajanan, Roti & Camilan' => 'Roti tawar, kerupuk, kacang, permen, dan snack kemasan',
                'Susu, Bayi & Kesehatan' => 'Susu kotak/bubuk, popok bayi, tisu, minyak kayu putih, dan obat ringan',
                'Kebutuhan Rumah Tangga' => 'Sabun, deterjen, sampo, pasta gigi, sabun cuci piring, dan pewangi',
                'Gas, Galon & Lainnya' => 'Gas LPG, air galon isi ulang, plastik kemasan, dan kebutuhan lainnya',
            ];

            $categories = [];
            foreach ($categoryDefs as $cName => $cDesc) {
                $cat = $store->categories()->firstOrCreate(['name' => $cName], ['description' => $cDesc]);
                if (! $cat->description) {
                    $cat->update(['description' => $cDesc]);
                }
                $categories[$cName] = $cat;
            }

            $suppliers = [
                $store->suppliers()->firstOrCreate(
                    ['name' => 'Pak Haji Rahmat'],
                    [
                        'phone' => '081234567890',
                        'address' => 'Pasar Induk, Bandar Lampung',
                        'company_name' => 'UD Rahmat Jaya',
                    ]
                ),
                $store->suppliers()->firstOrCreate(
                    ['name' => 'Ibu Sinta'],
                    [
                        'phone' => '082198765432',
                        'address' => 'Jl. Kartini No. 12, Bandar Lampung',
                        'company_name' => 'Toko Berkah Grosir',
                    ]
                ),
            ];

            // [kode, nama, kategori, pemasok_idx, harga_beli, harga_jual, stok, satuan, kemasan, isi_kemasan, stok_min]
            $items = [
                // 1. Beras, gula, tepung, dan bahan pokok
                ['BRS-001', 'Beras Medium', 'Beras, Gula & Bahan Pokok', 0, 15400, 16500, 100, 'kg', 'karung', 25, 25],
                ['BRS-002', 'Beras Premium', 'Beras, Gula & Bahan Pokok', 0, 17200, 18500, 100, 'kg', 'karung', 25, 25],
                ['GLA-001', 'Gula Pasir', 'Beras, Gula & Bahan Pokok', 0, 18500, 20000, 100, 'kg', 'karung', 50, 20],
                ['TPG-001', 'Tepung Terigu', 'Beras, Gula & Bahan Pokok', 0, 11600, 13000, 50, 'kg', 'karung', 25, 15],
                ['TPG-002', 'Tepung Tapioka', 'Beras, Gula & Bahan Pokok', 0, 12000, 13500, 50, 'kg', 'karung', 25, 15],
                ['GRM-001', 'Garam Halus 500 g', 'Beras, Gula & Bahan Pokok', 0, 1500, 2000, 80, 'bungkus', 'dus', 40, 20],
                ['TLR-001', 'Telur Ayam', 'Beras, Gula & Bahan Pokok', 0, 1500, 1800, 300, 'butir', 'peti', 300, 60],

                // 2. Minyak goreng dan bahan dapur
                ['MNY-001', 'Minyakita 1 Liter', 'Minyak & Bumbu Dapur', 0, 15000, 16000, 48, 'botol', 'dus', 12, 24],
                ['MNY-002', 'Minyak Goreng 1 Liter', 'Minyak & Bumbu Dapur', 0, 22500, 24500, 48, 'botol', 'dus', 12, 24],
                ['MNY-003', 'Minyak Goreng 2 Liter', 'Minyak & Bumbu Dapur', 0, 45000, 47000, 24, 'pouch', 'dus', 6, 12],
                ['KCP-001', 'Kecap Manis 135 ml', 'Minyak & Bumbu Dapur', 1, 4000, 5000, 48, 'botol', 'dus', 24, 12],
                ['SAU-001', 'Saus Sambal 135 ml', 'Minyak & Bumbu Dapur', 1, 4500, 5500, 48, 'botol', 'dus', 24, 12],
                ['BWG-001', 'Bawang Putih', 'Minyak & Bumbu Dapur', 1, 35000, 39000, 30, 'kg', 'karung', 10, 10],
                ['BWG-002', 'Bawang Merah', 'Minyak & Bumbu Dapur', 1, 35000, 40000, 30, 'kg', 'karung', 10, 10],

                // 3. Makanan instan, kopi, dan minuman
                ['MIE-001', 'Mie Instan Goreng', 'Makanan Instan, Kopi & Minuman', 1, 3000, 3500, 120, 'bungkus', 'dus', 40, 40],
                ['MIE-002', 'Mie Instan Kuah', 'Makanan Instan, Kopi & Minuman', 1, 2900, 3500, 120, 'bungkus', 'dus', 40, 40],
                ['KOP-001', 'Kopi Sachet', 'Makanan Instan, Kopi & Minuman', 1, 1100, 1500, 200, 'sachet', 'dus', 100, 30],
                ['TEH-001', 'Teh Celup', 'Makanan Instan, Kopi & Minuman', 1, 10000, 12000, 36, 'kotak', 'dus', 12, 12],
                ['SSU-001', 'Susu Kental Manis Sachet', 'Makanan Instan, Kopi & Minuman', 1, 1500, 2000, 240, 'sachet', 'dus', 120, 30],
                ['AIR-001', 'Air Mineral 600 ml', 'Makanan Instan, Kopi & Minuman', 1, 2000, 3000, 72, 'botol', 'dus', 24, 24],
                ['BSK-001', 'Biskuit Kemasan', 'Makanan Instan, Kopi & Minuman', 1, 3000, 4000, 48, 'bungkus', 'dus', 24, 20],

                // 4. Sabun, deterjen, dan kebutuhan rumah tangga
                ['DTR-001', 'Deterjen Sachet', 'Kebutuhan Rumah Tangga', 1, 800, 1000, 200, 'sachet', 'dus', 100, 30],
                ['SBN-001', 'Sabun Mandi', 'Kebutuhan Rumah Tangga', 1, 3000, 4000, 72, 'batang', 'dus', 72, 20],
                ['PST-001', 'Pasta Gigi', 'Kebutuhan Rumah Tangga', 1, 9000, 10000, 24, 'tube', 'dus', 12, 12],
                ['SMP-001', 'Sampo Sachet', 'Kebutuhan Rumah Tangga', 1, 800, 1000, 240, 'sachet', 'dus', 120, 30],
                ['SBN-002', 'Sabun Cuci Piring', 'Kebutuhan Rumah Tangga', 1, 5000, 6000, 48, 'pouch', 'dus', 24, 12],
                ['PWG-001', 'Pewangi Pakaian Sachet', 'Kebutuhan Rumah Tangga', 1, 850, 1000, 200, 'sachet', 'dus', 100, 30],
            ];

            foreach ($items as [$code, $name, $category, $supplierIdx, $buy, $sell, $stock, $unit, $packUnit, $perPack, $min]) {
                $existing = $store->products()->where('code', $code)->first()
                    ?? $store->products()->where('name', $name)->first();

                if ($existing) {
                    $existing->update([
                        'category_id' => $categories[$category]->id,
                        'supplier_id' => $suppliers[$supplierIdx]->id,
                        'code' => $code,
                        'name' => $name,
                        'purchase_price' => $buy,
                        'selling_price' => $sell,
                        'unit' => $unit,
                        'pack_unit' => $packUnit,
                        'units_per_pack' => $perPack,
                        'min_stock' => $min,
                    ]);
                } else {
                    $product = $store->products()->make([
                        'category_id' => $categories[$category]->id,
                        'supplier_id' => $suppliers[$supplierIdx]->id,
                        'code' => $code,
                        'name' => $name,
                        'purchase_price' => $buy,
                        'selling_price' => $sell,
                        'unit' => $unit,
                        'pack_unit' => $packUnit,
                        'units_per_pack' => $perPack,
                        'min_stock' => $min,
                    ]);
                    $product->stock = $stock;
                    $product->save();

                    if ($stock > 0) {
                        $store->stockMovements()->create([
                            'product_id' => $product->id,
                            'user_id' => $admin?->id,
                            'type' => StockMovementType::InitialStock,
                            'quantity_change' => $stock,
                            'stock_before' => 0,
                            'stock_after' => $stock,
                            'notes' => 'Stok awal katalog sembako',
                        ]);
                    }
                }
            }
        });
    }
}
