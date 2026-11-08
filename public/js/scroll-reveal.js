(() => {
    const targets = document.querySelectorAll('.home-rooms .room-card, .rooms-page .rooms-card');
    if (!targets.length || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const observer = new IntersectionObserver((entries, activeObserver) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-motion-visible');
            activeObserver.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

    targets.forEach((target) => {
        target.classList.add('motion-reveal');
        observer.observe(target);
    });
})();
