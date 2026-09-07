<?php

namespace App\Services;

use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reads and publishes booking policy.
 *
 * Registered as a singleton, so the current version is fetched once per
 * request rather than on every price calculation.
 */
class PolicyService
{
    private ?PolicyVersion $current = null;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The version new bookings are made under.
     *
     * Falls back to creating version 1 from config so a fresh install can take
     * a booking before an admin has ever touched the policy screen.
     */
    public function current(): PolicyVersion
    {
        return $this->current ??= PolicyVersion::where('is_current', true)->first()
            ?? $this->createInitialVersion();
    }

    public function forget(): void
    {
        $this->current = null;
    }

    /**
     * Publish a change.
     *
     * Never an update: a new row is inserted and the current flag moved, so
     * every reservation already pointing at the old version keeps the terms it
     * was booked under. That is the whole reason the table exists.
     */
    public function publish(array $attributes, ?User $actor = null, ?string $note = null): PolicyVersion
    {
        return DB::transaction(function () use ($attributes, $actor, $note) {
            $previous = $this->current();

            $next = PolicyVersion::create(array_merge(
                $previous->only([
                    'turnover_buffer_minutes',
                    'unpaid_hold_minutes',
                    'downpayment_percent',
                    'full_refund_hours_before',
                    'partial_refund_hours_before',
                    'no_show_grace_minutes',
                    'free_reschedule_hours_before',
                    'tax_percent_bp',
                    'service_fee_cents',
                ]),
                $attributes,
                [
                    'version' => (int) PolicyVersion::max('version') + 1,
                    'effective_from' => now(),
                    'is_current' => true,
                    'created_by_user_id' => $actor?->id,
                    'change_note' => $note,
                ],
            ));

            PolicyVersion::whereKeyNot($next->id)->update(['is_current' => false]);

            $this->audit->record(
                action: 'policy.published',
                subject: $next,
                actor: $actor,
                before: $previous->only(array_keys($attributes)),
                after: $next->only(array_keys($attributes)),
            );

            $this->current = $next;

            return $next;
        });
    }

    private function createInitialVersion(): PolicyVersion
    {
        return PolicyVersion::create(array_merge(
            config('hotel.policy_defaults'),
            [
                'version' => 1,
                'effective_from' => now(),
                'is_current' => true,
                'change_note' => 'Initial policy, seeded from config/hotel.php.',
            ],
        ));
    }
}
