<?php

namespace App\Services;

use App\Support\ReportFormatter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analisis persediaan dengan rata-rata penjualan sederhana (BUKAN machine learning).
 * Aturan hari data dan saran pembelian sama dengan AiContextBuilder (Tahap 9),
 * sehingga angka di AI Assistant dan Analisis AI konsisten.
 */
class StockAnalysisService
{
    public const PERIODS = [7, 14, 30];
    public const COVERS = [7, 14, 30];
    public const DEFAULT_PERIOD = 30;
    public const DEFAULT_COVER = 14;
    public const URGENT_DAYS = 7;
    public const SLOW_DAYS = 60;
    public const NEW_DAYS = 7;

    public const URGENCY_LABELS = [
        'habis' => 'Habis',
        'mendesak' => 'Mendesak',
        'direncanakan' => 'Direncanakan',
    ];

    private const URGENCY_ORDER = ['habis' => 0, 'mendesak' => 1, 'direncanakan' => 2];

    /** @return array<string, mixed> */
    public function analyze(
        int $storeId,
        int $period = self::DEFAULT_PERIOD,
        int $cover = self::DEFAULT_COVER,
        ?int $category = null,
    ): array {
        $today = today();
        $windowStart = $today->copy()->subDays($period - 1);

        $firstSale = DB::table('sales')->where('store_id', $storeId)->min('sold_at');
        $hasSales = $firstSale !== null;

        // Hari data dihitung sejak penjualan pertama (maksimal sebesar periode),
        // agar toko baru tidak terlihat "lambat" hanya karena datanya masih sedikit.
        $start = $windowStart->copy();
        $dataDays = 0;
        if ($hasSales) {
            $firstDate = Carbon::parse($firstSale)->startOfDay();
            if ($firstDate->greaterThan($start)) {
                $start = $firstDate;
            }
            $dataDays = max(1, (int) $start->diffInDays($today) + 1);
        }

        $sold = $hasSales ? $this->soldPerProduct($storeId, $start, $today) : collect();
        $lastSold = $hasSales ? $this->lastSoldPerProduct($storeId) : collect();

        $products = DB::table('products as p')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as sp', 'sp.id', '=', 'p.supplier_id')
            ->where('p.store_id', $storeId)
            ->whereNull('p.deleted_at')
            ->when($category, fn ($q, $id) => $q->where('p.category_id', $id))
            ->orderBy('p.name')
            ->get([
                'p.id', 'p.code', 'p.name', 'p.unit', 'p.pack_unit', 'p.units_per_pack',
                'p.stock', 'p.min_stock', 'p.purchase_price', 'p.created_at',
                'c.name as category', 'sp.name as supplier',
            ]);

        $newSince = $today->copy()->subDays(self::NEW_DAYS - 1);
        $reorder = [];
        $risk = [];
        $slow = [];

        foreach ($products as $p) {
            $row = $this->buildRow($p, $hasSales, $dataDays, $cover, $sold, $lastSold, $today, $newSince);

            $isReorder = $hasSales
                ? $row['suggested'] > 0
                    && ($row['stock'] <= $row['min_stock'] || ($row['days_left'] !== null && $row['days_left'] < $cover))
                : $row['stock'] <= $row['min_stock'];

            $isRisk = $hasSales && $row['stock'] > 0 && $row['sold'] > 0 && $row['days_left'] < $cover;

            $isSlow = $hasSales && $row['stock'] > 0
                && ($row['sold'] === 0 || ($row['days_left'] !== null && $row['days_left'] > self::SLOW_DAYS));

            if ($isReorder) {
                $reorder[] = $row + ['reason' => $this->reorderReason($row, $hasSales)];
            }
            if ($isRisk) {
                $risk[] = $row + ['reason' => $this->riskReason($row, $cover)];
            }
            if ($isSlow) {
                $slow[] = $row + ['reason' => $this->slowReason($row, $dataDays)];
            }
        }

        usort($reorder, fn (array $a, array $b) => [
            self::URGENCY_ORDER[$a['urgency']], $a['days_left'] ?? PHP_FLOAT_MAX, mb_strtolower($a['name']),
        ] <=> [
            self::URGENCY_ORDER[$b['urgency']], $b['days_left'] ?? PHP_FLOAT_MAX, mb_strtolower($b['name']),
        ]);
        usort($risk, fn (array $a, array $b) => [$a['days_left'], mb_strtolower($a['name'])] <=> [$b['days_left'], mb_strtolower($b['name'])]);
        usort($slow, fn (array $a, array $b) => [-$a['tied_value'], mb_strtolower($a['name'])] <=> [-$b['tied_value'], mb_strtolower($b['name'])]);

        $categoryName = $category
            ? DB::table('categories')->where('store_id', $storeId)->where('id', $category)->value('name')
            : null;

        return [
            'data' => [
                'has_sales' => $hasSales,
                'data_days' => $dataDays,
                'period' => $period,
                'cover' => $cover,
                'from' => $hasSales ? $start->toDateString() : null,
                'to' => $today->toDateString(),
                'category' => $category,
                'category_name' => $categoryName,
            ],
            'notes' => $this->notes($hasSales, $dataDays, $period),
            'summary' => [
                'products' => $products->count(),
                'reorder_count' => count($reorder),
                'urgent_count' => count(array_filter($reorder, fn (array $r) => $r['urgency'] !== 'direncanakan')),
                'reorder_cost' => round(array_sum(array_map(fn (array $r) => $r['estimated_cost'] ?? 0, $reorder)), 2),
                'risk_count' => count($risk),
                'slow_count' => count($slow),
                'slow_value' => round(array_sum(array_column($slow, 'tied_value')), 2),
            ],
            'formulas' => $this->formulas($cover),
            'definitions' => $this->definitions($cover),
            'reorder' => $reorder,
            'risk' => $risk,
            'slow' => $slow,
        ];
    }

