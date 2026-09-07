<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages sent through the contact form.
     *
     * Stored rather than emailed. A contact form that hands its only copy to
     * an SMTP server is one bounce away from losing a guest's message, and
     * this system has no mail transport configured. The front desk reads them
     * from an inbox screen; wiring a notification on top later changes nothing
     * about the record.
     */
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');

            // Set when the sender was signed in, so the desk can see the
            // account behind a message without matching on email.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Quoted by the guest, not resolved to a foreign key: a mistyped
            // reference is still useful context, and a real one may belong to
            // a booking that has since been purged.
            $table->string('reservation_reference')->nullable();

            $table->timestamp('read_at')->nullable();
            $table->foreignId('read_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('replied_at')->nullable();
            $table->text('internal_notes')->nullable();

            // Kept for abuse handling. Not shown to anyone but staff.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // The inbox: unread first, newest first.
            $table->index(['read_at', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
