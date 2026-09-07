<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DurationPackage;
use App\Models\PackagePrice;
use App\Models\RoomType;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The package price grid: one price per room type per duration.
 *
 * Editing a price changes only what new bookings cost. Existing reservations
 * carry their own snapshot and are untouched, which is why there is no
 * effective-dating here.
 */
class PricingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('manage-catalogue');

        $roomTypes = RoomType::ordered()->with('packagePrices')->get();
        $packages = DurationPackage::ordered()->get();

        return view('admin.pricing.index', [
            'roomTypes' => $roomTypes,
            'packages' => $packages,
            'grid' => $roomTypes->mapWithKeys(fn (RoomType $type) => [
                $type->id => $packages->mapWithKeys(fn (DurationPackage $p) => [
                    $p->id => $type->packagePrices->firstWhere('duration_package_id', $p->id),
                ]),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-catalogue');

        $validated = $request->validate([
            'prices' => ['required', 'array'],
            'prices.*.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $changes = [];

        DB::transaction(function () use ($validated, &$changes) {
            foreach ($validated['prices'] as $roomTypeId => $byPackage) {
                foreach ($byPackage as $packageId => $amount) {
                    if ($amount === null || $amount === '') {
                        continue;
                    }

                    $cents = Money::parse((string) $amount);

                    $price = PackagePrice::firstOrNew([
                        'room_type_id' => (int) $roomTypeId,
                        'duration_package_id' => (int) $packageId,
                    ]);

                    if ($price->exists && $price->price_cents === $cents) {
                        continue;
                    }

                    $changes[] = [
                        'room_type_id' => (int) $roomTypeId,
                        'duration_package_id' => (int) $packageId,
                        'from' => $price->exists ? Money::format($price->price_cents) : null,
                        'to' => Money::format($cents),
                    ];

                    $price->fill([
                        'price_cents' => $cents,
                        'currency' => config('hotel.currency'),
                        'is_active' => true,
                    ])->save();
                }
            }
        });

        if ($changes !== []) {
            $this->audit->record('pricing.updated', null, $request->user(), after: $changes);
        }

        return back()->with('status', $changes === []
            ? 'No prices changed.'
            : count($changes).' price'.(count($changes) === 1 ? '' : 's').' updated. Existing bookings are unaffected.');
    }
}