    /**
     * Bentuk laporan (sama dengan ReportService) untuk ekspor PDF dan Excel.
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public function procurementReport(array $analysis): array
    {
        $d = $analysis['data'];
        $s = $analysis['summary'];
        $has = $d['has_sales'];

        $columns = [
            $this->col('rank', '#', 'number'),
            $this->col('code', 'Kode'),
            $this->col('name', 'Barang'),
            $this->col('supplier', 'Pemasok'),
            $this->col('urgency', 'Tingkat'),
            $this->col('stock', 'Stok', 'number'),
            $this->col('unit', 'Satuan'),
            $this->col('min_stock', 'Stok minimum', 'number'),
        ];

        if ($has) {
            array_push(
                $columns,
                $this->col('days_text', 'Sisa hari'),
                $this->col('suggested', 'Saran beli', 'number'),
                $this->col('pack_text', 'Setara kemasan'),
                $this->col('purchase_price', 'Harga beli/satuan', 'money'),
                $this->col('estimated_cost', 'Perkiraan biaya', 'money'),
            );
        }

        $rows = [];
        foreach ($analysis['reorder'] as $i => $r) {
            $rows[] = [
                'rank' => $i + 1,
                'code' => $r['code'],
                'name' => $r['name'],
                'supplier' => $r['supplier'] ?? 'Tanpa pemasok',
                'urgency' => self::URGENCY_LABELS[$r['urgency']],
                'stock' => $r['stock'],
                'unit' => $r['unit'],
                'min_stock' => $r['min_stock'],
                'days_text' => self::formatDays($r['days_left']),
                'suggested' => $r['suggested'],
                'pack_text' => $r['suggested_packs'] ? '≈ '.$r['suggested_packs'].' '.$r['pack_unit'] : '—',
                'purchase_price' => $r['purchase_price'],
                'estimated_cost' => $r['estimated_cost'],
            ];
        }

        $summary = [
            ['label' => 'Barang perlu dibeli', 'value' => $s['reorder_count'], 'type' => 'number'],
            ['label' => 'Barang habis atau mendesak', 'value' => $s['urgent_count'], 'type' => 'number', 'tone' => 'yellow'],
        ];
        if ($has) {
            $summary[] = ['label' => 'Perkiraan biaya pengadaan', 'value' => $s['reorder_cost'], 'type' => 'money'];
        }

        $filters = [
            $has
                ? "Periode analisis: {$d['period']} hari terakhir (data penjualan tersedia {$d['data_days']} hari)"
                : "Periode analisis: {$d['period']} hari terakhir (belum ada data penjualan)",
            "Cakupan stok: {$d['cover']} hari ke depan",
        ];
        if ($d['category_name']) {
            $filters[] = 'Kategori: '.$d['category_name'];
        }

        return [
            'title' => 'Daftar Prioritas Pengadaan',
            'filters_text' => $filters,
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $summary,
            'notes' => array_merge(
                $analysis['notes'],
                $analysis['formulas'],
                ['Daftar ini dihitung dengan rata-rata penjualan sederhana, bukan prediksi machine learning. Jumlah beli adalah saran dalam satuan terkecil; sesuaikan dengan kondisi toko dan harga terbaru dari pemasok.'],
            ),
        ];
    }

    /** Teks sisa hari untuk tabel dan ekspor. */
    public static function formatDays(?float $days): string
    {
        if ($days === null) {
            return '—';
        }
        if ($days > 365) {
            return '> 365';
        }

        return rtrim(rtrim(number_format($days, 1, ',', '.'), '0'), ',');
    }

