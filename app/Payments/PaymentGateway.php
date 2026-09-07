<?php

namespace App\Payments;

/**
 * The boundary between this system and whoever takes the money.
 *
 * Everything the application knows about online payment is expressed in these
 * four methods and the DTOs around them. No controller, model or service
 * mentions a provider by name; they all depend on this interface, resolved
 * from config/hotel.php.
 *
 * Adding a real provider means writing one class that implements this and
 * changing HOTEL_PAYMENT_DRIVER. Nothing else moves.
 *
 * The shape assumes redirect-style checkout: we ask the provider for a hosted
 * page, send the guest there, and find out what happened when they come back
 * or when we ask. Card details never enter this system, which is why there is
 * no method here that accepts them.
 */
interface PaymentGateway
{
    /** The key this gateway is registered under, stored on each payment row. */
    public function name(): string;

    /**
     * Open a checkout the guest can be redirected to.
     *
     * Implementations must treat CheckoutRequest::$idempotencyKey as such:
     * calling this twice with the same key must not create two charges.
     */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /**
     * Ask the provider what actually happened.
     *
     * This is the authority, not the redirect the guest arrives back on. A
     * guest can close the tab, come back on a different device, or have the
     * return URL tampered with; the answer to "was this paid" always comes
     * from here.
     */
    public function retrieve(string $providerReference): GatewayPaymentStatus;

    /** Return money against a settled charge. */
    public function refund(GatewayRefundRequest $request): GatewayRefundResult;
}
