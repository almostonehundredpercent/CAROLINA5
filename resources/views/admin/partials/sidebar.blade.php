@php
    $adminNavigation = collect(\App\Support\AdminPermissions::navigation(auth()->user()))
        ->filter(fn ($item) => \App\Support\AdminPermissions::allows(auth()->user(), $item['ability']));
    $primaryNavigation = $adminNavigation->where('group', 'primary');
    $moreNavigation = $adminNavigation->where('group', 'more');
    $moreIsActive = $moreNavigation->contains(fn ($item) => request()->routeIs($item['route']));
@endphp
<aside class="admin-sidebar">
    <a class="admin-brand" href="{{ route('home') }}"><img src="{{ asset('images/carolina-logo.jpg') }}" alt="Carolina logo"><b>Carolina</b><small>TRANSIENT & AIRBNB</small></a>
    <nav class="admin-nav" aria-label="Staff navigation">
        @foreach($primaryNavigation as $item)
            <a class="{{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}"><i aria-hidden="true">{{ $item['icon'] }}</i>{{ $item['label'] }}</a>
        @endforeach
        @if($moreNavigation->isNotEmpty())
            <details class="admin-more" {{ $moreIsActive ? 'open' : '' }}>
                <summary>More <span aria-hidden="true">⌄</span></summary>
                <div class="admin-more-links">
                    @foreach($moreNavigation as $item)
                        <a class="{{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}"><i aria-hidden="true">{{ $item['icon'] }}</i>{{ $item['label'] }}</a>
                    @endforeach
                </div>
            </details>
        @endif
    </nav>
    <a href="{{ route('home') }}" class="admin-home"><i aria-hidden="true">⌂</i>Go to home page</a>
    <form method="POST" action="{{ route('logout') }}" class="admin-logout">@csrf<button class="logout-button" type="submit"><i aria-hidden="true">⎋</i>Log out</button></form>
</aside>
