const sermonWorkspace = document.querySelector('[data-sermon-workspace]');

if (sermonWorkspace) {
    const noteForm = sermonWorkspace.querySelector('[data-sermon-note-form]');
    const editor = sermonWorkspace.querySelector('[data-sermon-rich-editor]');
    const contentHtmlInput = noteForm?.querySelector('[data-sermon-content-html]');
    const contentTextInput = noteForm?.querySelector('[data-sermon-content-text]');
    const referenceTagsInput = noteForm?.querySelector('[data-sermon-reference-tags-json]');
    const verseRefsInput = noteForm?.querySelector('[data-sermon-verse-refs-json]');
    const stormBoardInput = noteForm?.querySelector('[data-sermon-storm-board-json]');
    const summaryField = sermonWorkspace.querySelector('[data-sermon-summary-field]');
    const speakerNotesField = sermonWorkspace.querySelector('[data-sermon-speaker-notes-field]');
    const titleField = noteForm?.querySelector('input[name="title"]');
    const statusField = sermonWorkspace.querySelector('[data-sermon-ai-status]');
    const sidePanel = sermonWorkspace.querySelector('[data-sermon-side-panel]');
    const sidePanelToggle = sermonWorkspace.querySelector('[data-sermon-side-panel-toggle]');
    const sidePanelClose = sermonWorkspace.querySelector('[data-sermon-side-panel-close]');
    const verseQueryInput = sermonWorkspace.querySelector('[data-sermon-verse-query]');
    const verseSearchButton = sermonWorkspace.querySelector('[data-sermon-verse-search]');
    const verseResults = sermonWorkspace.querySelector('[data-sermon-verse-results]');
    const verseRefList = sermonWorkspace.querySelector('[data-sermon-verse-ref-list]');
    const copyShareUrlButton = sermonWorkspace.querySelector('[data-sermon-copy-share-url]');
    const shareUrlInput = sermonWorkspace.querySelector('[data-sermon-share-url]');
    const aiSummaryButton = sermonWorkspace.querySelector('[data-sermon-ai-summary]');
    const aiReferencesButton = sermonWorkspace.querySelector('[data-sermon-ai-references]');
    const toolbar = sermonWorkspace.querySelector('[data-sermon-toolbar]');
    const boardRoot = sermonWorkspace.querySelector('[data-sermon-board]');
    const linkInput = toolbar?.querySelector('[data-editor-link-input]');
    const linkApplyButton = toolbar?.querySelector('[data-editor-link-apply]');
    const csrfInput = noteForm?.querySelector('input[name="csrf_token"]');
    const verseModal = document.querySelector('[data-sermon-verse-modal]');
    const verseModalContent = verseModal?.querySelector('[data-sermon-verse-modal-content]');
    const verseModalReference = verseModal?.querySelector('[data-sermon-verse-modal-reference]');
    const verseModalTranslation = verseModal?.querySelector('[data-sermon-verse-modal-translation]');
    const verseModalText = verseModal?.querySelector('[data-sermon-verse-modal-text]');
    const verseModalStatus = verseModal?.querySelector('[data-sermon-verse-modal-status]');
    const verseModalClose = verseModal?.querySelector('[data-sermon-verse-modal-close]');
    const insertCitationButton = verseModal?.querySelector('[data-sermon-insert-citation]');
    const paraphraseVerseButton = verseModal?.querySelector('[data-sermon-paraphrase-verse]');
    let verseRefs = parseJsonArray(verseRefsInput?.value);
    let savedRange = null;
    let activeVerse = null;

    const setStatus = (message) => {
        if (statusField instanceof HTMLElement) {
            statusField.textContent = message;
        }
    };

    const escapeHtml = (value) => String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const readBoard = () => {
        const board = {};

        boardRoot?.querySelectorAll('[data-board-column]').forEach((column) => {
            const key = column.getAttribute('data-board-column');

            if (!key) {
                return;
            }

            board[key] = Array.from(column.querySelectorAll('textarea'))
                .map((textarea) => textarea.value.trim())
                .filter(Boolean);
        });

        return board;
    };

    const syncSidePanelState = (open) => {
        sermonWorkspace.classList.toggle('is-side-panel-open', open);

        if (sidePanelToggle instanceof HTMLButtonElement) {
            sidePanelToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            sidePanelToggle.textContent = open ? 'Hide Side Tools' : 'Move Tools To Side';
        }
    };

    const rememberSelection = () => {
        if (!(editor instanceof HTMLElement)) {
            return;
        }

        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);

        if (!editor.contains(range.commonAncestorContainer)) {
            return;
        }

        savedRange = range.cloneRange();
    };

    const restoreSelection = () => {
        if (!savedRange) {
            return;
        }

        const selection = window.getSelection();

        if (!selection) {
            return;
        }

        selection.removeAllRanges();
        selection.addRange(savedRange);
    };

    const syncHiddenFields = () => {
        if (!(editor instanceof HTMLElement)) {
            return;
        }

        if (contentHtmlInput instanceof HTMLInputElement) {
            const rawHtml = editor.innerHTML.trim();
            contentHtmlInput.value = rawHtml !== '' ? rawHtml : '<p></p>';
        }

        if (contentTextInput instanceof HTMLInputElement) {
            contentTextInput.value = editor.innerText.trim();
        }

        if (referenceTagsInput instanceof HTMLInputElement) {
            referenceTagsInput.value = JSON.stringify(readReferenceTags());
        }

        if (verseRefsInput instanceof HTMLInputElement) {
            verseRefsInput.value = JSON.stringify(verseRefs);
        }

        if (stormBoardInput instanceof HTMLInputElement) {
            stormBoardInput.value = JSON.stringify(readBoard());
        }
    };

    const readReferenceTags = () => Array.from(sermonWorkspace.querySelectorAll('[data-reference-group]'))
        .flatMap((input) => {
            if (!(input instanceof HTMLInputElement)) {
                return [];
            }

            const tagType = input.getAttribute('data-reference-group') || '';

            return input.value
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean)
                .map((label) => ({
                    tag_type: tagType,
                    label,
                }));
        });

    const renderVerseRefs = () => {
        if (!(verseRefList instanceof HTMLElement)) {
            return;
        }

        if (verseRefs.length === 0) {
            verseRefList.innerHTML = '<p class="muted-copy">No verses attached yet.</p>';
            return;
        }

        verseRefList.innerHTML = verseRefs.map((verseRef, index) => `
            <div class="sermon-verse-ref-card">
                <div>
                    <strong>${escapeHtml(verseRef.reference_label || 'Verse')}</strong>
                    <span>${escapeHtml(titleCase(verseRef.reference_kind || 'citation'))}</span>
                </div>
                <button class="button button-secondary" type="button" data-remove-verse-ref="${index}">Remove</button>
            </div>
        `).join('');
    };

    const createBoardCard = (text = '') => {
        const card = document.createElement('div');
        card.className = 'sermon-board-card';
        card.innerHTML = `
            <textarea rows="3">${escapeHtml(text)}</textarea>
            <button class="button button-secondary" type="button" data-board-remove-card>Remove</button>
        `;

        return card;
    };

    const insertHtmlAtSelection = (html) => {
        if (!(editor instanceof HTMLElement)) {
            return;
        }

        editor.focus();
        restoreSelection();

        if (document.queryCommandSupported && document.queryCommandSupported('insertHTML')) {
            document.execCommand('insertHTML', false, html);
        } else {
            const selection = window.getSelection();

            if (!selection || selection.rangeCount === 0) {
                editor.insertAdjacentHTML('beforeend', html);
                return;
            }

            const range = selection.getRangeAt(0);
            range.deleteContents();
            const fragment = range.createContextualFragment(html);
            range.insertNode(fragment);
        }

        rememberSelection();
        syncHiddenFields();
    };

    const wrapSelectionWithClass = (className) => {
        if (!(editor instanceof HTMLElement)) {
            return;
        }

        editor.focus();
        restoreSelection();
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0);

        if (!editor.contains(range.commonAncestorContainer)) {
            return;
        }

        const span = document.createElement('span');
        span.className = className;

        try {
            range.surroundContents(span);
        } catch (error) {
            const contents = range.extractContents();
            span.appendChild(contents);
            range.insertNode(span);
        }

        selection.removeAllRanges();
        const nextRange = document.createRange();
        nextRange.selectNodeContents(span);
        selection.addRange(nextRange);
        savedRange = nextRange.cloneRange();
        syncHiddenFields();
    };

    const mergeReferenceSuggestions = (tags, refs) => {
        const groupedInputMap = {};

        sermonWorkspace.querySelectorAll('[data-reference-group]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
                groupedInputMap[input.getAttribute('data-reference-group') || ''] = input;
            }
        });

        tags.forEach((tag) => {
            const input = groupedInputMap[String(tag.tag_type || '')];

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const current = input.value
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean);

            if (!current.includes(String(tag.label || '').trim())) {
                current.push(String(tag.label || '').trim());
            }

            input.value = current.join(', ');
        });

        refs.forEach((ref) => {
            const exists = verseRefs.some((existing) => (
                Number(existing.verse_id) === Number(ref.verse_id)
                && String(existing.reference_kind) === String(ref.reference_kind)
            ));

            if (!exists) {
                verseRefs.push(ref);
            }
        });

        renderVerseRefs();
        syncHiddenFields();
    };

    const titleCase = (value) => value
        .split(/[_\s-]+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

    const parseVerseCardPayload = (node) => ({
        verse_id: Number(node.getAttribute('data-verse-id') || '0'),
        reference_label: node.getAttribute('data-reference-label') || '',
        verse_text: node.getAttribute('data-verse-text') || '',
        translation: node.getAttribute('data-translation') || '',
    });

    const openVerseModal = (verse) => {
        activeVerse = verse;

        if (!(verseModal instanceof HTMLElement)) {
            return;
        }

        if (verseModalReference instanceof HTMLElement) {
            verseModalReference.textContent = String(verse.reference_label || 'Verse');
        }

        if (verseModalTranslation instanceof HTMLElement) {
            verseModalTranslation.textContent = String(verse.translation || '');
        }

        if (verseModalText instanceof HTMLElement) {
            verseModalText.textContent = String(verse.verse_text || '');
        }

        if (verseModalStatus instanceof HTMLElement) {
            verseModalStatus.textContent = 'Choose how you want this verse to appear in the document.';
        }

        verseModal.hidden = false;
        verseModal.setAttribute('aria-hidden', 'false');
    };

    const closeVerseModal = () => {
        if (!(verseModal instanceof HTMLElement)) {
            return;
        }

        verseModal.hidden = true;
        verseModal.setAttribute('aria-hidden', 'true');
        activeVerse = null;
    };

    const addVerseRef = (verseRef) => {
        const exists = verseRefs.some((existing) => (
            Number(existing.verse_id) === Number(verseRef.verse_id)
            && String(existing.reference_kind) === String(verseRef.reference_kind)
        ));

        if (!exists) {
            verseRefs.push(verseRef);
            renderVerseRefs();
            syncHiddenFields();
        }
    };

    const runAiRequest = async (endpoint, payload) => {
        if (!(csrfInput instanceof HTMLInputElement)) {
            throw new Error('The form token is missing.');
        }

        const formData = new FormData();
        formData.set('csrf_token', csrfInput.value);

        Object.entries(payload).forEach(([key, value]) => {
            formData.set(key, String(value ?? ''));
        });

        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const rawText = await response.text();
        let parsed = {};

        try {
            parsed = rawText ? JSON.parse(rawText) : {};
        } catch (error) {
            throw new Error(rawText.trim() || 'The AI response could not be read.');
        }

        if (!response.ok) {
            throw new Error(parsed.error || 'The AI request could not be completed.');
        }

        return parsed;
    };

    const searchVerses = async () => {
        if (!(verseQueryInput instanceof HTMLInputElement) || !(verseResults instanceof HTMLElement)) {
            return;
        }

        const query = verseQueryInput.value.trim();

        if (query === '') {
            verseResults.innerHTML = '<p class="muted-copy">Enter a verse search first.</p>';
            return;
        }

        verseResults.innerHTML = '<p class="muted-copy">Searching Scripture...</p>';

        try {
            const url = new URL(window.location.origin + '/sermon-verse-search.php');
            url.searchParams.set('q', query);

            const response = await fetch('sermon-verse-search.php?' + url.searchParams.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const rawText = await response.text();
            const payload = rawText ? JSON.parse(rawText) : {};

            if (!response.ok) {
                throw new Error(payload.error || 'The verse search could not be completed.');
            }

            const results = Array.isArray(payload.results) ? payload.results : [];

            if (results.length === 0) {
                verseResults.innerHTML = '<p class="muted-copy">No verses matched that search.</p>';
                return;
            }

            verseResults.innerHTML = results.map((result) => `
                <button
                    class="sermon-verse-result"
                    type="button"
                    data-open-verse-modal
                    data-verse-id="${escapeHtml(result.verse_id)}"
                    data-reference-label="${escapeHtml(result.reference_label)}"
                    data-verse-text="${escapeHtml(result.verse_text)}"
                    data-translation="${escapeHtml(result.translation)}"
                >
                    <strong>${escapeHtml(result.reference_label)}</strong>
                    <span>${escapeHtml(result.verse_text)}</span>
                </button>
            `).join('');
        } catch (error) {
            verseResults.innerHTML = `<p class="muted-copy">${escapeHtml(error instanceof Error ? error.message : 'Verse search failed.')}</p>`;
        }
    };

    toolbar?.querySelectorAll('[data-editor-command]').forEach((button) => {
        button.addEventListener('click', () => {
            const command = button.getAttribute('data-editor-command');

            if (!command || !(editor instanceof HTMLElement)) {
                return;
            }

            editor.focus();
            restoreSelection();
            document.execCommand(command, false, null);
            rememberSelection();
            syncHiddenFields();
        });
    });

    toolbar?.querySelectorAll('[data-editor-block]').forEach((button) => {
        button.addEventListener('click', () => {
            const block = button.getAttribute('data-editor-block');

            if (!block || !(editor instanceof HTMLElement)) {
                return;
            }

            editor.focus();
            restoreSelection();
            document.execCommand('formatBlock', false, block);
            rememberSelection();
            syncHiddenFields();
        });
    });

    toolbar?.querySelectorAll('[data-editor-highlight]').forEach((button) => {
        button.addEventListener('click', () => {
            const className = button.getAttribute('data-editor-highlight');

            if (!className) {
                return;
            }

            wrapSelectionWithClass(className);
        });
    });

    linkApplyButton?.addEventListener('click', () => {
        if (!(linkInput instanceof HTMLInputElement) || !(editor instanceof HTMLElement)) {
            return;
        }

        const url = linkInput.value.trim();

        if (url === '') {
            return;
        }

        editor.focus();
        restoreSelection();
        document.execCommand('createLink', false, url);
        editor.querySelectorAll('a').forEach((anchor) => {
            anchor.classList.add('note-inline-link');
            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener noreferrer');
        });
        rememberSelection();
        syncHiddenFields();
    });

    editor?.addEventListener('mouseup', rememberSelection);
    editor?.addEventListener('keyup', rememberSelection);
    editor?.addEventListener('focus', rememberSelection);
    editor?.addEventListener('click', (event) => {
        const target = event.target;
        const verseChip = target instanceof Element ? target.closest('.note-verse-chip') : null;

        if (!(verseChip instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        const verseId = Number(verseChip.getAttribute('data-verse-id') || '0');
        const matchingVerseRef = verseRefs.find((verseRef) => Number(verseRef.verse_id) === verseId);

        openVerseModal({
            verse_id: verseId,
            reference_label: verseChip.getAttribute('data-verse-reference') || verseChip.textContent || 'Verse',
            verse_text: verseChip.getAttribute('data-verse-text') || String(matchingVerseRef?.quote_text || ''),
            translation: '',
        });
    });

    verseSearchButton?.addEventListener('click', searchVerses);
    verseQueryInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchVerses();
        }
    });

    verseResults?.addEventListener('click', (event) => {
        const target = event.target;
        const button = target instanceof Element ? target.closest('[data-open-verse-modal]') : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        openVerseModal(parseVerseCardPayload(button));
    });

    verseRefList?.addEventListener('click', (event) => {
        const target = event.target;
        const button = target instanceof Element ? target.closest('[data-remove-verse-ref]') : null;

        if (!(button instanceof HTMLElement)) {
            return;
        }

        const index = Number(button.getAttribute('data-remove-verse-ref') || '-1');

        if (Number.isNaN(index) || index < 0) {
            return;
        }

        verseRefs.splice(index, 1);
        renderVerseRefs();
        syncHiddenFields();
    });

    insertCitationButton?.addEventListener('click', () => {
        if (!activeVerse) {
            return;
        }

        const reference = String(activeVerse.reference_label || 'Verse');
        insertHtmlAtSelection(
            `<span class="note-verse-chip" data-verse-id="${escapeHtml(activeVerse.verse_id)}" data-verse-reference="${escapeHtml(reference)}" data-verse-text="${escapeHtml(activeVerse.verse_text || '')}" contenteditable="false">${escapeHtml(reference)}</span>&nbsp;`
        );
        addVerseRef({
            verse_id: Number(activeVerse.verse_id),
            reference_kind: 'citation',
            reference_label: reference,
            quote_text: String(activeVerse.verse_text || ''),
        });
        closeVerseModal();
    });

    paraphraseVerseButton?.addEventListener('click', async () => {
        if (!activeVerse) {
            return;
        }

        try {
            if (verseModalStatus instanceof HTMLElement) {
                verseModalStatus.textContent = 'Building a paraphrase draft...';
            }

            syncHiddenFields();
            const payload = await runAiRequest('sermon-ai-paraphrase.php', {
                verse_id: activeVerse.verse_id,
                context: contentTextInput instanceof HTMLInputElement ? contentTextInput.value : '',
            });
            const draft = payload.draft || {};
            const reference = String(draft.reference_label || activeVerse.reference_label || 'Verse');
            const paraphrase = String(draft.paraphrase || '').trim();

            if (paraphrase === '') {
                throw new Error('The paraphrase draft was empty.');
            }

            insertHtmlAtSelection(
                `<blockquote><p>${escapeHtml(paraphrase)}</p><p><span class="note-verse-chip" data-verse-id="${escapeHtml(activeVerse.verse_id)}" data-verse-reference="${escapeHtml(reference)}" data-verse-text="${escapeHtml(activeVerse.verse_text || '')}" contenteditable="false">${escapeHtml(reference)}</span></p></blockquote>`
            );
            addVerseRef({
                verse_id: Number(activeVerse.verse_id),
                reference_kind: 'paraphrase',
                reference_label: reference,
                quote_text: String(activeVerse.verse_text || ''),
            });
            closeVerseModal();
            setStatus(`Paraphrase drafted with ${payload.model || 'OpenAI'}. Review it before saving.`);
        } catch (error) {
            if (verseModalStatus instanceof HTMLElement) {
                verseModalStatus.textContent = error instanceof Error ? error.message : 'The paraphrase could not be created.';
            }
        }
    });

    verseModalClose?.addEventListener('click', closeVerseModal);
    verseModal?.addEventListener('click', (event) => {
        if (!(verseModalContent instanceof HTMLElement) || !(event.target instanceof Node)) {
            return;
        }

        if (!verseModalContent.contains(event.target)) {
            closeVerseModal();
        }
    });

    aiSummaryButton?.addEventListener('click', async () => {
        try {
            syncHiddenFields();
            setStatus('Building a sermon summary draft...');
            const payload = await runAiRequest('sermon-ai-summary.php', {
                speaker_notes_text: speakerNotesField instanceof HTMLTextAreaElement ? speakerNotesField.value : '',
                note_text: contentTextInput instanceof HTMLInputElement ? contentTextInput.value : '',
            });
            const draft = payload.draft || {};
            const summaryParts = [String(draft.summary || '').trim()];

            if (Array.isArray(draft.key_points) && draft.key_points.length > 0) {
                summaryParts.push('Key points: ' + draft.key_points.join(' | '));
            }

            if (summaryField instanceof HTMLTextAreaElement) {
                summaryField.value = summaryParts.filter(Boolean).join('\n\n');
            }

            if (titleField instanceof HTMLInputElement && titleField.value.trim() === '') {
                titleField.value = String(draft.title || '').trim();
            }

            setStatus(`Summary drafted with ${payload.model || 'OpenAI'}. Review it before saving.`);
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'The summary could not be created.');
        }
    });

    aiReferencesButton?.addEventListener('click', async () => {
        try {
            syncHiddenFields();
            setStatus('Suggesting references and themes...');
            const payload = await runAiRequest('sermon-ai-references.php', {
                note_text: contentTextInput instanceof HTMLInputElement ? contentTextInput.value : '',
            });
            const draft = payload.draft || {};
            const tags = Array.isArray(draft.reference_tags) ? draft.reference_tags : [];
            const refs = Array.isArray(draft.verse_refs) ? draft.verse_refs : [];
            mergeReferenceSuggestions(tags, refs);
            setStatus(`References suggested with ${payload.model || 'OpenAI'}. Review them before saving.`);
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'Reference suggestions could not be created.');
        }
    });

    copyShareUrlButton?.addEventListener('click', async () => {
        if (!(shareUrlInput instanceof HTMLInputElement) || !navigator.clipboard?.writeText) {
            return;
        }

        try {
            await navigator.clipboard.writeText(shareUrlInput.value);
            setStatus('Short link copied.');
        } catch (error) {
            setStatus('The short link could not be copied yet.');
        }
    });

    sidePanelToggle?.addEventListener('click', () => {
        syncSidePanelState(!sermonWorkspace.classList.contains('is-side-panel-open'));
    });

    sidePanelClose?.addEventListener('click', () => {
        syncSidePanelState(false);
    });

    boardRoot?.addEventListener('click', (event) => {
        const target = event.target;
        const addButton = target instanceof Element ? target.closest('[data-board-add-card]') : null;
        const removeButton = target instanceof Element ? target.closest('[data-board-remove-card]') : null;

        if (addButton instanceof HTMLElement) {
            const columnKey = addButton.getAttribute('data-board-add-card');
            const cardList = columnKey ? boardRoot.querySelector(`[data-board-card-list="${columnKey}"]`) : null;

            if (cardList instanceof HTMLElement) {
                cardList.appendChild(createBoardCard(''));
                syncHiddenFields();
            }
        }

        if (removeButton instanceof HTMLElement) {
            const card = removeButton.closest('.sermon-board-card');

            if (card instanceof HTMLElement) {
                card.remove();
                syncHiddenFields();
            }
        }
    });

    boardRoot?.addEventListener('input', syncHiddenFields);
    sermonWorkspace.querySelectorAll('[data-reference-group]').forEach((input) => {
        input.addEventListener('change', syncHiddenFields);
        input.addEventListener('blur', syncHiddenFields);
    });

    noteForm?.addEventListener('submit', () => {
        syncHiddenFields();
    });

    renderVerseRefs();
    syncSidePanelState(false);
    syncHiddenFields();
}

function parseJsonArray(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }

    try {
        const parsed = JSON.parse(value);

        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}
