<?php

namespace App\Support;

use App\Models\Amenity;
use App\Models\Room;
use App\Models\RoomType;

/**
 * schema.org JSON-LD.
 *
 * Google reads this to decide whether the site describes a real, locatable
 * business, and AI summaries lift answers straight out of it. It is emitted in
 * the document head as `application/ld+json`.
 *
 * ---------------------------------------------------------------------------
 * What is deliberately not here
 * ---------------------------------------------------------------------------
 *
 * `aggregateRating` and `review`. Publishing a rating for reviews the site
 * does not collect and display is a straightforward policy violation that
 * risks a manual penalty, and it would be a fabricated claim about real
 * guests. Add these only once genuine reviews are stored and visible on the
 * page, and populate them from those rows.
 *
 * `starRating` is emitted only when one is configured, for the same reason: an
 * unclassified property inventing four stars is a claim, not markup.
 */
class StructuredData
{
    /**
     * The property itself. This is the anchor entity for the whole site.
     */
    public static function hotel(): array
    {
        $graph = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Hotel',
            '@id' => url('/#hotel'),
            'name' => config('hotel.name'),
            'description' => config('hotel.seo.default_description'),
            'url' => url('/'),
            'telephone' => config('hotel.contact_phone'),
            'email' => config('hotel.contact_email'),
            'priceRange' => config('hotel.seo.price_range'),
            'currenciesAccepted' => config('hotel.currency'),
            'paymentAccepted' => 'Cash, Credit Card, Bank Transfer',
            'image' => self::images(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => config('hotel.address.street'),
                'addressLocality' => config('hotel.address.district'),
                'addressRegion' => config('hotel.address.region'),
                'postalCode' => config('hotel.address.postal_code'),
                'addressCountry' => config('hotel.address.country'),
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => config('hotel.geo.latitude'),
                'longitude' => config('hotel.geo.longitude'),
            ],
            'checkinTime' => config('hotel.seo.check_in_time'),
            'checkoutTime' => config('hotel.seo.check_out_time'),
            'numberOfRooms' => self::roomCount(),
            'amenityFeature' => self::amenities(),
            'makesOffer' => self::offers(),
            'sameAs' => config('hotel.seo.social_profiles') ?: null,

            // Reception is staffed around the clock, which is the whole point
            // of a property that sells 4am arrivals.
            'openingHoursSpecification' => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opens' => '00:00',
                'closes' => '23:59',
            ],
        ], fn ($value) => $value !== null && $value !== []);

        if ($rating = config('hotel.seo.star_rating')) {
            $graph['starRating'] = ['@type' => 'Rating', 'ratingValue' => $rating];
        }

        return $graph;
    }

    /**
     * The FAQ, from the same array that renders the visible accordion.
     *
     * Google restricted FAQ *rich results* to a narrow set of authoritative
     * sites in 2023, so this is unlikely to produce the dropdown it once did.
     * It stays because the markup is still read by AI summaries and assistants,
     * and because it costs nothing to be machine-readable.
     *
     * @param  array<int, array{question:string, answer:string}>  $faqs
     */
    public static function faqPage(array $faqs): ?array
    {
        if ($faqs === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    /**
     * The contact page, pointing back at the property entity rather than
     * describing a second business with the same address.
     */
    public static function contactPage(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ContactPage',
            'name' => 'Contact '.config('hotel.name'),
            'url' => route('contact'),
            'mainEntity' => [
                '@id' => url('/#hotel'),
                '@type' => 'Hotel',
                'name' => config('hotel.name'),
                'telephone' => config('hotel.contact_phone'),
                'email' => config('hotel.contact_email'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => config('hotel.address.street'),
                    'addressLocality' => config('hotel.address.district'),
                    'addressRegion' => config('hotel.address.region'),
                    'postalCode' => config('hotel.address.postal_code'),
                    'addressCountry' => config('hotel.address.country'),
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'contactType' => 'reservations',
                    'telephone' => config('hotel.contact_phone'),
                    'email' => config('hotel.contact_email'),
                    'availableLanguage' => ['en'],
                    'hoursAvailable' => [
                        '@type' => 'OpeningHoursSpecification',
                        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                        'opens' => '00:00',
                        'closes' => '23:59',
                    ],
                ],
            ],
        ];
    }

    /** Enables the sitelinks search box, and names the site as an entity. */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/#website'),
            'name' => config('hotel.name'),
            'url' => url('/'),
            'publisher' => ['@id' => url('/#hotel')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('availability').'?date={date}',
                ],
                'query-input' => 'required name=date',
            ],
        ];
    }

    /** One room type as a bookable product, for the room detail pages. */
    public static function hotelRoom(RoomType $roomType): array
    {
        $cheapest = $roomType->packagePrices->where('is_active', true)->min('price_cents');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'HotelRoom',
            'name' => $roomType->name,
            'description' => $roomType->short_description ?: $roomType->description,
            'url' => route('rooms.show', $roomType),
            'image' => $roomType->photos->map(fn ($photo) => $photo->url())->values()->all() ?: null,
            'occupancy' => [
                '@type' => 'QuantitativeValue',
                'maxValue' => $roomType->max_occupancy,
                'unitCode' => 'C62',
            ],
            'bed' => $roomType->bed_configuration,
            'floorSize' => $roomType->size_sqm ? [
                '@type' => 'QuantitativeValue',
                'value' => $roomType->size_sqm,
                'unitCode' => 'MTK',
            ] : null,
            'amenityFeature' => $roomType->amenities
                ->map(fn ($amenity) => [
                    '@type' => 'LocationFeatureSpecification',
                    'name' => $amenity->name,
                    'value' => true,
                ])->values()->all() ?: null,
            'containedInPlace' => ['@id' => url('/#hotel')],
            'offers' => $cheapest ? [
                '@type' => 'Offer',
                'price' => number_format($cheapest / 100, 2, '.', ''),
                'priceCurrency' => config('hotel.currency'),
                'availability' => 'https://schema.org/InStock',
                'url' => route('availability', ['room_type_id' => $roomType->id]),
            ] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Breadcrumbs. Search engines render these in place of a raw URL, which is
     * worth more than it sounds on a deep page.
     *
     * @param  array<int, array{name:string, url:string}>  $crumbs
     */
    public static function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (int $index, array $crumb) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], array_keys($crumbs), $crumbs),
        ];
    }

    // -----------------------------------------------------------------------

    private static function roomCount(): ?int
    {
        $count = Room::query()->where('is_bookable', true)->count();

        return $count > 0 ? $count : null;
    }

    /** @return array<int, array<string, mixed>> */
    private static function amenities(): array
    {
        return Amenity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name) => [
                '@type' => 'LocationFeatureSpecification',
                'name' => $name,
                'value' => true,
            ])
            ->all();
    }

    /**
     * The duration packages, as offers. This is the part that actually
     * describes what is unusual about the property to a machine.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function offers(): array
    {
        return RoomType::query()
            ->where('is_active', true)
            ->with(['packagePrices' => fn ($q) => $q->where('is_active', true), 'packagePrices.durationPackage'])
            ->get()
            ->flatMap(fn (RoomType $type) => $type->packagePrices->map(fn ($price) => [
                '@type' => 'Offer',
                'name' => sprintf('%s, %d-hour stay', $type->name, $price->durationPackage->hours),
                'price' => number_format($price->price_cents / 100, 2, '.', ''),
                'priceCurrency' => $price->currency,
                'availability' => 'https://schema.org/InStock',
                'url' => route('rooms.show', $type),
            ]))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private static function images(): array
    {
        return RoomType::query()
            ->where('is_active', true)
            ->with('photos')
            ->get()
            ->flatMap(fn (RoomType $type) => $type->photos->map(fn ($photo) => $photo->url()))
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }
}
