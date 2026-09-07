<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
        'address_line',
        'city',
        'postal_code',
        'country',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    /** Reservations this user holds as the guest. */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** Reservations this user created on someone else's behalf (walk-in, phone). */
    public function createdReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'created_by_user_id');
    }

    /** Face-to-face payments attributed to this staff member. */
    public function recordedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'recorded_by_user_id');
    }

    public function processedRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'processed_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function publishedPolicyVersions(): HasMany
    {
        return $this->hasMany(PolicyVersion::class, 'created_by_user_id');
    }

    // ---------------------------------------------------------------------
    // Role helpers
    //
    // These exist so policies read as one expression. Authorisation decisions
    // themselves live in app/Policies, never inline in controllers.
    // ---------------------------------------------------------------------

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Staff or admin. Admins hold every staff capability. */
    public function isPersonnel(): bool
    {
        return $this->role->isPersonnel();
    }

    // ---------------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------------

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('role', UserRole::Customer);
    }

    public function scopePersonnel(Builder $query): Builder
    {
        return $query->whereIn('role', [UserRole::Staff, UserRole::Admin]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
