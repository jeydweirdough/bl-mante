<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Catch-all trail for staff and admin actions that are not status changes --
 * price edits, policy publication, room reassignment, account changes.
 *
 * Status changes have their own typed tables because they are read on the
 * operational hot path and need their own indexes.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'actor_role' => UserRole::class,
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actorLabel(): string
    {
        return $this->actor?->name ?? 'System';
    }
}
