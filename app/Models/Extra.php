<?php

namespace App\Models;

use App\Enums\PricingBasis;
use Database\Factories\ExtraFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Extra extends Model
{
    /** @use HasFactory<ExtraFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_cents',
        'pricing_basis',
        'is_active',
        'available_during_stay',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pricing_basis' => PricingBasis::class,
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'available_during_stay' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function reservationExtras(): HasMany
    {
        return $this->hasMany(ReservationExtra::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Line total for a given quantity over a given stay shape.
     *
     * Hours and persons are arguments rather than reservation lookups because
     * an extra added mid-stay covers only the remaining hours.
     */
    public function lineTotalCents(int $quantity, int $hours, int $persons): int
    {
        return $this->price_cents
            * max(1, $quantity)
            * $this->pricing_basis->multiplier($hours, $persons);
    }
}
