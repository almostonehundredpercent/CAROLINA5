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
    <footer id="contact">
        <div class="brand"><img class="brand-logo" src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><span>Carolina <small>TRANSIENT & AIRBNB</small></span></div>
        <p>Affordable lodging made easy in Tabaco City.</p>
        <p>Questions? Contact our front desk anytime.</p>
        <small>&copy; {{ date('Y') }} Carolina Transient & Airbnb</small>
    </footer>
</body>
</html>
