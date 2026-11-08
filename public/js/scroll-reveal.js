(() => {
    const targets = document.querySelectorAll('.home-rooms .room-card, .rooms-page .rooms-card, .ber-promo');
    if (!targets.length || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    let lastScrollY = window.scrollY;
    let scrollDirection = 'down';

    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        if (currentScrollY !== lastScrollY) scrollDirection = currentScrollY > lastScrollY ? 'down' : 'up';
        lastScrollY = currentScrollY;
    }, { passive: true });

    const observer = new IntersectionObserver(entries => {
        entries.forEach((entry) => {
            const target = entry.target;

            if (entry.isIntersecting) {
                target.dataset.motionState = 'visible';
                target.dataset.motionSeen = 'true';
                target.classList.add('is-motion-visible');
                return;
            }

            if (target.dataset.motionSeen === 'true') {
                target.dataset.motionState = scrollDirection === 'down' ? 'above' : 'below';
                target.classList.remove('is-motion-visible');
            } else {
                target.dataset.motionState = target.getBoundingClientRect().top < 0 ? 'above' : 'below';
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });

    targets.forEach((target) => {
        target.classList.add('motion-reveal');
        observer.observe(target);
    });
})();
