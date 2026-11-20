<?php

namespace App\Support;

class ReportFormatter
{
    public static function rupiah(float|int|string|null $value): string
    {
        $v = (float) $value;
        $decimals = abs($v - round($v)) < 0.005 ? 0 : 2;

        return ($v < 0 ? '-' : '').'Rp '.number_format(abs($v), $decimals, ',', '.');
    }

    public static function number(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'aman' => 'Stok Aman',
            'menipis' => 'Stok Menipis',
            'habis' => 'Stok Habis',
            default => (string) $status,
        };
    }

    /** Teks untuk ditampilkan (PDF dan ringkasan). */
    public static function display(mixed $value, string $type): string
    {
        return match ($type) {
            'money' => self::rupiah($value),
            'number' => self::number($value),
            'status' => self::statusLabel($value),
            default => ($value === null || $value === '') ? '—' : (string) $value,
        };
    }

    /** Nilai sel Excel: angka tetap angka agar bisa dijumlahkan. */
    public static function excelValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'money' => (float) $value,
            'number' => (int) $value,
            'status' => self::statusLabel($value),
            default => $value === null ? '' : (string) $value,
        };
    }
}
