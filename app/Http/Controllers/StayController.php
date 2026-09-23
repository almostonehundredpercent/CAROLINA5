<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class StayController extends Controller
{
    public static function link(Booking $booking): string
    {
        return URL::temporarySignedRoute('stay.show', now()->addDays(7), ['booking' => $booking->id]);
    }

    public function show(Booking $booking)
    {
        // Never retain a ready promise when a new operational conflict appears.
        if ($booking->readiness === 'ready' && (! $booking->room || $booking->room->operational_status !== 'available'
            || $booking->room->blocks()->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists()
            || Booking::where('room_id', $booking->room_id)->whereKeyNot($booking->id)->where('status', 'confirmed')->whereNotNull('checked_in_at')->whereNull('checked_out_at')->exists())) {
            $booking->readiness = 'unconfirmed';
        }
        $notices = DB::table('stay_notices')->where('active', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn ($q) => $q->whereNull('room_id')->orWhere('room_id', $booking->room_id))->latest('updated_at')->get();

        return response()->view('bookings.stay', compact('booking', 'notices'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function arrival(Request $request, Booking $booking)
    {
        abort_if($booking->status === 'cancelled' || $booking->checked_in_at || $booking->checked_out_at || ($booking->check_out_at ?? $booking->check_out->copy()->endOfDay())->isPast(), 409, 'This stay no longer accepts arrival updates.');
        $data = $request->validate(['arrival_update' => 'required|in:on_time,running_late,cannot_make_it']);
        $booking->arrival_update = $data['arrival_update'];
        $booking->arrival_updated_at = now();
        $booking->save();
        ActivityLog::create(['booking_id' => $booking->id, 'event' => 'guest_arrival_update', 'description' => 'Guest arrival: '.$data['arrival_update']]);

        return redirect(self::link($booking))->with('success', 'Your arrival update has been shared with reception. This does not cancel or change your booking.');
    }

    public function board()
    {
        $bookings = Booking::with('room')->whereIn('status', ['pending', 'confirmed'])->whereNull('checked_out_at')
            ->where('check_out', '>=', today())->where('check_in', '<=', today()->addDays(7))->orderBy('check_in')->paginate(20);
        $rooms = Room::where('is_active', true)->orderBy('name')->get();
        $notices = DB::table('stay_notices')->where('active', true)->where('ends_at', '>', now())->latest()->get();

        return view('admin.arrivals', compact('bookings', 'rooms', 'notices'));
    }

    public function readiness(Request $request, Booking $booking)
    {
        $data = $request->validate(['readiness' => 'required|in:unconfirmed,preparing,ready', 'ready_estimate' => 'nullable|date|after:now', 'bag_drop_available' => 'required|boolean', 'arrival_instructions' => 'nullable|string|max:1500']);
        abort_unless($booking->status === 'confirmed' && ! $booking->checked_out_at, 422, 'Confirm the booking before publishing room readiness.');
        if ($data['readiness'] === 'ready') {
            $busy = Booking::where('room_id', $booking->room_id)->whereKeyNot($booking->id)->whereNotNull('checked_in_at')->whereNull('checked_out_at')->where('status', 'confirmed')->exists();
            $blocked = $booking->room->blocks()->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists();
            abort_if($busy || $blocked || $booking->room->operational_status !== 'available', 422, 'Resolve the current stay or room operation before marking ready.');
        }
        foreach ($data as $key => $value) {
            $booking->$key = $value;
        }
        if ($data['readiness'] !== 'preparing') {
            $booking->ready_estimate = null;
        }
        $booking->readiness_updated_at = now();
        $booking->save();
        ActivityLog::create(['booking_id' => $booking->id, 'user_id' => auth()->id(), 'event' => 'readiness_updated', 'description' => 'Room readiness: '.$data['readiness']]);

        return back()->with('success', 'Guest arrival page updated.');
    }

    public function notice(Request $request)
    {
        $data = $request->validate(['room_id' => 'nullable|exists:rooms,id', 'type' => 'required|in:weather,power,water,travel,other', 'title' => 'required|string|max:120', 'message' => 'required|string|max:2000', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at|after:now']);
        DB::table('stay_notices')->insert($data + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);
        ActivityLog::create(['user_id' => auth()->id(), 'event' => 'stay_notice_published', 'description' => $data['title']]);

        return back()->with('success', 'Notice published on affected guest arrival pages.');
    }

    public function resolve(int $notice)
    {
        abort_unless(DB::table('stay_notices')->where('id', $notice)->exists(), 404);
        DB::table('stay_notices')->where('id', $notice)->update(['active' => false, 'updated_at' => now()]);
        ActivityLog::create(['user_id' => auth()->id(), 'event' => 'stay_notice_resolved', 'description' => 'Notice '.$notice.' resolved']);

        return back()->with('success', 'Notice resolved.');
    }
}
