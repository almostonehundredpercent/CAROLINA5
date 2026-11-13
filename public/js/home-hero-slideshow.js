(() => {
    const carousel = document.querySelector('.home-hero-slideshow');
    const slides = [...(carousel?.querySelectorAll('.home-hero-slide') || [])];
    if (!carousel || slides.length < 2) return;

    const hero = carousel.closest('.home-hero');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Math.max(3000, Number.parseInt(carousel.dataset.interval || '6500', 10));

    let activeIndex = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
    let timer = null;
    let pointerInside = false;
    let keyboardInside = false;
    let heroVisible = true;

    const render = index => {
        activeIndex = (index + slides.length) % slides.length;
        const nextSlide = slides[(activeIndex + 1) % slides.length];
        if (nextSlide.loading === 'lazy') nextSlide.loading = 'eager';
        slides.forEach((slide, slideIndex) => {
            const active = slideIndex === activeIndex;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', String(!active));
        });
    };

    const syncRotation = () => {
        window.clearInterval(timer);
        timer = null;
        if (!pointerInside && !keyboardInside && heroVisible && !document.hidden && !reducedMotion.matches) {
            timer = window.setInterval(() => render(activeIndex + 1), interval);
        }
    };

    hero?.addEventListener('pointerenter', () => { pointerInside = true; syncRotation(); });
    hero?.addEventListener('pointerleave', () => { pointerInside = false; syncRotation(); });
    hero?.addEventListener('focusin', () => { keyboardInside = true; syncRotation(); });
    hero?.addEventListener('focusout', event => {
        if (!hero.contains(event.relatedTarget)) {
            keyboardInside = false;
            syncRotation();
        }
    });
    document.addEventListener('visibilitychange', syncRotation);
    reducedMotion.addEventListener?.('change', syncRotation);

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            heroVisible = entries.some(entry => entry.isIntersecting);
            syncRotation();
        }, { threshold: 0.08 });
        observer.observe(carousel);
    }

    render(activeIndex);
    syncRotation();
})();
