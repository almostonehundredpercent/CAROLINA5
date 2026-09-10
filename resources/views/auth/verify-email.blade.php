@extends('layouts.app')
@section('title','Verify email | Carolina')
@section('content')<section class="auth-page"><form class="auth-card" method="POST" action="{{ route('verification.send') }}">@csrf<span class="eyebrow">ACCOUNT SECURITY</span><h1>Verify your email.</h1><p>Use the link sent to your email address to verify your account. You may request a new link below.</p><button class="button">Send another verification link</button><p class="auth-switch"><a href="{{ route('home') }}">Back to home</a></p></form></section>@endsection
