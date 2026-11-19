<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Tunai = 'tunai';
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::Qris => 'QRIS',
        };
    }
}
