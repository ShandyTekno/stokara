<?php

namespace App\Enums;

enum StockMovementType: string
{
    case InitialStock = 'initial_stock';
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Sale = 'sale';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'Stok Awal',
            self::StockIn => 'Barang Masuk',
            self::StockOut => 'Barang Keluar',
            self::Sale => 'Penjualan',
            self::Adjustment => 'Penyesuaian',
        };
    }
}
