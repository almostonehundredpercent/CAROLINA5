<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomController;
use App\Models\Room;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', ['featuredRooms' => Room::where('is_active', true)->withAvg('approvedReviews', 'rating')->withCount('approvedReviews')->orderBy('price_per_night')->take(6)->get(), 'availabilityRooms' => Room::where('is_active', true)->orderBy('name')->get()]);
})->name('home');
Route::get('/sitemap.xml', function () {
    $urls = collect([route('home'), route('rooms.index'), route('bookings.lookup'), route('privacy'), route('terms')])->merge(Room::where('is_active', true)->pluck('slug')->map(fn ($slug) => route('rooms.show', $slug)));
    return response()->view('sitemap', compact('urls'))->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/health', function () { \Illuminate\Support\Facades\DB::select('select 1'); return response()->json(['status' => 'ok']); })->name('health');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room:slug}/availability', [BookingController::class, 'availability'])->name('rooms.availability');
Route::get('/booking-lookup', [BookingController::class, 'lookupForm'])->name('bookings.lookup');
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
Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) { $request->fulfill(); return redirect()->route('home')->with('success', 'Your email address has been verified.'); })->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) { try { $request->user()->sendEmailVerificationNotification(); } catch (\Throwable $exception) { report($exception); } return back()->with('success', 'If mail is configured, a verification link has been sent.'); })->middleware(['auth', 'throttle:6,1'])->name('verification.send');
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
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/rooms', [AdminController::class, 'rooms'])->name('rooms');
    Route::get('/rooms/create', [AdminController::class, 'createRoom'])->name('rooms.create');
    Route::post('/rooms', [AdminController::class, 'storeRoom'])->name('rooms.store');
    Route::get('/rooms/{room}/edit', [AdminController::class, 'editRoom'])->name('rooms.edit');
    Route::patch('/rooms/{room}', [AdminController::class, 'updateRoom'])->name('rooms.update');
    Route::delete('/rooms/{room}', [AdminController::class, 'archiveRoom'])->name('rooms.archive');
    Route::get('/bookings', [AdminController::class, 'bookings'])->name('bookings');
    Route::get('/walk-ins', [AdminController::class, 'walkInForm'])->name('walk-ins.create');
    Route::post('/walk-ins', [AdminController::class, 'storeWalkIn'])->name('walk-ins.store');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/activity', [AdminController::class, 'activity'])->name('activity');
    Route::get('/staff', [AdminController::class, 'staff'])->name('staff');
    Route::patch('/staff/{user}/role', [AdminController::class, 'updateStaffRole'])->name('staff.role');
    Route::patch('/rooms/{room}/status', [AdminController::class, 'updateRoomStatus'])->name('rooms.status');
    Route::patch('/bookings/{booking}', [AdminController::class, 'updateBooking'])->name('bookings.update');
    Route::patch('/bookings/{booking}/payment', [AdminController::class, 'updatePayment'])->name('bookings.payment');
    Route::post('/bookings/{booking}/check-in', [AdminController::class, 'updateBooking'])->name('bookings.check-in');
    Route::post('/bookings/{booking}/check-out', [AdminController::class, 'updateBooking'])->name('bookings.check-out');
    Route::patch('/reviews/{review}', [AdminController::class, 'updateReview'])->name('reviews.update');
});
