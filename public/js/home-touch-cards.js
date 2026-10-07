(() => {
    if (!window.matchMedia('(any-pointer: coarse)').matches ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const cardSelector = '.home-availability, .ber-promo-home, .home-lookup .empty-state, .home-room-carousel .room-card, .home-benefits .benefit-grid > div';
    let activeCard = null;
    let pointerId = null;
    let startX = 0;
    let startY = 0;
    let releaseTimer;
    let pendingCard = null;

    const release = (immediate = false) => {
        clearTimeout(releaseTimer);
        pendingCard?.classList.remove('is-touch-pressed');
        pendingCard = null;
        if (!activeCard) return;
        const card = activeCard;
        activeCard = null;
        pointerId = null;
        if (immediate) card.classList.remove('is-touch-pressed');
        else {
            pendingCard = card;
            releaseTimer = setTimeout(() => {
                card.classList.remove('is-touch-pressed');
                pendingCard = null;
            }, 130);
        }
    };

    document.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' || !event.isPrimary) return;
        const card = event.target.closest(cardSelector);
        if (!card) return;
        release(true);
        activeCard = card;
        pointerId = event.pointerId;
        startX = event.clientX;
        startY = event.clientY;
        card.classList.add('is-touch-pressed');
    }, { passive: true });

    document.addEventListener('pointermove', (event) => {
        if (event.pointerId === pointerId && Math.hypot(event.clientX - startX, event.clientY - startY) > 12) release(true);
    }, { passive: true });
    document.addEventListener('pointerup', (event) => {
        if (event.pointerId === pointerId) release();
    }, { passive: true });
    document.addEventListener('pointercancel', (event) => {
        if (event.pointerId === pointerId) release(true);
    }, { passive: true });
    window.addEventListener('scroll', () => release(true), { passive: true });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) release(true);
    });
})();
