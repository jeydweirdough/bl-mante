<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Face-to-face payments.
 *
 * These never touch the gateway. They are recorded directly and attributed to
 * the staff member who took them, which is the requirement -- the attribution
 * is not optional and is enforced by PaymentService, not just by this form.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('recordPayment', $reservation);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(array_map(fn ($m) => $m->value, PaymentMethod::faceToFace()))],
            'kind' => ['required', Rule::enum(PaymentKind::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = Money::parse((string) $validated['amount']);

        $payment = $this->payments->recordFaceToFace(
            reservation: $reservation,
            amountCents: $amount,
            method: PaymentMethod::from($validated['method']),
            staff: $request->user(),
            kind: PaymentKind::from($validated['kind']),
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', sprintf(
            '%s recorded by %s. Balance is now %s.',
            Money::format($payment->amount_cents),
            $request->user()->name,
            Money::format($reservation->fresh()->balance_due_cents),
        ));
    }
}
