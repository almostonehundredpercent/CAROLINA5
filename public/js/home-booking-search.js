const initializeHomeBookingSearch = () => {
    document.querySelectorAll('.home-booking-search').forEach(form => {
        const stay = form.querySelector('[data-home-stay]');
        const mode = form.querySelector('[name="mode"]');
        if (!stay || !mode) return;

        const syncMode = () => {
            mode.value = stay.selectedOptions[0]?.dataset.mode || 'dates';
        };

        stay.addEventListener('change', syncMode);
        syncMode();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHomeBookingSearch, { once: true });
} else {
    initializeHomeBookingSearch();
}
