(() => {
    const input = document.querySelector('[data-search-input]');
    if (!(input instanceof HTMLInputElement)) return;
    const historyKey = 'good-news-scripture-searches-v2';
    try {
        const saved = JSON.parse(localStorage.getItem(historyKey) || '[]');
        let recent = Array.isArray(saved) ? saved.filter((item) => typeof item === 'string' && item.length <= 200).slice(0, 6) : [];
        if (input.value.trim()) {
            recent = [input.value.trim(), ...recent.filter((item) => item !== input.value.trim())].slice(0, 6);
            localStorage.setItem(historyKey, JSON.stringify(recent));
        }
        const panel = document.querySelector('[data-recent-searches]');
        const list = document.querySelector('[data-recent-search-list]');
        if (panel && list && recent.length) {
            panel.hidden = false;
            recent.forEach((query) => {
                const link = document.createElement('a');
                const url = new URL('search.php', window.location.href);
                url.searchParams.set('q', query);
                const translation = new URL(window.location.href).searchParams.get('translation');
                if (translation) url.searchParams.set('translation', translation);
                link.href = url.href;
                link.textContent = query;
                list.append(link);
            });
            document.querySelector('[data-clear-searches]')?.addEventListener('click', () => {
                localStorage.removeItem(historyKey);
                panel.hidden = true;
            });
        }
    } catch { /* Search works when browser storage is disabled. */ }
    const positionKey = `scripture-results:${window.location.search}`;
    document.querySelector('[data-search-results]')?.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('a')) return;
        try { sessionStorage.setItem(positionKey, String(window.scrollY)); } catch { /* Optional restoration. */ }
    });
    window.addEventListener('pageshow', (event) => {
        const navigation = performance.getEntriesByType('navigation')[0];
        if (event.persisted || navigation?.type !== 'back_forward') return;
        try {
            const position = Number(sessionStorage.getItem(positionKey));
            if (Number.isFinite(position) && position > 0) window.scrollTo(0, position);
        } catch { /* Browser history remains available. */ }
    });
})();
