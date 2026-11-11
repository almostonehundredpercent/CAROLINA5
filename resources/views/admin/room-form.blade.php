<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->exists ? 'Edit room' : 'Add room' }} · Carolina</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <style>
        .room-editor{display:grid;gap:18px;max-width:1000px}.room-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.room-form label,.rate-request-form label{display:grid;gap:6px;font-size:12px;font-weight:700;color:var(--muted)}.room-form input,.room-form textarea,.room-form select,.rate-request-form input,.rate-request-form textarea{width:100%;font:inherit;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:9px;padding:11px}.room-form textarea,.room-form .full,.rate-request-form .full{grid-column:1/-1}.room-form textarea,.rate-request-form textarea{min-height:105px;resize:vertical}.room-form button,.rate-request-form button,.rate-apply-button{min-height:42px;border:0;border-radius:9px;background:var(--orange);color:#fff;padding:0 16px;font-weight:750;cursor:pointer}.room-form .check{display:flex;align-items:center;gap:8px;color:var(--ink)}.room-form .check input{width:auto}.rate-lock{display:grid;gap:5px;padding:14px 16px;border:1px solid #9b7135;border-radius:12px;background:linear-gradient(135deg,#fff6e6,#fffdf8);color:#3f2b16}.rate-lock small{color:#775a37}.rate-lock strong{font-size:1.1rem}.rate-workflow{padding:18px;border:1px solid var(--line);border-radius:14px;background:var(--paper)}.rate-workflow h2{margin:0 0 6px}.rate-workflow>p{margin:0 0 16px;color:var(--muted);line-height:1.5}.rate-request-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.rate-request-form .full{grid-column:1/-1}.rate-history{display:grid;gap:10px;margin-top:18px}.rate-history-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.55)}.rate-history-item p{margin:4px 0;color:var(--muted);font-size:.88rem;line-height:1.45}.rate-status{display:inline-flex;padding:4px 9px;border-radius:99px;background:#efe5d7;color:#593b18;font-size:.75rem;font-weight:800;text-transform:capitalize}.rate-status.approved{background:#e1f4e7;color:#166534}.rate-status.rejected,.rate-status.expired,.rate-status.email_failed{background:#fbe3e0;color:#a84336}.rate-apply-form{margin:0}.rate-subtle{font-size:.8rem;color:var(--muted)}.admin-flash{margin:0 0 14px;padding:12px 14px;border-radius:10px;background:#e5f5e8;color:#176238}.admin-error{margin:0 0 14px;padding:12px 14px;border:1px solid #e5a29a;border-radius:10px;background:#fbe3e0;color:#92342b}.room-form-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:stretch}.room-form-actions button,.room-form-actions a{display:inline-flex;width:100%;min-height:44px;align-items:center;justify-content:center;border-radius:9px;padding:0 16px;font-weight:750;text-decoration:none}.room-form-actions button{border:1px solid #d98200;background:#e88b00;color:#21160b}.room-form-actions a{border:1px solid #765a40;background:#2b211b;color:#fff0dd}.room-form-actions a:hover{background:#382a21;border-color:#ac7a36}.html-light .room-form-actions a,html:not(.dark-mode) .room-form-actions a{border-color:#d4b58b;background:#fff7eb;color:#68420f}.html-light .room-form-actions a:hover,html:not(.dark-mode) .room-form-actions a:hover{background:#ffeed4;border-color:#c88731}
        @media(max-width:700px){.room-form,.rate-request-form{grid-template-columns:1fr}.room-form .full,.rate-request-form .full{grid-column:auto}.rate-history-item{grid-template-columns:1fr}.rate-apply-button{width:100%}.room-form-actions{grid-template-columns:1fr}.room-form-actions button,.room-form-actions a{min-height:48px}}
    </style>
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main">
        <header class="admin-topbar"><div><p class="admin-kicker">ROOM CATALOG</p><h1>{{ $room->exists ? 'Edit room' : 'Add room' }}</h1><p class="admin-subtitle">Room details are editable here. Existing room rates use owner approval.</p></div></header>
        @if(session('success'))<div class="admin-flash" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="admin-error" role="alert">{{ $errors->first() }}</div>@endif

        <div class="room-editor">
            <section class="panel" aria-label="Room details">
                <form class="room-form" method="POST" action="{{ $action }}" enctype="multipart/form-data">
                    @csrf @if($method === 'PATCH')@method('PATCH')@endif
                    <label>Room name<input name="name" required value="{{ old('name', $room->name) }}"></label>
                    <label>URL slug<input name="slug" required pattern="[a-z0-9-]+" value="{{ old('slug', $room->slug) }}" aria-describedby="slug-help"></label>
                    <small id="slug-help">Lowercase letters, numbers, and hyphens only.</small>
                    <label>Room type<input name="room_type" required value="{{ old('room_type', $room->room_type) }}"></label>
                    <label>Maximum guests<input type="number" name="guests" min="1" max="30" required value="{{ old('guests', $room->guests ?: 1) }}"></label>
                    <label>Beds<input type="number" name="beds" min="1" max="20" required value="{{ old('beds', $room->beds ?: 1) }}"></label>
                    @if($room->exists)
                        <div class="rate-lock" role="note"><small>LOCKED BASE RATE · OWNER APPROVAL REQUIRED</small><strong>₱{{ number_format((float) $room->price_per_night, 2) }} {{ $room->rate_label }}</strong><small>Rate changes cannot be submitted through this room details form.</small></div>
                    @else
                        <label>Base price (₱)<input type="number" name="price_per_night" min="0" step="0.01" required value="{{ old('price_per_night', $room->price_per_night) }}"></label>
                    @endif
                    <label>Short-stay duration (optional)<select name="rental_hours"><option value="">Nightly stay</option>@foreach([3,6,12,22,24] as $hours)<option value="{{ $hours }}" @selected((int) old('rental_hours', $room->rental_hours) === $hours)>{{ $hours }} hours</option>@endforeach</select></label>
                    <label>Default check-in time<input type="time" name="default_check_in_time" value="{{ old('default_check_in_time', $room->default_check_in_time ? substr($room->default_check_in_time, 0, 5) : '') }}"></label>
                    <label>Image URL (optional)<input type="url" name="image_url" value="{{ old('image_url', $room->image_url) }}"></label>
                    <label>Upload image (JPG, PNG, WebP; 5 MB max)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                    <label>Amenities, separated by commas<input name="amenities" value="{{ old('amenities', implode(', ', $room->amenities ?? [])) }}"></label>
                    <label class="full">Description<textarea name="description" required>{{ old('description', $room->description) }}</textarea></label>
                    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $room->exists ? $room->is_active : true))> Visible and bookable</label>
                    @if($room->image_url)<label class="check"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>@endif
                    <div class="room-form-actions full"><button type="submit">{{ $room->exists ? 'Save room details' : 'Add room' }}</button><a href="{{ route('admin.rooms') }}">Cancel</a></div>
                </form>
            </section>

            @if($room->exists)
                <section class="rate-workflow" aria-labelledby="rate-workflow-title">
                    <p class="admin-kicker">CONTROLLED RATE CHANGE</p>
                    <h2 id="rate-workflow-title">Request an owner-approved rate</h2>
                    <p>Submitting sends the owner a private approval link. The room rate stays unchanged until the owner approves and an Organization Admin applies it.</p>
                    @if($ownerEmailConfigured)
                        <form class="rate-request-form" method="POST" action="{{ route('admin.rooms.price-changes.store', $room) }}">
                            @csrf
                            <label>Proposed rate (₱)<input type="number" name="proposed_price" min="0.01" max="999999" step="0.01" required value="{{ old('proposed_price') }}"></label>
                            <label class="full">Reason for the change<textarea name="reason" minlength="10" maxlength="1000" required placeholder="Explain why the room rate should change.">{{ old('reason') }}</textarea></label>
                            <button class="full" type="submit">Email owner for approval</button>
                        </form>
                    @else
                        <p class="admin-error">Set <code>ROOM_PRICE_OWNER_ADDRESS</code> in the server environment before sending an approval request.</p>
                    @endif

                    @if($priceChanges->isNotEmpty())
                        <div class="rate-history" aria-label="Recent rate requests">
                            <p class="admin-kicker">RATE REQUEST HISTORY</p>
                            @foreach($priceChanges as $change)
                                <article class="rate-history-item">
                                    <div>
                                        <span class="rate-status {{ $change->status }}">{{ str_replace('_', ' ', $change->status) }}</span>
                                        <strong>{{ $change->reference }}</strong>
                                        <p>₱{{ number_format((float) $change->old_price, 2) }} → ₱{{ number_format((float) $change->proposed_price, 2) }} · {{ $change->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT<br>{{ $change->reason }}</p>
                                        <span class="rate-subtle">Owner record: {{ $change->owner_email }}@if($change->approved_at) · Approved {{ $change->approved_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT @endif @if($change->applied_at) · Applied {{ $change->applied_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT @endif</span>
                                    </div>
                                    @if($change->status === 'approved')
                                        <form class="rate-apply-form" method="POST" action="{{ route('admin.rooms.price-changes.apply', [$room, $change]) }}" onsubmit="return confirm('Apply the owner-approved rate to future bookings? Existing bookings will keep their saved totals.');">
                                            @csrf<button class="rate-apply-button" type="submit">Apply approved rate</button>
                                        </form>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </main>
</div>
</body>
</html>
