<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPersonnel();
    }

    public function view(User $user, Room $room): bool
    {
        return $user->isPersonnel();
    }

    /** Housekeeping is a front-desk job. */
    public function updateHousekeeping(User $user, Room $room): bool
    {
        return $user->isPersonnel();
    }

    /** Taking a room off sale entirely is an admin decision. */
    public function takeOutOfService(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Room $room): bool
    {
        return $user->isAdmin();
    }

    /**
     * Rooms are never deleted -- they carry reservation history that must keep
     * resolving. Deactivation is the supported operation.
     */
    public function delete(User $user, Room $room): bool
    {
        return false;
    }
}
