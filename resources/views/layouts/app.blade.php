<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Carolina Transient & Airbnb | Tabaco City')</title>
    <meta name="description" content="Reserve short and overnight stays with Carolina Transient & Airbnb in Tabaco City, Albay.">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website"><meta property="og:title" content="@yield('title', 'Carolina Transient & Airbnb | Tabaco City')"><meta property="og:description" content="Reserve short and overnight stays in Tabaco City, Albay."><meta property="og:url" content="{{ url()->current() }}"><meta property="og:image" content="{{ asset('images/carolina-logo.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'LodgingBusiness', 'name' => 'Carolina Transient & Airbnb', 'url' => url('/'), 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Tabaco City', 'addressRegion' => 'Albay', 'addressCountry' => 'PH'] ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
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
    <link rel="stylesheet" href="{{ asset('css/loading.css') }}?v={{ filemtime(public_path('css/loading.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/footer-apple.css') }}?v={{ filemtime(public_path('css/footer-apple.css')) }}">
    @stack('styles')
</head>
<body>
    <div class="page-loader" id="page-loader" role="status" aria-live="polite" aria-label="Loading">
        <div class="page-loader-card"><div class="loader-mark" aria-hidden="true"></div><b id="page-loader-title">Preparing your stay</b><p id="page-loader-message">Just a moment…</p></div>
    </div>
    <div class="network-loader" id="network-loader" role="status" aria-live="polite"><span class="network-loader-dots" aria-hidden="true"><i></i><i></i><i></i></span><span>Updating availability</span></div>
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
    @stack('late-styles')
    @php($footerContact = \App\Models\BusinessContact::current())
    <footer id="contact" class="site-footer">
        <div class="footer-content">
            <section class="footer-brand">
                <a class="brand" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><span>Carolina <small>TRANSIENT & AIRBNB</small></span></a>
                <p>A thoughtful, comfortable stay in the heart of Tabaco City.</p>
                <a class="footer-primary-action" href="{{ route('rooms.index') }}">Explore rooms <span aria-hidden="true">→</span></a>
            </section>

            <nav class="footer-column footer-quick" aria-label="Footer navigation">
                <h2>Explore</h2>
                <a href="{{ route('rooms.index') }}">Rooms</a>
                <a href="{{ route('bookings.lookup') }}">Find booking</a>
                <a href="{{ route('home') }}#about">About</a>
            </nav>

            <section class="footer-column footer-contact">
                <h2>Get in touch</h2>
                <p class="footer-location"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"></path><circle cx="12" cy="9" r="2.25"></circle></svg>Tabaco City, Albay</p>
                @if($footerContact?->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $footerContact->phone) }}">{{ $footerContact->phone }}</a>@endif
                @if($footerContact?->email)<a href="mailto:{{ $footerContact->email }}">{{ $footerContact->email }}</a>@endif
                <a class="footer-social" href="{{ $footerContact?->facebook_url ?: 'https://www.facebook.com/profile.php?id=61556306344437' }}" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h3V4.4c-.5-.1-1.8-.2-3.3-.2-3.4 0-5.7 2.1-5.7 5.9V13H4.3v4h3.8v5h4.7v-5h3.7l.6-4h-4.3v-2.5c0-1.2.3-2.5 2.2-2.5Z"></path></svg><span>Message us on Facebook</span></a>
            </section>
        </div>
        <div class="footer-bottom">
            <small>&copy; {{ date('Y') }} Carolina Transient & Airbnb</small>
            <div class="footer-bottom-actions">
                <a href="{{ route('privacy') }}">Privacy Policy</a><a href="{{ route('terms') }}">Terms of Service</a>
            </div>
        </div>
    </footer>
    <script>
        (() => {
            const loader = document.getElementById('page-loader');
            const title = document.getElementById('page-loader-title');
            const message = document.getElementById('page-loader-message');
            const network = document.getElementById('network-loader');
            let networkRequests = 0;
            let networkTimer;
            const show = (heading = 'Preparing your stay', detail = 'Just a moment…') => { title.textContent = heading; message.textContent = detail; loader.classList.add('is-visible'); };
            const hide = () => loader.classList.remove('is-visible');
            window.CarolinaLoading = { show, hide };
            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (!link || link.target === '_blank' || link.hasAttribute('download') || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                const destination = new URL(link.href, window.location.href);
                if (destination.origin !== window.location.origin || destination.href === window.location.href || destination.hash && destination.pathname === window.location.pathname) return;
                show('Opening your next step', 'Getting everything ready…');
            });
            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || form.dataset.noLoading !== undefined) return;
                const submitter = event.submitter;
                const copy = submitter?.dataset.loadingText || (form.matches('.booking-form form') ? 'Saving your reservation…' : 'Saving your changes…');
                show('Please wait', copy);
            });
            const originalFetch = window.fetch.bind(window);
            window.fetch = (...args) => {
                networkRequests += 1;
                clearTimeout(networkTimer);
                networkTimer = setTimeout(() => { if (networkRequests) network.classList.add('is-visible'); }, 220);
                return originalFetch(...args).finally(() => { networkRequests = Math.max(0, networkRequests - 1); if (!networkRequests) { clearTimeout(networkTimer); network.classList.remove('is-visible'); } });
            };
            window.addEventListener('pageshow', hide);
        })();

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
