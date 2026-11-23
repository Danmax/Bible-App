(() => {
    const reader = document.querySelector('[data-tap-reader]');
    const panel = document.querySelector('[data-reader-selection]');
    if (!reader || !panel) return;

    // Keep the action panel outside ancestors with backdrop filters. It remains
    // non-modal so readers can scroll and add verses without closing it.
    document.body.append(panel);
    const cards = [...reader.querySelectorAll('[data-verse-card]')];
    const selected = new Set();
    const form = panel.querySelector('[data-reader-marks]');
    const reference = panel.querySelector('[data-selection-reference]');
    const status = panel.querySelector('[data-selection-status]');
    const saveStatus = document.querySelector('[data-reader-save-status]');
    document.body.append(saveStatus);
    const noteLink = panel.querySelector('[data-selection-note]');
    let saving = false;
    let pointerStart = null;
    let statusTimer;

    const orderedSelection = () => cards.filter((card) => selected.has(card));
    const verseNumbers = (selection) => {
        const numbers = selection.map((card) => Number(card.dataset.verseNumber));
        const groups = [];
        for (let i = 0; i < numbers.length; i++) {
            const start = numbers[i];
            let end = start;
            while (numbers[i + 1] === end + 1) end = numbers[++i];
            groups.push(start === end ? String(start) : `${start}–${end}`);
        }
        return groups.join(', ');
    };
    const selectionReference = (selection) => `${reader.dataset.chapterLabel}:${verseNumbers(selection)} (${reader.dataset.translation})`;
    const announce = (message) => {
        window.clearTimeout(statusTimer);
        saveStatus.textContent = message;
        statusTimer = window.setTimeout(() => { saveStatus.textContent = ''; }, 5000);
    };
    const update = () => {
        const selection = orderedSelection();
        panel.hidden = selection.length === 0;
        document.body.classList.toggle('has-verse-selection', selection.length > 0);
        for (const card of cards) {
            const active = selected.has(card);
            card.classList.toggle('is-verse-selected', active);
            const text = card.querySelector('.reader-verse-text');
            text.setAttribute('aria-pressed', String(active));
            text.setAttribute('aria-label', `${active ? 'Deselect' : 'Select'} ${card.dataset.verseReference}: ${card.dataset.verseText}`);
        }
        if (!selection.length) return;
        reader.dispatchEvent(new CustomEvent('reader:verse-selected', { detail: selection[0] }));
        reference.textContent = selectionReference(selection);
        if (noteLink) {
            const url = new URL(noteLink.href, window.location.href);
            url.searchParams.set('verse_id', selection[0].dataset.verseId);
            noteLink.href = url.href;
            noteLink.title = selection.length === 1 ? 'Add a note to this verse' : 'Add a note to the first selected verse';
        }
        const commonColor = selection.map((card) => {
            try { return JSON.parse(card.dataset.verseBookmark || 'null')?.highlight_color || ''; } catch { return ''; }
        });
        panel.querySelectorAll('[data-selection-color]').forEach((button) => {
            button.setAttribute('aria-pressed', String(commonColor.every((color) => color === button.dataset.selectionColor)));
        });
    };
    const clearSelection = () => {
        if (saving) return;
        const first = orderedSelection()[0];
        const restoreFocus = panel.contains(document.activeElement);
        selected.clear();
        status.textContent = '';
        update();
        if (restoreFocus) first?.querySelector('.reader-verse-text')?.focus({ preventScroll: true });
    };
    const toggleVerse = (card) => {
        if (saving) return;
        window.clearTimeout(statusTimer);
        saveStatus.textContent = '';
        if (selected.has(card)) selected.delete(card);
        else selected.add(card);
        status.textContent = '';
        update();
    };

    reader.addEventListener('pointerdown', (event) => {
        pointerStart = { x: event.clientX, y: event.clientY, time: Date.now() };
    }, { passive: true });
    reader.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || event.target.closest('a, button, .reader-comparison-copy')) return;
        if (window.getSelection()?.toString().trim()) return;
        if (pointerStart && (Math.hypot(event.clientX - pointerStart.x, event.clientY - pointerStart.y) > 12 || Date.now() - pointerStart.time > 600)) return;
        const card = event.target.closest('[data-verse-card]');
        if (card) toggleVerse(card);
    });
    reader.addEventListener('keydown', (event) => {
        if (event.target.matches('.reader-verse-text') && ['Enter', ' '].includes(event.key)) {
            event.preventDefault();
            toggleVerse(event.target.closest('[data-verse-card]'));
        }
    });
    panel.querySelector('[data-selection-close]').addEventListener('click', clearSelection);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') clearSelection();
    });

    const saveMark = async (action, color = '') => {
        if (saving || !form || !selected.size) return;
        const selection = orderedSelection();
        const data = new FormData(form);
        data.set('mark_action', action);
        data.set('color', color);
        for (const card of selection) data.append('verse_ids[]', card.dataset.verseId);
        saving = true;
        panel.setAttribute('aria-busy', 'true');
        panel.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        status.textContent = 'Saving…';
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', signal: controller.signal });
            let result;
            try { result = await response.json(); } catch { throw new Error('Your session may have expired. Refresh the page and try again.'); }
            if (!response.ok) throw new Error(result.error || 'Your mark could not be saved. Please try again.');
            for (const card of selection) {
                const text = card.querySelector('.reader-verse-text');
                let bookmark;
                try { bookmark = JSON.parse(card.dataset.verseBookmark || 'null') || {}; } catch { bookmark = {}; }
                if (action === 'highlight') {
                    const mark = document.createElement('mark');
                    mark.className = `verse-highlight ${color}`;
                    mark.textContent = text.textContent;
                    text.replaceChildren(mark);
                    bookmark.highlight_color = color;
                } else if (action === 'clear-highlight') {
                    text.textContent = text.textContent;
                    bookmark.highlight_color = null;
                }
                card.dataset.verseBookmark = JSON.stringify(bookmark);
            }
            saving = false;
            const message = action === 'highlight' ? 'Highlight saved.' : action === 'bookmark' ? 'Saved to your Library.' : 'Highlight removed.';
            clearSelection();
            announce(message);
        } catch (error) {
            status.textContent = error.name === 'AbortError' ? 'Saving took too long. Please try again.' : error.message;
        } finally {
            window.clearTimeout(timeout);
            saving = false;
            panel.removeAttribute('aria-busy');
            panel.querySelectorAll('button').forEach((button) => { button.disabled = false; });
        }
    };
    const copyText = async (text) => {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        if (!copied) throw new Error('Copy is unavailable in this browser.');
    };
    panel.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-selection-color], [data-selection-action]');
        if (!button || saving) return;
        if (button.hasAttribute('data-selection-color')) {
            await saveMark('highlight', button.dataset.selectionColor);
            return;
        }
        const action = button.dataset.selectionAction;
        if (['bookmark', 'clear-highlight'].includes(action)) {
            await saveMark(action);
            return;
        }
        const selection = orderedSelection();
        const title = selectionReference(selection);
        const text = selection.map((card) => card.dataset.verseText).join(' ') + `\n${title}`;
        try {
            if (action === 'share' && navigator.share) {
                const url = new URL(reader.dataset.readingUrl, window.location.origin);
                url.hash = `verse-${selection[0].dataset.verseNumber}`;
                await navigator.share({ title, text, url: url.href });
                status.textContent = 'Shared.';
            } else {
                await copyText(text);
                status.textContent = action === 'share' ? 'Passage copied. Paste it to share.' : 'Passage copied.';
            }
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = 'Could not copy or share. Please try again.';
        }
    });

    document.querySelector('[data-reader-edition]')?.addEventListener('change', (event) => event.target.form.requestSubmit());
    document.querySelector('.reader-edition-picker')?.classList.add('is-enhanced');
    const siteHeader = document.querySelector('.site-header');
    if (siteHeader) {
        const headerSize = new ResizeObserver(() => {
            document.documentElement.style.setProperty('--reader-header-height', `${siteHeader.getBoundingClientRect().height}px`);
        });
        headerSize.observe(siteHeader);
    }
    // Put optional tools after the reading, keeping the passage in view first.
    const nav = document.querySelector('.bible-reading-page .scripture-nav');
    if (nav) {
        const drawers = document.querySelectorAll('.bible-reading-page .reader-search-drawer, .bible-reading-page .translation-comparison, .bible-reading-page .passage-resource-drawer');
        nav.after(...drawers);
    }
    update();
})();
