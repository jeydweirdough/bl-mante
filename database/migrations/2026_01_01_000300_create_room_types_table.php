<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();

            $table->unsignedTinyInteger('base_occupancy')->default(2);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->string('bed_configuration')->nullable();
            $table->unsignedSmallInteger('size_sqm')->nullable();

            // Per-room-type by nature. Snapshotted onto each granted extension
            // so a later rate change cannot alter what a guest was charged.
            $table->unsignedBigInteger('extension_hourly_rate_cents')->default(0);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('amenity_room_type', function (Blueprint $table) {
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['room_type_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_room_type');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('room_types');
    }
};