    /** Teks sisa hari untuk kalimat. */
    public static function daysPhrase(float $days): string
    {
        return $days > 365 ? 'lebih dari 1 tahun' : self::formatDays($days).' hari';
    }

    /** @return array<int, string> */
    public function formulas(int $cover): array
    {
        return [
            'Rata-rata penjualan harian = jumlah terjual dalam periode ÷ jumlah hari data penjualan.',
            'Perkiraan hari stok cukup = stok saat ini ÷ rata-rata penjualan harian.',
            "Estimasi kebutuhan = rata-rata penjualan harian × {$cover} hari (dibulatkan ke atas).",
            'Saran pembelian = estimasi kebutuhan + stok minimum − stok saat ini (minimal 0), dalam satuan terkecil.',
            'Perkiraan biaya = saran pembelian × harga beli per satuan terkecil.',
        ];
    }

    /** @return array<int, string> */
    public function definitions(int $cover): array
    {
        $urgent = self::URGENT_DAYS;
        $slow = self::SLOW_DAYS;
        $new = self::NEW_DAYS;

        return [
            "Prioritas pengadaan: barang dengan saran pembelian lebih dari 0 yang stoknya sudah di bawah atau sama dengan stok minimum, atau diperkirakan habis sebelum {$cover} hari.",
            "Tingkat: Habis (stok 0), Mendesak (stok sudah di bawah atau sama dengan minimum, atau sisa {$urgent} hari atau kurang), Direncanakan (selain itu).",
            "Berpotensi kehabisan: barang berstok yang terjual dalam periode dan diperkirakan habis sebelum {$cover} hari.",
            "Perputaran lambat: barang berstok yang tidak terjual dalam periode, atau stoknya cukup untuk lebih dari {$slow} hari penjualan. Modal tertahan = stok × harga beli. Daftar ini juga menunjukkan stok berlebih.",
            "Label Baru: barang dibuat kurang dari {$new} hari lalu, sehingga datanya belum cukup untuk menilai perputarannya.",
        ];
    }

    // ------------------------------------------------------------------ bagian

    /** @return array<string, mixed> */
    private function buildRow(
        object $p,
        bool $hasSales,
        int $dataDays,
        int $cover,
        Collection $sold,
        Collection $lastSold,
        Carbon $today,
        Carbon $newSince,
    ): array {
        $stock = (int) $p->stock;
        $min = (int) $p->min_stock;
        $cost = (float) $p->purchase_price;
        $qty = $hasSales ? (int) ($sold[$p->id] ?? 0) : null;

        $avg = $hasSales ? $qty / $dataDays : null;
        $daysLeft = null;
        $need = null;
        $suggested = null;

        if ($avg !== null) {
            $need = (int) ceil(round($avg * $cover, 4));
            $suggested = max(0, $need + $min - $stock);
            $daysLeft = $stock <= 0 ? 0.0 : ($qty > 0 ? round($stock / $avg, 1) : null);
        }

        $perPack = max(1, (int) $p->units_per_pack);
        $packs = ($suggested !== null && $suggested > 0 && $p->pack_unit && $perPack > 1)
            ? (int) ceil($suggested / $perPack)
            : null;

        $last = $hasSales ? ($lastSold[$p->id] ?? null) : null;

        return [
            'id' => (int) $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'category' => $p->category ?? 'Tanpa kategori',
            'supplier' => $p->supplier,
            'unit' => $p->unit,
            'pack_unit' => $p->pack_unit,
            'units_per_pack' => $perPack,
            'stock' => $stock,
            'min_stock' => $min,
            'sold' => $qty,
            'avg_daily' => $avg === null ? null : round($avg, 2),
            'days_left' => $daysLeft,
            'need' => $need,
            'suggested' => $suggested,
            'suggested_packs' => $packs,
            'purchase_price' => $cost,
            'estimated_cost' => $suggested === null ? null : round($suggested * $cost, 2),
            'tied_value' => round($stock * $cost, 2),
            'urgency' => $this->urgency($stock, $min, $daysLeft),
            'last_sold' => $hasSales ? ($last ? Carbon::parse($last)->format('d/m/Y') : 'Belum pernah') : null,
            'out_date' => ($stock > 0 && $qty > 0 && $daysLeft !== null)
                ? $today->copy()->addDays((int) floor($daysLeft))->format('d/m/Y')
                : null,
            'is_new' => $p->created_at !== null && Carbon::parse($p->created_at)->greaterThanOrEqualTo($newSince),
        ];
    }

