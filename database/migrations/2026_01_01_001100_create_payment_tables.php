<?php

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();

            $table->string('kind');
            $table->string('channel');
            $table->string('method');

            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('PHP');

            // Pending with a provider_reference is exactly the redirect
            // checkout in-flight state: the guest has left for the gateway and
            // has not come back yet.
            $table->string('status')->default(PaymentStatus::Pending->value);

            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->json('provider_payload')->nullable();

            // Guards double submits and duplicate gateway callbacks.
            $table->string('idempotency_key')->unique();

            // Required for face-to-face payments -- the requirement is that
            // they are attributable to the staff member who took them.
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'status']);
            $table->index(['status', 'paid_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->restrictOnDelete();

            // Null for a manual cash refund at the desk, which may not map to
            // any single recorded payment.
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('PHP');

            $table->string('reason');
            $table->string('tier_applied')->nullable();
            $table->string('status')->default(RefundStatus::Pending->value);
            $table->string('method');

            $table->string('provider_reference')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
    }
};
