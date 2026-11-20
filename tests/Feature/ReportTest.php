<?php

namespace Tests\Feature;

use App\Exports\ReportExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\ReportService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;
    private Category $drink;
    private Category $snack;
    private Product $cola;
    private Product $chips;
    private Product $cookie;
    private Product $tea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
        $this->drink = Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Minuman']);
        $this->snack = Category::factory()->create(['store_id' => $this->store->id, 'name' => 'Snack']);

        $make = fn (array $attrs) => Product::factory()->create($attrs + [
            'store_id' => $this->store->id,
            'unit' => 'pcs',
            'pack_unit' => null,
            'units_per_pack' => 1,
            'min_stock' => 10,
        ]);

        $this->cola = $make(['category_id' => $this->drink->id, 'code' => 'MNM-1', 'name' => 'Cola', 'stock' => 100, 'purchase_price' => 2000, 'selling_price' => 3000]);
        $this->chips = $make(['category_id' => $this->snack->id, 'code' => 'SNK-1', 'name' => 'Keripik', 'stock' => 50, 'purchase_price' => 1000, 'selling_price' => 1500]);
        $this->cookie = $make(['category_id' => $this->snack->id, 'code' => 'SNK-2', 'name' => 'Biskuit', 'stock' => 5, 'purchase_price' => 500, 'selling_price' => 800]);
        $this->tea = $make(['category_id' => $this->drink->id, 'code' => 'MNM-2', 'name' => 'Teh', 'stock' => 0, 'purchase_price' => 1500, 'selling_price' => 2500]);
    }

    // ---------------------------------------------------------------- bantuan

    private function sell(Product $product, int $qty, string $method = 'tunai', ?Carbon $when = null): Sale
    {
        $sale = app(StockService::class)->recordSale([
            'payment_method' => $method,
            'amount_paid' => 99999999,
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
        ], $this->admin);

        if ($when) {
            DB::table('sales')->where('id', $sale->id)->update(['sold_at' => $when]);
        }

        return $sale;
    }

    private function stockIn(Product $product, int $qty, bool $package, float $price, ?string $date = null): void
    {
        app(StockService::class)->recordStockIn([
            'transaction_date' => $date ?? today()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => $qty, 'is_package' => $package, 'price' => $price]],
        ], $this->admin);
    }

    private function stockOut(Product $product, int $qty, string $reason, ?string $notes = null): void
    {
        app(StockService::class)->recordStockOut([
            'transaction_date' => today()->toDateString(),
            'reason' => $reason,
            'notes' => $notes,
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
        ], $this->admin);
    }

    /** Props halaman seperti yang diterima frontend. */
    private function props(string $type, array $query = []): array
    {
        $response = $this->actingAs($this->admin)->get(route('reports.show', ['type' => $type] + $query));
        $response->assertOk();

        return json_decode(json_encode($response->viewData('page')), true)['props'];
    }

    private function summary(array $report): array
    {
        return collect($report['summary'])->mapWithKeys(fn ($s) => [$s['label'] => $s['value']])->all();
    }

    private function row(array $report, string $key, string $value): array
    {
        $row = collect($report['rows'])->firstWhere($key, $value);
        $this->assertNotNull($row, "Baris dengan {$key}={$value} tidak ditemukan");

        return $row;
    }

    // ------------------------------------------------------------- persediaan

    public function test_inventory_report_values_status_counts_and_filters(): void
    {
        $report = $this->props('persediaan')['report'];
        $s = $this->summary($report);

        $this->assertCount(4, $report['rows']);
        $this->assertEquals(4, $s['Jenis barang']);
        $this->assertEquals(155, $s['Total stok (satuan terkecil)']);
        $this->assertEquals(252500, $s['Nilai persediaan (harga beli)']);
        $this->assertEquals(2, $s['Stok aman']);
        $this->assertEquals(1, $s['Stok menipis']);
        $this->assertEquals(1, $s['Stok habis']);

        $cola = $this->row($report, 'code', 'MNM-1');
        $this->assertSame('aman', $cola['status']);
        $this->assertEquals(200000, $cola['value']);
        $this->assertSame('menipis', $this->row($report, 'code', 'SNK-2')['status']);
        $this->assertSame('habis', $this->row($report, 'code', 'MNM-2')['status']);

        $habis = $this->props('persediaan', ['status' => 'habis'])['report'];
        $this->assertCount(1, $habis['rows']);
        $this->assertSame('Teh', $habis['rows'][0]['name']);

        $snack = $this->props('persediaan', ['category' => $this->snack->id])['report'];
        $this->assertCount(2, $snack['rows']);
        $this->assertContains('Kategori: Snack', $snack['filters_text']);

        $this->assertSame([2, 1, 1], $report['chart']['datasets'][0]['data']);
    }

    public function test_inventory_ignores_deleted_products(): void
    {
        $this->tea->delete();

        $report = $this->props('persediaan')['report'];

        $this->assertCount(3, $report['rows']);
    }

    // ----------------------------------------------------------- barang masuk

    public function test_stock_in_report_converts_packages_and_totals(): void
    {
        $this->chips->forceFill(['pack_unit' => 'dus', 'units_per_pack' => 12])->save();
        $this->stockIn($this->cola, 2, false, 2500);
        $this->stockIn($this->chips, 1, true, 10000);

        $report = $this->props('barang-masuk')['report'];
        $s = $this->summary($report);

        $this->assertCount(2, $report['rows']);
        $this->assertEquals(2, $s['Jumlah transaksi']);
        $this->assertEquals(2, $s['Jumlah baris barang']);
        $this->assertEquals(15000, $s['Total biaya']);

        $row = $this->row($report, 'product', 'Keripik');
        $this->assertSame('1 dus', $row['bought']);
        $this->assertEquals(12, $row['quantity']);
        $this->assertEquals(833.33, $row['unit_cost']);
        $this->assertEquals(10000, $row['subtotal']);
    }

    public function test_stock_in_report_respects_period_and_category(): void
    {
        $this->stockIn($this->cola, 2, false, 2500);
        $this->stockIn($this->chips, 3, false, 1000);

        $drinks = $this->props('barang-masuk', ['category' => $this->drink->id])['report'];
        $this->assertCount(1, $drinks['rows']);
        $this->assertSame('Cola', $drinks['rows'][0]['product']);

        $past = $this->props('barang-masuk', [
            'from' => today()->subDays(10)->toDateString(),
            'to' => today()->subDays(5)->toDateString(),
        ])['report'];
        $this->assertCount(0, $past['rows']);
        $this->assertEquals(0, $this->summary($past)['Total biaya']);
    }

    // ---------------------------------------------------------- barang keluar

    public function test_stock_out_report_summary_and_reason_chart(): void
    {
        $this->stockOut($this->cola, 3, 'rusak');
        $this->stockOut($this->chips, 2, 'lainnya', 'Dipakai sendiri');

        $report = $this->props('barang-keluar')['report'];
        $s = $this->summary($report);

        $this->assertCount(2, $report['rows']);
        $this->assertEquals(2, $s['Jumlah transaksi']);
        $this->assertEquals(2, $s['Jumlah baris barang']);
        $this->assertEquals(5, $s['Total jumlah keluar']);

        $this->assertSame('Rusak', $this->row($report, 'product', 'Cola')['reason']);
        $this->assertSame('Dipakai sendiri', $this->row($report, 'product', 'Keripik')['notes']);

        $this->assertSame(['Rusak', 'Hilang', 'Kadaluarsa', 'Lainnya'], $report['chart']['labels']);
        $this->assertSame([1, 0, 0, 1], $report['chart']['datasets'][0]['data']);
    }

    // --------------------------------------------------------------- penjualan

    public function test_sales_report_totals_profit_and_payment_split(): void
    {
        $cash = $this->sell($this->cola, 10);                // 30.000, pokok 20.000
        $qris = $this->sell($this->chips, 4, 'qris');        // 6.000, pokok 4.000

        $report = $this->props('penjualan')['report'];
        $s = $this->summary($report);

        $this->assertCount(2, $report['rows']);
        $this->assertEquals(2, $s['Jumlah transaksi']);
        $this->assertEquals(36000, $s['Total penjualan']);
        $this->assertEquals(24000, $s['Total harga pokok']);
        $this->assertEquals(12000, $s['Laba kotor']);
        $this->assertEquals(30000, $s['Tunai']);
        $this->assertEquals(6000, $s['QRIS']);

        $row = $this->row($report, 'invoice', $cash->invoice_number);
        $this->assertSame('Tunai', $row['payment']);
        $this->assertEquals(10, $row['units']);
        $this->assertEquals(10000, $row['profit']);
        $this->assertSame('QRIS', $this->row($report, 'invoice', $qris->invoice_number)['payment']);
    }

    public function test_sales_report_period_and_category_filters(): void
    {
        $this->sell($this->cola, 10);
        $this->sell($this->chips, 4, 'qris');
        $this->sell($this->cola, 5, 'tunai', now()->subDays(40)); // 15.000, pokok 10.000

        // Periode bawaan (bulan ini) tidak memuat transaksi 40 hari lalu.
        $this->assertCount(2, $this->props('penjualan')['report']['rows']);

        $wide = $this->props('penjualan', ['from' => today()->subDays(45)->toDateString()])['report'];
        $s = $this->summary($wide);
        $this->assertCount(3, $wide['rows']);
        $this->assertEquals(51000, $s['Total penjualan']);
        $this->assertEquals(17000, $s['Laba kotor']);

        $snack = $this->props('penjualan', ['category' => $this->snack->id])['report'];
        $s = $this->summary($snack);
        $this->assertCount(1, $snack['rows']);
        $this->assertEquals(6000, $s['Total penjualan']);
        $this->assertEquals(2000, $s['Laba kotor']);
    }

    public function test_sales_report_chart_has_one_point_per_day(): void
    {
        $this->sell($this->cola, 10);

        $chart = $this->props('penjualan')['report']['chart'];

        $this->assertCount(now()->day, $chart['labels']);
        $this->assertCount(2, $chart['datasets']);
        $this->assertEquals(30000, end($chart['datasets'][0]['data']));
        $this->assertEquals(10000, end($chart['datasets'][1]['data']));
    }

    // ---------------------------------------------------- terlaris / kurang laku

    public function test_best_sellers_ranks_by_units_sold(): void
    {
        $this->sell($this->cola, 10);
        $this->sell($this->chips, 4);
        $this->sell($this->cookie, 1);

        $report = $this->props('produk-terlaris')['report'];
        $s = $this->summary($report);

        $this->assertSame(['Cola', 'Keripik', 'Biskuit'], array_column($report['rows'], 'product'));
        $this->assertSame([1, 2, 3], array_column($report['rows'], 'rank'));
        $this->assertEquals(10, $report['rows'][0]['qty']);
        $this->assertEquals(30000, $report['rows'][0]['revenue']);
        $this->assertEquals(10000, $report['rows'][0]['profit']);

        $this->assertEquals(3, $s['Jenis produk terjual']);
        $this->assertEquals(15, $s['Total unit terjual']);
        $this->assertEquals(36800, $s['Total penjualan']);
        $this->assertSame('Cola', $s['Produk terlaris']);
    }

    public function test_best_sellers_is_limited_to_top_20_but_summary_covers_all(): void
    {
        $this->sell($this->cola, 3);

        Product::factory()->count(21)->create([
            'store_id' => $this->store->id,
            'stock' => 5,
            'selling_price' => 1000,
            'purchase_price' => 500,
        ])->each(fn (Product $p) => $this->sell($p, 1));

        $report = $this->props('produk-terlaris')['report'];

        $this->assertCount(20, $report['rows']);
        $this->assertEquals(22, $this->summary($report)['Jenis produk terjual']);
        $this->assertSame('Cola', $report['rows'][0]['product']);
    }

    public function test_slow_movers_lists_least_sold_first_and_skips_empty_stock(): void
    {
        $this->sell($this->cola, 10);

        $report = $this->props('produk-kurang-laku')['report'];
        $s = $this->summary($report);

        // Teh (stok 0) tidak ikut; seri diurutkan dari stok terbesar.
        $this->assertSame(['Keripik', 'Biskuit', 'Cola'], array_column($report['rows'], 'product'));
        $this->assertSame([0, 0, 10], array_column($report['rows'], 'qty'));
        $this->assertSame('Belum pernah', $report['rows'][0]['last_sold']);
        $this->assertSame(now()->format('d/m/Y'), $report['rows'][2]['last_sold']);

        $this->assertEquals(3, $s['Produk berstok dianalisis']);
        $this->assertEquals(2, $s['Produk tanpa penjualan']);
        $this->assertEquals(52500, $s['Nilai stok tanpa penjualan']); // 50×1000 + 5×500
    }

    // ----------------------------------------------------- isolasi dan hak akses

    public function test_reports_never_include_other_stores_data(): void
    {
        $other = Store::factory()->create();
        $foreign = Product::factory()->create(['store_id' => $other->id, 'stock' => 9]);

        $saleId = DB::table('sales')->insertGetId([
            'store_id' => $other->id,
            'invoice_number' => 'INV-X-0001',
            'sold_at' => now(),
            'total_amount' => 5000,
            'amount_paid' => 5000,
            'change_amount' => 0,
            'payment_method' => 'qris',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sale_details')->insert([
            'store_id' => $other->id,
            'sale_id' => $saleId,
            'product_id' => $foreign->id,
            'quantity' => 5,
            'unit_price' => 1000,
            'unit_cost' => 500,
            'subtotal' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertCount(0, $this->props('penjualan')['report']['rows']);
        $this->assertCount(0, $this->props('produk-terlaris')['report']['rows']);
        $this->assertCount(4, $this->props('persediaan')['report']['rows']);
        $this->assertCount(3, $this->props('produk-kurang-laku')['report']['rows']);
    }

    public function test_karyawan_is_forbidden_from_all_report_routes(): void
    {
        $this->actingAs($this->karyawan);

        $this->get(route('reports.index'))->assertForbidden();

        foreach (array_keys(ReportService::TYPES) as $type) {
            $this->get(route('reports.show', $type))->assertForbidden();
            $this->get(route('reports.pdf', $type))->assertForbidden();
            $this->get(route('reports.excel', $type))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_and_unknown_type_is_not_found(): void
    {
        $this->get(route('reports.index'))->assertRedirect('/login');
        $this->get('/laporan/penjualan')->assertRedirect('/login');

        $this->actingAs($this->admin)->get('/laporan/tidak-ada')->assertNotFound();
        $this->get('/laporan/tidak-ada/pdf')->assertNotFound();
    }

    // ------------------------------------------------------------- validasi

    public function test_filter_validation(): void
    {
        $foreignCategory = Category::factory()->create();
        $this->actingAs($this->admin);

        $this->get(route('reports.show', ['type' => 'persediaan', 'category' => $foreignCategory->id]))
            ->assertSessionHasErrors('category');

        $this->get(route('reports.show', ['type' => 'persediaan', 'status' => 'rusak']))
            ->assertSessionHasErrors('status');

        $this->get(route('reports.show', ['type' => 'penjualan', 'from' => 'bukan-tanggal']))
            ->assertSessionHasErrors('from');

        $tooLong = ['from' => today()->subDays(400)->toDateString(), 'to' => today()->toDateString()];
        $this->get(route('reports.show', ['type' => 'penjualan'] + $tooLong))->assertSessionHasErrors('to');
        $this->get(route('reports.pdf', ['type' => 'penjualan'] + $tooLong))->assertSessionHasErrors('to');

        // Laporan persediaan tidak memakai periode, jadi rentang itu tidak dipersoalkan.
        $this->get(route('reports.show', ['type' => 'persediaan'] + $tooLong))->assertOk();
    }

    public function test_reversed_dates_are_swapped(): void
    {
        $props = $this->props('penjualan', [
            'from' => today()->toDateString(),
            'to' => today()->subDays(3)->toDateString(),
        ]);

        $this->assertSame(today()->subDays(3)->toDateString(), $props['filters']['from']);
        $this->assertSame(today()->toDateString(), $props['filters']['to']);
    }

    public function test_index_lists_all_reports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index'));
        $response->assertOk();

        $reports = json_decode(json_encode($response->viewData('page')), true)['props']['reports'];

        $this->assertSame(array_keys(ReportService::TYPES), array_column($reports, 'slug'));
    }

    // ------------------------------------------------------------------ ekspor

    public function test_screen_shows_at_most_100_rows_but_exports_all(): void
    {
        Product::factory()->count(101)->create(['store_id' => $this->store->id]); // total 105 dengan 4 barang awal

        $props = $this->props('persediaan');
        $this->assertSame(105, $props['rowsTotal']);
        $this->assertSame(100, $props['screenRows']);
        $this->assertCount(100, $props['report']['rows']);

        Excel::fake();
        $this->get(route('reports.excel', 'persediaan'))->assertOk();

        Excel::assertDownloaded('laporan-persediaan-'.now()->format('Ymd').'.xlsx', function (ReportExport $export) {
            return count($export->array()) > 105;
        });
    }

    public function test_pdf_download_has_pdf_content_type_and_filename(): void
    {
        $this->sell($this->cola, 3);
        $from = today()->subDays(2);
        $to = today();

        $response = $this->actingAs($this->admin)->get(route('reports.pdf', [
            'type' => 'penjualan',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'laporan-penjualan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf',
            $response->headers->get('Content-Disposition'),
        );
    }

    public function test_excel_export_contains_summary_header_and_rows(): void
    {
        $sale = $this->sell($this->cola, 10);
        $from = today()->subDays(2);
        $to = today();

        Excel::fake();

        $this->actingAs($this->admin)->get(route('reports.excel', [
            'type' => 'penjualan',
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]))->assertOk();

        Excel::assertDownloaded(
            'laporan-penjualan-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx',
            function (ReportExport $export) use ($sale) {
                $cells = collect($export->array())->flatten();

                return $cells->contains('Laporan Penjualan')
                    && $cells->contains('No. invoice')
                    && $cells->contains($sale->invoice_number)
                    && $cells->contains(30000.0);
            },
        );
    }

    public function test_every_report_exports_to_real_pdf_and_excel_files(): void
    {
        $this->sell($this->cola, 3);
        $this->stockIn($this->chips, 2, false, 1000);
        $this->stockOut($this->cookie, 1, 'rusak');

        $this->actingAs($this->admin);

        foreach (array_keys(ReportService::TYPES) as $type) {
            $pdf = $this->get(route('reports.pdf', $type));
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), "PDF {$type} tidak valid");

            $xlsx = $this->get(route('reports.excel', $type));
            $xlsx->assertOk();
            $this->assertStringStartsWith(
                'PK',
                file_get_contents($xlsx->baseResponse->getFile()->getPathname()),
                "Excel {$type} bukan file xlsx",
            );
        }
    }
}
