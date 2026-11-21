<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    public const array THEME_COLORS = [
        'blue' => 'Biru Samudra',
        'emerald' => 'Hijau Zamrud',
        'indigo' => 'Indigo Modern',
        'rose' => 'Merah Mawar',
        'amber' => 'Kuning Amber',
        'violet' => 'Ungu Violet',
        'slate' => 'Abu Elegan',
    ];

    public const array SIDEBAR_STYLES = [
        'dark' => 'Gelap Elegan',
        'colored' => 'Warna Tema',
        'light' => 'Terang Minimalis',
    ];

    public const array FONT_FAMILIES = [
        'figtree' => 'Figtree',
        'inter' => 'Inter',
        'plus-jakarta-sans' => 'Plus Jakarta Sans',
        'poppins' => 'Poppins',
        'nunito' => 'Nunito',
        'roboto' => 'Roboto',
    ];

    public const array FONT_SIZES = [
        'sm' => 'Kecil (Kompak)',
        'md' => 'Normal (Standar)',
        'lg' => 'Besar (Nyaman)',
    ];

    public const array BORDER_RADII = [
        'sharp' => 'Tajam',
        'rounded' => 'Membulat (Standar)',
        'pill' => 'Ekstra Bulat',
    ];

    public const array DEFAULT_SETTINGS = [
        'theme_color' => 'blue',
        'sidebar_style' => 'dark',
        'font_family' => 'figtree',
        'font_size' => 'md',
        'border_radius' => 'rounded',
        'compact_mode' => false,
        'receipt_footer' => 'Terima kasih telah berbelanja!',
    ];

    protected $fillable = [
        'name',
        'app_name',
        'tagline',
        'phone',
        'address',
        'logo',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo ? asset('storage/'.$this->logo) : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedSettings(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
