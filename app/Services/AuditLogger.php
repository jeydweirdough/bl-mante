<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes the catch-all trail for staff and admin actions.
 *
 * Reservation and room *status* changes do not come through here; they have
 * their own typed tables because they are read on the operational hot path.
 * Everything else -- price edits, policy publication, room reassignment,
 * account changes -- lands here.
 */
class AuditLogger
{
    public function record(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $this->ipAddress(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Console runs (the scheduler) have no request behind them, and asking
     * for an IP there would blow up rather than record nothing.
     */
    private function ipAddress(): ?string
    {
        return app()->runningInConsole() ? null : Request::ip();
    }
}
