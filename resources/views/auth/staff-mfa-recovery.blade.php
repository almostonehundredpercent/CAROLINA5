@extends('layouts.app')
@push('styles')<link rel="stylesheet" href="{{ asset('css/staff-mfa.css') }}?v={{ filemtime(public_path('css/staff-mfa.css')) }}">@endpush
@section('content')
<section class="auth-page"><div class="auth-card">
    <span class="eyebrow">SAVE THESE CODES</span>
    <h1>Your backup access.</h1>
    <p>Save these one-time recovery codes somewhere private. They will not be shown again. Each code works only once if you lose your authenticator.</p>
    <ul class="mfa-recovery-list">@foreach ($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
    <a class="button" href="{{ route(\App\Support\AdminPermissions::landingRoute(auth()->user())) }}">Continue to staff tools</a>
</div></section>
@endsection
