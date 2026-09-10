<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Carolina Transient & Airbnb | Tabaco City')</title>
    <meta name="description" content="Reserve short and overnight stays with Carolina Transient & Airbnb in Tabaco City, Albay.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script>
        if (localStorage.getItem('carolina-theme') === 'dark' || (!localStorage.getItem('carolina-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
</head>
<body>
    <header class="nav">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo" src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><span>Carolina <small>TRANSIENT & AIRBNB</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
            <span class="sr-only">Open navigation menu</span>
            <span></span><span></span><span></span>
        </button>
        <nav id="primary-navigation">
            <a href="{{ route('rooms.index') }}">Rooms</a>
            <a href="{{ route('bookings.lookup') }}">Find booking</a>
            <a href="{{ route('home') }}#about">About</a>
            <a href="#contact">Contact</a>
            @auth
                <a href="{{ route('bookings.index') }}">My bookings</a>
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.dashboard') }}">Admin</a>
                @endif
                <button class="theme-toggle nav-theme-toggle" type="button" aria-label="Enable dark mode" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"></svg>
                    <span>Dark mode</span>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="nav-button" type="submit">Sign out</button>
                </form>
            @else
                <button class="theme-toggle nav-theme-toggle" type="button" aria-label="Enable dark mode" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"></svg>
                    <span>Dark mode</span>
                </button>
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

            <section class="footer-column footer-quick">
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
                <a href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">●</span> Facebook</a>
            </section>

            <section class="footer-column footer-follow">
                <h2>Follow us</h2>
                <a class="social-link" href="https://www.facebook.com/profile.php?id=61556306344437" target="_blank" rel="noopener noreferrer" aria-label="Follow Carolina Transient & Airbnb on Facebook">f</a>
                <p>Stay connected<br>for updates!</p>
            </section>
        </div>
        <div class="footer-bottom">
            <small>&copy; {{ date('Y') }} Carolina Transient & Airbnb. All rights reserved.</small>
            <div class="footer-bottom-actions">
                <a href="{{ route('privacy') }}">Privacy Policy</a><a href="{{ route('terms') }}">Terms of Service</a>
            </div>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('.menu-toggle');
            const navigation = document.querySelector('#primary-navigation');
            const themeToggle = document.querySelector('.theme-toggle');
            const setThemeToggle = () => {
                const dark = document.documentElement.classList.contains('dark-mode');
                themeToggle?.setAttribute('aria-pressed', String(dark));
                themeToggle?.setAttribute('aria-label', dark ? 'Enable light mode' : 'Enable dark mode');
                if (themeToggle) {
                    themeToggle.querySelector('span').textContent = dark ? 'Light mode' : 'Dark mode';
                    themeToggle.querySelector('svg').innerHTML = dark
                        ? '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>'
                        : '<path d="M20.6 15.3A8.3 8.3 0 0 1 8.7 3.4 8.4 8.4 0 1 0 20.6 15.3Z"></path>';
                }
            };

            setThemeToggle();
            themeToggle?.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark-mode');
                localStorage.setItem('carolina-theme', document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light');
                setThemeToggle();
            });

            document.querySelectorAll('.password-toggle').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = button.closest('.password-field')?.querySelector('input');
                    if (!input) return;
                    const visible = input.type === 'text';
                    input.type = visible ? 'password' : 'text';
                    button.textContent = visible ? 'Show' : 'Hide';
                    button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
                    button.setAttribute('aria-pressed', String(!visible));
                });
            });

            if (!toggle || !navigation) return;

            toggle.addEventListener('click', () => {
                const open = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!open));
                navigation.classList.toggle('is-open', !open);
                document.body.classList.toggle('menu-open', !open);
            });

            navigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
                toggle.setAttribute('aria-expanded', 'false');
                navigation.classList.remove('is-open');
                document.body.classList.remove('menu-open');
            }));
        });
    </script>
</body>
</html>
