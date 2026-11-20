<?php

namespace App\Services\Ai;

use App\Support\ReportFormatter;
use Illuminate\Support\Str;

/**
 * Mode demo: jawaban dibuat dengan aturan sederhana dari data nyata toko.
 * Selalu berlabel SIMULASI dan tidak pernah berpura-pura sebagai AI sungguhan.
 */
class DemoProvider implements AiProvider
{
    public const LABEL = '[SIMULASI] Jawaban ini dibuat oleh mode demo dari data toko Anda dengan aturan sederhana. Ini bukan AI sungguhan.';
    public const FOOTER = 'Untuk mengaktifkan AI sungguhan, isi ANTHROPIC_API_KEY di file .env lalu jalankan php artisan config:clear.';

    public function reply(string $system, array $messages, array $context): AiReply
    {
        $last = $messages === [] ? '' : (string) $messages[array_key_last($messages)]['content'];
        $question = Str::lower($last);

        $body = match ($this->intent($question)) {
            'slow' => $this->slowMovers($context),
            'best' => $this->bestSellers($context, $question),
            'reorder' => $this->reorder($context),
            'low' => $this->lowStock($context),
            'sales' => $this->sales($context, $question),
            'inventory' => $this->inventory($context),
            default => $this->help(),
        };

        return new AiReply(self::LABEL."\n\n".$body."\n\n".self::FOOTER, true, ['provider' => 'demo']);
    }

    private function intent(string $q): string
    {
        return match (true) {
            Str::contains($q, ['jarang', 'lambat', 'kurang laku', 'tidak laku', 'sepi']) => 'slow',
            Str::contains($q, ['laris', 'paling banyak terjual', 'best seller']) => 'best',
            Str::contains($q, ['tambah', 'beli', 'belanja', 'pengadaan', 'restock', 'rekomendasi', 'dipesan', 'kulakan']) => 'reorder',
            Str::contains($q, ['hampir habis', 'menipis', 'habis', 'stok rendah', 'stok minimum']) => 'low',
            Str::contains($q, ['penjualan', 'omzet', 'pendapatan', 'tren', 'transaksi']) => 'sales',
            Str::contains($q, ['kondisi', 'persediaan', 'ringkasan', 'stok']) => 'inventory',
            default => 'help',
        };
    }

    private function lowStock(array $c): string
    {
        $total = (int) $c['jumlah_barang_stok_rendah_total'];

        if ($total === 0) {
            return 'Semua barang masih di atas stok minimum. Tidak ada yang menipis atau habis saat ini.';
        }

        $shown = count($c['barang_stok_rendah']);
        $head = "Ada {$total} barang yang stoknya menipis atau habis"
            .($shown < $total ? " (menampilkan {$shown} teratas)" : '').':';

        return implode("\n", array_merge([$head], $this->lowStockLines($c)));
    }

    private function bestSellers(array $c, string $q): string
    {
        $week = Str::contains($q, 'minggu');
        $list = $week ? $c['produk_terlaris_7_hari'] : $c['produk_terlaris_30_hari'];
        $label = $week ? '7 hari terakhir' : '30 hari terakhir';

        if (! $c['status_data']['ada_penjualan'] || $list === []) {
            return "Data penjualan untuk {$label} belum mencukupi: belum ada transaksi penjualan pada periode itu.";
        }

        $lines = ["Produk terlaris dalam {$label} (menurut jumlah terjual):"];
        foreach (array_slice($list, 0, 5) as $i => $p) {
            $lines[] = sprintf(
                '- %d. **%s**: terjual %s %s, penjualan %s',
                $i + 1, $p['nama'], $this->n($p['terjual']), $p['satuan'], $this->rp($p['pendapatan']),
            );
        }

        return implode("\n", $lines);
    }

    private function slowMovers(array $c): string
    {
        if (! $c['status_data']['ada_penjualan']) {
            return 'Data penjualan belum mencukupi: belum ada transaksi penjualan, jadi belum bisa menilai barang yang lambat terjual.';
        }

        if ($c['produk_kurang_laku_30_hari'] === []) {
            return 'Tidak ada barang berstok yang bisa dianalisis saat ini.';
        }

        $lines = ['Barang berstok yang paling sedikit terjual dalam 30 hari terakhir:'];
        foreach (array_slice($c['produk_kurang_laku_30_hari'], 0, 5) as $p) {
            $lines[] = sprintf(
                '- **%s**: terjual %s %s, stok %s %s, terakhir terjual %s',
                $p['nama'], $this->n($p['terjual_30_hari']), $p['satuan'], $this->n($p['stok']), $p['satuan'], $p['terakhir_terjual'],
            );
        }

        return implode("\n", $lines);
    }

