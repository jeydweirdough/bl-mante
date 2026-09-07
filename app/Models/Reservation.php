<?php

namespace App\Models;

use App\Enums\CancellationInitiator;
use App\Enums\ExtensionStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationChannel;
use App\Enums\ReservationPaymentStatus;
use App\Enums\ReservationStatus;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'blocked_until' => 'datetime',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'no_show_at' => 'datetime',

            'status' => ReservationStatus::class,
            'payment_status' => ReservationPaymentStatus::class,
            'payment_mode' => PaymentMode::class,
            'channel' => ReservationChannel::class,
            'cancellation_initiator' => CancellationInitiator::class,

            'package_hours' => 'integer',
            'buffer_minutes' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'reschedule_count' => 'integer',

            'package_price_cents' => 'integer',
            'extras_total_cents' => 'integer',
            'extensions_total_cents' => 'integer',
            'discount_total_cents' => 'integer',
            'tax_total_cents' => 'integer',
            'fees_total_cents' => 'integer',
            'total_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'amount_refunded_cents' => 'integer',
            'balance_due_cents' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function durationPackage(): BelongsTo
    {
        return $this->belongsTo(DurationPackage::class);
    }

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany(ReservationExtra::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ReservationExtension::class)->latest('requested_at');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(ReservationReschedule::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class)->oldest('created_at');
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(ReservationStatusTransition::class)->oldest('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function settledPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->where('status', PaymentStatus::Succeeded);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    /** Reservations that hold their room against its calendar. */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', ReservationStatus::occupyingValues());
    }

    /**
     * Reservations whose blocked interval overlaps [$start, $blockedUntil).
     *
     * Half-open on both sides. A reservation whose buffer ends exactly at
     * $start does not overlap, and neither does one starting exactly at
     * $blockedUntil. This predicate is duplicated character for character in
     * the SQLite trigger and the PostgreSQL exclusion constraint; changing
     * one means changing all three.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $blockedUntil): Builder
    {
        return $query
            ->where('starts_at', '<', $blockedUntil)
            ->where('blocked_until', '>', $start);
    }

    public function scopeForRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeArrivingOn(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('starts_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
    }

    public function scopeDepartingOn(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('ends_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
    }

    // ---------------------------------------------------------------------
    // Identity
    // ---------------------------------------------------------------------

    /**
     * A short, unambiguous booking code a guest can read over the phone.
     * Excludes vowels and the characters that get misheard or mistyped.
     */
    public static function generateReference(): string
    {
        $alphabet = '23456789ACDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $code = 'BM-'.collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
        } while (static::where('reference', $code)->exists());

        return $code;
    }

    public function guestName(): string
    {
        return $this->customer?->name ?? $this->guest_name ?? 'Guest';
    }

    public function guestEmail(): ?string
    {
        return $this->customer?->email ?? $this->guest_email;
    }

    public function guestPhone(): ?string
    {
        return $this->customer?->phone ?? $this->guest_phone;
    }

    public function partySize(): int
    {
        return $this->adults + $this->children;
    }

    // ---------------------------------------------------------------------
    // Interval questions
    // ---------------------------------------------------------------------

    /** The window this reservation blocks, stay plus trailing buffer. */
    public function blockedMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->blocked_until);
    }

    public function overlaps(CarbonInterface $start, CarbonInterface $blockedUntil): bool
    {
        return $this->starts_at->lt($blockedUntil) && $this->blocked_until->gt($start);
    }

    /** True while the guest's stay is running right now. */
    public function isInHouse(): bool
    {
        return $this->status === ReservationStatus::CheckedIn;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    // ---------------------------------------------------------------------
    // Money
    // ---------------------------------------------------------------------

    public function isPaidInFull(): bool
    {
        return $this->balance_due_cents <= 0;
    }

    public function hasAnyPayment(): bool
    {
        return $this->amount_paid_cents > 0;
    }

    public function downpaymentDueCents(): int
    {
        return $this->policyVersion->downpaymentFor($this->total_cents);
    }

    /**
     * Recompute the denormalised money columns from the payment and refund
     * ledger. Always called inside the transaction that wrote the ledger row,
     * so the roll-ups can never be observed out of step with it.
     */
    public function recalculateFinancials(): void
    {
        $this->amount_paid_cents = (int) $this->payments()
            ->where('status', PaymentStatus::Succeeded)
            ->sum('amount_cents');

        $this->amount_refunded_cents = (int) $this->refunds()
            ->where('status', RefundStatus::Succeeded)
            ->sum('amount_cents');

        $this->extras_total_cents = (int) $this->extras()->sum('line_total_cents');

        $this->extensions_total_cents = (int) $this->extensions()
            ->where('status', ExtensionStatus::Approved)
            ->sum('charge_cents');

        $net = $this->amount_paid_cents - $this->amount_refunded_cents;
        $this->balance_due_cents = $this->total_cents - $net;

        $this->payment_status = match (true) {
            $this->amount_refunded_cents > 0 && $net <= 0 => ReservationPaymentStatus::Refunded,
            $this->amount_refunded_cents > 0 => ReservationPaymentStatus::PartiallyRefunded,
            $net <= 0 => ReservationPaymentStatus::Unpaid,
            $this->balance_due_cents <= 0 => ReservationPaymentStatus::PaidInFull,
            default => ReservationPaymentStatus::PartiallyPaid,
        };
    }

    // ---------------------------------------------------------------------
    // What the guest and the desk are allowed to do
    //
    // These answer "does this action make sense for this reservation", which
    // is a domain question. Whether a given *user* may perform it is a
    // separate question answered by ReservationPolicy.
    // ---------------------------------------------------------------------

    public function isCancellable(): bool
    {
        return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);
    }

    public function isReschedulable(): bool
    {
        return $this->status === ReservationStatus::Confirmed && $this->starts_at->isFuture();
    }

    public function isExtendable(): bool
    {
        return $this->status === ReservationStatus::CheckedIn;
    }

    public function canAcceptExtras(): bool
    {
        return in_array($this->status, [
            ReservationStatus::Pending,
            ReservationStatus::Confirmed,
            ReservationStatus::CheckedIn,
        ], true);
    }

    public function hasFreeRescheduleLeft(): bool
    {
        return $this->reschedule_count === 0;
    }

    public function hasPendingExtension(): bool
    {
        return $this->extensions()->where('status', ExtensionStatus::Requested)->exists();
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }

    /** A one-line description used in the duplicate-booking message. */
    public function shortDescription(): string
    {
        return sprintf(
            '%s (%s, %s to %s)',
            $this->reference,
            $this->roomType?->name ?? 'room',
            $this->starts_at->format('D j M, H:i'),
            $this->ends_at->format('H:i'),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $reservation) {
            $reservation->reference ??= static::generateReference();
            $reservation->currency ??= config('hotel.currency');
        });
    }
}
