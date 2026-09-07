<?php

namespace App\Http\Controllers;

use App\Payments\Gateways\MockPaymentGateway;
use App\Payments\PaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Stands in for the payment provider's own hosted page.
 *
 * This is part of the mock implementation, not part of the hotel system.
 * With a real provider configured, these routes are not registered and this
 * controller is never reached -- the guest would be on the provider's domain.
 *
 * There are no card fields here, deliberately: in a redirect-style checkout
 * they would be on the provider's page, and this system is built so card data
 * never reaches it.
 */
class MockGatewayController extends Controller
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
        abort_unless($gateway instanceof MockPaymentGateway, 404);
    }

    public function show(string $reference): View
    {
        $session = $this->mock()->session($reference);

        abort_if($session === null, 404, 'That checkout session is not known.');

        return view('payments.mock', [
            'session' => $session,
            'reference' => $reference,
        ]);
    }

    public function approve(string $reference): RedirectResponse
    {
        $session = $this->mock()->session($reference);

        abort_if($session === null, 404);

        $this->mock()->simulateApproval($reference);

        return redirect()->away($session['return_url']);
    }

    public function cancel(string $reference): RedirectResponse
    {
        $session = $this->mock()->session($reference);

        abort_if($session === null, 404);

        $this->mock()->simulateCancellation($reference);

        return redirect()->away($session['cancel_url']);
    }

    private function mock(): MockPaymentGateway
    {
        return $this->gateway;
    }
}
