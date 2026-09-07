<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingBasis;
use App\Http\Controllers\Controller;
use App\Models\Extra;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExtraController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('manage-catalogue');

        return view('admin.extras.index', [
            'extras' => Extra::ordered()->withCount('reservationExtras')->get(),
            'bases' => PricingBasis::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-catalogue');

        $data = $this->validated($request);

        $extra = Extra::create($data);

        $this->audit->record('extra.created', $extra, $request->user(), after: $data);

        return back()->with('status', "{$extra->name} added.");
    }

    public function update(Request $request, Extra $extra): RedirectResponse
    {
        $this->authorize('manage-catalogue');

        $data = $this->validated($request, $extra);
        $before = $extra->only(array_keys($data));

        $extra->update($data);

        $this->audit->record('extra.updated', $extra, $request->user(), $before, $data);

        return back()->with('status', "{$extra->name} updated. Bookings that already include it are unchanged.");
    }

    /**
     * Disabling, not deleting.
     *
     * The extra keeps existing so historical reservation lines referencing it
     * keep resolving; those lines carry their own name and price snapshot, so
     * nothing about them changes either way.
     */
    public function toggle(Request $request, Extra $extra): RedirectResponse
    {
        $this->authorize('manage-catalogue');

        $extra->update(['is_active' => ! $extra->is_active]);

        $this->audit->record(
            $extra->is_active ? 'extra.enabled' : 'extra.disabled',
            $extra,
            $request->user(),
        );

        return back()->with('status', $extra->is_active
            ? "{$extra->name} is offered again."
            : "{$extra->name} is no longer offered. Existing bookings keep it.");
    }

    private function validated(Request $request, ?Extra $extra = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'pricing_basis' => ['required', Rule::enum(PricingBasis::class)],
            'available_during_stay' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]);

        return [
            'name' => $validated['name'],
            'slug' => $extra?->slug ?? Str::slug($validated['name']).'-'.Str::lower(Str::random(4)),
            'description' => $validated['description'] ?? null,
            'price_cents' => Money::parse((string) $validated['price']),
            'pricing_basis' => $validated['pricing_basis'],
            'available_during_stay' => $request->boolean('available_during_stay'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }
}
