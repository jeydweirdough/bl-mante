<?php

namespace App\Models;

use App\Enums\PricingBasis;
use Database\Factories\ReservationExtraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra as it was sold, not as it is defined today.
 *
 * Every priced field is a snapshot so an admin can rename, re-price or
 * disable the underlying extra without touching what a guest agreed to.
 */
class ReservationExtra extends Model
{
    /** @use HasFactory<ReservationExtraFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pricing_basis_snapshot' => PricingBasis::class,
            'unit_price_cents_snapshot' => 'integer',
            'line_total_cents' => 'integer',
            'quantity' => 'integer',
            'hours' => 'integer',
            'persons' => 'integer',
            'added_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /** e.g. "2 x 6 hours" -- how the line total was arrived at. */
    public function basisDescription(): string
    {
        return match ($this->pricing_basis_snapshot) {
            PricingBasis::PerBooking => $this->quantity.' x per booking',
            PricingBasis::PerHour => $this->quantity.' x '.$this->hours.' hours',
            PricingBasis::PerPerson => $this->quantity.' x '.$this->persons.' persons',
        };
    }
}