    private function reorder(array $c): string
    {
        $total = (int) $c['jumlah_barang_stok_rendah_total'];

        if ($total === 0) {
            return 'Belum ada barang yang perlu ditambah: semua barang masih di atas stok minimum.';
        }

        if (! $c['status_data']['ada_penjualan']) {
            return implode("\n", array_merge([
                'Data penjualan belum mencukupi untuk menghitung kebutuhan dari rata-rata penjualan. Barang berikut sudah di bawah atau sama dengan stok minimum dan perlu dicek:',
            ], $this->lowStockLines($c)));
        }

        $lines = ['Saran pembelian untuk barang yang stoknya menipis atau habis:'];
        foreach ($c['barang_stok_rendah'] as $p) {
            if (($p['saran_pembelian'] ?? 0) <= 0) {
                continue;
            }

            $lines[] = sprintf(
                '- **%s**: beli sekitar %s %s (stok %s, rata-rata terjual %s per hari)',
                $p['nama'], $this->n($p['saran_pembelian']), $p['satuan'], $this->n($p['stok']), $this->d($p['rata_rata_terjual_per_hari']),
            );
        }

        if (count($lines) === 1) {
            return 'Berdasarkan perhitungan sederhana, belum ada barang stok rendah yang perlu ditambah saat ini.';
        }

        $lines[] = '';
        $lines[] = 'Rumus sederhana: rata-rata penjualan harian × '.AiContextBuilder::COVER_DAYS.' hari + stok minimum − stok saat ini. Ini bukan prediksi machine learning.';

        return implode("\n", $lines);
    }

    private function sales(array $c, string $q): string
    {
        if (! $c['status_data']['ada_penjualan']) {
            return 'Data penjualan belum mencukupi: belum ada transaksi penjualan yang tercatat.';
        }

        $periods = match (true) {
            Str::contains($q, 'bulan lalu') => ['bulan_lalu' => 'Bulan lalu'],
            Str::contains($q, 'bulan') => ['bulan_ini' => 'Bulan ini'],
            Str::contains($q, 'minggu') => ['tujuh_hari_terakhir' => '7 hari terakhir'],
            Str::contains($q, 'hari ini') => ['hari_ini' => 'Hari ini'],
            default => ['hari_ini' => 'Hari ini', 'tujuh_hari_terakhir' => '7 hari terakhir', 'bulan_ini' => 'Bulan ini'],
        };

        $lines = ['Ringkasan penjualan:'];
        foreach ($periods as $key => $label) {
            $p = $c['penjualan'][$key];
            $lines[] = sprintf(
                '- **%s**: %s transaksi, penjualan %s, %s unit terjual, laba kotor %s',
                $label, $this->n($p['transaksi']), $this->rp($p['pendapatan']), $this->n($p['unit_terjual']), $this->rp($p['laba_kotor']),
            );
        }

        $change = $c['perbandingan_7_hari']['selisih_persen'];
        if ($change !== null && (isset($periods['tujuh_hari_terakhir']) || count($periods) > 1)) {
            $lines[] = sprintf(
                'Dibanding 7 hari sebelumnya, penjualan %s %s%%.',
                $change >= 0 ? 'naik' : 'turun', $this->d(abs($change)),
            );
        }

        $lines[] = 'Laba kotor = penjualan dikurangi harga beli (belum termasuk biaya operasional).';

        return implode("\n", $lines);
    }

    private function inventory(array $c): string
    {
        $p = $c['persediaan'];

        if ((int) $p['jumlah_jenis_barang'] === 0) {
            return 'Belum ada barang yang terdaftar di toko ini.';
        }

        $lines = [sprintf(
            'Kondisi persediaan toko: %s jenis barang. %s aman, %s menipis, %s habis. Nilai persediaan (harga beli): %s.',
            $this->n($p['jumlah_jenis_barang']), $this->n($p['stok_aman']), $this->n($p['stok_menipis']), $this->n($p['stok_habis']),
            $this->rp($p['nilai_persediaan_harga_beli']),
        )];

        if ($c['barang_stok_rendah'] !== []) {
            $lines[] = 'Perlu perhatian:';
            $lines = array_merge($lines, $this->lowStockLines($c, 3));
        }

        return implode("\n", $lines);
    }

    private function help(): string
    {
        return implode("\n", [
            'Saya belum mengenali pertanyaan itu. Di mode demo, saya hanya bisa menjawab topik berikut dari data toko Anda:',
            '- Barang yang stoknya hampir habis',
            '- Produk terlaris',
            '- Barang yang jarang terjual',
            '- Jumlah stok yang perlu ditambah',
            '- Kondisi persediaan',
            '- Ringkasan penjualan',
        ]);
    }

    /** @return array<int, string> */
    private function lowStockLines(array $c, ?int $limit = null): array
    {
        $items = $limit ? array_slice($c['barang_stok_rendah'], 0, $limit) : $c['barang_stok_rendah'];

        return array_map(fn (array $p) => sprintf(
            '- **%s**: %s, stok %s %s (minimum %s)',
            $p['nama'], $p['status'], $this->n($p['stok']), $p['satuan'], $this->n($p['stok_minimum']),
        ), $items);
    }

    private function n(mixed $value): string
    {
        return ReportFormatter::number($value);
    }

    private function rp(mixed $value): string
    {
        return ReportFormatter::rupiah($value);
    }

    private function d(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
