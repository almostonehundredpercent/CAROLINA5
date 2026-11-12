@php
    $adminNavigation = collect(\App\Support\AdminPermissions::navigation(auth()->user()))
        ->filter(fn ($item) => \App\Support\AdminPermissions::allows(auth()->user(), $item['ability']));
@endphp
<style>
    .admin-nav a > .admin-nav-icon {
        display: inline-grid !important;
        width: 19px !important;
        height: 19px !important;
        flex: 0 0 19px !important;
        place-items: center;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    .admin-nav-icon svg {
        display: block;
        width: 18px;
        height: 18px;
        fill: currentColor;
    }
</style>
<aside class="admin-sidebar">
    <a class="admin-brand" href="{{ route('home') }}"><img src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><b>Carolina</b><small>TRANSIENT & AIRBNB</small></a>
    <nav class="admin-nav" aria-label="Staff navigation">
        @foreach($adminNavigation as $item)
            <a class="{{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">@include('admin.partials.sidebar-icon', ['route' => $item['route']]){{ $item['label'] }}</a>
        @endforeach
    </nav>
    <button class="admin-theme-toggle" type="button" data-admin-theme-toggle role="switch" aria-label="Dark mode" aria-checked="false">
        <span data-admin-theme-label>Dark mode</span>
        <span class="theme-switch-track" aria-hidden="true"><span class="theme-switch-thumb"><svg viewBox="0 0 24 24"></svg></span></span>
    </button>
    <form method="POST" action="{{ route('logout') }}" class="admin-logout">@csrf<button class="logout-button" type="submit"><i aria-hidden="true">⎋</i>Log out</button></form>
</aside>
<script>
    (() => {
        const root = document.documentElement;
        const saved = localStorage.getItem('carolina-theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark-mode');
        else root.classList.remove('dark-mode');
    })();
</script>
<script src="{{ asset('js/theme-switch.js') }}?v={{ filemtime(public_path('js/theme-switch.js')) }}"></script>
<script src="{{ asset('js/admin-mobile-tables.js') }}?v={{ filemtime(public_path('js/admin-mobile-tables.js')) }}" defer></script>
