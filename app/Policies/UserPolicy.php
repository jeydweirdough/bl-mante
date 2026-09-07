<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPersonnel();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isPersonnel() || $user->is($target);
    }

    public function manageStaff(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() || $user->is($target);
    }

    /**
     * An admin cannot strip their own privileges or deactivate themselves --
     * the quickest way to lock a property out of its own admin area.
     */
    public function changeRole(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }
}
