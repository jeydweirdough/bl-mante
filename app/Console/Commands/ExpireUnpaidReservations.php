<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Releases pending reservations whose unpaid hold window has elapsed.
 *
 * Runs every minute. The hold length comes from each reservation's own policy
 * version, which was already resolved into hold_expires_at when the booking
 * was made -- so changing the setting today does not move the deadline for a
 * booking already sitting in the queue.
 */
class ExpireUnpaidReservations extends Command
{
    protected $signature = 'reservations:expire-holds
                            {--dry-run : List what would be released without touching anything}';

    protected $description = 'Release pending reservations whose unpaid hold window has expired';

    public function handle(ReservationService $reservations): int
    {
        $query = $reservations->expiredHoldsQuery();
        $dryRun = (bool) $this->option('dry-run');

        $released = 0;
        $failed = 0;

        $query->with('room')->chunkById(100, function ($batch) use ($reservations, $dryRun, &$released, &$failed) {
            foreach ($batch as $reservation) {
                /** @var Reservation $reservation */
                if ($dryRun) {
                    $this->line(sprintf(
                        '  would release %s (room %s, held until %s)',
                        $reservation->reference,
                        $reservation->room->number,
                        $reservation->hold_expires_at->format('H:i'),
                    ));
                    $released++;

                    continue;
                }

                try {
                    $reservations->expireHold($reservation);
                    $released++;
                } catch (Throwable $e) {
                    // One bad row must not stop the sweep; the rest of the
                    // queue still needs releasing.
                    $failed++;
                    report($e);
                    $this->error(sprintf('  %s could not be released: %s', $reservation->reference, $e->getMessage()));
                }
            }
        });

        $this->info(sprintf(
            '%s %d expired hold%s.',
            $dryRun ? 'Would release' : 'Released',
            $released,
            $released === 1 ? '' : 's',
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
