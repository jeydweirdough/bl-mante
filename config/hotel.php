<?php

use App\Payments\Gateways\MockPaymentGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Property identity
    |--------------------------------------------------------------------------
    |
    | Non-policy configuration. Anything with financial meaning lives in the
    | policy_versions table instead, because it has to be frozen per booking.
    |
    */

    'name' => env('HOTEL_NAME', 'Bel Mante Hotel'),

    'timezone' => env('HOTEL_TIMEZONE', 'Asia/Manila'),

    'currency' => env('HOTEL_CURRENCY', 'PHP'),

    'currency_symbol' => env('HOTEL_CURRENCY_SYMBOL', '₱'),

    'contact_email' => env('HOTEL_CONTACT_EMAIL', 'frontdesk@belmante.test'),

    'contact_phone' => env('HOTEL_CONTACT_PHONE', '+63 2 8555 0100'),

    /*
    |--------------------------------------------------------------------------
    | Address and location
    |--------------------------------------------------------------------------
    |
    | Held as separate parts, not one string, because search engines want a
    | structured PostalAddress and the coordinates drive the map result. These
    | must match the property's Google Business Profile exactly -- a mismatched
    | name, address or phone across the two is the most common reason a hotel
    | fails to rank locally.
    |
    | REPLACE THESE WITH THE REAL PROPERTY DETAILS BEFORE LAUNCH.
    |
    */

    'address' => [
        'street' => env('HOTEL_STREET', '118 Mabini Street'),
        'district' => env('HOTEL_DISTRICT', 'Malate'),
        'city' => env('HOTEL_CITY', 'Manila'),
        'region' => env('HOTEL_REGION', 'Metro Manila'),
        'postal_code' => env('HOTEL_POSTAL_CODE', '1004'),
        'country' => env('HOTEL_COUNTRY', 'PH'),
        'country_name' => env('HOTEL_COUNTRY_NAME', 'Philippines'),
    ],

    'geo' => [
        'latitude' => (float) env('HOTEL_LATITUDE', 14.5726),
        'longitude' => (float) env('HOTEL_LONGITUDE', 120.9878),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search engine metadata
    |--------------------------------------------------------------------------
    |
    | Defaults for pages that do not set their own. Titles are kept under 60
    | characters and descriptions between 150 and 160, which is roughly what
    | Google renders before truncating.
    |
    | Deliberately absent: any review or rating markup. Publishing
    | aggregateRating for reviews this site does not actually collect and
    | display is a policy violation that risks a manual penalty, quite apart
    | from being untrue. Add it only once real guest reviews are stored and
    | shown on the page.
    |
    */

    'seo' => [
        'tagline' => env('HOTEL_TAGLINE', 'Hourly rooms in Malate, Manila'),

        'default_description' => env('HOTEL_META_DESCRIPTION',
            'Book a room by the hour in Malate, Manila. Clean, quiet, soundproofed rooms in 3, 6, 12 and 22-hour packages, with free cancellation and no overnight minimum.'),

        // Used for the star rating in structured data. Leave null if the
        // property is not officially classified -- inventing one is the kind
        // of claim that gets markup discounted entirely.
        'star_rating' => env('HOTEL_STAR_RATING') !== null ? (float) env('HOTEL_STAR_RATING') : null,

        'price_range' => env('HOTEL_PRICE_RANGE', '$$'),

        'check_in_time' => env('HOTEL_CHECKIN_TIME', '00:00'),
        'check_out_time' => env('HOTEL_CHECKOUT_TIME', '23:59'),

        // The share card image. 1200x630 is the size both Facebook and X crop
        // cleanly.
        'social_image' => env('HOTEL_SOCIAL_IMAGE', 'images/social-card.png'),

        // Official profiles, used for schema.org sameAs. An empty entry is
        // dropped rather than published as a broken link.
        'social_profiles' => array_filter([
            env('HOTEL_FACEBOOK_URL'),
            env('HOTEL_INSTAGRAM_URL'),
            env('HOTEL_GOOGLE_PROFILE_URL'),
        ]),
    ],

    /*
    |--------------------------------------------------------------------------
    | Booking window
    |--------------------------------------------------------------------------
    |
    | How far ahead the public availability search will look, and the earliest
    | start hour a customer may pick relative to now.
    |
    */

    'search_horizon_days' => (int) env('HOTEL_SEARCH_HORIZON_DAYS', 90),

    'minimum_lead_minutes' => (int) env('HOTEL_MINIMUM_LEAD_MINUTES', 0),

    /*
     * How far the rooms page looks forward for the next free slot when the
     * requested window is full.
     *
     * Telling a visitor "fully booked" and stopping is a dead end; telling them
     * the next free hour gives them something to click. The scan is done in
     * memory over one query, so the cost of a longer horizon is small -- but a
     * fortnight is already past the point where anyone waits.
     */
    'rooms_lookahead_days' => (int) env('HOTEL_ROOMS_LOOKAHEAD_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | The online payment path resolves through this driver. Adding a real
    | provider means writing one class implementing App\Payments\PaymentGateway
    | and pointing HOTEL_PAYMENT_DRIVER at its key here. Nothing outside this
    | file and that class needs to change.
    |
    */

    'payments' => [

        'driver' => env('HOTEL_PAYMENT_DRIVER', 'mock'),

        'gateways' => [

            'mock' => [
                'class' => MockPaymentGateway::class,

                // How long a mock checkout session stays usable before the
                // gateway reports it expired. Kept below the unpaid hold so
                // the two never race.
                'session_ttl_minutes' => (int) env('HOTEL_MOCK_SESSION_TTL', 20),

                // Set to a float between 0 and 1 to have the mock gateway
                // randomly decline a share of payments, for exercising the
                // failure path locally.
                'failure_rate' => (float) env('HOTEL_MOCK_FAILURE_RATE', 0.0),
            ],

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Initial policy defaults
    |--------------------------------------------------------------------------
    |
    | Used only to create policy version 1 on a fresh install. After that the
    | live values come from the policy_versions table, and each reservation
    | keeps a reference to the version in force when it was made.
    |
    */

    'policy_defaults' => [
        'turnover_buffer_minutes' => 60,
        'unpaid_hold_minutes' => 30,
        'downpayment_percent' => 50,
        'full_refund_hours_before' => 24,
        'partial_refund_hours_before' => 6,
        'no_show_grace_minutes' => 60,
        'free_reschedule_hours_before' => 24,
        'tax_percent_bp' => 1200,
        'service_fee_cents' => 0,
    ],

];