    private function urgency(int $stock, int $min, ?float $daysLeft): string
    {
        return match (true) {
            $stock <= 0 => 'habis',
            $stock <= $min || ($daysLeft !== null && $daysLeft <= self::URGENT_DAYS) => 'mendesak',
            default => 'direncanakan',
        };
    }

    private function reorderReason(array $r, bool $hasSales): string
    {
        $stockText = ReportFormatter::number($r['stock']).' '.$r['unit'];
        $state = $r['stock'] <= 0
            ? 'Stok habis'
            : ($r['stock'] <= $r['min_stock']
                ? "Stok {$stockText} sudah di bawah atau sama dengan minimum ({$r['min_stock']})"
                : "Stok {$stockText}");

        if (! $hasSales) {
            return $state.'. Data penjualan belum cukup untuk menghitung kebutuhan.';
        }

        $parts = [$state];
        if ($r['sold'] > 0) {
            $parts[] = 'rata-rata terjual '.$this->dec($r['avg_daily']).' '.$r['unit'].' per hari';
            if ($r['stock'] > 0) {
                $parts[] = 'cukup sekitar '.self::daysPhrase($r['days_left']);
            }
        } else {
            $parts[] = 'tidak terjual dalam periode analisis';
        }

        return implode('; ', $parts).'.';
    }

    private function riskReason(array $r, int $cover): string
    {
        $stockText = ReportFormatter::number($r['stock']).' '.$r['unit'];

        return "Stok {$stockText} cukup sekitar ".self::daysPhrase($r['days_left'])
            ." (habis sekitar {$r['out_date']}), lebih cepat dari cakupan {$cover} hari.";
    }

    private function slowReason(array $r, int $dataDays): string
    {
        $stockText = ReportFormatter::number($r['stock']).' '.$r['unit'];
        $tied = ReportFormatter::rupiah($r['tied_value']);

        if ($r['sold'] === 0) {
            return "Tidak terjual dalam {$dataDays} hari terakhir; stok {$stockText} (modal {$tied} tertahan).";
        }

        return "Stok {$stockText} cukup untuk sekitar ".self::daysPhrase($r['days_left'])
            .' penjualan, lebih dari '.self::SLOW_DAYS." hari (modal {$tied} tertahan).";
    }

    /** @return array<int, string> */
    private function notes(bool $hasSales, int $dataDays, int $period): array
    {
        if (! $hasSales) {
            return ['Belum ada transaksi penjualan sama sekali: rata-rata penjualan, estimasi kebutuhan, dan saran pembelian belum bisa dihitung. Daftar prioritas hanya memuat barang yang stoknya sudah di bawah atau sama dengan stok minimum.'];
        }

        if ($dataDays < 7) {
            return ["Data penjualan baru mencakup {$dataDays} hari; rata-rata dan saran pembelian masih kurang akurat."];
        }

        if ($dataDays < $period) {
            return ["Data penjualan tersedia {$dataDays} hari, kurang dari periode analisis {$period} hari; rata-rata dihitung dari {$dataDays} hari."];
        }

        return [];
    }

    private function soldPerProduct(int $storeId, Carbon $from, Carbon $to): Collection
    {
        return DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sold_at', '>=', $from->toDateString())
            ->whereDate('s.sold_at', '<=', $to->toDateString())
            ->groupBy('d.product_id')
            ->selectRaw('d.product_id, SUM(d.quantity) as qty')
            ->pluck('qty', 'product_id');
    }

    private function lastSoldPerProduct(int $storeId): Collection
    {
        return DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->groupBy('d.product_id')
            ->selectRaw('d.product_id, MAX(s.sold_at) as last_sold')
            ->pluck('last_sold', 'product_id');
    }

    private function dec(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }

    /** @return array{key: string, label: string, type: string} */
    private function col(string $key, string $label, string $type = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }
}
