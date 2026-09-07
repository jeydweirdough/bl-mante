<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Marks confirmed bookings whose guest never arrived.
 *
 * A guest has a grace period after their start time before this fires. The
 * length is read from each reservation's own policy version inside the query,
 * so a booking made under a longer grace keeps it.
 *
 * A no-show forfeits all payment and releases the room -- the release happens
 * because the status leaves the occupying set, which is what the availability
 * query and the exclusion constraint both key off.
 */
class MarkNoShows extends Command
{
    protected $signature = 'reservations:mark-no-shows
                            {--dry-run : List what would be marked without touching anything}';

    protected $description = 'Mark confirmed bookings as no-shows once the grace period has passed';

    public function handle(ReservationService $reservations): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $marked = 0;
        $failed = 0;

        $reservations->overdueArrivalsQuery()
            ->with(['room', 'policyVersion'])
            ->chunkById(100, function ($batch) use ($reservations, $dryRun, &$marked, &$failed) {
                foreach ($batch as $reservation) {
                    if ($dryRun) {
                        $this->line(sprintf(
                            '  would mark %s (room %s, due %s, grace %d min)',
                            $reservation->reference,
                            $reservation->room->number,
                            $reservation->starts_at->format('D H:i'),
                            $reservation->policyVersion->no_show_grace_minutes,
                        ));
                        $marked++;

                        continue;
                    }

                    try {
                        $reservations->markNoShow($reservation);
                        $marked++;
                    } catch (Throwable $e) {
                        $failed++;
                        report($e);
                        $this->error(sprintf('  %s could not be marked: %s', $reservation->reference, $e->getMessage()));
                    }
                }
            }, 'reservations.id', 'id');

        $this->info(sprintf(
            '%s %d no-show%s.',
            $dryRun ? 'Would mark' : 'Marked',
            $marked,
            $marked === 1 ? '' : 's',
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
