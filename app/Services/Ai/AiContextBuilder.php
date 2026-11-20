<?php

namespace App\Services\Ai;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menyusun ringkasan data toko (hanya satu toko) yang dikirim ke AI.
 * Semua angka dihitung di sini dari database; model tidak menghitung sendiri.
 */
class AiContextBuilder
{
    public const COVER_DAYS = 14;
    public const WINDOW_DAYS = 30;
    public const LOW_STOCK_LIMIT = 20;
    public const TOP_LIMIT = 10;

    /** @return array<string, mixed> */
    public function build(int $storeId): array
    {
        $today = today();
        $windowStart = $today->copy()->subDays(self::WINDOW_DAYS - 1);

        $storeName = (string) DB::table('stores')->where('id', $storeId)->value('name');
        $firstSale = DB::table('sales')->where('store_id', $storeId)->min('sold_at');
        $hasSales = $firstSale !== null;

        // Rata-rata penjualan dihitung sejak penjualan pertama (maksimal 30 hari),
        // agar toko baru tidak terlihat "lambat" hanya karena datanya masih sedikit.
        $velocityStart = $windowStart->copy();
        $dataDays = 0;
        if ($hasSales) {
            $firstDate = Carbon::parse($firstSale)->startOfDay();
            if ($firstDate->greaterThan($velocityStart)) {
                $velocityStart = $firstDate;
            }
            $dataDays = max(1, (int) $velocityStart->diffInDays($today) + 1);
        }

        $velocity = $hasSales ? $this->soldSince($storeId, $velocityStart, $today) : collect();

        $stats = DB::table('products')
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as habis,
                SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) as menipis,
                SUM(stock * purchase_price) as nilai')
            ->first();

        $total = (int) $stats->total;
        $habis = (int) $stats->habis;
        $menipis = (int) $stats->menipis;

        [$lowTotal, $lowStock] = $this->lowStock($storeId, $hasSales, $dataDays, $velocity);

        $thisWeek = $this->periodTotals($storeId, $today->copy()->subDays(6), $today);
        $prevWeek = $this->periodTotals($storeId, $today->copy()->subDays(13), $today->copy()->subDays(7));
        $change = $prevWeek['pendapatan'] > 0
            ? round(($thisWeek['pendapatan'] - $prevWeek['pendapatan']) / $prevWeek['pendapatan'] * 100, 1)
            : null;

        $prevMonthEnd = $today->copy()->startOfMonth()->subDay();

