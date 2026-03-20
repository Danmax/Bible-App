const menuToggle = document.querySelector('.menu-toggle');
const primaryNav = document.querySelector('.primary-nav');

if (menuToggle && primaryNav) {
    menuToggle.addEventListener('click', () => {
        const isOpen = primaryNav.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}

const filterGroups = document.querySelectorAll('[data-filter-group]');

filterGroups.forEach((group) => {
    const buttons = group.querySelectorAll('[data-filter]');
    const results = document.querySelector('[data-filter-results]');

    if (!results) {
        return;
    }

    const cards = results.querySelectorAll('[data-category]');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const filter = button.getAttribute('data-filter');

            buttons.forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');

            cards.forEach((card) => {
                const category = card.getAttribute('data-category');
                const shouldShow = filter === 'all' || category === filter;
                card.classList.toggle('is-hidden', !shouldShow);
            });
        });
    });
});

const yearNode = document.querySelector('#year');

if (yearNode) {
    yearNode.textContent = String(new Date().getFullYear());
}

const readerNav = document.querySelector('[data-reader-nav]');

if (readerNav) {
    const bookSelect = readerNav.querySelector('[data-reader-select="book"]');
    const chapterSelect = readerNav.querySelector('[data-reader-select="chapter"]');
    const verseSelect = readerNav.querySelector('[data-reader-select="verse"]');

    const submitNav = () => {
        if (readerNav instanceof HTMLFormElement) {
            readerNav.requestSubmit();
        }
    };

    bookSelect?.addEventListener('change', () => {
        if (chapterSelect) {
            chapterSelect.value = '';
        }

        if (verseSelect) {
            verseSelect.value = '';
        }

        submitNav();
    });

    chapterSelect?.addEventListener('change', () => {
        if (verseSelect) {
            verseSelect.value = '';
        }

        submitNav();
    });

    verseSelect?.addEventListener('change', submitNav);
}

const chapterReader = document.querySelector('[data-chapter-reader]');
const bookmarkPopup = document.querySelector('[data-bookmark-popup]');
const bookmarkPopupForm = document.querySelector('[data-bookmark-popup-form]');

if (chapterReader && bookmarkPopup && bookmarkPopupForm) {
    const popupModeLabel = bookmarkPopup.querySelector('[data-popup-mode-label]');
    const popupReference = bookmarkPopup.querySelector('[data-popup-reference]');
    const popupPreview = bookmarkPopup.querySelector('[data-popup-preview]');
    const popupClose = bookmarkPopup.querySelector('[data-popup-close]');
    const popupClear = bookmarkPopup.querySelector('[data-popup-clear]');
    const popupNoteLink = bookmarkPopup.querySelector('[data-popup-note-link]');
    const colorPicker = bookmarkPopup.querySelector('[data-color-picker]');
    const colorInput = bookmarkPopupForm.querySelector('input[name="highlight_color"]');
    const actionInput = bookmarkPopupForm.querySelector('input[name="action"]');
    const verseIdInput = bookmarkPopupForm.querySelector('input[name="verse_id"]');
    const selectedTextInput = bookmarkPopupForm.querySelector('input[name="selected_text"]');
    const selectionStartInput = bookmarkPopupForm.querySelector('input[name="selection_start"]');
    const selectionEndInput = bookmarkPopupForm.querySelector('input[name="selection_end"]');
    const tagInput = bookmarkPopupForm.querySelector('input[name="tag"]');
    const noteInput = bookmarkPopupForm.querySelector('textarea[name="note"]');

    const resetPopupFields = () => {
        actionInput.value = 'save-bookmark';
        verseIdInput.value = '';
        selectedTextInput.value = '';
        selectionStartInput.value = '';
        selectionEndInput.value = '';

        if (tagInput) {
            tagInput.value = '';
        }

        if (noteInput) {
            noteInput.value = '';
        }
    };

    const hidePopup = () => {
        bookmarkPopup.hidden = true;
    };

    const setActiveColor = (color) => {
        if (!colorPicker || !colorInput) {
            return;
        }

        colorInput.value = color;

        colorPicker.querySelectorAll('[data-color]').forEach((button) => {
            button.classList.toggle('is-active', button.getAttribute('data-color') === color);
        });
    };

    const openPopupForVerse = (verseCard, selectedText = '', selectionStart = '', selectionEnd = '') => {
        const verseId = verseCard.getAttribute('data-verse-id') || '';
        const verseReference = verseCard.getAttribute('data-verse-reference') || 'Verse';
        const verseText = verseCard.getAttribute('data-verse-text') || '';

        verseIdInput.value = verseId;
        selectedTextInput.value = selectedText;
        selectionStartInput.value = selectionStart;
        selectionEndInput.value = selectionEnd;
        actionInput.value = selectedText ? 'save-section' : 'save-bookmark';

        if (popupModeLabel) {
            popupModeLabel.textContent = selectedText ? 'Highlight Selection' : 'Save Verse';
        }

        if (popupReference) {
            popupReference.textContent = verseReference;
        }

        if (popupPreview) {
            popupPreview.textContent = selectedText
                ? `"${selectedText}"`
                : verseText;
        }

        if (popupNoteLink) {
            popupNoteLink.setAttribute('href', `/notes.php?verse_id=${encodeURIComponent(verseId)}`);
        }

        bookmarkPopup.hidden = false;
    };

    const updateSelection = () => {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0);
        const startVerse = range.startContainer.parentElement?.closest('.reader-verse-text');
        const endVerse = range.endContainer.parentElement?.closest('.reader-verse-text');

        if (!startVerse || !endVerse || startVerse !== endVerse) {
            return;
        }

        const selectedText = selection.toString().trim();

        if (!selectedText) {
            return;
        }

        const verseCard = startVerse.closest('[data-verse-card]');

        if (!verseCard) {
            return;
        }

        const preRange = range.cloneRange();
        preRange.selectNodeContents(startVerse);
        preRange.setEnd(range.startContainer, range.startOffset);

        const start = preRange.toString().length;
        const end = start + range.toString().length;

        openPopupForVerse(verseCard, selectedText, String(start), String(end));
    };

    chapterReader.querySelectorAll('[data-verse-card]').forEach((verseCard) => {
        verseCard.addEventListener('click', (event) => {
            const selection = window.getSelection();

            if (selection && !selection.isCollapsed) {
                return;
            }

            openPopupForVerse(verseCard);
        });
    });

    chapterReader.addEventListener('mouseup', () => {
        window.setTimeout(updateSelection, 0);
    });

    chapterReader.addEventListener('keyup', () => {
        window.setTimeout(updateSelection, 0);
    });

    colorPicker?.querySelectorAll('[data-color]').forEach((button) => {
        button.addEventListener('click', () => {
            const color = button.getAttribute('data-color');

            if (color) {
                setActiveColor(color);
            }
        });
    });

    popupClose?.addEventListener('click', () => {
        window.getSelection()?.removeAllRanges();
        hidePopup();
    });

    popupClear?.addEventListener('click', () => {
        window.getSelection()?.removeAllRanges();
        resetPopupFields();
        hidePopup();
    });

    bookmarkPopupForm.addEventListener('submit', (event) => {
        if (!verseIdInput.value) {
            event.preventDefault();
            hidePopup();
        }

        if (actionInput.value === 'save-section' && !selectedTextInput.value) {
            event.preventDefault();
            hidePopup();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            window.getSelection()?.removeAllRanges();
            hidePopup();
        }
    });

    resetPopupFields();
    setActiveColor(colorInput?.value || 'neon-yellow');
}
