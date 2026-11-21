<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Hanya untuk uji beban di database lokal terpisah. Data ini tidak mengisi riwayat stok. */
class LoadTestSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Dilarang dijalankan di production.');

            return;
        }

        $store = Store::where('name', 'Toko Sembako Demo')->firstOrFail();
        $now = now();

        $products = [];
        for ($i = 1; $i <= 2000; $i++) {
            $buy = random_int(2, 80) * 500;
            $products[] = [
                'store_id' => $store->id, 'code' => sprintf('LT-%05d', $i), 'name' => "Barang Uji {$i}",
                'purchase_price' => $buy, 'selling_price' => $buy * 1.2,
                'stock' => random_int(0, 300), 'unit' => 'pcs', 'units_per_pack' => 1,
                'min_stock' => random_int(5, 40), 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($products, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        $catalog = DB::table('products')->where('store_id', $store->id)->where('code', 'like', 'LT-%')
            ->get(['id', 'purchase_price', 'selling_price']);

        for ($batch = 0; $batch < 40; $batch++) {
            $sales = [];
            $lines = [];

            for ($j = 0; $j < 500; $j++) {
                $n = $batch * 500 + $j + 1;
                $invoice = sprintf('INV-LT-%06d', $n);
                $when = $now->copy()->subDays(random_int(0, 89))->subMinutes(random_int(0, 1439));
                $total = 0;

                foreach ($catalog->random(random_int(1, 3)) as $p) {
                    $qty = random_int(1, 5);
                    $subtotal = $qty * (float) $p->selling_price;
                    $total += $subtotal;
                    $lines[$invoice][] = [
                        'store_id' => $store->id, 'product_id' => $p->id, 'quantity' => $qty,
                        'unit_price' => $p->selling_price, 'unit_cost' => $p->purchase_price,
                        'subtotal' => $subtotal, 'created_at' => $when, 'updated_at' => $when,
                    ];
                }

                $sales[] = [
                    'store_id' => $store->id, 'invoice_number' => $invoice, 'sold_at' => $when,
                    'total_amount' => $total, 'amount_paid' => $total, 'change_amount' => 0,
                    'payment_method' => $n % 3 === 0 ? 'qris' : 'tunai',
                    'created_at' => $when, 'updated_at' => $when,
                ];
            }

            DB::table('sales')->insert($sales);
            $ids = DB::table('sales')->where('store_id', $store->id)
                ->whereIn('invoice_number', array_keys($lines))->pluck('id', 'invoice_number');

            $details = [];
            foreach ($lines as $invoice => $rows) {
                foreach ($rows as $row) {
                    $details[] = $row + ['sale_id' => $ids[$invoice]];
                }
            }
            foreach (array_chunk($details, 1000) as $chunk) {
                DB::table('sale_details')->insert($chunk);
            }
        }
    }
}
