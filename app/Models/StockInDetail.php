<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockInDetail extends Model
{
    use HasFactory, BelongsToStore;

    protected $fillable = [
        'stock_in_id', 'product_id', 'input_quantity', 'is_package',
        'units_per_pack', 'quantity', 'unit_cost', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'is_package' => 'boolean',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockIn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
