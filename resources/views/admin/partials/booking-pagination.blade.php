@php
    $currentPage = $individualBookings->currentPage();
    $lastPage = $individualBookings->lastPage();
    $firstVisiblePage = max(1, min($currentPage - 2, $lastPage - 4));
    $lastVisiblePage = min($lastPage, $firstVisiblePage + 4);
@endphp
<nav class="booking-pagination" aria-label="Booking page navigation">
    <div class="booking-pagination-buttons">
        @if($currentPage > 1)
            <a href="{{ $individualBookings->url(1) }}#booking-results">First</a>
            <a href="{{ $individualBookings->previousPageUrl() }}#booking-results">Previous</a>
        @else
            <span class="is-disabled" aria-disabled="true">First</span>
            <span class="is-disabled" aria-disabled="true">Previous</span>
        @endif

        @for($page = $firstVisiblePage; $page <= $lastVisiblePage; $page++)
            @if($page === $currentPage)
                <span class="is-current" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $individualBookings->url($page) }}#booking-results" aria-label="Booking page {{ $page }}">{{ $page }}</a>
            @endif
        @endfor

        @if($individualBookings->hasMorePages())
            <a href="{{ $individualBookings->nextPageUrl() }}#booking-results">Next</a>
            <a href="{{ $individualBookings->url($lastPage) }}#booking-results">Last</a>
        @else
            <span class="is-disabled" aria-disabled="true">Next</span>
            <span class="is-disabled" aria-disabled="true">Last</span>
        @endif
    </div>
</nav>
