<?php

namespace Tests\Support;

use App\Models\DurationPackage;
use App\Models\PackagePrice;
use App\Models\PolicyVersion;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * A minimal but complete property: one policy, the four packages, one room
 * type with prices, and however many rooms a test needs.
 *
 * Kept deliberately small so each test's own arrangement is what stands out.
 */
trait BuildsProperty
{
    protected PolicyVersion $policy;

    protected RoomType $roomType;

    /** @var Collection<int, Room> */
    protected $rooms;

    protected DurationPackage $sixHours;

    protected function buildProperty(int $roomCount = 1, int $bufferMinutes = 60): void
    {
        $this->policy = PolicyVersion::factory()->current()->withBuffer($bufferMinutes)->create();

        $this->roomType = RoomType::factory()->sleeping(4)->create([
            'extension_hourly_rate_cents' => 30000,
        ]);

        $this->sixHours = DurationPackage::factory()->ofHours(6)->create();

        PackagePrice::factory()->costing(200000)->create([
            'room_type_id' => $this->roomType->id,
            'duration_package_id' => $this->sixHours->id,
        ]);

        $this->rooms = Room::factory()
            ->count($roomCount)
            ->create(['room_type_id' => $this->roomType->id]);

        // Relationships are read straight after creation all over the suite.
        $this->roomType->load('packagePrices');
    }

    protected function room(int $index = 0): Room
    {
        return $this->rooms[$index];
    }

    /** A start time that is always in the future and always on the hour. */
    protected function tomorrowAt(int $hour): CarbonImmutable
    {
        return CarbonImmutable::tomorrow()->setHour($hour)->startOfHour();
    }

    protected function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function staffMember(): User
    {
        return User::factory()->staff()->create();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
