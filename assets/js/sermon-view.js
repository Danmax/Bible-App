const sermonView = document.querySelector('[data-sermon-view]');

if (sermonView instanceof HTMLElement) {
    const content = sermonView.querySelector('[data-sermon-view-content]');
    const modal = document.querySelector('[data-sermon-study-modal]');
    const modalContent = modal?.querySelector('[data-sermon-study-modal-content]');
    const closeButton = modal?.querySelector('[data-sermon-study-close]');
    const referenceNode = modal?.querySelector('[data-sermon-study-reference]');
    const translationNode = modal?.querySelector('[data-sermon-study-translation]');
    const textNode = modal?.querySelector('[data-sermon-study-text]');
    const readerLink = modal?.querySelector('[data-sermon-study-reader]');

    const parseVerses = () => {
        try {
            const parsed = JSON.parse(sermonView.getAttribute('data-sermon-verses') || '[]');

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    };

    const verses = parseVerses();
    const versesById = new Map();
    const versesByReference = new Map();

    verses.forEach((verse) => {
        const verseId = Number(verse.verse_id || 0);
        const reference = String(verse.reference_label || '').trim().toLowerCase();

        if (verseId > 0) {
            versesById.set(verseId, verse);
        }

        if (reference !== '') {
            versesByReference.set(reference, verse);
        }
    });

    const readerUrlForVerse = (verse) => {
        const storedUrl = String(verse.reader_url || '').trim();

        if (storedUrl !== '') {
            return storedUrl;
        }

        const params = new URLSearchParams();
        const translation = String(verse.translation || '').trim();
        const bookId = Number(verse.book_id || 0);
        const chapter = Number(verse.chapter_number || 0);
        const verseNumber = Number(verse.verse_number || 0);

        if (translation !== '') {
            params.set('translation', translation);
        }

        if (bookId > 0) {
            params.set('book_id', String(bookId));
        }

        if (chapter > 0) {
            params.set('chapter', String(chapter));
        }

        if (verseNumber > 0) {
            params.set('verse', String(verseNumber));
        }

        const query = params.toString();

        return `bible.php${query === '' ? '' : `?${query}`}`;
    };

    const verseFromElement = (element) => {
        const verseId = Number(element.getAttribute('data-verse-id') || '0');

        if (verseId > 0 && versesById.has(verseId)) {
            return versesById.get(verseId);
        }

        const reference = String(
            element.getAttribute('data-verse-reference')
            || element.getAttribute('data-reference-label')
            || element.textContent
            || ''
        ).trim();

        if (reference !== '' && versesByReference.has(reference.toLowerCase())) {
            return versesByReference.get(reference.toLowerCase());
        }

        return {
            verse_id: verseId,
            book_id: Number(element.getAttribute('data-book-id') || '0'),
            chapter_number: Number(element.getAttribute('data-chapter-number') || '0'),
            verse_number: Number(element.getAttribute('data-verse-number') || '0'),
            reference_label: reference || 'Scripture',
            verse_text: element.getAttribute('data-verse-text') || '',
            translation: element.getAttribute('data-translation') || '',
        };
    };

    const openStudyModal = (verse) => {
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        if (referenceNode instanceof HTMLElement) {
            referenceNode.textContent = String(verse.reference_label || 'Scripture');
        }

        if (translationNode instanceof HTMLElement) {
            translationNode.textContent = String(verse.translation || '');
        }

        if (textNode instanceof HTMLElement) {
            textNode.textContent = String(verse.verse_text || 'This verse text is not attached to the note yet.');
        }

        if (readerLink instanceof HTMLAnchorElement) {
            readerLink.href = readerUrlForVerse(verse);
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        closeButton?.focus();
    };

    const closeStudyModal = () => {
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    };

    const hydrateInlineScriptureLinks = () => {
        if (!(content instanceof HTMLElement)) {
            return;
        }

        content.querySelectorAll('.note-scripture-link, .note-verse-chip').forEach((element) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            const verse = verseFromElement(element);

            element.setAttribute('data-sermon-study-ref', 'inline');
            element.setAttribute('role', 'button');
            element.setAttribute('tabindex', '0');
            element.setAttribute('title', `Study ${String(verse.reference_label || 'this verse')}`);

            if (!element.getAttribute('data-verse-id') && Number(verse.verse_id || 0) > 0) {
                element.setAttribute('data-verse-id', String(verse.verse_id));
            }

            if (!element.getAttribute('data-verse-reference')) {
                element.setAttribute('data-verse-reference', verse.reference_label || element.textContent || 'Scripture');
            }
        });
    };

    hydrateInlineScriptureLinks();

    sermonView.addEventListener('click', (event) => {
        const target = event.target;
        const studyTrigger = target instanceof Element ? target.closest('[data-sermon-study-ref], .note-scripture-link, .note-verse-chip') : null;

        if (!(studyTrigger instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        openStudyModal(verseFromElement(studyTrigger));
    });

    sermonView.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const target = event.target;
        const studyTrigger = target instanceof Element ? target.closest('[data-sermon-study-ref], .note-scripture-link, .note-verse-chip') : null;

        if (!(studyTrigger instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        openStudyModal(verseFromElement(studyTrigger));
    });

    closeButton?.addEventListener('click', closeStudyModal);
    modal?.addEventListener('click', (event) => {
        if (!(modalContent instanceof HTMLElement) || !(event.target instanceof Node)) {
            return;
        }

        if (!modalContent.contains(event.target)) {
            closeStudyModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeStudyModal();
        }
    });
}
