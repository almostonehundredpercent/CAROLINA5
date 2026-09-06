<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Carolina') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
</head>
<body>
    <header class="nav">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><span>Carolina <small>TRANSIENT & AIRBNB</small></span>
        </a>
        <nav>
            <a href="{{ route('rooms.index') }}">Rooms</a>
            <a href="{{ route('bookings.lookup') }}">Find booking</a>
            <a href="{{ route('home') }}#about">About</a>
            <a href="#contact">Contact</a>

            @auth
                <a href="{{ route('bookings.index') }}">My bookings</a>
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="nav-button" type="submit">Sign out</button>
                </form>
            @else
                <a class="nav-button" href="{{ route('login') }}">Sign in</a>
            @endauth
        </nav>
    </header>
    <main>
        @if(session('success'))
            <div class="flash success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="flash error">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>
    <footer id="contact" class="site-footer">
        <div class="footer-content">
            <section class="footer-brand">
                <a class="brand" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><span>Carolina <small>TRANSIENT & AIRBNB</small></span></a>
                <p>Comfortable stays in the heart of Tabaco City.</p>
            </section>

            <section class="footer-column">
                <h2>Quick links</h2>
                <a href="{{ route('rooms.index') }}">Rooms</a>
                <a href="{{ route('bookings.lookup') }}">Find booking</a>
                <a href="{{ route('home') }}#about">About</a>
                <a href="#contact">Contact</a>
            </section>

            <section class="footer-column footer-contact">
                <h2>Contact</h2>
                <p><span aria-hidden="true">⌖</span> Tabaco City, Albay</p>
                <a href="{{ route('bookings.lookup') }}"><span aria-hidden="true">⌕</span> Find your booking</a>
                <a href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">●</span> Message Carolina on Facebook</a>
            </section>

            <section class="footer-column footer-follow">
                <h2>Follow us</h2>
                <a class="social-link" href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer" aria-label="Follow Carolina Transient & Airbnb on Facebook">f</a>
                <p>Stay connected<br>for updates!</p>
            </section>
        </div>
        <div class="footer-bottom">
            <small>&copy; {{ date('Y') }} Carolina Transient & Airbnb. All rights reserved.</small>
            <div><a href="#">Privacy Policy</a><a href="#">Terms of Service</a></div>
        </div>
    </footer>
</body>
</html>
