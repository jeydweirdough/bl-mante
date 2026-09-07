<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Held as data rather than a hard-coded enum so an admin can add a
        // package later without a migration. `hours` is what drives
        // ends_at = starts_at + hours.
        Schema::create('duration_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('hours')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('duration_package_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('price_cents');
            $table->char('currency', 3)->default('PHP');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The current price grid only. Historical prices live on
            // reservations as snapshots, so no effective-dating is needed.
            $table->unique(['room_type_id', 'duration_package_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('duration_packages');
    }
};
