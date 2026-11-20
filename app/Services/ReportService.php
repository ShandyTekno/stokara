<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\StockOutReason;
use App\Support\ReportFormatter;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public const MAX_DAYS = 366;
    public const TOP_LIMIT = 20;

    public const TYPES = [
        'persediaan' => [
            'label' => 'Laporan Persediaan',
            'description' => 'Kondisi stok, status, dan nilai persediaan saat ini.',
            'period' => false,
            'status' => true,
        ],
        'barang-masuk' => [
            'label' => 'Laporan Barang Masuk',
            'description' => 'Riwayat pembelian dan penambahan stok pada periode tertentu.',
            'period' => true,
            'status' => false,
        ],
        'barang-keluar' => [
            'label' => 'Laporan Barang Keluar',
            'description' => 'Barang rusak, hilang, atau kadaluarsa pada periode tertentu.',
            'period' => true,
            'status' => false,
        ],
        'penjualan' => [
            'label' => 'Laporan Penjualan',
            'description' => 'Transaksi penjualan, omzet, harga pokok, dan laba kotor.',
            'period' => true,
            'status' => false,
        ],
        'produk-terlaris' => [
            'label' => 'Laporan Produk Terlaris',
            'description' => 'Barang dengan jumlah terjual terbanyak pada periode.',
            'period' => true,
            'status' => false,
        ],
        'produk-kurang-laku' => [
            'label' => 'Laporan Produk Kurang Laku',
            'description' => 'Barang berstok yang paling sedikit terjual pada periode.',
            'period' => true,
            'status' => false,
        ],
    ];

    /**
     * @param  array{from?: ?string, to?: ?string, category?: mixed, status?: ?string}  $input
     * @return array{from: Carbon, to: Carbon, category: ?int, status: ?string}
     */
    public function resolveFilters(array $input, bool $usesPeriod = true): array
    {
        $from = ! empty($input['from']) ? Carbon::parse($input['from'])->startOfDay() : now()->startOfMonth();
        $to = ! empty($input['to']) ? Carbon::parse($input['to'])->endOfDay() : now()->endOfDay();

        if ($from > $to) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        if ($usesPeriod && $days > self::MAX_DAYS) {
            throw ValidationException::withMessages([
                'to' => 'Rentang tanggal maksimal '.self::MAX_DAYS.' hari.',
            ]);
        }

        return [
            'from' => $from,
            'to' => $to,
            'category' => ! empty($input['category']) ? (int) $input['category'] : null,
            'status' => $input['status'] ?? null,
        ];
    }

    public function generate(string $type, int $storeId, array $filters): array
    {
        $meta = self::TYPES[$type] ?? null;
        abort_if($meta === null, 404);

        $body = match ($type) {
            'persediaan' => $this->inventory($storeId, $filters),
            'barang-masuk' => $this->stockIns($storeId, $filters),
            'barang-keluar' => $this->stockOuts($storeId, $filters),
            'penjualan' => $this->sales($storeId, $filters),
            'produk-terlaris' => $this->bestSellers($storeId, $filters),
            'produk-kurang-laku' => $this->slowMovers($storeId, $filters),
        };

        return array_merge([
            'type' => $type,
            'title' => $meta['label'],
            'filters_text' => $this->filterLines($meta, $storeId, $filters),
        ], $body);
    }

    // ---------------------------------------------------------------- laporan

    private function inventory(int $storeId, array $f): array
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.store_id', $storeId)
            ->whereNull('p.deleted_at')
            ->when($f['category'], fn ($q, $id) => $q->where('p.category_id', $id));
        $this->applyStatus($query, $f['status']);

        $items = $query->orderBy('c.name')->orderBy('p.name')->get([
            'p.code', 'p.name', 'p.unit', 'p.stock', 'p.min_stock', 'p.purchase_price', 'c.name as category',
        ]);

        $counts = ['aman' => 0, 'menipis' => 0, 'habis' => 0];
        $totalStock = 0;
        $totalValue = 0.0;

        $rows = $items->map(function ($r) use (&$counts, &$totalStock, &$totalValue) {
            $stock = (int) $r->stock;
            $status = $this->status($stock, (int) $r->min_stock);
            $value = round($stock * (float) $r->purchase_price, 2);

            $counts[$status]++;
            $totalStock += $stock;
            $totalValue += $value;

            return [
                'code' => $r->code,
                'name' => $r->name,
                'category' => $r->category ?? 'Tanpa kategori',
                'stock' => $stock,
                'unit' => $r->unit,
                'min_stock' => (int) $r->min_stock,
                'status' => $status,
                'purchase_price' => (float) $r->purchase_price,
                'value' => $value,
            ];
        })->all();

        return [
            'columns' => [
                $this->col('code', 'Kode'),
                $this->col('name', 'Barang'),
                $this->col('category', 'Kategori'),
                $this->col('stock', 'Stok', 'number'),
                $this->col('unit', 'Satuan'),
                $this->col('min_stock', 'Stok minimum', 'number'),
                $this->col('status', 'Status', 'status'),
                $this->col('purchase_price', 'Harga beli', 'money'),
                $this->col('value', 'Nilai persediaan', 'money'),
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Jenis barang', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total stok (satuan terkecil)', 'value' => $totalStock, 'type' => 'number'],
                ['label' => 'Nilai persediaan (harga beli)', 'value' => round($totalValue, 2), 'type' => 'money'],
                ['label' => 'Stok aman', 'value' => $counts['aman'], 'type' => 'number', 'tone' => 'green'],
                ['label' => 'Stok menipis', 'value' => $counts['menipis'], 'type' => 'number', 'tone' => 'yellow'],
                ['label' => 'Stok habis', 'value' => $counts['habis'], 'type' => 'number', 'tone' => 'red'],
            ],
            'chart' => [
                'kind' => 'bar',
                'title' => 'Jumlah barang menurut status stok',
                'labels' => ['Stok Aman', 'Stok Menipis', 'Stok Habis'],
                'datasets' => [[
                    'label' => 'Jumlah barang',
                    'data' => [$counts['aman'], $counts['menipis'], $counts['habis']],
                    'backgroundColor' => ['#16a34a', '#eab308', '#dc2626'],
                ]],
                'currency' => false,
            ],
            'notes' => ['Nilai persediaan dihitung dari jumlah stok × harga beli per satuan terkecil saat ini.'],
        ];
    }

    private function stockIns(int $storeId, array $f): array
    {
        $lines = DB::table('stock_in_details as d')
            ->join('stock_ins as s', 's.id', '=', 'd.stock_in_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as sp', 'sp.id', '=', 's.supplier_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.transaction_date', '>=', $f['from']->toDateString())
            ->whereDate('s.transaction_date', '<=', $f['to']->toDateString())
            ->when($f['category'], fn ($q, $id) => $q->where('p.category_id', $id))
            ->orderByDesc('s.transaction_date')->orderByDesc('s.id')->orderBy('d.id')
            ->get([
                's.id as stock_in_id', 's.transaction_number', 's.transaction_date', 'sp.name as supplier',
                'p.name as product', 'p.unit', 'p.pack_unit', 'c.name as category',
                'd.input_quantity', 'd.is_package', 'd.quantity', 'd.unit_cost', 'd.subtotal',
            ]);

        $rows = $lines->map(function ($r) {
            $date = Carbon::parse($r->transaction_date);

            return [
                'date_key' => $date->toDateString(),
                'date' => $date->format('d/m/Y'),
                'number' => $r->transaction_number,
                'supplier' => $r->supplier ?? 'Tanpa pemasok',
                'product' => $r->product,
                'category' => $r->category ?? 'Tanpa kategori',
                'bought' => ReportFormatter::number($r->input_quantity).' '.($r->is_package ? $r->pack_unit : $r->unit),
                'quantity' => (int) $r->quantity,
                'unit' => $r->unit,
                'unit_cost' => (float) $r->unit_cost,
                'subtotal' => (float) $r->subtotal,
            ];
        })->all();

        [$labels, $values] = $this->series($rows, 'subtotal', $f);

        return [
            'columns' => [
                $this->col('date', 'Tanggal'),
                $this->col('number', 'No. transaksi'),
                $this->col('supplier', 'Pemasok'),
                $this->col('product', 'Barang'),
                $this->col('category', 'Kategori'),
                $this->col('bought', 'Dibeli'),
                $this->col('quantity', 'Masuk stok', 'number'),
                $this->col('unit', 'Satuan'),
                $this->col('unit_cost', 'Harga/satuan', 'money'),
                $this->col('subtotal', 'Subtotal', 'money'),
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Jumlah transaksi', 'value' => $lines->pluck('stock_in_id')->unique()->count(), 'type' => 'number'],
                ['label' => 'Jumlah baris barang', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total biaya', 'value' => round(collect($rows)->sum('subtotal'), 2), 'type' => 'money'],
            ],
            'chart' => [
                'kind' => 'line',
                'title' => 'Biaya barang masuk',
                'labels' => $labels,
                'datasets' => [['label' => 'Biaya barang masuk', 'data' => $values]],
                'currency' => true,
            ],
            'notes' => ['"Masuk stok" dan "Harga/satuan" memakai satuan terkecil barang.'],
        ];
    }

    private function stockOuts(int $storeId, array $f): array
    {
        $lines = DB::table('stock_out_details as d')
            ->join('stock_outs as s', 's.id', '=', 'd.stock_out_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.transaction_date', '>=', $f['from']->toDateString())
            ->whereDate('s.transaction_date', '<=', $f['to']->toDateString())
            ->when($f['category'], fn ($q, $id) => $q->where('p.category_id', $id))
            ->orderByDesc('s.transaction_date')->orderByDesc('s.id')->orderBy('d.id')
            ->get([
                's.id as stock_out_id', 's.transaction_number', 's.transaction_date', 's.reason', 's.notes',
                'p.name as product', 'p.unit', 'c.name as category', 'd.quantity',
            ]);

        $rows = $lines->map(function ($r) {
            $date = Carbon::parse($r->transaction_date);

            return [
                'date_key' => $date->toDateString(),
                'date' => $date->format('d/m/Y'),
                'number' => $r->transaction_number,
                'reason' => StockOutReason::tryFrom($r->reason)?->label() ?? $r->reason,
                'reason_key' => $r->reason,
                'product' => $r->product,
                'category' => $r->category ?? 'Tanpa kategori',
                'quantity' => (int) $r->quantity,
                'unit' => $r->unit,
                'notes' => $r->notes ?? '—',
                'stock_out_id' => (int) $r->stock_out_id,
            ];
        })->all();

        $cases = StockOutReason::cases();
        $perReason = array_map(
            fn (StockOutReason $case) => collect($rows)->where('reason_key', $case->value)->pluck('stock_out_id')->unique()->count(),
            $cases,
        );

        return [
            'columns' => [
                $this->col('date', 'Tanggal'),
                $this->col('number', 'No. transaksi'),
                $this->col('reason', 'Alasan'),
                $this->col('product', 'Barang'),
                $this->col('category', 'Kategori'),
                $this->col('quantity', 'Jumlah', 'number'),
                $this->col('unit', 'Satuan'),
                $this->col('notes', 'Keterangan'),
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Jumlah transaksi', 'value' => $lines->pluck('stock_out_id')->unique()->count(), 'type' => 'number'],
                ['label' => 'Jumlah baris barang', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total jumlah keluar', 'value' => (int) collect($rows)->sum('quantity'), 'type' => 'number'],
            ],
            'chart' => [
                'kind' => 'bar',
                'title' => 'Jumlah transaksi menurut alasan',
                'labels' => array_map(fn (StockOutReason $c) => $c->label(), $cases),
                'datasets' => [['label' => 'Jumlah transaksi', 'data' => $perReason, 'backgroundColor' => '#dc2626']],
                'currency' => false,
            ],
            'notes' => ['"Total jumlah keluar" menjumlahkan satuan terkecil dari semua barang, yang bisa berbeda jenis satuannya.'],
        ];
    }

    private function sales(int $storeId, array $f): array
    {
        $sales = DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sold_at', '>=', $f['from']->toDateString())
            ->whereDate('s.sold_at', '<=', $f['to']->toDateString())
            ->when($f['category'], fn ($q, $id) => $q->where('p.category_id', $id))
            ->groupBy('s.id', 's.invoice_number', 's.sold_at', 's.payment_method', 'u.name')
            ->selectRaw('s.id, s.invoice_number, s.sold_at, s.payment_method, u.name as cashier, SUM(d.quantity) as units, SUM(d.subtotal) as revenue, SUM(d.unit_cost * d.quantity) as cost')
            ->orderByDesc('s.sold_at')->orderByDesc('s.id')
            ->get();

        $rows = $sales->map(function ($r) {
            $soldAt = Carbon::parse($r->sold_at);
            $revenue = round((float) $r->revenue, 2);
            $cost = round((float) $r->cost, 2);

            return [
                'date_key' => $soldAt->toDateString(),
                'sold_at' => $soldAt->format('d/m/Y H:i'),
                'invoice' => $r->invoice_number,
                'cashier' => $r->cashier ?? '—',
                'payment' => PaymentMethod::tryFrom($r->payment_method)?->label() ?? $r->payment_method,
                'payment_key' => $r->payment_method,
                'units' => (int) $r->units,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
            ];
        })->all();

        $collection = collect($rows);
        [$labels, $revenueSeries] = $this->series($rows, 'revenue', $f);
        [, $profitSeries] = $this->series($rows, 'profit', $f);

        $notes = ['Laba kotor = penjualan dikurangi harga beli saat transaksi (tanpa biaya operasional).'];
        if ($f['category']) {
            $notes[] = 'Dengan filter kategori, hanya baris barang dari kategori tersebut yang dihitung, sehingga total per transaksi bisa lebih kecil dari struknya.';
        }

        return [
            'columns' => [
                $this->col('sold_at', 'Waktu'),
                $this->col('invoice', 'No. invoice'),
                $this->col('cashier', 'Kasir'),
                $this->col('payment', 'Pembayaran'),
                $this->col('units', 'Jumlah unit', 'number'),
                $this->col('revenue', 'Penjualan', 'money'),
                $this->col('cost', 'Harga pokok', 'money'),
                $this->col('profit', 'Laba kotor', 'money'),
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Jumlah transaksi', 'value' => count($rows), 'type' => 'number'],
                ['label' => 'Total penjualan', 'value' => round($collection->sum('revenue'), 2), 'type' => 'money'],
                ['label' => 'Total harga pokok', 'value' => round($collection->sum('cost'), 2), 'type' => 'money'],
                ['label' => 'Laba kotor', 'value' => round($collection->sum('profit'), 2), 'type' => 'money', 'tone' => 'green'],
                ['label' => 'Tunai', 'value' => round($collection->where('payment_key', PaymentMethod::Tunai->value)->sum('revenue'), 2), 'type' => 'money'],
                ['label' => 'QRIS', 'value' => round($collection->where('payment_key', PaymentMethod::Qris->value)->sum('revenue'), 2), 'type' => 'money'],
            ],
            'chart' => [
                'kind' => 'line',
                'title' => 'Penjualan dan laba kotor',
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Penjualan', 'data' => $revenueSeries],
                    [
                        'label' => 'Laba kotor',
                        'data' => $profitSeries,
                        'borderColor' => '#16a34a',
                        'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                        'pointBackgroundColor' => '#16a34a',
                    ],
                ],
                'currency' => true,
            ],
            'notes' => $notes,
        ];
    }

    private function bestSellers(int $storeId, array $f): array
    {
        $all = DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sold_at', '>=', $f['from']->toDateString())
            ->whereDate('s.sold_at', '<=', $f['to']->toDateString())
            ->when($f['category'], fn ($q, $id) => $q->where('p.category_id', $id))
            ->groupBy('p.id', 'p.code', 'p.name', 'p.unit', 'c.name')
            ->selectRaw('p.code, p.name, p.unit, c.name as category, SUM(d.quantity) as qty, SUM(d.subtotal) as revenue, SUM(d.unit_cost * d.quantity) as cost')
            ->orderByDesc('qty')->orderBy('p.name')
            ->get();

        $rows = $all->take(self::TOP_LIMIT)->values()->map(fn ($r, $i) => [
            'rank' => $i + 1,
            'code' => $r->code,
            'product' => $r->name,
            'category' => $r->category ?? 'Tanpa kategori',
            'qty' => (int) $r->qty,
            'unit' => $r->unit,
            'revenue' => round((float) $r->revenue, 2),
            'profit' => round((float) $r->revenue - (float) $r->cost, 2),
        ])->all();

        $top = array_slice($rows, 0, 10);

        return [
            'columns' => [
                $this->col('rank', '#', 'number'),
                $this->col('code', 'Kode'),
                $this->col('product', 'Barang'),
                $this->col('category', 'Kategori'),
                $this->col('qty', 'Terjual', 'number'),
                $this->col('unit', 'Satuan'),
                $this->col('revenue', 'Penjualan', 'money'),
                $this->col('profit', 'Laba kotor', 'money'),
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Jenis produk terjual', 'value' => $all->count(), 'type' => 'number'],
                ['label' => 'Total unit terjual', 'value' => (int) $all->sum('qty'), 'type' => 'number'],
                ['label' => 'Total penjualan', 'value' => round((float) $all->sum('revenue'), 2), 'type' => 'money'],
                ['label' => 'Produk terlaris', 'value' => $all->first()->name ?? '—', 'type' => 'text'],
            ],
            'chart' => [
                'kind' => 'bar',
                'title' => '10 produk terlaris (jumlah terjual)',
                'labels' => array_map(fn ($r) => Str::limit($r['product'], 18), $top),
                'datasets' => [['label' => 'Terjual', 'data' => array_column($top, 'qty'), 'backgroundColor' => '#1e3a8a']],
                'currency' => false,
            ],
            'notes' => [
                'Menampilkan '.self::TOP_LIMIT.' produk teratas menurut jumlah terjual. Ringkasan di atas mencakup seluruh produk yang terjual.',
                'Jumlah terjual dihitung dalam satuan terkecil masing-masing barang.',
            ],
        ];
    }

    private function slowMovers(int $storeId, array $f): array
    {
        $baseSales = fn () => DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->groupBy('d.product_id');

        $sold = $baseSales()
            ->whereDate('s.sold_at', '>=', $f['from']->toDateString())
            ->whereDate('s.sold_at', '<=', $f['to']->toDateString())
            ->selectRaw('d.product_id, SUM(d.quantity) as qty');

        $last = $baseSales()->selectRaw('d.product_id, MAX(s.sold_at) as last_sold');

        $all = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoinSub($sold, 'sale_t', 'sale_t.product_id', '=', 'p.id')
            ->leftJoinSub($last, 'last_t', 'last_t.product_id', '=', 'p.id')
            ->where('p.store_id', $storeId)
            ->whereNull('p.deleted_at')
            ->where('p.stock', '>', 0)
            ->when($f['category'], fn ($q, $id) => $q->where('p.category_id', $id))
            ->selectRaw('p.code, p.name, p.unit, p.stock, p.purchase_price, c.name as category, COALESCE(sale_t.qty, 0) as qty, last_t.last_sold')
            ->orderByRaw('COALESCE(sale_t.qty, 0) asc')
            ->orderByDesc('p.stock')
            ->orderBy('p.name')
            ->get();

        $zero = $all->filter(fn ($r) => (int) $r->qty === 0);

        $rows = $all->take(self::TOP_LIMIT)->values()->map(fn ($r, $i) => [
            'rank' => $i + 1,
            'code' => $r->code,
            'product' => $r->name,
            'category' => $r->category ?? 'Tanpa kategori',
            'stock' => (int) $r->stock,
            'unit' => $r->unit,
            'qty' => (int) $r->qty,
            'last_sold' => $r->last_sold ? Carbon::parse($r->last_sold)->format('d/m/Y') : 'Belum pernah',
            'value' => round((int) $r->stock * (float) $r->purchase_price, 2),
        ])->all();

        $top = array_slice($rows, 0, 10);

        return [
            'columns' => [
                $this->col('rank', '#', 'number'),
                $this->col('code', 'Kode'),
                $this->col('product', 'Barang'),
                $this->col('category', 'Kategori'),
                $this->col('stock', 'Stok', 'number'),
                $this->col('unit', 'Satuan'),
                $this->col('qty', 'Terjual (periode)', 'number'),
                $this->col('last_sold', 'Terakhir terjual'),
                $this->col('value', 'Nilai stok', 'money'),
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Produk berstok dianalisis', 'value' => $all->count(), 'type' => 'number'],
                ['label' => 'Produk tanpa penjualan', 'value' => $zero->count(), 'type' => 'number', 'tone' => 'yellow'],
                [
                    'label' => 'Nilai stok tanpa penjualan',
                    'value' => round($zero->sum(fn ($r) => (int) $r->stock * (float) $r->purchase_price), 2),
                    'type' => 'money',
                ],
            ],
            'chart' => [
                'kind' => 'bar',
                'title' => '10 produk paling kurang laku',
                'labels' => array_map(fn ($r) => Str::limit($r['product'], 18), $top),
                'datasets' => [
                    ['label' => 'Stok', 'data' => array_column($top, 'stock'), 'backgroundColor' => '#eab308'],
                    ['label' => 'Terjual dalam periode', 'data' => array_column($top, 'qty'), 'backgroundColor' => '#1e3a8a'],
                ],
                'currency' => false,
            ],
            'notes' => [
                'Hanya barang yang masih memiliki stok. Diurutkan dari penjualan paling sedikit; jika sama, stok yang lebih besar lebih dulu.',
                'Menampilkan '.self::TOP_LIMIT.' produk teratas. Ringkasan mencakup seluruh barang berstok.',
                '"Terakhir terjual" dihitung dari seluruh riwayat penjualan, bukan hanya periode ini.',
            ],
        ];
    }

    // ----------------------------------------------------------------- bantuan

    private function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    private function status(int $stock, int $min): string
    {
        return $stock <= 0 ? 'habis' : ($stock <= $min ? 'menipis' : 'aman');
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        match ($status) {
            'habis' => $query->where('p.stock', '<=', 0),
            'menipis' => $query->where('p.stock', '>', 0)->whereColumn('p.stock', '<=', 'p.min_stock'),
            'aman' => $query->where('p.stock', '>', 0)->whereColumn('p.stock', '>', 'p.min_stock'),
            default => null,
        };
    }

    private function filterLines(array $meta, int $storeId, array $f): array
    {
        $lines = [$meta['period']
            ? 'Periode: '.$f['from']->format('d/m/Y').' – '.$f['to']->format('d/m/Y')
            : 'Kondisi per: '.now()->format('d/m/Y H:i')];

        if ($f['category']) {
            $name = DB::table('categories')->where('store_id', $storeId)->where('id', $f['category'])->value('name');
            if ($name) {
                $lines[] = 'Kategori: '.$name;
            }
        }

        if ($meta['status'] && $f['status']) {
            $lines[] = 'Status: '.ReportFormatter::statusLabel($f['status']);
        }

        return $lines;
    }

    /**
     * Deret waktu untuk grafik: harian jika rentang ≤ 62 hari, selain itu bulanan.
     * Pengelompokan dilakukan di PHP agar tidak bergantung pada fungsi tanggal MySQL.
     *
     * @return array{0: array<int, string>, 1: array<int, float>}
     */
    private function series(array $rows, string $valueKey, array $f): array
    {
        $daily = collect($rows)->groupBy('date_key')->map(fn ($g) => (float) $g->sum($valueKey))->all();

        $from = $f['from']->copy()->startOfDay();
        $to = $f['to']->copy()->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;

        $labels = [];
        $values = [];

        if ($days <= 62) {
            foreach (CarbonPeriod::create($from, $to) as $day) {
                $labels[] = $day->format('d/m');
                $values[] = round($daily[$day->toDateString()] ?? 0, 2);
            }

            return [$labels, $values];
        }

        $months = [];
        foreach ($daily as $date => $value) {
            $key = substr((string) $date, 0, 7);
            $months[$key] = ($months[$key] ?? 0) + $value;
        }

        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= $to->copy()->endOfDay()) {
            $labels[] = $cursor->format('m/Y');
            $values[] = round($months[$cursor->format('Y-m')] ?? 0, 2);
            $cursor->addMonth();
        }

        return [$labels, $values];
    }
}
