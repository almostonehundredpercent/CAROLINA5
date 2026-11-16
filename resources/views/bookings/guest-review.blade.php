@extends('layouts.app')

@section('title', 'Share feedback | Carolina Transient & Airbnb')

@section('content')
<style>
    .guest-feedback-page{max-width:760px;margin:52px auto;padding:0 20px 64px}.guest-feedback-card{background:var(--surface,#fff);border:1px solid var(--line,#eadccd);border-radius:18px;padding:clamp(24px,5vw,44px);box-shadow:0 16px 45px #42240d12}.guest-feedback-page .eyebrow{color:#a94f00}.guest-feedback-page h1{font-size:clamp(2rem,5vw,3rem);margin:0 0 8px}.guest-feedback-page .intro{color:var(--muted,#715f50);margin:0 0 24px}.feedback-stay{background:var(--sand,#faf5ee);padding:16px 18px;border-radius:12px;margin:22px 0 26px}.feedback-stay strong,.feedback-stay span{display:block}.feedback-stay span{color:var(--muted,#715f50);font-size:.92rem;margin-top:4px}.feedback-field{display:grid;gap:8px;margin:20px 0}.feedback-field label,.rating-label{font-weight:700}.feedback-field input,.feedback-field textarea{width:100%;box-sizing:border-box}.feedback-field textarea{min-height:150px;resize:vertical}.feedback-rating{display:flex;gap:9px;flex-wrap:wrap;margin-top:10px}.feedback-rating label{display:grid;place-items:center;width:48px;height:48px;border:1px solid var(--line,#eadccd);border-radius:12px;cursor:pointer;font-weight:700}.feedback-rating input{position:absolute;opacity:0}.feedback-rating label:has(input:checked){background:#a94f00;color:#fff;border-color:#a94f00}.feedback-consent{display:flex;align-items:flex-start;gap:10px;margin:22px 0;color:var(--muted,#715f50);font-size:.92rem}.feedback-consent input{margin-top:4px}.feedback-submit{width:100%;justify-content:center}.feedback-alert{padding:13px 16px;border-radius:10px;margin-bottom:18px}.feedback-alert.success{background:#e6f5e9;color:#205c35}.feedback-alert.info{background:#fff3df;color:#754405}.feedback-error{color:#a33932;font-size:.9rem}.feedback-done{text-align:center;padding:24px 0}.feedback-done .done-mark{width:58px;height:58px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;background:#e6f5e9;color:#267244;font-size:1.8rem;font-weight:700}
</style>

<section class="guest-feedback-page">
    <div class="guest-feedback-card">
        @if(session('success'))<div class="feedback-alert success" role="status">{{ session('success') }}</div>@endif
        @if(session('info'))<div class="feedback-alert info" role="status">{{ session('info') }}</div>@endif
        @if($booking->review)
            <div class="feedback-done">
                <div class="done-mark" aria-hidden="true">✓</div>
                <span class="eyebrow">FEEDBACK RECEIVED</span>
                <h1>Thank you for sharing.</h1>
                <p class="intro">A review has already been submitted for this stay. Carolina will decide whether to publish it, unchanged.</p>
            </div>
        @else
            <span class="eyebrow">GUEST FEEDBACK</span>
            <h1>How was your stay?</h1>
            <p class="intro">Your feedback helps future guests know what to expect.</p>
            <div class="feedback-stay">
                <strong>{{ $booking->room?->name ?? 'Your Carolina stay' }}</strong>
                <span>Booking {{ $booking->reference }} · Checked out {{ $booking->checked_out_at?->format('M j, Y') }}</span>
            </div>
            <p>Reviews are submitted as written. Staff can publish or hide them, but cannot edit your name or comment. You can submit one review for this stay. Please do not include phone numbers, email addresses, or home addresses.</p>
            <form method="POST" action="{{ route('bookings.guest-review.submit', ['booking' => $booking->id] + request()->query()) }}">
                @csrf
                <div class="feedback-field">
                    <label for="guest_name">Name to display</label>
                    <input id="guest_name" name="guest_name" type="text" maxlength="100" required value="{{ old('guest_name', 'Verified guest') }}" autocomplete="name">
                    @error('guest_name')<span class="feedback-error">{{ $message }}</span>@enderror
                </div>
                <fieldset class="feedback-field" style="border:0;padding:0">
                    <legend class="rating-label">Your rating</legend>
                    <div class="feedback-rating">
                        @for($rating = 1; $rating <= 5; $rating++)
                            <label aria-label="{{ $rating }} {{ \Illuminate\Support\Str::plural('star', $rating) }}">
                                <input type="radio" name="rating" value="{{ $rating }}" required @checked((string) old('rating') === (string) $rating)>
                                {{ $rating }}
                            </label>
                        @endfor
                    </div>
                    @error('rating')<span class="feedback-error">{{ $message }}</span>@enderror
                </fieldset>
                <div class="feedback-field">
                    <label for="comment">Your review <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                    <textarea id="comment" name="comment" maxlength="750" placeholder="What would you like other guests to know?">{{ old('comment') }}</textarea>
                    @error('comment')<span class="feedback-error">{{ $message }}</span>@enderror
                </div>
                <label class="feedback-consent">
                    <input type="checkbox" name="publish_consent" value="1" required @checked(old('publish_consent'))>
                    <span>I understand my name, rating, and comment may be published exactly as submitted. Carolina staff may publish or hide my review, but cannot edit it.</span>
                </label>
                @error('publish_consent')<p class="feedback-error">Please confirm before submitting.</p>@enderror
                <button class="button feedback-submit" type="submit">Submit feedback</button>
            </form>
            <p style="font-size:.84rem;color:var(--muted);margin-top:18px">This private link expires 30 days after checkout. No account is needed.</p>
        @endif
    </div>
</section>
@endsection
