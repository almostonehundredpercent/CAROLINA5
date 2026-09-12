@php($contact = \App\Models\BusinessContact::current())
<section {{ $attributes->merge(['class' => 'contact-card']) }} aria-label="Contact Carolina">
    <strong>Need help booking?</strong>
    @if($contact?->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $contact->phone) }}">Call {{ $contact->phone }}</a>@endif
    @if($contact?->email)<a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@endif
    @if($contact?->address)<span>{{ $contact->address }}</span>@endif
    @if($contact?->facebook_url)<a href="{{ $contact->facebook_url }}" target="_blank" rel="noopener noreferrer">Message Carolina on Facebook</a>@endif
</section>
