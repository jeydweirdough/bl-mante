<?php

namespace App\Console\Commands;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\RoomStateService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Keeps the housekeeping board honest.
 *
 * Room status describes the room right now, so it goes stale on its own as
 * time passes -- a room becomes Reserved because an arrival is approaching,
 * and Available again when that reservation is cancelled or released. Rather
 * than expecting every code path to remember to tidy up, the state is
 * recomputed from facts on a schedule.
 *
 * Cleaning and out-of-service are never touched: both are statements a person
 * made about the physical room.
 */
class SyncRoomStates extends Command
{
    protected $signature = 'rooms:sync-states';

    protected $description = 'Recompute each room\'s present status from its live reservations';

    public function handle(RoomStateService $rooms): int
    {
        $changed = 0;

        Room::query()
            ->whereNotIn('status', [RoomStatus::Cleaning->value, RoomStatus::OutOfService->value])
            ->chunkById(200, function ($batch) use ($rooms, &$changed) {
                foreach ($batch as $room) {
                    $before = $room->status;

                    try {
                        $rooms->syncPresentState($room);
                    } catch (Throwable $e) {
                        report($e);

                        continue;
                    }

                    if ($room->status !== $before) {
                        $changed++;
                    }
                }
            });

        $this->info("Updated {$changed} room".($changed === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
