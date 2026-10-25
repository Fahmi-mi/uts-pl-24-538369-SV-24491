<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gadget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'brand',
        'category',
        'store',
        'product_url',
        'target_price',
        'current_price',
        'priority',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'integer',
            'current_price' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderByDesc('recorded_at');
    }
}
