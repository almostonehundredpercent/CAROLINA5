<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Walk-in booking · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-walk-in.css') }}?v={{ filemtime(public_path('css/admin-walk-in.css')) }}">
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main walkin-page">
        <header class="admin-topbar walkin-header">
            <div>
                <p class="admin-kicker">FRONT DESK</p>
                <h1>New walk-in</h1>
                <p class="admin-subtitle">Choose a room and package. Checkout time and total update automatically.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="walkin-errors" role="alert">{{ $errors->first() }}</div>
        @endif

        @php
            $walkInRates = $rooms->mapWithKeys(function ($room) {
                $packages = [];
                foreach (\App\Support\StayPricing::rates($room) as $hours => $rates) {
                    foreach ($rates as $cooling => $rate) {
                        $packages[] = [
                            'hours' => (int) $hours,
                            'duration' => \App\Support\StayPricing::durationLabel((int) $hours),
                            'cooling' => $cooling,
                            'coolingLabel' => $cooling === 'fan' ? 'Fan' : 'Air-conditioned',
                            'rate' => (float) $rate,
                        ];
                    }
                }

                return [$room->id => $packages];
            });
        @endphp
        <script type="application/json" id="walkin-pricing-data">@json(['rooms' => $walkInRates, 'extraGuestRate' => \App\Support\StayPricing::EXTRA_GUEST_RATE])</script>

        <section class="panel walkin-card">
            @php($defaultWalkInCheckIn = \App\Support\PropertyTime::now()->copy()->addHour()->startOfHour())
            <form method="POST" action="{{ route('admin.walk-ins.store') }}" class="walkin-form" id="walkin-form">
                @csrf
                <input type="hidden" name="booking_type" value="hourly">
                <input type="hidden" name="hours" id="walkin-hours" value="{{ old('hours') }}">
                <input type="hidden" name="cooling_type" id="walkin-cooling" value="{{ old('cooling_type') }}">

                <section class="walkin-section">
                    <div class="walkin-section-heading">
                        <span class="walkin-step">1</span>
                        <div><h2>Choose a room</h2><p>Rates will appear after you select a room.</p></div>
                    </div>
                    <label class="walkin-field" for="walkin-room">
                        <span>Room</span>
                        <select name="room_id" id="walkin-room" required>
                            <option value="">Choose a room</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" data-room-name="{{ $room->name }}" data-room-type="{{ $room->room_type ?: 'Room' }}" data-room-guests="{{ $room->booking_guest_limit }}" @selected(old('room_id') == $room->id)>
                                    {{ $room->name }} · {{ $room->room_type ?: 'Room' }} · up to {{ $room->booking_guest_limit }} guests
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <p class="walkin-room-summary" id="walkin-room-summary" aria-live="polite" hidden></p>
                </section>

                <section class="walkin-section" id="walkin-package-section" hidden>
                    <div class="walkin-section-heading">
                        <span class="walkin-step">2</span>
                        <div><h2>Choose a rate</h2><p id="walkin-package-copy">Select a stay length and cooling option.</p></div>
                    </div>
                    <div class="walkin-package-grid" id="walkin-package-grid" role="group" aria-label="Available rate packages"></div>
                    <p class="walkin-inline-error" id="walkin-package-error" role="alert" hidden>Choose a rate package to continue.</p>
                </section>

                <section class="walkin-section">
                    <div class="walkin-section-heading">
                        <span class="walkin-step">3</span>
                        <div><h2>Guest details</h2><p>Prices include up to 2 guests.</p></div>
                    </div>
                    <div class="walkin-fields">
                        <label class="walkin-field" for="walkin-guest-name"><span>Guest name</span><input id="walkin-guest-name" name="guest_name" value="{{ old('guest_name') }}" autocomplete="name" required></label>
                        <label class="walkin-field" for="walkin-guests"><span>Number of guests</span><input id="walkin-guests" type="number" name="guests" min="1" max="30" value="{{ old('guests', 1) }}" required><small id="walkin-capacity-copy">Choose a room to see its capacity.</small></label>
                        <label class="walkin-field" for="walkin-guest-phone"><span>Phone <em>Optional</em></span><input id="walkin-guest-phone" name="guest_phone" type="tel" value="{{ old('guest_phone') }}" autocomplete="tel"></label>
                        <label class="walkin-field" for="walkin-guest-email"><span>Email <em>Optional</em></span><input id="walkin-guest-email" name="guest_email" type="email" value="{{ old('guest_email') }}" autocomplete="email"></label>
                    </div>
                </section>

                <section class="walkin-section walkin-time-section">
                    <div class="walkin-section-heading">
                        <span class="walkin-step">4</span>
                        <div><h2>Arrival</h2><p>Checkout is suggested from the selected package.</p></div>
                    </div>
                    <div class="walkin-fields">
                        <label class="walkin-field" for="walkin-check-in"><span>Check-in date</span><input id="walkin-check-in" type="date" name="check_in" min="{{ \App\Support\PropertyTime::today() }}" value="{{ old('check_in', $defaultWalkInCheckIn->toDateString()) }}" required></label>
                        <label class="walkin-field" for="walkin-check-in-time"><span>Check-in time</span><input id="walkin-check-in-time" type="time" name="check_in_time" step="3600" value="{{ old('check_in_time', $defaultWalkInCheckIn->format('H:i')) }}" required></label>
                    </div>
                </section>

                <section class="walkin-summary" aria-live="polite">
                    <div class="walkin-summary-item"><span>Suggested checkout</span><strong id="walkin-checkout">Choose a rate and arrival time</strong></div>
                    <div class="walkin-summary-total"><span>Estimated total</span><strong id="walkin-total">—</strong><small id="walkin-total-copy">The selected package includes up to 2 guests.</small></div>
                </section>

                <p class="walkin-help">This confirms the walk-in stay. Record any payment from the booking’s Payment details page. Room availability is checked again when you submit.</p>
                <button class="walkin-submit" type="submit">Confirm walk-in booking</button>
            </form>
        </section>
    </main>
</div>
<script src="{{ asset('js/admin-walk-in.js') }}?v={{ filemtime(public_path('js/admin-walk-in.js')) }}" defer></script>
</body>
</html>
