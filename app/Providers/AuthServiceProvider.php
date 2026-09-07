<?php

namespace App\Providers;

use App\Models\Amenity;
use App\Models\DurationPackage;
use App\Models\Extra;
use App\Models\PackagePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Policies\CataloguePolicy;
use App\Policies\ReservationPolicy;
use App\Policies\RoomPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * All authorisation lives here and in app/Policies.
 *
 * Controllers call authorize() or Gate::allows(); none of them inspect roles
 * directly, which is the whole point -- the rules are in one place and can be
 * read as a set.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // The catalogue models share one rule: admins write, everyone reads.
        foreach ([RoomType::class, Amenity::class, Extra::class, DurationPackage::class, PackagePrice::class] as $model) {
            Gate::policy($model, CataloguePolicy::class);
        }

        // Section-level gates, used by the navigation as well as the routes,
        // so a link is never shown to someone who would be refused.
        Gate::define('access-staff-area', fn (User $user) => $user->isPersonnel());
        Gate::define('access-admin-area', fn (User $user) => $user->isAdmin());
        Gate::define('manage-catalogue', fn (User $user) => $user->isAdmin());
        Gate::define('manage-staff', fn (User $user) => $user->isAdmin());
        Gate::define('configure-policy', fn (User $user) => $user->isAdmin());
        Gate::define('view-reports', fn (User $user) => $user->isAdmin());
    }
}
