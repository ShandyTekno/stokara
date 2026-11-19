<?php

namespace App\Enums;

enum StockStatus: string
{
    case Aman = 'aman';
    case Menipis = 'menipis';
    case Habis = 'habis';

    public function label(): string
    {
        return match ($this) {
            self::Aman => 'Stok Aman',
            self::Menipis => 'Stok Menipis',
            self::Habis => 'Stok Habis',
        };
    }

    // Hijau = aman, kuning = peringatan, merah = habis (sesuai desain)
    public function color(): string
    {
        return match ($this) {
            self::Aman => 'green',
            self::Menipis => 'yellow',
            self::Habis => 'red',
        };
    }
}
