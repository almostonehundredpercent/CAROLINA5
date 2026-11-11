@extends('layouts.app')
@section('title', 'About Carolina · Carolina Transient & Airbnb')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/about-page.css') }}?v={{ filemtime(public_path('css/about-page.css')) }}">
@endpush
@section('content')
<section class="page-hero slim about-page-hero">
    <div class="about-hero-inner">
        <div class="about-hero-copy">
            <span class="eyebrow">CAROLINA · TABACO CITY</span>
            <h1>Stay well.<br>Spend less.</h1>
            <p>Affordable bed-and-breakfast and boarding-house stays in Tabaco City, Albay.</p>
        </div>
        <aside class="about-hero-note">
            <span>ROOMS FOR DIFFERENT PLANS</span>
            <strong>Short stays, overnight visits, and room for the people you bring.</strong>
            <small>Compare each room’s details, capacity, and amenities before you book.</small>
        </aside>
    </div>
</section>

<section class="section compact about-page">
    <div class="about-intro">
        <div><span class="eyebrow">A LITTLE MORE COMFORT</span><h2>A practical, welcoming stay—made simple.</h2></div>
        <p>Carolina offers room options for quick visits and longer stays. Browse the details, choose what fits your plans, and review your reservation before payment.</p>
    </div>

    <div class="about-feature-grid">
        <article class="about-feature-card">
            <span class="about-feature-number">01</span>
            <h3>Choose your kind of stay</h3>
            <p>Find short-stay and overnight options, from rooms for two to a whole-house option for groups.</p>
        </article>
        <article class="about-feature-card">
            <span class="about-feature-number">02</span>
            <h3>Useful comforts</h3>
            <p>Depending on the room, enjoy thoughtful basics such as breakfast, Wi-Fi, hygiene kits, and streaming.</p>
        </article>
        <article class="about-feature-card">
            <span class="about-feature-number">03</span>
            <h3>Know what you’re booking</h3>
            <p>Room pages list capacity, rates, and amenities. Bathroom access and other features can vary by room.</p>
        </article>
    </div>

    <div class="about-visit-card">
        <div><span class="eyebrow">PLAN YOUR VISIT</span><h2>Make your Tabaco City stay easy.</h2><p>Browse the available rooms or reach out if you need help choosing one.</p></div>
        <div class="about-visit-actions"><a class="button" href="{{ route('rooms.index') }}">Explore rooms <span aria-hidden="true">→</span></a><a class="button button-outline" href="{{ route('contact') }}">Contact Carolina</a></div>
    </div>
</section>
@endsection
