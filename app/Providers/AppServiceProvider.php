<?php

namespace App\Providers;

use App\Models\Enquiry;
use App\Services\PolicyService;
use App\Support\SiteContent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One policy lookup per request rather than one per price calculation.
        $this->app->singleton(PolicyService::class);

        // The homepage reads a dozen content strings and each one interpolates
        // the live policy figures; resolving this once keeps that to a single
        // policy lookup.
        $this->app->singleton(SiteContent::class);
    }

    public function boot(): void
    {
        // Immutable dates by default. Booking windows are passed around and
        // derived from repeatedly; a mutating addHours() on a shared Carbon is
        // exactly the kind of bug that produces a reservation ending before it
        // starts.
        Date::use(CarbonImmutable::class);

        // Refuse to silently drop attributes that match no column, so a typo in
        // a fill() surfaces instead of quietly not saving.
        //
        // Lazy loading is deliberately left enabled: the staff board and the
        // reservation timeline walk relationships from Blade, and eager loading
        // is done explicitly in the controllers that need it.
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // The unread-enquiry badge in the staff navigation.
        //
        // A composer rather than a query in the Blade template: the count is
        // needed by one partial on every staff page, and putting the lookup in
        // the view would hide a database call inside markup. Guarded so it
        // never runs for a guest or a customer, who cannot see the badge.
        View::composer('layouts.navigation', function ($view) {
            $user = auth()->user();

            $view->with(
                'unreadEnquiries',
                $user?->isPersonnel() ? Enquiry::unread()->count() : 0,
            );
        });
    }
}
