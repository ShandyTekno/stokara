<?php

namespace App\Enums;

enum StockOutReason: string
{
    case Rusak = 'rusak';
    case Hilang = 'hilang';
    case Kadaluarsa = 'kadaluarsa';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
