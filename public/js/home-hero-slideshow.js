(() => {
    const carousel = document.querySelector('.home-hero-slideshow');
    const track = carousel?.querySelector('.home-hero-track');
    const slides = [...(track?.querySelectorAll('.home-hero-slide') || [])];
    if (!carousel || slides.length < 2) return;

    const controls = carousel.parentElement.querySelector('.home-hero-controls');
    const previous = controls?.querySelector('[data-hero-prev]');
    const next = controls?.querySelector('[data-hero-next]');
    const toggle = controls?.querySelector('[data-hero-toggle]');
    const indicators = [...(controls?.querySelectorAll('[data-hero-slide]') || [])];
    const status = controls?.querySelector('.home-hero-slide-status');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const interval = Math.max(4000, Number.parseInt(carousel.dataset.interval || '6500', 10));

    let activeIndex = Math.max(0, slides.findIndex(slide => slide.classList.contains('is-active')));
    let timer = null;
    let userPaused = false;
    let pointerInside = false;
    let keyboardInside = false;
    let heroVisible = true;

    const render = index => {
        activeIndex = (index + slides.length) % slides.length;
        track.style.transform = `translate3d(-${activeIndex * 100}%, 0, 0)`;
        const nextSlide = slides[(activeIndex + 1) % slides.length];
        if (nextSlide.loading === 'lazy') nextSlide.loading = 'eager';
        slides.forEach((slide, slideIndex) => {
            const active = slideIndex === activeIndex;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', String(!active));
        });
        indicators.forEach((indicator, slideIndex) => {
            if (slideIndex === activeIndex) indicator.setAttribute('aria-current', 'true');
            else indicator.removeAttribute('aria-current');
        });
        if (status) status.textContent = `Photo ${activeIndex + 1} of ${slides.length}`;
    };

    const syncRotation = () => {
        window.clearInterval(timer);
        timer = null;
        if (!userPaused && !pointerInside && !keyboardInside && heroVisible && !document.hidden && !reducedMotion.matches) {
            timer = window.setInterval(() => render(activeIndex + 1), interval);
        }
    };

    const updateMotionControls = () => {
        if (toggle) toggle.hidden = reducedMotion.matches;
        syncRotation();
    };

    previous?.addEventListener('click', () => { render(activeIndex - 1); syncRotation(); });
    next?.addEventListener('click', () => { render(activeIndex + 1); syncRotation(); });
    indicators.forEach(indicator => indicator.addEventListener('click', () => {
        render(Number.parseInt(indicator.dataset.heroSlide, 10));
        syncRotation();
    }));

    toggle?.addEventListener('click', () => {
        userPaused = !userPaused;
        toggle.setAttribute('aria-pressed', String(userPaused));
        toggle.setAttribute('aria-label', userPaused ? 'Resume automatic image rotation' : 'Pause automatic image rotation');
        toggle.title = userPaused ? 'Resume slideshow' : 'Pause slideshow';
        syncRotation();
    });

    controls?.addEventListener('pointerenter', () => { pointerInside = true; syncRotation(); });
    controls?.addEventListener('pointerleave', () => { pointerInside = false; syncRotation(); });
    controls?.addEventListener('focusin', () => { keyboardInside = true; syncRotation(); });
    controls?.addEventListener('focusout', event => {
        if (!controls.contains(event.relatedTarget)) {
            keyboardInside = false;
            syncRotation();
        }
    });
    document.addEventListener('visibilitychange', syncRotation);
    reducedMotion.addEventListener?.('change', updateMotionControls);

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            heroVisible = entries.some(entry => entry.isIntersecting);
            syncRotation();
        }, { threshold: 0.08 });
        observer.observe(carousel);
    }

    render(activeIndex);
    updateMotionControls();
})();
