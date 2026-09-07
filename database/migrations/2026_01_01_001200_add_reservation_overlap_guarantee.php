<?php

use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database-level guarantee that two reservations never overlap on the
     * same physical room.
     *
     * This is a second line of defence, not the primary mechanism. The
     * application already performs the availability check and the insert
     * inside one transaction (see AvailabilityService::reserve). What this
     * migration adds is a guarantee the application cannot violate even if
     * that logic is later changed by someone who has not read it.
     *
     * Both engines get the same rule, expressed the way each supports:
     *
     *   PostgreSQL  an EXCLUDE constraint over (room_id, tsrange) using
     *               btree_gist, which is the real thing.
     *   SQLite      BEFORE INSERT / BEFORE UPDATE triggers that RAISE(ABORT).
     *               Not as strong -- SQLite serialises writers anyway -- but
     *               it means local development and the test suite exercise
     *               the same failure path, with the same friendly message,
     *               that production will produce.
     *
     * Three details have to match the application exactly or the two will
     * disagree about back-to-back bookings:
     *
     *   1. The range is over [starts_at, blocked_until), i.e. the reservation
     *      plus its trailing turnover buffer.
     *   2. The range is half-open. A reservation whose buffer ends at 14:00
     *      does not collide with one starting at 14:00.
     *   3. Only occupying statuses participate. Cancelled, expired, no-show
     *      and checked-out rows keep their room_id and datetimes for history
     *      but must not block the slot.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // ReservationStatus::occupying() is the single definition of "occupies
        // the room". Building the SQL from it means the constraint and the
        // availability query can never drift apart.
        $statuses = ReservationStatus::occupyingValues();
        $statusList = "'".implode("','", $statuses)."'";

        match ($driver) {
            'pgsql' => $this->upPostgres($statusList),
            'sqlite' => $this->upSqlite($statusList),
            default => null,
        };
    }

    private function upPostgres(string $statusList): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<SQL
            ALTER TABLE reservations
                ADD CONSTRAINT reservations_no_overlap
                EXCLUDE USING gist (
                    room_id WITH =,
                    tsrange(starts_at, blocked_until, '[)') WITH &&
                )
                WHERE (status IN ($statusList))
        SQL);

        // Datetimes are stored without a timezone and always hold UTC, so
        // tsrange rather than tstzrange is the correct range type here.

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_interval_forward CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_buffer_after_end CHECK (blocked_until >= ends_at)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_starts_on_the_hour CHECK (EXTRACT(MINUTE FROM starts_at) = 0 AND EXTRACT(SECOND FROM starts_at) = 0)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_has_a_guest CHECK (user_id IS NOT NULL OR guest_name IS NOT NULL)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_adults_positive CHECK (adults >= 1)');

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount_cents > 0)');

        DB::statement('ALTER TABLE policy_versions ADD CONSTRAINT policy_downpayment_range CHECK (downpayment_percent BETWEEN 1 AND 100)');
        DB::statement('ALTER TABLE policy_versions ADD CONSTRAINT policy_tiers_ordered CHECK (full_refund_hours_before > partial_refund_hours_before)');
    }

    private function upSqlite(string $statusList): void
    {
        // SQLite cannot ALTER TABLE ADD CONSTRAINT, so the same rules are
        // expressed as triggers. The overlap predicate below is character for
        // character the same comparison AvailabilityService uses.
        $overlapPredicate = <<<SQL
            EXISTS (
                SELECT 1 FROM reservations AS other
                WHERE other.room_id = NEW.room_id
                  AND other.id <> NEW.id
                  AND other.status IN ($statusList)
                  AND other.starts_at < NEW.blocked_until
                  AND NEW.starts_at < other.blocked_until
            )
        SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER reservations_no_overlap_on_insert
            BEFORE INSERT ON reservations
            FOR EACH ROW WHEN NEW.status IN ($statusList)
            BEGIN
                SELECT RAISE(ABORT, 'reservations_no_overlap')
                WHERE $overlapPredicate;
            END
        SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER reservations_no_overlap_on_update
            BEFORE UPDATE ON reservations
            FOR EACH ROW WHEN NEW.status IN ($statusList)
            BEGIN
                SELECT RAISE(ABORT, 'reservations_no_overlap')
                WHERE $overlapPredicate;
            END
        SQL);

        // The validity rules PostgreSQL gets as CHECK constraints.
        $validity = <<<'SQL'
            SELECT RAISE(ABORT, 'reservations_interval_invalid')
            WHERE NEW.ends_at <= NEW.starts_at
               OR NEW.blocked_until < NEW.ends_at
               OR CAST(strftime('%M', NEW.starts_at) AS INTEGER) <> 0
               OR CAST(strftime('%S', NEW.starts_at) AS INTEGER) <> 0
               OR (NEW.user_id IS NULL AND NEW.guest_name IS NULL)
               OR NEW.adults < 1;
        SQL;

        DB::statement("CREATE TRIGGER reservations_validity_on_insert BEFORE INSERT ON reservations FOR EACH ROW BEGIN $validity END");
        DB::statement("CREATE TRIGGER reservations_validity_on_update BEFORE UPDATE ON reservations FOR EACH ROW BEGIN $validity END");

        DB::statement("CREATE TRIGGER payments_amount_positive BEFORE INSERT ON payments FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'payments_amount_positive') WHERE NEW.amount_cents <= 0; END");
        DB::statement("CREATE TRIGGER refunds_amount_positive BEFORE INSERT ON refunds FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'refunds_amount_positive') WHERE NEW.amount_cents <= 0; END");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            foreach ([
                'reservations' => [
                    'reservations_no_overlap',
                    'reservations_interval_forward',
                    'reservations_buffer_after_end',
                    'reservations_starts_on_the_hour',
                    'reservations_has_a_guest',
                    'reservations_adults_positive',
                ],
                'payments' => ['payments_amount_positive'],
                'refunds' => ['refunds_amount_positive'],
                'policy_versions' => ['policy_downpayment_range', 'policy_tiers_ordered'],
            ] as $table => $constraints) {
                foreach ($constraints as $constraint) {
                    DB::statement("ALTER TABLE $table DROP CONSTRAINT IF EXISTS $constraint");
                }
            }
        }

        if ($driver === 'sqlite') {
            foreach ([
                'reservations_no_overlap_on_insert',
                'reservations_no_overlap_on_update',
                'reservations_validity_on_insert',
                'reservations_validity_on_update',
                'payments_amount_positive',
                'refunds_amount_positive',
            ] as $trigger) {
                DB::statement("DROP TRIGGER IF EXISTS $trigger");
            }
        }
    }
};
