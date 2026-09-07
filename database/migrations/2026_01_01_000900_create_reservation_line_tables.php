<?php

use App\Enums\ExtensionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_id')->constrained()->restrictOnDelete();

            // Snapshots. An admin may rename, re-price or disable the extra;
            // none of that may change what this guest agreed to.
            $table->string('name_snapshot');
            $table->unsignedBigInteger('unit_price_cents_snapshot');
            $table->string('pricing_basis_snapshot');

            $table->unsignedInteger('quantity')->default(1);

            // Captured explicitly rather than derived from the reservation: a
            // per-hour extra added mid-stay may cover fewer hours than the
            // package, and a per-person extra must not silently re-price if
            // the guest count is edited later.
            $table->unsignedInteger('hours')->nullable();
            $table->unsignedInteger('persons')->nullable();

            $table->unsignedBigInteger('line_total_cents');
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_at');
            $table->timestamps();

            $table->index('reservation_id');
        });

        Schema::create('reservation_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedSmallInteger('additional_hours');
            $table->timestamp('previous_ends_at');
            $table->timestamp('new_ends_at');
            $table->unsignedBigInteger('hourly_rate_cents_snapshot');
            $table->unsignedBigInteger('charge_cents');

            $table->string('status')->default(ExtensionStatus::Requested->value)->index();
            $table->string('refusal_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'status']);
        });

        Schema::create('reservation_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            $table->timestamp('from_starts_at');
            $table->timestamp('from_ends_at');
            $table->foreignId('from_room_id')->constrained('rooms')->restrictOnDelete();

            $table->timestamp('to_starts_at');
            $table->timestamp('to_ends_at');
            $table->foreignId('to_room_id')->constrained('rooms')->restrictOnDelete();

            $table->boolean('was_free')->default(true);
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            // Null on the initial assignment made at booking time.
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->foreignId('to_room_id')->constrained('rooms')->restrictOnDelete();

            $table->string('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
        Schema::dropIfExists('reservation_reschedules');
        Schema::dropIfExists('reservation_extensions');
        Schema::dropIfExists('reservation_extras');
    }
};
