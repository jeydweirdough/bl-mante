<?php

use App\Enums\ReservationChannel;
use App\Enums\ReservationPaymentStatus;
use App\Enums\ReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Null for walk-in and phone guests who have no account. The
            // alternative -- auto-provisioning a shell user for every phone
            // booking -- pollutes the customer list and gives the duplicate
            // booking check an identity that means nothing.
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel')->default(ReservationChannel::Online->value)->index();

            // The type is stored alongside the room because a room can be
            // reclassified later, and the guest paid for the type they booked.
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('duration_package_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('package_hours');

            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');

            // Snapshot of the policy's buffer at booking time.
            $table->unsignedInteger('buffer_minutes');

            // ends_at + buffer_minutes, maintained by the application.
            //
            // A real stored column rather than a computed one, because the
            // PostgreSQL exclusion constraint has to build a tstzrange over
            // actual columns, and a virtual column would not be portable to
            // SQLite. Every overlap test in the system ranges over
            // [starts_at, blocked_until).
            $table->timestamp('blocked_until');

            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);

            $table->string('status')->default(ReservationStatus::Pending->value)->index();
            $table->string('payment_mode');
            $table->string('payment_status')->default(ReservationPaymentStatus::Unpaid->value)->index();

            $table->foreignId('policy_version_id')->constrained()->restrictOnDelete();

            // Money, in minor units. Integers throughout: exact on both SQLite
            // and PostgreSQL, and no rounding drift across downpayment and
            // refund splits.
            $table->char('currency', 3)->default('PHP');
            $table->unsignedBigInteger('package_price_cents')->default(0);
            $table->unsignedBigInteger('extras_total_cents')->default(0);
            $table->unsignedBigInteger('extensions_total_cents')->default(0);
            $table->unsignedBigInteger('discount_total_cents')->default(0);
            $table->unsignedBigInteger('tax_total_cents')->default(0);
            $table->unsignedBigInteger('fees_total_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);

            // Roll-ups, maintained inside the same transaction as the payment,
            // refund, extra or extension rows that move them. The payments and
            // refunds tables remain the ledger of record; these exist so the
            // daily board and reports do not aggregate on every render.
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->unsignedBigInteger('amount_refunded_cents')->default(0);
            $table->bigInteger('balance_due_cents')->default(0);

            // Set only on the online path. An at-property booking has no
            // payment by definition, so holding it to the unpaid window would
            // expire every one of them.
            $table->timestamp('hold_expires_at')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();

            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_initiator')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('reservations')->nullOnDelete();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            // The availability scan.
            $table->index(['room_id', 'starts_at', 'blocked_until'], 'reservations_availability_index');
            // Scheduler: expire unpaid holds.
            $table->index(['status', 'hold_expires_at']);
            // Scheduler: no-show sweep. Also the staff daily board.
            $table->index(['status', 'starts_at']);
            // The customer duplicate-booking check.
            $table->index(['user_id', 'starts_at', 'ends_at']);
            // Search: which rooms of a type are free.
            $table->index(['room_type_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
