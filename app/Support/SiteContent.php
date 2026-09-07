<?php

namespace App\Support;

use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Services\PolicyService;

/**
 * Reads the public marketing copy and fills in the live figures.
 *
 * The point of the indirection: the cancellation terms quoted on the homepage
 * come from the same policy row the booking engine enforces. An admin who
 * changes the refund window changes the sales copy with it, so the site cannot
 * promise something the system will refuse to do.
 *
 * Resolved once per request as a singleton, because the homepage reads a dozen
 * strings and each one would otherwise re-query the policy.
 */
class SiteContent
{
    private ?array $tokens = null;

    /**
     * The policy version the cached tokens were built from.
     *
     * Caching them outright would be wrong: an admin publishing a new policy
     * must change the terms quoted on the homepage immediately, and under a
     * long-lived worker this object outlives the request that built it. Keying
     * the cache on the version means a publish invalidates it by definition.
     */
    private ?int $tokensForPolicyVersion = null;

    public function __construct(private readonly PolicyService $policies) {}

    /**
     * A content value by dot path, with tokens replaced.
     *
     * Strings are interpolated; arrays are walked so a whole section can be
     * fetched in one call and still come back filled in.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->interpolate(config("content.$key", $default));
    }

    private function interpolate(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->interpolate($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return strtr($value, $this->tokens());
    }

    /**
     * @return array<string, string>
     */
    private function tokens(): array
    {
        $policy = $this->policies->current();

        if ($this->tokens !== null && $this->tokensForPolicyVersion === $policy->id) {
            return $this->tokens;
        }

        $this->tokensForPolicyVersion = $policy->id;

        return $this->tokens = [
            ':hotel' => config('hotel.name'),
            ':street' => config('hotel.address.street'),
            ':district' => config('hotel.address.district'),
            ':city' => config('hotel.address.city'),
            ':phone' => config('hotel.contact_phone'),
            ':email' => config('hotel.contact_email'),

            ':packages' => $this->packageList(),
            ':cheapest_price' => $this->cheapestPrice(),

            ':buffer_minutes' => (string) $policy->turnover_buffer_minutes,
            ':hold_minutes' => (string) $policy->unpaid_hold_minutes,
            ':downpayment_percent' => (string) $policy->downpayment_percent,
            ':full_refund_hours' => (string) $policy->full_refund_hours_before,
            ':partial_refund_hours' => (string) $policy->partial_refund_hours_before,
            ':grace_minutes' => (string) $policy->no_show_grace_minutes,
            ':reschedule_hours' => (string) $policy->free_reschedule_hours_before,
        ];
    }

    /** e.g. "3, 6, 12 and 22 hours" */
    private function packageList(): string
    {
        $hours = DurationPackage::query()
            ->where('is_active', true)
            ->orderBy('hours')
            ->pluck('hours');

        if ($hours->isEmpty()) {
            return 'a range of lengths';
        }

        return $hours->join(', ', ' and ').' hours';
    }

    /**
     * The lowest package price on offer, for the "from" figure.
     *
     * Falls back to a neutral phrase rather than a zero if nothing is priced
     * yet -- a homepage advertising rooms "from ₱0.00" is worse than one that
     * does not quote a price at all.
     */
    private function cheapestPrice(): string
    {
        // Both tables have an is_active column, so every predicate here is
        // table-qualified -- an unqualified one is ambiguous once joined.
        $cents = RoomType::query()
            ->join('package_prices', 'package_prices.room_type_id', '=', 'room_types.id')
            ->where('room_types.is_active', true)
            ->where('package_prices.is_active', true)
            ->min('package_prices.price_cents');

        return $cents ? Money::format((int) $cents) : 'a fair rate';
    }
}
