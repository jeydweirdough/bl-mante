<?php

use App\Exceptions\DomainRuleException;
use App\Exceptions\DuplicateReservationException;
use App\Exceptions\RoomNoLongerAvailableException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsPersonnel;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'personnel' => EnsureUserIsPersonnel::class,
            'admin' => EnsureUserIsAdmin::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Release pending reservations whose unpaid hold ran out. Every
        // minute, because a 30-minute hold that lingers for five is a room
        // nobody could book.
        $schedule->command('reservations:expire-holds')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Mark guests who never arrived, once their grace period has passed.
        $schedule->command('reservations:mark-no-shows')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Recompute the housekeeping board, which goes stale purely because
        // time passes.
        $schedule->command('rooms:sync-states')
            ->everyTenMinutes()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain refusals are not faults, so they do not belong in the error
        // log. A busy property refuses bookings all day; letting those fill the
        // log is how a real fault goes unnoticed.
        //
        // The one exception is a refusal that came from the *database*
        // guarantee rather than the application check. That means a race
        // actually reached the table -- rare, and worth knowing about, because
        // it says the transactional check was bypassed somehow.
        $exceptions->dontReportWhen(function (Throwable $e) {
            if ($e instanceof RoomNoLongerAvailableException && $e->causedByDatabaseConstraint) {
                return false;
            }

            return $e instanceof DomainRuleException;
        });

        // Domain refusals are normal outcomes of the rules, not faults: a room
        // going while someone was booking, an extension that will not fit, a
        // free reschedule already used. They are shown as a message on the page
        // the person was already on, never as an error page.
        //
        // Anything that does not extend DomainRuleException keeps Laravel's
        // normal handling and stays in the logs, which is the point of having
        // the distinction at all.

        // The duplicate case is handled first and separately, because it
        // belongs against the field the guest can change rather than as a
        // page-level banner.
        $exceptions->render(function (DuplicateReservationException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 409)
                : back()->withInput()->withErrors(['starts_at' => $e->getMessage()]);
        });

        $exceptions->render(function (DomainRuleException $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 409)
                : back()->withInput()->with('unavailable', $e->getMessage());
        });
    })->create();
