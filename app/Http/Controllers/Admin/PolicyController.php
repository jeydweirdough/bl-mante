<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyVersion;
use App\Services\PolicyService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Booking policy: buffer, hold window, downpayment, cancellation tiers,
 * no-show grace, reschedule window, tax and fees.
 *
 * Saving publishes a new version rather than editing the current one. Every
 * reservation already made keeps pointing at the version it was booked under,
 * so tightening the cancellation terms today cannot change what a guest who
 * booked yesterday is owed.
 */
class PolicyController extends Controller
{
    public function __construct(private readonly PolicyService $policies) {}

    public function edit(): View
    {
        $this->authorize('configure-policy');

        return view('admin.policy.edit', [
            'policy' => $this->policies->current(),
            'history' => PolicyVersion::with('createdBy')
                ->orderByDesc('version')
                ->limit(20)
                ->get(),
            'reservationsUnderCurrent' => $this->policies->current()->reservations()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('configure-policy');

        $validated = $request->validate([
            'turnover_buffer_minutes' => ['required', 'integer', 'between:0,720'],
            'unpaid_hold_minutes' => ['required', 'integer', 'between:5,1440'],
            'downpayment_percent' => ['required', 'integer', 'between:1,100'],
            'full_refund_hours_before' => ['required', 'integer', 'between:1,720'],
            'partial_refund_hours_before' => ['required', 'integer', 'between:0,719', 'lt:full_refund_hours_before'],
            'no_show_grace_minutes' => ['required', 'integer', 'between:0,720'],
            'free_reschedule_hours_before' => ['required', 'integer', 'between:0,720'],
            'tax_percent' => ['required', 'numeric', 'between:0,100'],
            'service_fee' => ['required', 'numeric', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:255'],
        ], [
            'partial_refund_hours_before.lt' => 'The partial-refund window must be shorter than the full-refund window.',
        ]);

        $version = $this->policies->publish(
            attributes: [
                'turnover_buffer_minutes' => $validated['turnover_buffer_minutes'],
                'unpaid_hold_minutes' => $validated['unpaid_hold_minutes'],
                'downpayment_percent' => $validated['downpayment_percent'],
                'full_refund_hours_before' => $validated['full_refund_hours_before'],
                'partial_refund_hours_before' => $validated['partial_refund_hours_before'],
                'no_show_grace_minutes' => $validated['no_show_grace_minutes'],
                'free_reschedule_hours_before' => $validated['free_reschedule_hours_before'],
                'tax_percent_bp' => (int) round(((float) $validated['tax_percent']) * 100),
                'service_fee_cents' => Money::parse((string) $validated['service_fee']),
            ],
            actor: $request->user(),
            note: $validated['change_note'] ?? null,
        );

        return back()->with('status', sprintf(
            'Published as policy version %d. Bookings made before now keep the terms they were made under.',
            $version->version,
        ));
    }
}
