<i class="admin-nav-icon" aria-hidden="true">
    @switch($route)
        @case('admin.frontdesk')
            <svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm1-15a1 1 0 1 0-2 0v5c0 .38.21.72.55.9l3.5 1.75a1 1 0 1 0 .9-1.8L13 11.38V7Z" clip-rule="evenodd"/></svg>
            @break
        @case('admin.bookings')
            <svg viewBox="0 0 24 24"><path d="M8 2a2 2 0 0 0-2 2H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3h-1a2 2 0 0 0-2-2H8Zm0 2h8v2H8V4Zm-2 6h2v2H6v-2Zm4 0h8v2h-8v-2Zm-4 4h2v2H6v-2Zm4 0h8v2h-8v-2Zm-4 4h2v2H6v-2Zm4 0h8v2h-8v-2Z"/></svg>
            @break
        @case('admin.rooms')
            <svg viewBox="0 0 24 24"><path d="M12.67 2.5a1 1 0 0 0-1.34 0l-9 8A1 1 0 0 0 3 12.25h1V20a2 2 0 0 0 2 2h4v-6h4v6h4a2 2 0 0 0 2-2v-7.75h1a1 1 0 0 0 .67-1.75l-9-8Z"/></svg>
            @break
        @case('admin.guests')
        @case('admin.staff')
            <svg viewBox="0 0 24 24"><path d="M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 19.5C2 15.91 5.36 13 9.5 13s7.5 2.91 7.5 6.5c0 .83-.67 1.5-1.5 1.5h-12c-.83 0-1.5-.67-1.5-1.5ZM17.5 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm.76 3.04c2.2.72 3.74 2.45 3.74 4.46 0 .83-.67 1.5-1.5 1.5H19c-.14-2.3-1.27-4.36-3.02-5.85.48-.1.98-.15 1.52-.15.26 0 .51.01.76.04Z"/></svg>
            @break
        @case('admin.dashboard')
            <svg viewBox="0 0 24 24"><path d="M4 2a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H4Zm10 2a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V4ZM4 14a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H4Zm10 2a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-4Z"/></svg>
            @break
        @case('admin.reports')
            <svg viewBox="0 0 24 24"><path d="M4 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H4Zm3 16a1 1 0 0 1-2 0v-4a1 1 0 1 1 2 0v4Zm4 0a1 1 0 0 1-2 0V9a1 1 0 1 1 2 0v9Zm4 0a1 1 0 0 1-2 0v-6a1 1 0 1 1 2 0v6Zm4 0a1 1 0 0 1-2 0V6a1 1 0 1 1 2 0v12Z"/></svg>
            @break
        @case('admin.promos')
            <svg viewBox="0 0 24 24"><path d="M21 12.59 12.59 21a2 2 0 0 1-2.83 0L3 14.24A2 2 0 0 1 2.41 13V4a2 2 0 0 1 2-2h9a2 2 0 0 1 1.41.59L21 8.76a2.7 2.7 0 0 1 0 3.83ZM7.5 6A1.5 1.5 0 1 0 7.5 9 1.5 1.5 0 0 0 7.5 6Z"/></svg>
            @break
        @case('admin.activity')
            <svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm1-15a1 1 0 1 0-2 0v5c0 .38.21.72.55.9l3.5 1.75a1 1 0 1 0 .9-1.8L13 11.38V7Z" clip-rule="evenodd"/></svg>
            @break
        @default
            <svg viewBox="0 0 24 24"><path d="M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5Z"/></svg>
    @endswitch
</i>
