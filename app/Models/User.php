<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // is_active sengaja tidak mass-assignable: hanya diubah lewat aksi toggle.
    protected $fillable = ['store_id', 'name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    // Default di level model, agar objek yang baru dibuat langsung aktif.
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isKaryawan(): bool
    {
        return $this->role === Role::Karyawan;
    }
}
