@extends('layouts.app')
@push('styles')<link rel="stylesheet" href="{{ asset('css/staff-mfa.css') }}?v={{ filemtime(public_path('css/staff-mfa.css')) }}">@endpush
@section('content')
<section class="auth-page"><div class="auth-card">
    <span class="eyebrow">STAFF SECURITY</span>
    <h1>Protect your account.</h1>
    <p>Before using staff tools, connect an authenticator app. Your password alone will no longer open the admin area.</p>
    @if (! $canConfirm)
        <form method="POST" action="{{ route('admin.mfa.begin') }}">@csrf
            <label>Confirm your password<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="button" type="submit">Set up authenticator</button>
        </form>
    @else
        <div class="mfa-qr" aria-label="Authenticator QR code">{!! $qrSvg !!}</div>
        <p>Scan this code in your authenticator app, or enter this setup key manually:</p>
        <p class="mfa-secret">{{ $user->mfa_secret }}</p>
        <form method="POST" action="{{ route('admin.mfa.confirm') }}">@csrf
            <label>Six-digit code<input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
            <button class="button" type="submit">Confirm authenticator</button>
        </form>
    @endif
</div></section>
@endsection
