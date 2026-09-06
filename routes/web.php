<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomController;
use App\Models\Room;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home', ['featuredRooms' => Room::where('is_active', true)->orderBy('price_per_night')->take(3)->get()]))->name('home');
Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
Route::get('/rooms/{room:slug}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/rooms/{room:slug}/availability', [BookingController::class, 'availability'])->name('rooms.availability');
Route::get('/booking-lookup', [BookingController::class, 'lookupForm'])->name('bookings.lookup');
Route::post('/booking-lookup', [BookingController::class, 'lookup'])->name('bookings.lookup.submit');
Route::patch('/booking-lookup/{booking}/cancel', [BookingController::class, 'cancelGuest'])->name('bookings.lookup.cancel');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login/form', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/rooms/{room:slug}/book', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/rooms/{room:slug}/book', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{booking}/receipt', [BookingController::class, 'receipt'])->name('bookings.receipt');
Route::post('/bookings/{booking}/confirm-payment', [BookingController::class, 'confirmPayment'])->name('bookings.confirm-payment');
Route::get('/bookings/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('bookings.confirmation');
Route::middleware('auth')->group(function () {
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/rooms', [AdminController::class, 'rooms'])->name('rooms');
    Route::get('/bookings', [AdminController::class, 'bookings'])->name('bookings');
    Route::get('/walk-ins', [AdminController::class, 'walkInForm'])->name('walk-ins.create');
    Route::post('/walk-ins', [AdminController::class, 'storeWalkIn'])->name('walk-ins.store');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::patch('/rooms/{room}/status', [AdminController::class, 'updateRoomStatus'])->name('rooms.status');
    Route::patch('/bookings/{booking}', [AdminController::class, 'updateBooking'])->name('bookings.update');
});
