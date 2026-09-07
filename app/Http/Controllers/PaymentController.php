<?php

namespace App\Http\Controllers;

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The customer-facing online payment flow.
 *
 * The gateway is reached only through PaymentService, which in turn only
 * knows the PaymentGateway interface. Nothing in this controller would change
 * if a real provider replaced the mock.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Choose full payment or the downpayment. */
    public function show(Reservation $reservation): View|RedirectResponse
    {
        $this->authorize('pay', $reservation);

        $reservation->load('policyVersion');

        return view('payments.choose', [
            'reservation' => $reservation,
            'fullAmount' => $reservation->balance_due_cents,
            'downpaymentAmount' => min(
                $reservation->downpaymentDueCents(),
                $reservation->balance_due_cents,
            ),
        ]);
    }

    /**
     * Open a hosted checkout and send the guest to it.
     *
     * The reservation stays pending with its hold running until the provider
     * confirms settlement -- arriving at the gateway is not payment.
     */
    public function checkout(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('pay', $reservation);

        $validated = $request->validate([
            'portion' => ['required', 'in:full,downpayment'],
        ]);

        $isDownpayment = $validated['portion'] === 'downpayment';

        $amount = $isDownpayment
            ? min($reservation->downpaymentDueCents(), $reservation->balance_due_cents)
            : $reservation->balance_due_cents;

        if ($amount <= 0) {
            return redirect()
                ->route('reservations.show', $reservation)
                ->with('status', 'There is nothing left to pay on this booking.');
        }

        // The return URL names the reservation rather than the payment, so it
        // can be built before the payment row exists and stays valid however
        // many checkout attempts a guest makes.
        ['session' => $session] = $this->payments->startOnlineCheckout(
            reservation: $reservation,
            kind: $isDownpayment ? PaymentKind::Downpayment : PaymentKind::Full,
            amountCents: $amount,
            returnUrl: route('payments.return', $reservation),
            cancelUrl: route('payments.return', $reservation),
        );

        return redirect()->away($session->redirectUrl);
    }

    /**
     * Where the guest lands on their way back from the gateway.
     *
     * The URL is treated as a nudge, not as evidence: the provider is asked
     * what actually happened. A guest can reload this, share it, or arrive
     * having paid on another device, so every open payment on the booking is
     * reconciled rather than the one the URL happens to name.
     */
    public function return(Reservation $reservation): RedirectResponse
    {
        $this->authorize('view', $reservation);

        $payment = null;

        foreach ($reservation->payments()->where('status', PaymentStatus::Pending)->get() as $open) {
            $synced = $this->payments->syncFromGateway($open);

            // Report on the one that settled if any did; otherwise the most
            // recent outcome is the useful thing to show.
            if ($payment === null || $synced->isSettled()) {
                $payment = $synced;
            }
        }

        if ($payment === null) {
            return redirect()
                ->route('reservations.show', $reservation)
                ->with('status', 'No payment was in progress for this booking.');
        }

        $message = match ($payment->status) {
            PaymentStatus::Succeeded => sprintf(
                '%s received. Your booking is confirmed.',
                Money::format($payment->amount_cents),
            ),
            PaymentStatus::Failed => 'That payment was declined. '.($payment->failure_reason ?? 'Please try again.'),
            PaymentStatus::Cancelled => 'Payment was cancelled. Your booking is still held until the hold expires.',
            PaymentStatus::Expired => 'The checkout session expired before it was completed.',
            PaymentStatus::Pending => 'We have not had confirmation from the payment provider yet.',
        };

        return redirect()
            ->route('reservations.show', $reservation)
            ->with($payment->isSettled() ? 'status' : 'unavailable', $message);
    }
}
