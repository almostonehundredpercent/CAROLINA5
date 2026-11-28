<i class="admin-nav-icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        @switch($route)
            @case('home')
                <path d="m3 11 9-8 9 8M5 10v10h5v-6h4v6h5V10"/>
                @break
            @case('admin.dashboard')
                <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
                @break
            @case('admin.frontdesk')
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                @break
            @case('admin.bookings')
                <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-12 5 2 2 4-4"/>
                @break
            @case('admin.reviews')
                <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
                @break
            @case('admin.rooms')
                <path d="M3 19V7m18 12v-5a2 2 0 0 0-2-2h-8v5M3 17h18"/><circle cx="7" cy="11" r="2"/>
                @break
            @case('admin.guests')
                <circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1m1-15a3 3 0 0 1 0 6m1 3a5 5 0 0 1 4 5v1"/>
                @break
            @case('admin.staff')
                <rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="8.5" cy="10" r="1.5"/><path d="M6 15a2.5 2.5 0 0 1 5 0m3-5h4m-4 4h4"/>
                @break
            @case('admin.reports')
                <path d="M4 4v16h16M8 16v-4m5 4V8m5 8V5"/>
                @break
            @case('admin.decision-support')
                <path d="M12 3a6 6 0 0 0-4 10.5c1.1 1 1.5 1.8 1.5 3.5h5c0-1.7.4-2.5 1.5-3.5A6 6 0 0 0 12 3Zm-2 17h4"/>
                @break
            @case('admin.promos')
                <path d="M3 4h8l9.4 9.4a1.8 1.8 0 0 1 0 2.5l-4.5 4.5a1.8 1.8 0 0 1-2.5 0L3 10V4Z"/><circle cx="7.5" cy="8" r="1"/>
                @break
            @case('admin.activity')
                <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6m-6 4h6m-6 4h4"/>
                @break
            @case('admin.account')
                <path d="M10 3h4l.5 2.6 1.7.9 2.5-.9 2 3.4-2 1.7v2.6l2 1.7-2 3.4-2.5-.9-1.7.9L14 21h-4l-.5-2.6-1.7-.9-2.5.9-2-3.4 2-1.7v-2.6L3.3 9l2-3.4 2.5.9 1.7-.9Z"/><circle cx="12" cy="12" r="3"/>
                @break
            @default
                <circle cx="12" cy="12" r="8"/><path d="M12 11v5m0-8h.01"/>
        @endswitch
    </svg>
</i>
