<?php

namespace App\Providers;

use App\Services\PolicyService;
use App\Support\SiteContent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
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
    }
}
