<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToStore;

    // `stock` dan `store_id` sengaja tidak di sini:
    // stok hanya berubah lewat service transaksi, toko diisi otomatis.
    protected $fillable = [
        'category_id', 'supplier_id', 'code', 'name',
        'purchase_price', 'selling_price',
        'unit', 'pack_unit', 'units_per_pack', 'min_stock', 'photo',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'units_per_pack' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockInDetails(): HasMany
    {
        return $this->hasMany(StockInDetail::class);
    }

    public function stockOutDetails(): HasMany
    {
        return $this->hasMany(StockOutDetail::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    protected function stockStatus(): Attribute
    {
        return Attribute::get(fn () => match (true) {
            $this->stock <= 0 => StockStatus::Habis,
            $this->stock <= $this->min_stock => StockStatus::Menipis,
            default => StockStatus::Aman,
        })->withoutObjectCaching();
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo ? '/storage/'.$this->photo : null);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(
            fn (Builder $q) => $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
        ));
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', 0);
    }
}
