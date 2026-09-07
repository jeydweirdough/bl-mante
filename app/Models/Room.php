<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'number',
        'floor',
        'status',
        'is_bookable',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'is_bookable' => 'boolean',
            'floor' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Reservations that currently hold this room against its calendar. */
    public function occupyingReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)
            ->whereIn('status', ReservationStatus::occupyingValues());
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(RoomStatusTransition::class)->latest('created_at');
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_bookable', true);
    }

    public function scopeOfType(Builder $query, int $roomTypeId): Builder
    {
        return $query->where('room_type_id', $roomTypeId);
    }

    /**
     * Whether a guest can be walked into this room right now.
     *
     * Distinct from availability for a future window, which is answered from
     * the reservations table by AvailabilityService. A room that is Occupied
     * at this moment is still perfectly bookable for next Tuesday.
     */
    public function acceptsGuestNow(): bool
    {
        return $this->is_bookable && $this->status->acceptsGuestNow();
    }

    public function label(): string
    {
        return 'Room '.$this->number;
    }
}
