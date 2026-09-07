<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MockGatewayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Staff;
use App\Payments\Gateways\MockPaymentGateway;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| Browsing room types, amenities, photos, package prices and availability, all
| without an account -- which is a requirement, not an accident.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/rooms', [RoomTypeController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{roomType}', [RoomTypeController::class, 'show'])->name('rooms.show');
Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');

// Rate limited because it is the one unauthenticated write in the application.
// Five an hour is well above what a real guest needs and well below what makes
// the inbox unusable.
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,60')
    ->name('contact.store');

// Generated rather than static files, so a new room type is published to
// search engines without anyone remembering to edit anything.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Customer
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function () {
    // Where signing in lands. Staff and admins have no use for the customer
    // booking list, and customers cannot see the desk, so the one named route
    // the auth scaffolding redirects to forks by role here rather than showing
    // everyone a page half of them cannot use.
    Route::get('/dashboard', function () {
        return auth()->user()->isPersonnel()
            ? redirect()->route('staff.dashboard')
            : redirect()->route('reservations.index');
    })->name('dashboard');

    Route::get('/book', [BookingController::class, 'create'])->name('booking.create');
    Route::post('/book', [BookingController::class, 'store'])->name('booking.store');

    Route::prefix('my')->name('reservations.')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('index');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('show');

        Route::get('/reservations/{reservation}/cancel', [ReservationController::class, 'confirmCancel'])->name('cancel.confirm');
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'cancel'])->name('cancel');

        Route::get('/reservations/{reservation}/reschedule', [ReservationController::class, 'editReschedule'])->name('reschedule.edit');
        Route::put('/reservations/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->name('reschedule');
        Route::get('/reservations/{reservation}/reschedule/check', [ReservationController::class, 'checkReschedule'])->name('reschedule.check');

        Route::post('/reservations/{reservation}/extensions', [ReservationController::class, 'requestExtension'])->name('extensions.store');
        Route::post('/reservations/{reservation}/extras', [ReservationController::class, 'addExtra'])->name('extras.store');

        Route::get('/reservations/{reservation}/pay', [PaymentController::class, 'show'])->name('pay');
        Route::post('/reservations/{reservation}/pay', [PaymentController::class, 'checkout'])->name('pay.checkout');
    });

    Route::get('/payments/{reservation}/return', [PaymentController::class, 'return'])->name('payments.return');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| The mock provider's hosted checkout
|--------------------------------------------------------------------------
|
| Registered only while the mock gateway is the configured driver. With a real
| provider these routes do not exist and the guest is on the provider's own
| domain instead. Nothing else in the application references them.
|
*/

if (config('hotel.payments.gateways.'.config('hotel.payments.driver').'.class') === MockPaymentGateway::class) {
    Route::prefix('checkout-simulator')->name('payments.mock.')->group(function () {
        Route::get('/{reference}', [MockGatewayController::class, 'show'])->name('show');
        Route::post('/{reference}/approve', [MockGatewayController::class, 'approve'])->name('approve');
        Route::post('/{reference}/cancel', [MockGatewayController::class, 'cancel'])->name('cancel');
    });
}

/*
|--------------------------------------------------------------------------
| Front desk
|--------------------------------------------------------------------------
|
| The middleware keeps customers out of the section entirely; per-action
| authorisation still runs through the policies.
|
*/

Route::middleware(['auth', 'active', 'personnel'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [Staff\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/reservations', [Staff\ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/create', [Staff\ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [Staff\ReservationController::class, 'store'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [Staff\ReservationController::class, 'show'])->name('reservations.show');

    Route::post('/reservations/{reservation}/check-in', [Staff\ReservationController::class, 'checkIn'])->name('reservations.check-in');
    Route::post('/reservations/{reservation}/check-out', [Staff\ReservationController::class, 'checkOut'])->name('reservations.check-out');
    Route::post('/reservations/{reservation}/no-show', [Staff\ReservationController::class, 'markNoShow'])->name('reservations.no-show');
    Route::post('/reservations/{reservation}/assign-room', [Staff\ReservationController::class, 'assignRoom'])->name('reservations.assign-room');
    Route::post('/reservations/{reservation}/cancel', [Staff\ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/extras', [Staff\ReservationController::class, 'addExtra'])->name('reservations.extras');

    Route::post('/reservations/{reservation}/payments', [Staff\PaymentController::class, 'store'])->name('payments.store');

    Route::post('/reservations/{reservation}/extensions', [Staff\ExtensionController::class, 'store'])->name('extensions.store');
    Route::post('/extensions/{extension}/approve', [Staff\ExtensionController::class, 'approve'])->name('extensions.approve');
    Route::post('/extensions/{extension}/refuse', [Staff\ExtensionController::class, 'refuse'])->name('extensions.refuse');

    Route::get('/enquiries', [Staff\EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('/enquiries/{enquiry}', [Staff\EnquiryController::class, 'show'])->name('enquiries.show');
    Route::put('/enquiries/{enquiry}', [Staff\EnquiryController::class, 'update'])->name('enquiries.update');

    Route::get('/housekeeping', [Staff\HousekeepingController::class, 'index'])->name('housekeeping');
    Route::post('/rooms/{room}/cleaning-complete', [Staff\HousekeepingController::class, 'markCleaningComplete'])->name('rooms.cleaning-complete');
    Route::post('/rooms/{room}/cleaning', [Staff\HousekeepingController::class, 'markCleaning'])->name('rooms.cleaning');
    Route::post('/rooms/{room}/out-of-service', [Staff\HousekeepingController::class, 'takeOutOfService'])->name('rooms.out-of-service');
    Route::post('/rooms/{room}/return-to-service', [Staff\HousekeepingController::class, 'returnToService'])->name('rooms.return-to-service');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/room-types', [Admin\RoomTypeController::class, 'index'])->name('room-types.index');
    Route::get('/room-types/create', [Admin\RoomTypeController::class, 'create'])->name('room-types.create');
    Route::post('/room-types', [Admin\RoomTypeController::class, 'store'])->name('room-types.store');
    Route::get('/room-types/{roomType}/edit', [Admin\RoomTypeController::class, 'edit'])->name('room-types.edit');
    Route::put('/room-types/{roomType}', [Admin\RoomTypeController::class, 'update'])->name('room-types.update');

    Route::get('/rooms', [Admin\RoomController::class, 'index'])->name('rooms.index');
    Route::post('/rooms', [Admin\RoomController::class, 'store'])->name('rooms.store');
    Route::put('/rooms/{room}', [Admin\RoomController::class, 'update'])->name('rooms.update');

    Route::get('/pricing', [Admin\PricingController::class, 'index'])->name('pricing.index');
    Route::put('/pricing', [Admin\PricingController::class, 'update'])->name('pricing.update');

    Route::get('/extras', [Admin\ExtraController::class, 'index'])->name('extras.index');
    Route::post('/extras', [Admin\ExtraController::class, 'store'])->name('extras.store');
    Route::put('/extras/{extra}', [Admin\ExtraController::class, 'update'])->name('extras.update');
    Route::post('/extras/{extra}/toggle', [Admin\ExtraController::class, 'toggle'])->name('extras.toggle');

    Route::get('/policy', [Admin\PolicyController::class, 'edit'])->name('policy.edit');
    Route::put('/policy', [Admin\PolicyController::class, 'update'])->name('policy.update');

    Route::get('/staff', [Admin\StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{user}', [Admin\StaffController::class, 'update'])->name('staff.update');
    Route::post('/staff/{user}/toggle', [Admin\StaffController::class, 'toggleActive'])->name('staff.toggle');

    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
});

require __DIR__.'/auth.php';
