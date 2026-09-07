<?php

namespace App\Services;

use App\Enums\ExtensionStatus;
use App\Models\DurationPackage;
use App\Models\Extra;
use App\Models\PolicyVersion;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Support\BookingWindow;
use App\Support\Quote;
use App\Support\QuoteLine;
use RuntimeException;

/**
 * Turns a room type, a package and a set of extras into money.
 *
 * Prices are read from the live catalogue here; the moment a booking is
 * written, ReservationService copies every figure onto the reservation row.
 * From then on nothing in this class can change what that guest owes.
 */
class PricingService
{
    public function __construct(private readonly PolicyService $policies) {}

    /**
     * @param  array<int, array{quantity:int}>  $extraSelections  keyed by extra id
     */
    public function quote(
        RoomType $roomType,
        DurationPackage $package,
        BookingWindow $window,
        array $extraSelections = [],
        int $partySize = 1,
        int $discountCents = 0,
        ?PolicyVersion $policy = null,
    ): Quote {
        $policy ??= $this->policies->current();

        $packagePrice = $roomType->priceFor($package);

        if ($packagePrice === null) {
            throw new RuntimeException(sprintf(
                'No price is configured for %s at %d hours.',
                $roomType->name,
                $package->hours,
            ));
        }

        $lines = $this->priceExtras($extraSelections, $window->hours, $partySize);
        $extrasTotal = array_sum(array_map(fn (QuoteLine $l) => $l->lineTotalCents, $lines));

        return $this->assemble(
            packagePrice: $packagePrice,
            extrasTotal: $extrasTotal,
            extensionsTotal: 0,
            discountCents: $discountCents,
            lines: $lines,
            policy: $policy,
        );
    }

    /**
     * Re-price an existing reservation in place.
     *
     * Used after an extra is added mid-stay or an extension is approved. The
     * package price and the policy come from the reservation itself, never
     * from the current catalogue -- re-reading either would silently re-price
     * a booking that has already been agreed.
     */
    public function repriceReservation(Reservation $reservation): Quote
    {
        $reservation->loadMissing(['extras', 'extensions', 'policyVersion']);

        $extrasTotal = (int) $reservation->extras->sum('line_total_cents');
        $extensionsTotal = (int) $reservation->extensions
            ->where('status', ExtensionStatus::Approved)
            ->sum('charge_cents');

        return $this->assemble(
            packagePrice: $reservation->package_price_cents,
            extrasTotal: $extrasTotal,
            extensionsTotal: $extensionsTotal,
            discountCents: $reservation->discount_total_cents,
            lines: [],
            policy: $reservation->policyVersion,
        );
    }

    /**
     * Price a set of extras against a stay of a given shape.
     *
     * @param  array<int, array{quantity:int, hours?:int}>  $selections  keyed by extra id
     * @return list<QuoteLine>
     */
    public function priceExtras(array $selections, int $stayHours, int $partySize): array
    {
        $ids = array_keys(array_filter($selections, fn ($s) => ($s['quantity'] ?? 0) > 0));

        if ($ids === []) {
            return [];
        }

        return Extra::query()
            ->whereKey($ids)
            ->where('is_active', true)
            ->get()
            ->map(function (Extra $extra) use ($selections, $stayHours, $partySize) {
                $selection = $selections[$extra->id];
                $quantity = max(1, (int) $selection['quantity']);

                // An extra added part-way through a stay covers only the hours
                // that remain, so the caller may override the span.
                $hours = (int) ($selection['hours'] ?? $stayHours);

                return new QuoteLine(
                    extra: $extra,
                    quantity: $quantity,
                    hours: $hours,
                    persons: $partySize,
                    unitPriceCents: $extra->price_cents,
                    basis: $extra->pricing_basis,
                    lineTotalCents: $extra->lineTotalCents($quantity, $hours, $partySize),
                );
            })
            ->values()
            ->all();
    }

    /** The hourly charge for keeping a room past its end time. */
    public function extensionChargeCents(Reservation $reservation, int $additionalHours): int
    {
        return $reservation->roomType->extension_hourly_rate_cents * max(1, $additionalHours);
    }

    /**
     * The one place the arithmetic lives.
     *
     * Tax is applied after the discount and before the fixed service fee, so a
     * discount reduces the tax with it. Rounding happens once, on the tax line,
     * using integer arithmetic throughout.
     *
     * @param  list<QuoteLine>  $lines
     */
    private function assemble(
        int $packagePrice,
        int $extrasTotal,
        int $extensionsTotal,
        int $discountCents,
        array $lines,
        PolicyVersion $policy,
    ): Quote {
        $subtotal = $packagePrice + $extrasTotal + $extensionsTotal;
        $discount = min($discountCents, $subtotal);
        $taxable = $subtotal - $discount;

        $tax = intdiv($taxable * $policy->tax_percent_bp + 5000, 10000);
        $fees = $policy->service_fee_cents;

        return new Quote(
            packagePriceCents: $packagePrice,
            extrasTotalCents: $extrasTotal,
            extensionsTotalCents: $extensionsTotal,
            discountTotalCents: $discount,
            taxTotalCents: $tax,
            feesTotalCents: $fees,
            totalCents: $taxable + $tax + $fees,
            extraLines: $lines,
            policy: $policy,
        );
    }
}
