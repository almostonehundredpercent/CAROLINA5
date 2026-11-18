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
                <p class="admin-subtitle">Choose room, rate, guests, and arrival. We’ll calculate checkout and total.</p>
            </div>
        </header>

        @if($errors->any())
            <div class="walkin-errors" role="alert">{{ $errors->first() }}</div>
        @endif
        <p class="walkin-errors" id="walkin-selection-error" role="alert" hidden></p>

        <script type="application/json" id="walkin-pricing-data">@json($walkInPricingData)</script>

        <section class="panel walkin-card">
            <form method="POST" action="{{ route('admin.walk-ins.store') }}" class="walkin-form" id="walkin-form" novalidate>
                @csrf
                <input type="hidden" name="booking_type" value="hourly">
                <input type="hidden" name="room_id" id="walkin-room" value="{{ old('room_id') }}">
                <input type="hidden" name="hours" id="walkin-hours" value="{{ old('hours') }}">
                <input type="hidden" name="cooling_type" id="walkin-cooling" value="{{ old('cooling_type') }}">
                <input type="hidden" name="guests" id="walkin-guests" value="{{ old('guests') }}">
                <input type="hidden" name="check_in" id="walkin-check-in" value="{{ old('check_in') }}">
                <input type="hidden" name="check_in_time" id="walkin-check-in-time" value="{{ old('check_in_time') }}">

                <section class="walkin-section">
                    <div class="walkin-section-heading">
                        <span class="walkin-step">1</span>
                        <div><h2>Choose a room</h2><p>Rates appear after you select a room.</p></div>
                    </div>
                    <div class="walkin-room-picker">
                        <button class="walkin-choice-trigger" id="walkin-room-trigger" type="button" aria-expanded="false" aria-controls="walkin-room-options">
                            <span id="walkin-room-trigger-copy">Choose a room</span><span class="walkin-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div class="walkin-choice-panel" id="walkin-room-options" hidden>
                            <div class="walkin-choice-panel-heading"><strong>Select a room</strong><button type="button" data-close-room-options>Close</button></div>
                            <div class="walkin-room-options" role="group" aria-label="Rooms">
                                @foreach($rooms as $room)
                                    <button class="walkin-room-option" type="button" data-room-option data-room-id="{{ $room->id }}" data-room-name="{{ $room->name }}" data-room-type="{{ $room->room_type ?: 'Room' }}" data-room-guests="{{ $room->booking_guest_limit }}" aria-pressed="false">
                                        <strong>{{ $room->name }}</strong>
                                        <small>{{ $room->room_type ?: 'Room' }} · up to {{ $room->booking_guest_limit }} guests</small>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p class="walkin-room-summary" id="walkin-room-summary" aria-live="polite" hidden></p>
                </section>

                <section class="walkin-section" id="walkin-package-section" hidden>
                    <div class="walkin-section-heading">
                        <span class="walkin-step">2</span>
                        <div><h2>Choose a rate</h2><p id="walkin-package-copy">Select a stay length and cooling option.</p></div>
                    </div>
                    <div class="walkin-package-grid" id="walkin-package-grid" role="group" aria-label="Available rate packages"></div>
                </section>

                <section class="walkin-section" id="walkin-guest-section" hidden>
                    <div class="walkin-section-heading">
                        <span class="walkin-step">3</span>
                        <div><h2>Select guests</h2><p>Prices include up to 2 guests.</p></div>
                    </div>
                    <div class="walkin-guest-options" id="walkin-guest-options" role="group" aria-label="Number of guests"></div>
                    <p class="walkin-field-note" id="walkin-capacity-copy"></p>
                </section>

                <section class="walkin-section walkin-time-section" id="walkin-arrival-section" hidden>
                    <div class="walkin-section-heading">
                        <span class="walkin-step">4</span>
                        <div><h2>Select date and time</h2><p>Checkout is calculated from the selected rate.</p></div>
                    </div>
                    <div class="walkin-date-time-buttons">
                        <div class="walkin-date-time-field">
                            <span class="walkin-field-title">Check-in date</span>
                            <button class="walkin-choice-trigger" id="walkin-date-trigger" type="button" aria-expanded="false" aria-controls="walkin-calendar"><span id="walkin-date-copy">Choose a date</span><span class="walkin-chevron" aria-hidden="true">⌄</span></button>
                            <div class="walkin-choice-panel walkin-calendar" id="walkin-calendar" hidden>
                                <div class="walkin-calendar-heading"><button type="button" id="walkin-calendar-prev" aria-label="Previous month">‹</button><strong id="walkin-calendar-month" aria-live="polite"></strong><button type="button" id="walkin-calendar-next" aria-label="Next month">›</button></div>
                                <div class="walkin-calendar-week" aria-hidden="true"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
                                <div class="walkin-calendar-days" id="walkin-calendar-days" role="group" aria-label="Choose check-in date"></div>
                            </div>
                        </div>
                        <div class="walkin-date-time-field">
                            <span class="walkin-field-title">Check-in time</span>
                            <button class="walkin-choice-trigger" id="walkin-time-trigger" type="button" aria-expanded="false" aria-controls="walkin-time-options" disabled><span id="walkin-time-copy">Choose a time</span><span class="walkin-chevron" aria-hidden="true">⌄</span></button>
                            <div class="walkin-choice-panel walkin-time-options" id="walkin-time-options" role="group" aria-label="Choose check-in time" hidden></div>
                        </div>
                    </div>
                </section>

                <div class="walkin-finish" id="walkin-finish-section" hidden>
                    <section class="walkin-summary" aria-live="polite">
                        <div class="walkin-summary-item"><span>Suggested checkout</span><strong id="walkin-checkout">—</strong></div>
                        <div class="walkin-summary-total"><span>Estimated total</span><strong id="walkin-total">—</strong><small id="walkin-total-copy"></small></div>
                    </section>

                    <section class="walkin-optional-details">
                        <div class="walkin-optional-heading"><strong>Guest contact</strong><span>Optional</span></div>
                        <div class="walkin-fields">
                            <label class="walkin-field" for="walkin-guest-name"><span>Name <em>Optional</em></span><input id="walkin-guest-name" name="guest_name" value="{{ old('guest_name') }}" autocomplete="name"></label>
                            <label class="walkin-field" for="walkin-guest-phone"><span>Phone number <em>Optional</em></span><input id="walkin-guest-phone" name="guest_phone" type="tel" value="{{ old('guest_phone') }}" autocomplete="tel"></label>
                            <label class="walkin-field" for="walkin-guest-email"><span>Email <em>Optional</em></span><input id="walkin-guest-email" name="guest_email" type="email" value="{{ old('guest_email') }}" autocomplete="email"></label>
                        </div>
                    </section>

                    <p class="walkin-help">Room availability is checked again when you confirm. Record any payment from the booking’s Payment details page.</p>
                    <button class="walkin-submit" type="submit">Confirm walk-in booking</button>
                </div>
            </form>
        </section>
    </main>
</div>
<script src="{{ asset('js/admin-walk-in.js') }}?v={{ filemtime(public_path('js/admin-walk-in.js')) }}" defer></script>
</body>
</html>