        return [
            'toko' => $this->clean($storeName, 100),
            'tanggal_hari_ini' => $today->toDateString(),
            'catatan_data' => $this->notes($total, $hasSales, $dataDays),
            'status_data' => [
                'ada_penjualan' => $hasSales,
                'hari_data_penjualan' => $dataDays,
            ],
            'persediaan' => [
                'jumlah_jenis_barang' => $total,
                'stok_aman' => $total - $habis - $menipis,
                'stok_menipis' => $menipis,
                'stok_habis' => $habis,
                'nilai_persediaan_harga_beli' => round((float) $stats->nilai, 2),
            ],
            'jumlah_barang_stok_rendah_total' => $lowTotal,
            'barang_stok_rendah' => $lowStock,
            'penjualan' => [
                'hari_ini' => $this->periodTotals($storeId, $today, $today),
                'tujuh_hari_terakhir' => $thisWeek,
                'tiga_puluh_hari_terakhir' => $this->periodTotals($storeId, $windowStart, $today),
                'bulan_ini' => $this->periodTotals($storeId, $today->copy()->startOfMonth(), $today),
                'bulan_lalu' => $this->periodTotals($storeId, $prevMonthEnd->copy()->startOfMonth(), $prevMonthEnd),
            ],
            'perbandingan_7_hari' => [
                'pendapatan_7_hari_terakhir' => $thisWeek['pendapatan'],
                'pendapatan_7_hari_sebelumnya' => $prevWeek['pendapatan'],
                'selisih_persen' => $change,
            ],
            'penjualan_harian_14_hari' => $this->dailySeries($storeId, $today),
            'produk_terlaris_7_hari' => $this->topSellers($storeId, $today->copy()->subDays(6), $today),
            'produk_terlaris_30_hari' => $this->topSellers($storeId, $windowStart, $today),
            'produk_kurang_laku_30_hari' => $hasSales ? $this->slowMovers($storeId, $windowStart, $today) : [],
            'barang_masuk_30_hari' => $this->stockInTotals($storeId, $windowStart, $today),
            'barang_keluar_30_hari' => $this->stockOutTotals($storeId, $windowStart, $today),
            'aturan_saran_pembelian' => 'saran_pembelian = rata-rata terjual per hari × '.self::COVER_DAYS
                .' hari (dibulatkan ke atas) + stok minimum − stok saat ini, minimal 0. Perhitungan rata-rata sederhana, bukan prediksi machine learning. Bernilai null bila data penjualan belum ada.',
        ];
    }

    /** @param array<string, mixed> $context */
    public function toJson(array $context): string
    {
        return (string) json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }

    // ------------------------------------------------------------------ bagian

    /** @return array{0: int, 1: array<int, array<string, mixed>>} */
    private function lowStock(int $storeId, bool $hasSales, int $dataDays, $velocity): array
    {
        $query = DB::table('products')
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->whereColumn('stock', '<=', 'min_stock');

        $total = (clone $query)->count();

        $rows = $query->orderBy('stock')->orderBy('name')
            ->limit(self::LOW_STOCK_LIMIT)
            ->get(['id', 'code', 'name', 'unit', 'stock', 'min_stock'])
            ->map(function ($p) use ($hasSales, $dataDays, $velocity) {
                $stock = (int) $p->stock;
                $min = (int) $p->min_stock;
                $avg = $hasSales ? ((int) ($velocity[$p->id] ?? 0)) / $dataDays : null;

                $suggest = null;
                $cover = null;
                if ($avg !== null) {
                    $suggest = max(0, (int) ceil(round($avg * self::COVER_DAYS, 4)) + $min - $stock);
                    $cover = $avg > 0 ? round($stock / $avg, 1) : null;
                }

                return [
                    'kode' => $this->clean($p->code, 40),
                    'nama' => $this->clean($p->name),
                    'stok' => $stock,
                    'satuan' => $this->clean($p->unit, 20),
                    'stok_minimum' => $min,
                    'status' => $stock <= 0 ? 'habis' : 'menipis',
                    'rata_rata_terjual_per_hari' => $avg === null ? null : round($avg, 2),
                    'perkiraan_hari_stok_cukup' => $cover,
                    'saran_pembelian' => $suggest,
                ];
            })
            ->all();

        return [$total, $rows];
    }

    private function soldSince(int $storeId, Carbon $from, Carbon $to)
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

    /** @return array<string, mixed> */
    private function periodTotals(int $storeId, Carbon $from, Carbon $to): array
    {
        $r = DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sold_at', '>=', $from->toDateString())
            ->whereDate('s.sold_at', '<=', $to->toDateString())
            ->selectRaw('COUNT(DISTINCT d.sale_id) as trx,
                COALESCE(SUM(d.subtotal), 0) as revenue,
                COALESCE(SUM(d.quantity), 0) as units,
                COALESCE(SUM(d.unit_cost * d.quantity), 0) as cost')
            ->first();

        $revenue = round((float) $r->revenue, 2);
        $cost = round((float) $r->cost, 2);

        return [
            'dari' => $from->toDateString(),
            'sampai' => $to->toDateString(),
            'transaksi' => (int) $r->trx,
            'pendapatan' => $revenue,
            'unit_terjual' => (int) $r->units,
            'laba_kotor' => round($revenue - $cost, 2),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function dailySeries(int $storeId, Carbon $today): array
    {
        $from = $today->copy()->subDays(13);

        $rows = DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sold_at', '>=', $from->toDateString())
            ->whereDate('s.sold_at', '<=', $today->toDateString())
            ->selectRaw('DATE(s.sold_at) as day, COUNT(DISTINCT d.sale_id) as trx, SUM(d.subtotal) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i)->toDateString();
            $row = $rows[$date] ?? null;

            $series[] = [
                'tanggal' => $date,
                'transaksi' => (int) ($row->trx ?? 0),
                'pendapatan' => round((float) ($row->revenue ?? 0), 2),
            ];
        }

        return $series;
    }

    /** @return array<int, array<string, mixed>> */
    private function topSellers(int $storeId, Carbon $from, Carbon $to): array
    {
        return DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->whereDate('s.sold_at', '>=', $from->toDateString())
            ->whereDate('s.sold_at', '<=', $to->toDateString())
            ->groupBy('p.id', 'p.name', 'p.unit')
            ->selectRaw('p.name, p.unit, SUM(d.quantity) as qty, SUM(d.subtotal) as revenue')
            ->orderByDesc('qty')
            ->orderBy('p.name')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($r) => [
                'nama' => $this->clean($r->name),
                'terjual' => (int) $r->qty,
                'satuan' => $this->clean($r->unit, 20),
                'pendapatan' => round((float) $r->revenue, 2),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function slowMovers(int $storeId, Carbon $from, Carbon $to): array
    {
        $sales = fn () => DB::table('sale_details as d')
            ->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('d.store_id', $storeId)
            ->where('s.store_id', $storeId)
            ->groupBy('d.product_id');

        $sold = $sales()
            ->whereDate('s.sold_at', '>=', $from->toDateString())
            ->whereDate('s.sold_at', '<=', $to->toDateString())
            ->selectRaw('d.product_id, SUM(d.quantity) as qty');

        $last = $sales()->selectRaw('d.product_id, MAX(s.sold_at) as last_sold');

        return DB::table('products as p')
            ->leftJoinSub($sold, 'sale_t', 'sale_t.product_id', '=', 'p.id')
            ->leftJoinSub($last, 'last_t', 'last_t.product_id', '=', 'p.id')
            ->where('p.store_id', $storeId)
            ->whereNull('p.deleted_at')
            ->where('p.stock', '>', 0)
            ->selectRaw('p.name, p.unit, p.stock, COALESCE(sale_t.qty, 0) as qty, last_t.last_sold')
            ->orderByRaw('COALESCE(sale_t.qty, 0) asc')
            ->orderByDesc('p.stock')
            ->orderBy('p.name')
            ->limit(self::TOP_LIMIT)
            ->get()
            ->map(fn ($r) => [
                'nama' => $this->clean($r->name),
                'stok' => (int) $r->stock,
                'satuan' => $this->clean($r->unit, 20),
                'terjual_30_hari' => (int) $r->qty,
                'terakhir_terjual' => $r->last_sold ? Carbon::parse($r->last_sold)->format('d/m/Y') : 'Belum pernah',
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function stockInTotals(int $storeId, Carbon $from, Carbon $to): array
    {
        $r = DB::table('stock_ins')
            ->where('store_id', $storeId)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_cost), 0) as total')
            ->first();

        return ['transaksi' => (int) $r->n, 'total_biaya' => round((float) $r->total, 2)];
    }

    /** @return array<string, mixed> */
    private function stockOutTotals(int $storeId, Carbon $from, Carbon $to): array
    {
        $r = DB::table('stock_outs as s')
            ->leftJoin('stock_out_details as d', 'd.stock_out_id', '=', 's.id')
            ->where('s.store_id', $storeId)
            ->whereDate('s.transaction_date', '>=', $from->toDateString())
            ->whereDate('s.transaction_date', '<=', $to->toDateString())
            ->selectRaw('COUNT(DISTINCT s.id) as n, COALESCE(SUM(d.quantity), 0) as units')
            ->first();

        return ['transaksi' => (int) $r->n, 'total_unit' => (int) $r->units];
    }

    /** @return array<int, string> */
    private function notes(int $products, bool $hasSales, int $dataDays): array
    {
        $notes = ['Stok dihitung dalam satuan terkecil masing-masing barang; jangan menjumlahkan satuan yang berbeda.'];

        if ($products === 0) {
            $notes[] = 'Belum ada barang yang terdaftar di toko ini.';
        }

        if (! $hasSales) {
            $notes[] = 'Belum ada transaksi penjualan sama sekali: analisis penjualan (produk terlaris, kurang laku, tren, dan saran pembelian) belum bisa dilakukan.';
        } elseif ($dataDays < 7) {
            $notes[] = "Data penjualan baru mencakup {$dataDays} hari; rata-rata penjualan dan saran pembelian masih kurang akurat.";
        }

        return $notes;
    }

    /** Membersihkan teks bebas dari data pengguna sebelum masuk instruksi AI. */
    private function clean(?string $value, int $max = 80): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F<>]+/u', ' ', (string) $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return Str::limit($value, $max, '…');
    }
}
