{{-- resources/views/guest.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carolina - Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>

<nav>
    <div class="nav-logo">
        <img src="{{ asset('images/carolina-logo.png') }}" alt="Carolina">
    </div>
    <div class="nav-links">
        <a href="{{ route('home') }}" class="active">Home</a>
        <a href="#">About Us</a>
        <a href="#">Contact</a>
    </div>
    <div class="nav-user">
        <i class="ti ti-user"></i>
    </div>
</nav>

<div class="content">

    <div class="panel">
        <h2 class="panel-title">My Bookings</h2>
        <hr>
        @forelse ($bookings ?? [] as $booking)
            <div class="booking-item">{{ $booking->room->name }} — {{ $booking->check_in }}</div>
        @empty
            <p class="empty-text">No Current Bookings</p>
        @endforelse
    </div>

    <div class="right-col">

        <div class="panel">
            <h2 class="panel-title">Available Rooms</h2>
            <div class="rooms-grid">
                @foreach ($rooms ?? [] as $room)
                <div class="room-card">
                    <div class="room-img">
                        <img src="{{ asset('storage/' . $room->image) }}" alt="{{ $room->name }}">
                    </div>
                    <div class="room-info">
                        <div class="room-meta">
                            <span>{{ $room->name }}</span>
                            <span>{{ $room->type }}</span>
                        </div>
                        <div class="room-sub">{{ $room->capacity }}pax</div>
                        <a href="#" class="btn-book">Book Now</a>
                    </div>
                </div>
                @endforeach
            </div>
            <a href="#" class="btn-viewall">View All</a>
        </div>

        <div class="panel">
            <h2 class="panel-title">Special Offers</h2>
            <div class="offer-row">
                <span class="offer-icon">🎁</span>
                <p class="offer-text">Book this November (until Nov.30) and enjoy ₱50 off on all room rates!</p>
            </div>
        </div>

    </div>
</div>

</body>
</html>