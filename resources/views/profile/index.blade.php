@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer-profile.css') }}?v={{ filemtime(public_path('css/customer-profile.css')) }}">
@endpush
@section('content')
<section class="page-hero slim customer-profile-hero">
    <div class="customer-profile-head">
        <span class="eyebrow">YOUR ACCOUNT</span>
        <h1>Welcome back, {{ auth()->user()->name }}!</h1>
        <p>Update your account details and keep your Carolina stays organized.</p>
    </div>
</section>
<section class="section compact customer-profile">
    @if(session('profile_success'))<p class="customer-profile-alert" role="status">{{ session('profile_success') }}</p>@endif
    <div class="customer-profile-actions" aria-label="Quick actions">
        <a class="customer-profile-action" href="{{ route('booking.start') }}"><span aria-hidden="true">＋</span><strong>New booking</strong><small>Find your next stay</small></a>
        <a class="customer-profile-action" href="{{ route('contact') }}"><span aria-hidden="true">✉</span><strong>Contact Carolina</strong><small>Get help with your stay</small></a>
    </div>
    <nav class="customer-profile-tabs" aria-label="Account pages">
        <a href="{{ route('profile.show') }}" aria-current="page">Profile information</a>
        <a href="{{ route('bookings.index') }}">My bookings</a>
    </nav>
    <article class="customer-profile-information" id="profile-information">
        <div class="customer-profile-card-heading"><div><span class="profile-kicker">ACCOUNT DETAILS</span><h2>Personal information</h2></div><span class="profile-member-badge">Member since {{ auth()->user()->created_at->format('Y') }}</span></div>
        <form class="customer-profile-form" method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PATCH')
            <div class="customer-profile-form-grid">
                <label>Full name<input name="name" type="text" value="{{ old('name', auth()->user()->name) }}" autocomplete="name" maxlength="255" required @error('name') aria-invalid="true" aria-describedby="profile-name-error" @enderror>@error('name')<small class="customer-profile-field-error" id="profile-name-error">{{ $message }}</small>@enderror</label>
                <label>Email address<input name="email" type="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="profile-email-error" @enderror>@error('email')<small class="customer-profile-field-error" id="profile-email-error">{{ $message }}</small>@enderror</label>
                <label>Contact number (optional)<input name="contact_number" type="tel" value="{{ old('contact_number', auth()->user()->contact_number) }}" autocomplete="tel" inputmode="tel" maxlength="20" placeholder="09XX XXX XXXX" @error('contact_number') aria-invalid="true" aria-describedby="profile-contact-number-error" @else aria-describedby="profile-contact-number-hint" @enderror>@error('contact_number')<small class="customer-profile-field-error" id="profile-contact-number-error">{{ $message }}</small>@else<small class="customer-profile-field-hint" id="profile-contact-number-hint">Use a Philippine mobile number, such as 09XX XXX XXXX.</small>@enderror</label>
            </div>
            <div class="customer-profile-form-footer">
                <p>Email status: <strong>{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}</strong>. Account created {{ auth()->user()->created_at->format('F j, Y') }}.</p>
                @unless(auth()->user()->hasVerifiedEmail())<a href="{{ route('verification.notice') }}">Verify your email</a>@endunless
                <button class="button" type="submit">Save changes</button>
            </div>
        </form>
    </article>
</section>
@endsection
