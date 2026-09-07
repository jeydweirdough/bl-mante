<?php

namespace Database\Seeders;

use App\Enums\PricingBasis;
use App\Models\Amenity;
use App\Models\DurationPackage;
use App\Models\Extra;
use App\Models\PackagePrice;
use App\Models\PolicyVersion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypePhoto;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The property itself: policy, room types, physical rooms, packages, prices
 * and extras. Everything a booking needs before anyone can make one.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $this->policy();
        $this->settings();

        $packages = $this->packages();
        $amenities = $this->amenities();
        $roomTypes = $this->roomTypes($amenities, $packages);

        $this->rooms($roomTypes);
        $this->extras();
    }

    private function policy(): void
    {
        PolicyVersion::firstOrCreate(
            ['version' => 1],
            array_merge(config('hotel.policy_defaults'), [
                'effective_from' => now()->subMonths(6),
                'is_current' => true,
                'change_note' => 'Opening policy.',
            ]),
        );
    }

    private function settings(): void
    {
        Setting::instance()->update([
            'address' => '118 Mabini Street, Malate, Manila',
            'checkin_instructions' => 'Present your booking reference and a valid ID at the front desk. '
                .'Rooms are released to the next guest after the turnover break, so please leave by your end time.',
        ]);
    }

    private function packages(): array
    {
        return collect([
            [3, '3-hour stay', 'A short rest between commitments.'],
            [6, '6-hour stay', 'Half a day, enough to sleep properly.'],
            [12, '12-hour stay', 'Overnight, or a full working day.'],
            [22, '22-hour stay', 'Effectively a full day with an early handover.'],
        ])->mapWithKeys(fn (array $row) => [
            $row[0] => DurationPackage::firstOrCreate(
                ['hours' => $row[0]],
                ['name' => $row[1], 'description' => $row[2], 'sort_order' => $row[0], 'is_active' => true],
            ),
        ])->all();
    }

    private function amenities(): array
    {
        return collect([
            ['Air conditioning', 'Individually controlled.'],
            ['Free Wi-Fi', 'Fibre, 200 Mbps.'],
            ['Smart TV', 'With the usual streaming apps.'],
            ['Hot shower', 'Rain head, unlimited hot water.'],
            ['Work desk', 'With a proper chair and power outlets.'],
            ['Mini fridge', 'Stocked on request.'],
            ['Blackout curtains', 'For daytime sleeping.'],
            ['Soundproofing', 'Double-glazed windows.'],
            ['Bathtub', 'Deep soaking tub.'],
            ['City view', 'From the upper floors.'],
        ])->mapWithKeys(fn (array $row) => [
            $row[0] => Amenity::firstOrCreate(
                ['slug' => Str::slug($row[0])],
                ['name' => $row[0], 'description' => $row[1], 'is_active' => true],
            ),
        ])->all();
    }

    private function roomTypes(array $amenities, array $packages): array
    {
        // Prices are per package, in minor units, and rise less than linearly
        // with duration -- which is how a property that sells by the hour
        // actually prices, and makes the longer packages worth testing against.
        $definitions = [
            [
                'name' => 'Standard Queen',
                'short' => 'A quiet room with one queen bed, built for a proper rest.',
                'description' => 'Our smallest room and the one most people book. One queen bed, blackout curtains and '
                    ."serious soundproofing, because half our guests are sleeping in the middle of the afternoon.\n\n"
                    .'The desk is full size rather than a token shelf, so a six-hour package works as a working day.',
                'occupancy' => [2, 2],
                'beds' => 'One queen bed',
                'size' => 22,
                'extension' => 25000,
                'prices' => [3 => 95000, 6 => 145000, 12 => 215000, 22 => 295000],
                'amenities' => ['Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Hot shower', 'Work desk', 'Blackout curtains', 'Soundproofing'],
                'photo' => 'standard-queen',
            ],
            [
                'name' => 'Deluxe Twin',
                'short' => 'Two beds, more floor space, good for colleagues or friends.',
                'description' => 'Two single beds rather than one large one, which sounds minor until you are sharing '
                    ."a room with a colleague between flights.\n\nMore floor space than the Standard, a mini fridge, "
                    .'and enough room to actually open a suitcase.',
                'occupancy' => [2, 3],
                'beds' => 'Two single beds',
                'size' => 30,
                'extension' => 32000,
                'prices' => [3 => 125000, 6 => 185000, 12 => 275000, 22 => 385000],
                'amenities' => ['Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Hot shower', 'Work desk', 'Mini fridge', 'Blackout curtains'],
                'photo' => 'deluxe-twin',
            ],
            [
                'name' => 'Executive King',
                'short' => 'A king bed, a bathtub and a view worth the upper floors.',
                'description' => 'A king bed, a deep bathtub and a corner position on the upper floors. The room people '
                    ."book when the twelve-hour package is standing in for a hotel night.\n\nThe desk faces the window "
                    .'rather than the wall, which is a small thing that matters over a long day.',
                'occupancy' => [2, 3],
                'beds' => 'One king bed',
                'size' => 42,
                'extension' => 45000,
                'prices' => [3 => 175000, 6 => 255000, 12 => 385000, 22 => 545000],
                'amenities' => ['Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Hot shower', 'Work desk', 'Mini fridge', 'Blackout curtains', 'Soundproofing', 'Bathtub', 'City view'],
                'photo' => 'executive-king',
            ],
            [
                'name' => 'Family Suite',
                'short' => 'A separate living area and enough beds for four.',
                'description' => 'A bedroom with a king bed plus a living area with a proper sofa bed, and a door between '
                    ."them. Sleeps four without anybody having to be diplomatic about it.\n\nThe only room type where the "
                    .'22-hour package sells more than the 6.',
                'occupancy' => [3, 5],
                'beds' => 'One king bed and a sofa bed',
                'size' => 58,
                'extension' => 55000,
                'prices' => [3 => 215000, 6 => 315000, 12 => 475000, 22 => 675000],
                'amenities' => ['Air conditioning', 'Free Wi-Fi', 'Smart TV', 'Hot shower', 'Work desk', 'Mini fridge', 'Blackout curtains', 'Bathtub', 'City view'],
                'photo' => 'family-suite',
            ],
        ];

        $created = [];

        foreach ($definitions as $index => $definition) {
            $roomType = RoomType::updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                [
                    'name' => $definition['name'],
                    'short_description' => $definition['short'],
                    'description' => $definition['description'],
                    'base_occupancy' => $definition['occupancy'][0],
                    'max_occupancy' => $definition['occupancy'][1],
                    'bed_configuration' => $definition['beds'],
                    'size_sqm' => $definition['size'],
                    'extension_hourly_rate_cents' => $definition['extension'],
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );

            $roomType->amenities()->sync(
                collect($definition['amenities'])->map(fn (string $name) => $amenities[$name]->id)->all()
            );

            foreach ($definition['prices'] as $hours => $cents) {
                PackagePrice::updateOrCreate(
                    [
                        'room_type_id' => $roomType->id,
                        'duration_package_id' => $packages[$hours]->id,
                    ],
                    [
                        'price_cents' => $cents,
                        'currency' => config('hotel.currency'),
                        'is_active' => true,
                    ],
                );
            }

            foreach ([1, 2] as $n) {
                RoomTypePhoto::updateOrCreate(
                    [
                        'room_type_id' => $roomType->id,
                        'path' => "images/rooms/{$definition['photo']}-{$n}.svg",
                    ],
                    [
                        'alt_text' => $definition['name'],
                        'sort_order' => $n,
                        'is_cover' => $n === 1,
                    ],
                );
            }

            $created[$definition['name']] = $roomType;
        }

        return $created;
    }

    /**
     * Eighteen rooms across four floors.
     *
     * Enough that a search returns several options, few enough that a busy
     * afternoon in the seeded data genuinely exhausts a room type -- which is
     * what makes the "no longer available" path testable by hand.
     */
    private function rooms(array $roomTypes): void
    {
        $layout = [
            'Standard Queen' => ['201', '202', '203', '204', '205', '206'],
            'Deluxe Twin' => ['301', '302', '303', '304', '305'],
            'Executive King' => ['401', '402', '403', '404'],
            'Family Suite' => ['501', '502', '503'],
        ];

        foreach ($layout as $typeName => $numbers) {
            foreach ($numbers as $number) {
                Room::updateOrCreate(
                    ['number' => $number],
                    [
                        'room_type_id' => $roomTypes[$typeName]->id,
                        'floor' => (int) substr($number, 0, 1),
                        'is_bookable' => true,
                    ],
                );
            }
        }

        // One room deliberately out of service, so the availability query is
        // exercised against inventory that exists but is not offered.
        Room::where('number', '206')->update([
            'is_bookable' => false,
            'notes' => 'Air conditioning replacement, back in service next month.',
        ]);
    }

    private function extras(): void
    {
        $definitions = [
            ['Breakfast tray', 'Delivered to the room at a time you choose.', 32000, PricingBasis::PerPerson, true],
            ['Airport transfer', 'One way, up to three passengers.', 145000, PricingBasis::PerBooking, true],
            ['Parking', 'Covered basement parking.', 8000, PricingBasis::PerHour, true],
            ['Extra pillow and blanket set', 'One additional set.', 15000, PricingBasis::PerBooking, true],
            ['Laundry service', 'Same-day, up to five items.', 45000, PricingBasis::PerBooking, true],
            ['In-room massage', 'Sixty minutes with a licensed therapist.', 190000, PricingBasis::PerPerson, true],
            // Kept but no longer offered, to prove that disabling an extra
            // leaves the bookings that already include it untouched.
            ['Welcome fruit basket', 'Discontinued in favour of the breakfast tray.', 55000, PricingBasis::PerBooking, false],
        ];

        foreach ($definitions as $index => [$name, $description, $cents, $basis, $active]) {
            Extra::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'price_cents' => $cents,
                    'pricing_basis' => $basis,
                    'is_active' => $active,
                    'available_during_stay' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
