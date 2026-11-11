document.addEventListener('click', (event) => {
    const backLink = event.target.closest('[data-room-detail-back]');

    if (!backLink || !document.referrer || window.history.length < 2) return;

    try {
        const previousPage = new URL(document.referrer, window.location.href);
        if (previousPage.origin !== window.location.origin) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        window.history.back();
    } catch {
        // Keep the room catalogue link as the fallback if referrer parsing fails.
    }
}, true);
