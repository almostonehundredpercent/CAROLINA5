document.addEventListener('DOMContentLoaded', () => {
    const data = document.getElementById('room-gallery-data');
    const originalImage = document.querySelector('.room-detail > img');

    if (!data || !originalImage) return;
    const roomDetail = originalImage.parentNode;

    let roomGallery;
    try {
        roomGallery = JSON.parse(data.textContent);
    } catch {
        return;
    }

    if (!Array.isArray(roomGallery.images) || roomGallery.images.length < 2) return;

    const gallery = document.createElement('div');
    gallery.className = 'room-gallery';
    gallery.tabIndex = 0;
    gallery.setAttribute('role', 'region');
    gallery.setAttribute('aria-label', `${roomGallery.room} photo gallery`);

    const stage = document.createElement('div');
    stage.className = 'room-gallery-stage';
    const count = document.createElement('span');
    count.className = 'room-gallery-count';
    count.setAttribute('aria-live', 'polite');
    count.setAttribute('aria-atomic', 'true');

    const makeArrow = (direction, label, glyph) => {
        const button = document.createElement('button');
        button.className = `room-gallery-arrow room-gallery-${direction}`;
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.textContent = glyph;
        return button;
    };

    const previous = makeArrow('previous', 'Previous photo', '‹');
    const next = makeArrow('next', 'Next photo', '›');
    originalImage.classList.add('room-gallery-main-image');
    originalImage.loading = 'eager';
    originalImage.decoding = 'async';
    const thumbnails = document.createElement('div');
    thumbnails.className = 'room-gallery-thumbnails';
    thumbnails.setAttribute('role', 'group');
    thumbnails.setAttribute('aria-label', `${roomGallery.room} photos`);

    const thumbnailButtons = roomGallery.images.map((src, index) => {
        const button = document.createElement('button');
        button.className = 'room-gallery-thumbnail';
        button.type = 'button';
        button.setAttribute('aria-label', `Show photo ${index + 1} of ${roomGallery.images.length}`);
        button.setAttribute('aria-pressed', 'false');

        const thumbnail = document.createElement('img');
        thumbnail.src = src;
        thumbnail.alt = '';
        thumbnail.loading = 'lazy';
        thumbnail.decoding = 'async';
        button.append(thumbnail);
        button.addEventListener('click', () => show(index));
        thumbnails.append(button);

        return button;
    });

    let activeIndex = 0;
    const show = (index) => {
        activeIndex = Math.max(0, Math.min(index, roomGallery.images.length - 1));
        originalImage.src = roomGallery.images[activeIndex];
        originalImage.alt = `${roomGallery.room} photo ${activeIndex + 1} of ${roomGallery.images.length}`;
        count.textContent = `${activeIndex + 1} / ${roomGallery.images.length}`;
        previous.disabled = activeIndex === 0;
        next.disabled = activeIndex === roomGallery.images.length - 1;
        thumbnailButtons.forEach((button, thumbnailIndex) => {
            button.setAttribute('aria-pressed', String(thumbnailIndex === activeIndex));
        });
    };

    previous.addEventListener('click', () => show(activeIndex - 1));
    next.addEventListener('click', () => show(activeIndex + 1));
    gallery.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' && activeIndex > 0) show(activeIndex - 1);
        if (event.key === 'ArrowRight' && activeIndex < roomGallery.images.length - 1) show(activeIndex + 1);
    });

    let touchStartX = null;
    stage.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, { passive: true });
    stage.addEventListener('touchend', (event) => {
        const touchEndX = event.changedTouches[0]?.clientX;
        if (touchStartX === null || touchEndX === undefined) return;
        const distance = touchEndX - touchStartX;
        if (Math.abs(distance) > 45) show(activeIndex + (distance < 0 ? 1 : -1));
        touchStartX = null;
    }, { passive: true });

    roomDetail.replaceChild(gallery, originalImage);
    stage.append(originalImage, previous, next, count);
    gallery.append(stage, thumbnails);
    show(0);
});
