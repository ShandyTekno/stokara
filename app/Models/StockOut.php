<?php

namespace App\Models;

use App\Enums\StockOutReason;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOut extends Model
{
    use HasFactory, BelongsToStore;

    protected $fillable = [
        'user_id', 'transaction_number', 'transaction_date', 'reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'reason' => StockOutReason::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(StockOutDetail::class);
    }
}
