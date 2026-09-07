<?php

use App\Enums\RoomStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->unsignedSmallInteger('floor')->nullable();

            // Present-tense housekeeping state of the physical room. This is
            // NOT the source of truth for availability over time -- that is
            // derived entirely from the reservations table. A room that is
            // occupied right now is still bookable for next Tuesday.
            $table->string('status')->default(RoomStatus::Available->value)->index();

            // Admin kill-switch, independent of the housekeeping status.
            $table->boolean('is_bookable')->default(true)->index();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['room_type_id', 'is_bookable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
