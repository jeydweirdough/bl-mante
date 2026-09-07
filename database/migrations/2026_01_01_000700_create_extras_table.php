<?php

use App\Enums\PricingBasis;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->string('pricing_basis')->default(PricingBasis::PerBooking->value);

            // Disabling sets this false. Historical reservation_extras rows are
            // unaffected because each carries its own name, price and basis
            // snapshot, which is why an extra is never deleted.
            $table->boolean('is_active')->default(true)->index();

            $table->boolean('available_during_stay')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extras');
    }
};
