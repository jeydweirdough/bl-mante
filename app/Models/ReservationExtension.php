<?php

namespace App\Models;

use App\Enums\ExtensionStatus;
use Database\Factories\ReservationExtensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to keep the room for additional hours.
 *
 * Refused requests are kept: the audit requirement covers refusals, and the
 * record is what explains to a guest why they were told no.
 */
class ReservationExtension extends Model
{
    /** @use HasFactory<ReservationExtensionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ExtensionStatus::class,
            'additional_hours' => 'integer',
            'previous_ends_at' => 'datetime',
            'new_ends_at' => 'datetime',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'hourly_rate_cents_snapshot' => 'integer',
            'charge_cents' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === ExtensionStatus::Requested;
    }
}
