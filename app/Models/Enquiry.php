<?php

namespace App\Models;

use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message sent through the contact form.
 *
 * Everything here is written by a member of the public, so nothing in it is
 * trusted: it is escaped on display and never interpolated into a query, a
 * mail header or a redirect.
 */
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function readBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by_user_id');
    }

    /** The booking the guest quoted, if it still exists. */
    public function reservation(): ?Reservation
    {
        return $this->reservation_reference
            ? Reservation::where('reference', $this->reservation_reference)->first()
            : null;
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(?User $staff = null): void
    {
        if ($this->isUnread()) {
            $this->forceFill([
                'read_at' => now(),
                'read_by_user_id' => $staff?->id,
            ])->save();
        }
    }

    public function subjectLine(): string
    {
        return $this->subject ?: 'General enquiry';
    }
}
