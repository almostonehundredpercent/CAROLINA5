@php
    $adminNavigation = collect(\App\Support\AdminPermissions::navigation(auth()->user()))
        ->filter(fn ($item) => \App\Support\AdminPermissions::allows(auth()->user(), $item['ability']));
    $primaryNavigation = $adminNavigation->where('group', 'primary');
    $moreNavigation = $adminNavigation->where('group', 'more');
    $moreIsActive = $moreNavigation->contains(fn ($item) => request()->routeIs($item['route']));
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
        @foreach($primaryNavigation as $item)
            <a class="{{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">@include('admin.partials.sidebar-icon', ['route' => $item['route']]){{ $item['label'] }}</a>
        @endforeach
        @if($moreNavigation->isNotEmpty())
            <details class="admin-more" {{ $moreIsActive ? 'open' : '' }}>
                <summary>More <span aria-hidden="true">⌄</span></summary>
                <div class="admin-more-links">
                    @foreach($moreNavigation as $item)
                        <a class="{{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">@include('admin.partials.sidebar-icon', ['route' => $item['route']]){{ $item['label'] }}</a>
                    @endforeach
                </div>
            </details>
        @endif
    </nav>
    <button class="admin-theme-toggle" type="button" data-admin-theme-toggle aria-pressed="false">
        <span aria-hidden="true">◐</span><span data-admin-theme-label>Dark mode</span>
    </button>
    <a href="{{ route('home') }}" class="admin-home"><i aria-hidden="true">⌂</i>Go to home page</a>
    <form method="POST" action="{{ route('logout') }}" class="admin-logout">@csrf<button class="logout-button" type="submit"><i aria-hidden="true">⎋</i>Log out</button></form>
</aside>
<script>
    (() => {
        const root = document.documentElement;
        const toggle = document.querySelector('[data-admin-theme-toggle]');
        const label = document.querySelector('[data-admin-theme-label]');
        const sync = () => {
            const dark = root.classList.contains('dark-mode');
            toggle?.setAttribute('aria-pressed', String(dark));
            if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
        };
        const saved = localStorage.getItem('carolina-theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark-mode');
        else root.classList.remove('dark-mode');
        sync();
        toggle?.addEventListener('click', () => {
            root.classList.toggle('dark-mode');
            localStorage.setItem('carolina-theme', root.classList.contains('dark-mode') ? 'dark' : 'light');
            sync();
        });
    })();
</script>
