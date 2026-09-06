<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.admin_dashboard', [
            'bookingCount' => Booking::count(),
            'pendingCount' => Booking::where('status', 'pending')->count(),
            'roomCount' => Room::where('is_active', true)->count(),
            'bookings' => Booking::with(['user', 'room'])->latest()->take(12)->get(),
        ]);
    }

    public function updateBooking(Request $request, Booking $booking)
    {
        $booking->update($request->validate(['status' => 'required|in:pending,confirmed,cancelled']));
        return back()->with('success', 'Booking status updated.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
