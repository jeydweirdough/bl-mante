<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\RefundTier;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'reason' => RefundReason::class,
            'tier_applied' => RefundTier::class,
            'status' => RefundStatus::class,
            'method' => PaymentMethod::class,
            'amount_cents' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** Null for a manual cash refund that maps to no single recorded payment. */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', RefundStatus::Succeeded);
    }

    public function attributionLabel(): string
    {
        return $this->processedBy?->name ?? 'System';
    }
}
