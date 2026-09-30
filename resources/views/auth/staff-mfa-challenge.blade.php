@extends('layouts.app')
@push('styles')<link rel="stylesheet" href="{{ asset('css/staff-mfa.css') }}?v={{ filemtime(public_path('css/staff-mfa.css')) }}">@endpush
@section('content')
<section class="auth-page"><div class="auth-card">
    <span class="eyebrow">STAFF SECURITY</span>
    <h1>One more step.</h1>
    <p>Enter the current code from your authenticator app, or use one saved recovery code.</p>
    <form method="POST" action="{{ route('admin.mfa.verify') }}">@csrf
        <label>Authenticator code<input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></label>
        <label>Recovery code (only if needed)<input type="text" name="recovery_code" autocomplete="off"></label>
        <button class="button" type="submit">Continue to staff tools</button>
    </form>
</div></section>
@endsection
