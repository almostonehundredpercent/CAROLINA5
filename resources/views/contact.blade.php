@extends('layouts.app')
@section('title', 'Contact Carolina · Carolina Transient & Airbnb')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/contact-page.css') }}?v={{ filemtime(public_path('css/contact-page.css')) }}">
@endpush
@section('content')
<section class="page-hero slim contact-page-hero">
    <div class="contact-hero-inner">
        <a class="contact-back-link" href="{{ route('home') }}"><span aria-hidden="true">←</span> Back to Home</a>
        <div class="contact-hero-copy">
            <div><span class="eyebrow">WE’RE HERE TO HELP</span><h1>Get in touch.</h1></div>
            <p>Questions about a room, an upcoming stay, or finding us? Contact Carolina and we’ll help you plan your visit.</p>
        </div>
        <aside class="contact-hero-note"><span>CAROLINA · TABACO CITY</span><strong>Good stays start with a clear answer.</strong><small>Room details, booking questions, or directions—we’re happy to help.</small></aside>
    </div>
</section>

<section class="section compact contact-page">
    <div class="contact-experience-grid">
        <section class="contact-options" aria-labelledby="contact-options-title">
            <div class="contact-page-heading">
                <span class="eyebrow">CONTACT CAROLINA</span>
                <h2 id="contact-options-title">Talk to our team.</h2>
                <p>For booking questions, include your room and reservation reference when available.</p>
            </div>
            <div class="contact-method-grid">
                @if($contact?->phone)
                    <a class="contact-method-card" href="tel:{{ preg_replace('/[^+0-9]/', '', $contact->phone) }}">
                        <span class="contact-method-icon" aria-hidden="true">☎</span><span class="contact-method-label">Call us</span><strong>{{ $contact->phone }}</strong><small>Tap to call Carolina</small>
                    </a>
                @endif
                @if($contact?->email)
                    <a class="contact-method-card" href="mailto:{{ $contact->email }}">
                        <span class="contact-method-icon" aria-hidden="true">✉</span><span class="contact-method-label">Email</span><strong>{{ $contact->email }}</strong><small>Send us your question</small>
                    </a>
                @endif
                <a class="contact-method-card contact-method-card--primary" href="{{ $contact?->facebook_url ?: 'https://www.facebook.com/profile.php?id=61556306344437' }}" target="_blank" rel="noopener noreferrer">
                    <span class="contact-method-icon" aria-hidden="true">f</span><span class="contact-method-label">Message Carolina</span><strong>Facebook Messenger</strong><small>Open our Facebook page</small>
                </a>
                @if($contact?->hours)
                    <article class="contact-method-card contact-method-card--hours">
                        <span class="contact-method-icon" aria-hidden="true">◷</span><span class="contact-method-label">Contact hours</span><strong>{{ $contact->hours }}</strong><small>We’ll get back to you as soon as we can.</small>
                    </article>
                @endif
            </div>
        </section>

        <aside class="contact-address-card" aria-label="Carolina location">
            <span class="contact-location-icon" aria-hidden="true">⌖</span>
            <span class="eyebrow">VISIT US</span>
            <h2>Find your way to Carolina.</h2>
            <p>Tomas Cabiles Street, Zone 6, San Juan, Tabaco City, Albay—near Tabaco College.</p>
            <a class="button" href="https://www.google.com/maps/dir/?api=1&amp;destination=13.3567576%2C123.7260803" target="_blank" rel="noopener noreferrer">Open directions <span aria-hidden="true">↗</span></a>
            <small>The map below marks the exact location.</small>
        </aside>
    </div>
    @include('bookings.partials.location-map', ['locationEyebrow' => 'MAP & DIRECTIONS'])
</section>
@endsection
