<?php

namespace App\Models;

use App\Services\PolicyService;
use Database\Factories\PolicyVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An immutable snapshot of booking policy.
 *
 * Nothing here is ever updated in place except `is_current`. Publishing a
 * change inserts a new row; every reservation keeps a foreign key to the row
 * that was current when it was made.
 */
class PolicyVersion extends Model
{
    /** @use HasFactory<PolicyVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'version',
        'turnover_buffer_minutes',
        'unpaid_hold_minutes',
        'downpayment_percent',
        'full_refund_hours_before',
        'partial_refund_hours_before',
        'no_show_grace_minutes',
        'free_reschedule_hours_before',
        'tax_percent_bp',
        'service_fee_cents',
        'effective_from',
        'is_current',
        'created_by_user_id',
        'change_note',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'is_current' => 'boolean',
            'turnover_buffer_minutes' => 'integer',
            'unpaid_hold_minutes' => 'integer',
            'downpayment_percent' => 'integer',
            'full_refund_hours_before' => 'integer',
            'partial_refund_hours_before' => 'integer',
            'no_show_grace_minutes' => 'integer',
            'free_reschedule_hours_before' => 'integer',
            'tax_percent_bp' => 'integer',
            'service_fee_cents' => 'integer',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The version new bookings are made under.
     *
     * Resolved through the container by PolicyService so it is fetched once
     * per request rather than on every price calculation.
     */
    public static function current(): self
    {
        return app(PolicyService::class)->current();
    }

    public function taxPercent(): float
    {
        return $this->tax_percent_bp / 100;
    }

    /** The downpayment owed on a given total, rounded up to the minor unit. */
    public function downpaymentFor(int $totalCents): int
    {
        return (int) ceil($totalCents * $this->downpayment_percent / 100);
    }
}
