(() => {
    const root = document.documentElement;
    const switches = () => document.querySelectorAll('.theme-toggle[role="switch"], [data-admin-theme-toggle][role="switch"]');

    const syncTheme = () => {
        const dark = root.classList.contains('dark-mode');
        if (document.querySelector('.admin-shell')) document.body?.classList.toggle('admin-dark', dark);
        switches().forEach((control) => {
            control.setAttribute('aria-checked', String(dark));
            const icon = control.querySelector('.theme-switch-thumb svg');
            if (icon) {
                icon.innerHTML = dark
                    ? '<path d="M12 3.5a8.5 8.5 0 1 0 8.5 8.5A8.5 8.5 0 0 1 12 3.5Z"></path>'
                    : '<circle cx="12" cy="12" r="3.5"></circle><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>';
            }
        });
    };

    const setTheme = (dark) => {
        root.classList.toggle('dark-mode', dark);
        try {
            localStorage.setItem('carolina-theme', dark ? 'dark' : 'light');
        } catch (_) {
            // Keep the switch usable if browser storage is unavailable.
        }
        syncTheme();
        window.dispatchEvent(new CustomEvent('carolina:themechange', { detail: { theme: dark ? 'dark' : 'light' } }));
    };

    const initialize = () => {
        syncTheme();
        switches().forEach(control => control.addEventListener('click', () => {
            setTheme(!root.classList.contains('dark-mode'));
        }));
        window.addEventListener('storage', (event) => {
            if (event.key !== 'carolina-theme') return;
            root.classList.toggle('dark-mode', event.newValue === 'dark');
            syncTheme();
            window.dispatchEvent(new CustomEvent('carolina:themechange', { detail: { theme: event.newValue === 'dark' ? 'dark' : 'light' } }));
        });
        window.addEventListener('carolina:themechange', syncTheme);
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
