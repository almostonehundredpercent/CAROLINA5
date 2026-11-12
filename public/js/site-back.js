document.addEventListener('click', (event) => {
    const backLink = event.target.closest('[data-site-back]');

    if (!backLink || !document.referrer || window.history.length < 2) return;

    try {
        const previousPage = new URL(document.referrer, window.location.href);
        if (previousPage.origin !== window.location.origin) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        window.history.back();
    } catch {
        // Keep the explicit destination as the fallback if referrer parsing fails.
    }
}, true);
