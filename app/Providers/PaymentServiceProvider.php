<?php

namespace App\Providers;

use App\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Resolves the configured online payment gateway.
 *
 * This is the only place in the application that knows which provider is in
 * use. Everything else type-hints PaymentGateway and is handed whatever
 * HOTEL_PAYMENT_DRIVER points at.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function () {
            $driver = config('hotel.payments.driver');
            $config = config("hotel.payments.gateways.$driver");

            if (! is_array($config) || ! isset($config['class'])) {
                throw new InvalidArgumentException(
                    "No payment gateway is configured under [hotel.payments.gateways.$driver]."
                );
            }

            $class = $config['class'];

            if (! is_subclass_of($class, PaymentGateway::class)) {
                throw new InvalidArgumentException(
                    "[$class] must implement ".PaymentGateway::class.'.'
                );
            }

            return new $class($config);
        });
    }
}
