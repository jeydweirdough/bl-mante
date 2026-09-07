<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booking policy, versioned.
     *
     * Admin edits never update a row here. Publishing a change inserts a new
     * version and moves the is_current flag. Every reservation stores the id
     * of the version in force when it was made, which is how "the policy
     * applied is the one in force when the booking was made" is enforced
     * structurally rather than by convention.
     *
     * A consequence worth stating: changing the turnover buffer does not
     * retroactively alter existing reservations' blocked_until values.
     */
    public function up(): void
    {
        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();

            $table->unsignedInteger('turnover_buffer_minutes')->default(60);
            $table->unsignedInteger('unpaid_hold_minutes')->default(30);
            $table->unsignedInteger('downpayment_percent')->default(50);
            $table->unsignedInteger('full_refund_hours_before')->default(24);
            $table->unsignedInteger('partial_refund_hours_before')->default(6);
            $table->unsignedInteger('no_show_grace_minutes')->default(60);
            $table->unsignedInteger('free_reschedule_hours_before')->default(24);

            // Basis points, so 1200 = 12%. Integers only; see the money note
            // in every other table.
            $table->unsignedInteger('tax_percent_bp')->default(0);
            $table->unsignedBigInteger('service_fee_cents')->default(0);

            $table->timestamp('effective_from');
            $table->boolean('is_current')->default(false)->index();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_versions');
    }
};
