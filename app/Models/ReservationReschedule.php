<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records an in-place move -- the free reschedule path.
 *
 * The cancel-and-rebook path is not recorded here; it is represented by the
 * new reservation's `rescheduled_from_id`.
 */
class ReservationReschedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_starts_at' => 'datetime',
            'from_ends_at' => 'datetime',
            'to_starts_at' => 'datetime',
            'to_ends_at' => 'datetime',
            'was_free' => 'boolean',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
