<script>
(() => {
    const renderedPropertyDate = @json(\Carbon\Carbon::now('Asia/Manila')->toDateString());
    const currentPropertyDate = () => {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit',
        }).formatToParts(new Date());

        return `${parts.find(part => part.type === 'year').value}-${parts.find(part => part.type === 'month').value}-${parts.find(part => part.type === 'day').value}`;
    };
    const refreshIfDayChanged = () => {
        if (currentPropertyDate() !== renderedPropertyDate) window.location.reload();
    };

    window.setInterval(refreshIfDayChanged, 30000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshIfDayChanged();
    });
})();
</script>
