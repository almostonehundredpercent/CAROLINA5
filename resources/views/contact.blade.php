@extends('layouts.app')
@section('title', 'Contact Carolina · Carolina Transient & Airbnb')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/contact-page.css') }}?v={{ filemtime(public_path('css/contact-page.css')) }}">
@endpush
@section('content')
<section class="page-hero slim contact-page-hero">
    <div class="contact-hero-inner">
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
                <a class="contact-method-card contact-method-card--primary" href="{{ $contact?->facebook_url ?: 'https://www.facebook.com/profile.php?id=61556306344437' }}" target="_blank" rel="noopener noreferrer">
                    <span class="contact-method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg></span><span class="contact-method-label">Message Carolina</span><strong>Facebook Messenger</strong><small>Open our Facebook page</small><span class="contact-method-external" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M14 3h7v7M10 14 21 3"/><path d="M19 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/></svg></span>
                </a>
                @php($contactPhone = $contact?->phone ?: '0994 995 2174')
                <a class="contact-method-card" href="tel:{{ preg_replace('/[^+0-9]/', '', $contactPhone) }}">
                    <span class="contact-method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 8.9a16 16 0 0 0 6 6l1.2-1.3a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.8 2.1z"/></svg></span><span class="contact-method-label">Call us</span><strong>{{ $contactPhone }}</strong><small>Tap to call Carolina</small><span class="contact-method-external" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </a>
                @if($contact?->email)
                    <a class="contact-method-card" href="mailto:{{ $contact->email }}">
                        <span class="contact-method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span><span class="contact-method-label">Email</span><strong>{{ $contact->email }}</strong><small>Send us your question</small><span class="contact-method-external" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                    </a>
                @endif
                @if($contact?->hours)
                    <article class="contact-method-card contact-method-card--hours">
                        <span class="contact-method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span class="contact-method-label">Contact hours</span><strong>{{ $contact->hours }}</strong><small>We’ll get back to you as soon as we can.</small>
                    </article>
                @endif
            </div>
        </section>

        <aside class="contact-address-card" aria-label="Carolina location">
            <span class="contact-location-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
            <span class="eyebrow">VISIT US</span>
            <h2>Find your way to Carolina.</h2>
            <p>Tomas Cabiles Street, Zone 6, San Juan, Tabaco City, Albay—near Tabaco College.</p>
            <a class="button" href="https://www.google.com/maps/dir/?api=1&amp;destination=13.3567576%2C123.7260803" target="_blank" rel="noopener noreferrer">Open directions <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 3h7v7M10 14 21 3"/><path d="M19 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/></svg></a>
            <small>The map below marks the exact location.</small>
        </aside>
    </div>
    @include('bookings.partials.location-map', ['locationEyebrow' => 'MAP & DIRECTIONS'])
</section>
@endsection
