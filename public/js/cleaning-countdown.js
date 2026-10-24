(() => {
    const formatDuration = (totalSeconds) => {
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    };

    document.querySelectorAll('[data-cleaning-countdown]').forEach((timer) => {
        const value = timer.querySelector('[data-countdown-value]');
        const completionMessage = timer.querySelector('[data-countdown-complete]');
        let remaining = Math.max(0, Number.parseInt(timer.dataset.remainingSeconds, 10) || 0);

        if (!value) return;

        const render = () => {
            value.textContent = formatDuration(remaining);
            timer.dataset.remainingSeconds = String(remaining);

            if (remaining === 0) {
                timer.dataset.countdownState = 'complete';
                timer.setAttribute('aria-label', 'Cleaning window complete');
                timer.setAttribute('aria-live', 'polite');
                completionMessage?.removeAttribute('hidden');
            }
        };

        render();

        if (remaining === 0) return;

        const interval = window.setInterval(() => {
            remaining = Math.max(0, remaining - 1);
            render();

            if (remaining === 0) window.clearInterval(interval);
        }, 1000);
    });
})();
