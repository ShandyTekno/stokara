<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory, BelongsToStore;

    protected $fillable = [
        'ai_conversation_id', 'role', 'content', 'is_simulation', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_simulation' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
