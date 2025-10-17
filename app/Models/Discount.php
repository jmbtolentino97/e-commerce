<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'stackable' => 'boolean',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(DiscountApplication::class);
    }

    public function scopeActive($query)
    {
        $now = now();
        return $query
            ->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeInDateRange($query, $value)
    {
        // value: "YYYY-MM-DD,YYYY-MM-DD"
        [$start, $end] = array_pad(explode(',', (string) $value, 2), 2, null);
        if ($start) {
            $query->where(function ($q) use ($start) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $start . ' 23:59:59');
            });
        }
        if ($end) {
            $query->where(function ($q) use ($end) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $end . ' 00:00:00');
            });
        }
    }
}
