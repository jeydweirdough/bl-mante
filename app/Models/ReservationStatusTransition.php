<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Every reservation state change writes one of these with the
 * actor and the time, which is the requirement.
 *
 * A null actor means the scheduler did it: hold expiry and the no-show sweep
 * are system-initiated and have no human behind them.
 */
class ReservationStatusTransition extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'from_status' => ReservationStatus::class,
            'to_status' => ReservationStatus::class,
            'actor_role' => UserRole::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorLabel(): string
    {
        return $this->actor?->name ?? 'System';
    }

    public function describe(): string
    {
        return $this->from_status === null
            ? 'Created as '.$this->to_status->label()
            : $this->from_status->label().' to '.$this->to_status->label();
    }
}
