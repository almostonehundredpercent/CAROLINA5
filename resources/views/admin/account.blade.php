<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account settings · Carolina Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-account.css') }}?v={{ filemtime(public_path('css/admin-account.css')) }}">
</head>
<body>
<div class="admin-shell">
    @include('admin.partials.sidebar')
    <main class="admin-main account-page">
        <header class="admin-topbar">
            <div>
                <p class="admin-kicker">SECURITY &amp; PROFILE</p>
                <h1>Account settings</h1>
                <p class="admin-subtitle">Change the sign-in email and password for your own staff account.</p>
            </div>
            <div class="admin-user"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>{{ \App\Support\AdminPermissions::role(auth()->user()) === 'organization_admin' ? 'Organization Admin' : 'Staff account' }}</small></div></div>
        </header>

        @include('admin.partials.flash')

        <section class="account-settings-grid" aria-label="Account controls">
            <article class="panel account-settings-card">
                <div class="account-card-heading">
                    <span class="account-card-icon" aria-hidden="true">@include('admin.partials.sidebar-icon', ['route' => 'admin.account'])</span>
                    <div><p class="admin-kicker">PERSONAL DETAILS</p><h2>Sign-in information</h2></div>
                </div>
                <p class="account-card-copy">Update the name and sign-in email for this staff account. Changing your email requires your current password and sends a verification link to the new address.</p>

                <form class="account-settings-form" method="POST" action="{{ route('admin.account.email') }}">
                    @csrf
                    @method('PATCH')
                    <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
                    <label>Full name<input name="name" type="text" value="{{ old('name', auth()->user()->name) }}" autocomplete="name" maxlength="255" required></label>
                    <label>Email address<input name="email" type="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" maxlength="255" required></label>
                    <div class="account-verification {{ auth()->user()->hasVerifiedEmail() ? 'is-verified' : 'is-unverified' }}">
                        <span aria-hidden="true">{{ auth()->user()->hasVerifiedEmail() ? '✓' : '!' }}</span>
                        <p>Email is {{ auth()->user()->hasVerifiedEmail() ? 'verified' : 'not verified' }}. @if(auth()->user()->hasVerifiedEmail()) Changing it sends a new verification link. @else Verify your current address to restore the verified status. @endif</p>
                    </div>
                    <button class="account-primary" type="submit">Save email and name</button>
                </form>
            </article>

            @if(auth()->user()->isAdmin())
                <article class="panel account-settings-card">
                    <div class="account-card-heading">
                        <span class="account-card-icon" aria-hidden="true">✉</span>
                        <div><p class="admin-kicker">ROOM RATE APPROVALS</p><h2>Approval inbox</h2></div>
                    </div>
                    <p class="account-card-copy">New room-rate change requests will be sent here for owner approval. Requests already submitted keep the email address recorded when they were created.</p>

                    <form class="account-settings-form" method="POST" action="{{ route('admin.account.rate-approval-email') }}">
                        @csrf
                        @method('PATCH')
                        <label>Rate approval email<input name="recipient_email" type="email" value="{{ old('recipient_email', $rateApprovalEmail) }}" autocomplete="email" maxlength="255" required></label>
                        <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
                        <button class="account-primary" type="submit">Save approval email</button>
                    </form>
                </article>
            @endif

            <article class="panel account-settings-card">
                <div class="account-card-heading">
                    <span class="account-card-icon" aria-hidden="true">⌑</span>
                    <div><p class="admin-kicker">PASSWORD</p><h2>Change password</h2></div>
                </div>
                <p class="account-card-copy">Use your current password to confirm this change. Your new password must be at least 12 characters; other active sessions will be signed out.</p>

                <form class="account-settings-form" method="POST" action="{{ route('admin.account.password') }}">
                    @csrf
                    @method('PATCH')
                    <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
                    <label>New password<input name="password" type="password" autocomplete="new-password" minlength="12" required></label>
                    <label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label>
                    <button class="account-primary" type="submit">Update password</button>
                </form>
            </article>
        </section>
    </main>
</div>
</body>
</html>
