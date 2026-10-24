document.addEventListener('DOMContentLoaded', () => {
    const data = document.getElementById('room-gallery-data');
    const originalImage = document.querySelector('.room-detail > img');

    if (!data || !originalImage) return;

    let roomGallery;
    try {
        roomGallery = JSON.parse(data.textContent);
    } catch {
        return;
    }

    if (!Array.isArray(roomGallery.images) || roomGallery.images.length === 0) return;

    const roomDetail = originalImage.parentNode;
    const hasMultipleImages = roomGallery.images.length > 1;
    const gallery = document.createElement('div');
    gallery.className = 'room-gallery';
    if (!hasMultipleImages) gallery.classList.add('room-gallery--single');
    gallery.setAttribute('role', 'region');
    gallery.setAttribute('aria-label', `${roomGallery.room} photo gallery`);

    const stage = document.createElement('div');
    stage.className = 'room-gallery-stage';

    const openImage = document.createElement('button');
    openImage.className = 'room-gallery-open';
    openImage.type = 'button';
    openImage.setAttribute('aria-label', `Enlarge ${roomGallery.room} photo`);
    openImage.title = 'View larger photo';

    originalImage.classList.add('room-gallery-main-image');
    originalImage.loading = 'eager';
    originalImage.decoding = 'async';
    const lightbox = document.createElement('dialog');
    lightbox.className = 'room-lightbox';
    lightbox.setAttribute('aria-label', `${roomGallery.room} photos`);

    const toolbar = document.createElement('div');
    toolbar.className = 'room-lightbox-toolbar';

    const lightboxCount = document.createElement('span');
    lightboxCount.className = 'room-lightbox-count';
    lightboxCount.setAttribute('aria-live', 'polite');
    lightboxCount.setAttribute('aria-atomic', 'true');

    const close = document.createElement('button');
    close.className = 'room-lightbox-close';
    close.type = 'button';
    close.setAttribute('aria-label', 'Close enlarged photo');
    close.textContent = '×';

    const lightboxStage = document.createElement('div');
    lightboxStage.className = 'room-lightbox-stage';
    const enlargedImage = document.createElement('img');
    enlargedImage.className = 'room-lightbox-image';

    const makeArrow = (direction, label, glyph) => {
        const button = document.createElement('button');
        button.className = `room-gallery-arrow room-gallery-${direction}`;
        button.type = 'button';
        button.setAttribute('aria-label', label);
        button.textContent = glyph;
        return button;
    };

    let previous;
    let next;
    let lightboxPrevious;
    let lightboxNext;
    if (hasMultipleImages) {
        previous = makeArrow('previous', 'Previous photo', '‹');
        next = makeArrow('next', 'Next photo', '›');
        lightboxPrevious = makeArrow('previous', 'Previous photo', '‹');
        lightboxNext = makeArrow('next', 'Next photo', '›');
        lightboxStage.append(lightboxPrevious, lightboxNext);
    }

    toolbar.append(lightboxCount, close);
    lightboxStage.prepend(enlargedImage);
    lightbox.append(toolbar, lightboxStage);

    const count = document.createElement('span');
    count.className = 'room-gallery-count';
    count.setAttribute('aria-live', 'polite');
    count.setAttribute('aria-atomic', 'true');

    const thumbnails = document.createElement('div');
    thumbnails.className = 'room-gallery-thumbnails';
    thumbnails.setAttribute('role', 'group');
    thumbnails.setAttribute('aria-label', `${roomGallery.room} photos`);

    let activeIndex = 0;
    const show = (index) => {
        activeIndex = Math.max(0, Math.min(index, roomGallery.images.length - 1));
        const imageCount = roomGallery.images.length;
        const source = roomGallery.images[activeIndex];
        const imageLabel = imageCount > 1
            ? `${roomGallery.room} photo ${activeIndex + 1} of ${imageCount}`
            : `${roomGallery.room} photo`;

        originalImage.src = source;
        originalImage.alt = imageLabel;
        enlargedImage.src = source;
        enlargedImage.alt = imageLabel;
        openImage.setAttribute('aria-label', imageCount > 1
            ? `Enlarge ${roomGallery.room} photo ${activeIndex + 1} of ${imageCount}`
            : `Enlarge ${roomGallery.room} photo`);
        count.textContent = imageCount > 1 ? `${activeIndex + 1} / ${imageCount}` : '';
        lightboxCount.textContent = imageCount > 1 ? `${activeIndex + 1} / ${imageCount}` : `Viewing ${roomGallery.room}`;

        if (hasMultipleImages) {
            previous.disabled = activeIndex === 0;
            next.disabled = activeIndex === imageCount - 1;
            lightboxPrevious.disabled = activeIndex === 0;
            lightboxNext.disabled = activeIndex === imageCount - 1;
            thumbnails.querySelectorAll('button').forEach((button, thumbnailIndex) => {
                button.setAttribute('aria-pressed', String(thumbnailIndex === activeIndex));
            });
        }
    };

    if (hasMultipleImages) {
        roomGallery.images.forEach((source, index) => {
            const button = document.createElement('button');
            button.className = 'room-gallery-thumbnail';
            button.type = 'button';
            button.setAttribute('aria-label', `Show photo ${index + 1} of ${roomGallery.images.length}`);
            button.setAttribute('aria-pressed', 'false');

            const thumbnail = document.createElement('img');
            thumbnail.src = source;
            thumbnail.alt = '';
            thumbnail.loading = 'lazy';
            thumbnail.decoding = 'async';
            button.append(thumbnail);
            button.addEventListener('click', () => show(index));
            thumbnails.append(button);
        });

        previous.addEventListener('click', () => show(activeIndex - 1));
        next.addEventListener('click', () => show(activeIndex + 1));
        lightboxPrevious.addEventListener('click', () => show(activeIndex - 1));
        lightboxNext.addEventListener('click', () => show(activeIndex + 1));
        gallery.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft' && activeIndex > 0) show(activeIndex - 1);
            if (event.key === 'ArrowRight' && activeIndex < roomGallery.images.length - 1) show(activeIndex + 1);
        });
        lightbox.addEventListener('keydown', (event) => {
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
    }

    openImage.addEventListener('click', () => {
        lightbox.showModal();
        close.focus();
    });
    close.addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) lightbox.close();
    });
    lightbox.addEventListener('close', () => openImage.focus());

    roomDetail.replaceChild(gallery, originalImage);
    openImage.append(originalImage);
    stage.append(openImage);
    if (hasMultipleImages) stage.append(previous, next, count);
    gallery.append(stage);
    if (hasMultipleImages) gallery.append(thumbnails);
    document.body.append(lightbox);
    show(0);
});
