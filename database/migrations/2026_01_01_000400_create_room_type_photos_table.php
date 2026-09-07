<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // "Exactly one cover per room type" is enforced in RoomType, not
            // by a partial unique index: SQLite and PostgreSQL express those
            // differently and the rule is cheap to hold in one place.
            $table->boolean('is_cover')->default(false);

            $table->timestamps();

            $table->index(['room_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_photos');
    }
};
