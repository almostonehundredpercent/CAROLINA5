(() => {
    const root = document.documentElement;
    const switches = () => document.querySelectorAll('.theme-toggle[role="switch"], [data-admin-theme-toggle][role="switch"]');

    const syncTheme = () => {
        const dark = root.classList.contains('dark-mode');
        if (document.querySelector('.admin-shell')) document.body?.classList.toggle('admin-dark', dark);
        switches().forEach((control) => {
            control.setAttribute('aria-checked', String(dark));
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
