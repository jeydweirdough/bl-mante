<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'channel' => PaymentChannel::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'provider_payload' => 'array',
            'initiated_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** The staff member who took a face-to-face payment. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Succeeded);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending);
    }

    public function isSettled(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }

    /** How much of this payment has already been given back. */
    public function refundedCents(): int
    {
        return (int) $this->refunds()
            ->where('status', RefundStatus::Succeeded)
            ->sum('amount_cents');
    }

    public function attributionLabel(): string
    {
        return $this->recordedBy?->name ?? 'Online';
    }
}
