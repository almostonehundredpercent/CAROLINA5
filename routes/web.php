<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StayController;
use App\Models\Room;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', ['featuredRooms' => Room::where('is_active', true)->withAvg('approvedReviews', 'rating')->withCount('approvedReviews')->orderBy('price_per_night')->take(6)->get(), 'availabilityRooms' => Room::where('is_active', true)->orderBy('name')->get()]);
})->name('home');
Route::get('/sitemap.xml', function () {
    $urls = collect([route('home'), route('rooms.index'), route('bookings.lookup'), route('privacy'), route('terms')])->merge(Room::where('is_active', true)->pluck('slug')->map(fn ($slug) => route('rooms.show', $slug)));

    return response()->view('sitemap', compact('urls'))->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/health', function () {
    DB::select('select 1');

    return response()->json(['status' => 'ok']);
})->name('health');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room:slug}/availability', [BookingController::class, 'availability'])->name('rooms.availability');
Route::get('/booking-lookup', [BookingController::class, 'lookupForm'])->name('bookings.lookup');
Route::get('/stay/{booking}', [StayController::class, 'show'])->middleware(['signed', 'throttle:60,1'])->name('stay.show');
Route::post('/stay/{booking}/arrival', [StayController::class, 'arrival'])->middleware(['signed', 'throttle:10,1'])->name('stay.arrival');
Route::post('/booking-lookup', [BookingController::class, 'lookup'])->middleware('throttle:5,1')->name('bookings.lookup.submit');
Route::patch('/booking-lookup/{booking}/cancel', [BookingController::class, 'cancelGuest'])->middleware('throttle:5,1')->name('bookings.lookup.cancel');
Route::patch('/booking-lookup/{booking}/extend', [BookingController::class, 'extendGuest'])->middleware('throttle:5,1')->name('bookings.lookup.extend');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login/form', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->middleware('guest')->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware(['guest', 'throttle:5,1'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest', 'throttle:5,1'])->name('password.update');
Route::get('/email/verify', fn () => view('auth.verify-email'))->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect()->route('home')->with('success', 'Your email address has been verified.');
})->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/verification-notification', function (Request $request) {
    try {
        $request->user()->sendEmailVerificationNotification();
    } catch (Throwable $exception) {
        report($exception);
    }

return back()->with('success', 'If mail is configured, a verification link has been sent.');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/rooms/{room:slug}/book', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/rooms/{room:slug}/book', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('bookings.store');
Route::get('/bookings/{booking}/receipt', [BookingController::class, 'receipt'])->name('bookings.receipt');
Route::get('/bookings/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('bookings.confirmation');
Route::middleware('auth')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/bookings/{booking}/review', [BookingController::class, 'submitReview'])->name('bookings.review');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/arrivals', [StayController::class, 'board'])->middleware('admin.permission:frontdesk')->name('arrivals');
    Route::patch('/arrivals/{booking}', [StayController::class, 'readiness'])->middleware('admin.permission:frontdesk')->name('arrivals.update');
    Route::post('/stay-notices', [StayController::class, 'notice'])->middleware('admin.permission:frontdesk')->name('stay-notices.store');
    Route::patch('/stay-notices/{notice}/resolve', [StayController::class, 'resolve'])->middleware('admin.permission:frontdesk')->name('stay-notices.resolve');
    Route::get('/dashboard', [AdminController::class, 'index'])->middleware('admin.permission:dashboard')->name('dashboard');
    Route::get('/today', [AdminController::class, 'frontdesk'])->middleware('admin.permission:frontdesk')->name('frontdesk');
    Route::get('/rooms', [AdminController::class, 'rooms'])->middleware('admin.permission:rooms')->name('rooms');
    Route::get('/rooms/create', [AdminController::class, 'createRoom'])->middleware('admin.permission:staff')->name('rooms.create');
    Route::post('/rooms', [AdminController::class, 'storeRoom'])->middleware('admin.permission:staff')->name('rooms.store');
    Route::get('/rooms/{room}/edit', [AdminController::class, 'editRoom'])->middleware('admin.permission:staff')->name('rooms.edit');
    Route::patch('/rooms/{room}', [AdminController::class, 'updateRoom'])->middleware('admin.permission:staff')->name('rooms.update');
    Route::delete('/rooms/{room}', [AdminController::class, 'archiveRoom'])->middleware('admin.permission:staff')->name('rooms.archive');
    Route::patch('/rooms/{room}/status', [AdminController::class, 'updateRoomStatus'])->middleware('admin.permission:room_operations')->name('rooms.status');
    Route::get('/bookings', [AdminController::class, 'bookings'])->middleware('admin.permission:bookings')->name('bookings');
    Route::get('/bookings-export.csv', [AdminController::class, 'exportBookings'])->middleware('admin.permission:exports')->name('bookings.export');
    Route::get('/bookings/{booking}', [AdminController::class, 'showBooking'])->middleware('admin.permission:bookings')->name('bookings.show');
    Route::get('/guests', [AdminController::class, 'guests'])->middleware('admin.permission:guests')->name('guests');
    Route::get('/guests/{guest}', [AdminController::class, 'showGuest'])->middleware('admin.permission:guests')->name('guests.show');
    Route::post('/guests/{guest}/notes', [AdminController::class, 'storeGuestNote'])->middleware('admin.permission:guests')->name('guests.notes.store');
    Route::patch('/guests/{guest}/restriction', [AdminController::class, 'updateGuestRestriction'])->middleware('admin.permission:staff')->name('guests.restriction');
    Route::get('/walk-ins', [AdminController::class, 'walkInForm'])->middleware('admin.permission:frontdesk')->name('walk-ins.create');
    Route::post('/walk-ins', [AdminController::class, 'storeWalkIn'])->middleware('admin.permission:frontdesk')->name('walk-ins.store');
    Route::get('/reports', [AdminController::class, 'reports'])->middleware('admin.permission:reports')->name('reports');
    Route::get('/activity', [AdminController::class, 'activity'])->middleware('admin.permission:activity')->name('activity');
    Route::get('/staff', [AdminController::class, 'staff'])->middleware('admin.permission:staff')->name('staff');
    Route::patch('/staff/{user}/role', [AdminController::class, 'updateStaffRole'])->middleware('admin.permission:staff')->name('staff.role');
    Route::patch('/bookings/{booking}', [AdminController::class, 'updateBooking'])->middleware('admin.permission:bookings')->name('bookings.update');
    Route::patch('/bookings/{booking}/payment', [AdminController::class, 'updatePayment'])->middleware('admin.permission:payments')->name('bookings.payment');
    Route::post('/bookings/{booking}/check-in', [AdminController::class, 'updateBooking'])->middleware('admin.permission:bookings')->name('bookings.check-in');
    Route::post('/bookings/{booking}/check-out', [AdminController::class, 'updateBooking'])->middleware('admin.permission:bookings')->name('bookings.check-out');
    Route::patch('/reviews/{review}', [AdminController::class, 'updateReview'])->middleware('admin.permission:reviews')->name('reviews.update');
});
