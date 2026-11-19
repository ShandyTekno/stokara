<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Karyawan = 'karyawan';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin / Pemilik Toko',
            self::Karyawan => 'Karyawan',
        };
    }
}
