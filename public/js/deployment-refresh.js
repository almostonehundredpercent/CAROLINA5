(() => {
    const {version, endpoint} = document.currentScript.dataset;
    let lastInteraction = Date.now();
    let edited = false;
    let checking = false;

    for (const event of ['pointerdown', 'keydown', 'scroll']) {
        window.addEventListener(event, () => { lastInteraction = Date.now(); }, {passive: true, capture: true});
    }
    for (const event of ['input', 'change', 'submit']) {
        document.addEventListener(event, () => { edited = true; }, true);
    }

    const safe = () => !edited && document.visibilityState === 'visible'
        && Date.now() - lastInteraction >= 30000
        && !document.querySelector('.page-loader.is-visible, .network-loader.is-visible, dialog[open]');

    setInterval(async () => {
        if (checking || !safe()) return;
        checking = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 8000);
        try {
            const response = await fetch(endpoint, {cache: 'no-store', signal: controller.signal});
            if (!response.ok) return;
            const {version: latest} = await response.json();
            if (typeof latest !== 'string' || !latest || latest === version || !safe()) return;
            const key = 'carolina:deployment-refresh';
            const transition = version + ':' + latest;
            if (sessionStorage.getItem(key) === transition) return;
            sessionStorage.setItem(key, transition);
            window.location.reload();
        } catch {
            // An unreachable version check should never interrupt browsing.
        } finally {
            clearTimeout(timeout);
            checking = false;
        }
    }, 60000);
})();
