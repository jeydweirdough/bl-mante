<?php

namespace App\Policies;

use App\Models\User;

/**
 * Room types, amenities, packages, prices and extras.
 *
 * These share one rule -- admins manage them, everyone can read them -- so
 * they share one policy rather than four identical ones. It is registered
 * against each model in AuthServiceProvider.
 */
class CataloguePolicy
{
    public function viewAny(?User $user): bool
    {
        // The public browses room types, amenities and prices without an
        // account, which is a requirement.
        return true;
    }

    public function view(?User $user, $model = null): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, $model = null): bool
    {
        return $user->isAdmin();
    }

    /**
     * Catalogue entries are deactivated, not deleted, so historical
     * reservations that reference them keep resolving.
     */
    public function delete(User $user, $model = null): bool
    {
        return false;
    }
}
