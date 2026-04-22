const menuToggle = document.querySelector('.menu-toggle');
const primaryNav = document.querySelector('.primary-nav');

if (menuToggle && primaryNav) {
    const setMobileBodyLock = (isLocked) => {
        if (isLocked) {
            const scrollY = window.scrollY || window.pageYOffset || 0;
            document.body.dataset.navScrollY = String(scrollY);
            document.body.style.top = `-${scrollY}px`;
            document.body.classList.add('nav-open');
            return;
        }

        const previousScrollY = Number(document.body.dataset.navScrollY || '0') || 0;
        document.body.classList.remove('nav-open');
        document.body.style.top = '';
        delete document.body.dataset.navScrollY;
        window.scrollTo(0, previousScrollY);
    };

    const syncMobileNavState = (isOpen) => {
        primaryNav.classList.toggle('is-open', isOpen);
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        setMobileBodyLock(isOpen);
    };

    menuToggle.addEventListener('click', () => {
        syncMobileNavState(!primaryNav.classList.contains('is-open'));
    });

    primaryNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            syncMobileNavState(false);
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 760 && primaryNav.classList.contains('is-open')) {
            syncMobileNavState(false);
        }
    });
}

const siteHeader = document.querySelector('.site-header');

if (siteHeader) {
    let lastScrollY = window.scrollY;
    let ticking = false;
    let hideTimer = null;

    const updateHeader = () => {
        const currentScrollY = window.scrollY;
        const scrolledDown = currentScrollY > lastScrollY;
        const pastThreshold = currentScrollY > 80;

        if (scrolledDown && pastThreshold) {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                siteHeader.classList.add('is-hidden');
            }, 120);
        } else {
            clearTimeout(hideTimer);
            siteHeader.classList.remove('is-hidden');
        }

        lastScrollY = currentScrollY;
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }, { passive: true });
}

const moreMenus = document.querySelectorAll('.more-nav');

moreMenus.forEach((moreMenu) => {
    const links = moreMenu.querySelectorAll('.more-nav-menu a');

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!(link instanceof HTMLAnchorElement)) {
                return;
            }

            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const href = link.href;

            if (href === '') {
                return;
            }

            event.preventDefault();
            moreMenu.classList.add('is-closing');

            window.setTimeout(() => {
                moreMenu.open = false;
                moreMenu.classList.remove('is-closing');

                if (primaryNav && menuToggle) {
                    primaryNav.classList.remove('is-open');
                    menuToggle.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('nav-open');
                }

                window.location.assign(href);
            }, 150);
        });
    });
});

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

const appThemeSelects = document.querySelectorAll('[data-app-theme-select]');
const appThemeStatusNodes = document.querySelectorAll('[data-app-theme-status]');
const appThemeSwatches = document.querySelectorAll('[data-app-theme-option]');

if (appThemeSelects.length > 0 || appThemeSwatches.length > 0) {
    const themeMetaColors = {
        'good-news': '#22333b',
        spring: '#5f8f52',
        summer: '#1d6fa3',
        fall: '#8c4b22',
        winter: '#496c88',
        'wood-cabin': '#6b4423',
        swordsman: '#4d6275',
        'dark-mode': '#15181d',
    };
    const defaultTheme = 'good-news';
    const allowedThemes = Object.keys(themeMetaColors);
    const themeColorMeta = document.querySelector('meta[name="theme-color"]');

    const normalizeAppTheme = (theme) => (
        allowedThemes.includes(String(theme || '').trim()) ? String(theme).trim() : defaultTheme
    );

    const setThemeStatus = (message) => {
        appThemeStatusNodes.forEach((node) => {
            if (node instanceof HTMLElement) {
                node.textContent = message;
            }
        });
    };

    const syncThemeControls = (theme) => {
        appThemeSelects.forEach((selectNode) => {
            if (selectNode instanceof HTMLSelectElement) {
                selectNode.value = theme;
            }
        });

        appThemeSwatches.forEach((buttonNode) => {
            if (buttonNode instanceof HTMLElement) {
                buttonNode.classList.toggle('is-active', buttonNode.getAttribute('data-app-theme-option') === theme);
            }
        });
    };

    const applyAppTheme = (theme, { announce = false } = {}) => {
        const normalizedTheme = normalizeAppTheme(theme);
        document.documentElement.setAttribute('data-theme', normalizedTheme);

        if (themeColorMeta instanceof HTMLMetaElement) {
            themeColorMeta.setAttribute('content', themeMetaColors[normalizedTheme] || themeMetaColors[defaultTheme]);
        }

        try {
            window.localStorage.setItem('app-theme', normalizedTheme);
        } catch (error) {
            // Ignore storage failures and keep the in-memory theme.
        }

        syncThemeControls(normalizedTheme);

        if (announce) {
            const selectedLabel = appThemeSelects[0] instanceof HTMLSelectElement
                ? appThemeSelects[0].selectedOptions[0]?.textContent?.trim() || 'Theme'
                : 'Theme';
            setThemeStatus(`${selectedLabel} theme applied on this device.`);
        }
    };

    let initialTheme = defaultTheme;

    try {
        initialTheme = normalizeAppTheme(window.localStorage.getItem('app-theme'));
    } catch (error) {
        initialTheme = normalizeAppTheme(document.documentElement.getAttribute('data-theme'));
    }

    applyAppTheme(initialTheme);

    appThemeSelects.forEach((selectNode) => {
        selectNode.addEventListener('change', () => {
            if (selectNode instanceof HTMLSelectElement) {
                applyAppTheme(selectNode.value, { announce: true });
            }
        });
    });

    appThemeSwatches.forEach((buttonNode) => {
        buttonNode.addEventListener('click', () => {
            const nextTheme = buttonNode.getAttribute('data-app-theme-option');

            if (nextTheme) {
                applyAppTheme(nextTheme, { announce: true });
            }

            const themeNav = buttonNode.closest('[data-theme-nav]');

            if (themeNav instanceof HTMLDetailsElement && themeNav.open) {
                themeNav.classList.add('is-closing');
                window.setTimeout(() => {
                    themeNav.open = false;
                    themeNav.classList.remove('is-closing');
                }, 150);
            }
        });
    });
}

const goodNewsRadioSections = document.querySelectorAll('[data-good-news-radio]');

goodNewsRadioSections.forEach((section) => {
    if (!(section instanceof HTMLElement)) {
        return;
    }

    const audioNode = section.querySelector('[data-radio-audio]');
    const videoWrapperNode = section.querySelector('[data-radio-video-wrapper]');
    const videoNode = section.querySelector('[data-radio-video]');
    const kindNode = section.querySelector('[data-radio-kind]');
    const nameNode = section.querySelector('[data-radio-name]');
    const taglineNode = section.querySelector('[data-radio-tagline]');
    const linkNode = section.querySelector('[data-radio-link]');
    const liveIndicatorNode = section.querySelector('[data-radio-live-indicator]');
    const stationButtons = Array.from(section.querySelectorAll('[data-radio-station]'));

    if (!(audioNode instanceof HTMLAudioElement) || stationButtons.length === 0) {
        return;
    }

    const applyRadioStation = (buttonNode, { autoplay = false } = {}) => {
        if (!(buttonNode instanceof HTMLButtonElement)) {
            return;
        }

        const streamUrl = String(buttonNode.dataset.streamUrl || '').trim();
        const listenUrl = String(buttonNode.dataset.listenUrl || '').trim();
        const name = String(buttonNode.dataset.name || '').trim();
        const kind = String(buttonNode.dataset.kind || '').trim();
        const tagline = String(buttonNode.dataset.tagline || '').trim();
        const youtubePlaylistId = String(buttonNode.dataset.youtubePlaylistId || '').trim();
        const isLive = buttonNode.dataset.isLive === '1';
        const liveVideoId = String(buttonNode.dataset.liveVideoId || '').trim();
        const shouldResumePlayback = autoplay || !audioNode.paused;
        const streamChanged = audioNode.currentSrc !== streamUrl && audioNode.getAttribute('src') !== streamUrl;
        const playlistEmbedUrl = youtubePlaylistId !== ''
            ? `https://www.youtube-nocookie.com/embed/videoseries?list=${encodeURIComponent(youtubePlaylistId)}`
            : '';
        const liveEmbedUrl = isLive && liveVideoId !== ''
            ? `https://www.youtube-nocookie.com/embed/${encodeURIComponent(liveVideoId)}`
            : '';
        const activeEmbedUrl = liveEmbedUrl !== '' ? liveEmbedUrl : playlistEmbedUrl;

        stationButtons.forEach((stationButton) => {
            stationButton.classList.toggle('is-active', stationButton === buttonNode);
            stationButton.setAttribute('aria-pressed', stationButton === buttonNode ? 'true' : 'false');
        });

        if (kindNode instanceof HTMLElement) {
            kindNode.textContent = kind;
        }

        if (nameNode instanceof HTMLElement) {
            nameNode.textContent = name;
        }

        if (taglineNode instanceof HTMLElement) {
            taglineNode.textContent = tagline;
        }

        if (linkNode instanceof HTMLAnchorElement && listenUrl !== '') {
            linkNode.href = listenUrl;
        }

        if (liveIndicatorNode instanceof HTMLElement) {
            liveIndicatorNode.hidden = !isLive;
        }

        if (activeEmbedUrl !== '') {
            audioNode.pause();

            if (videoWrapperNode instanceof HTMLElement) {
                videoWrapperNode.hidden = false;
            }

            if (videoNode instanceof HTMLIFrameElement && videoNode.src !== activeEmbedUrl) {
                videoNode.src = activeEmbedUrl;
            }

            audioNode.hidden = true;
        } else {
            if (videoWrapperNode instanceof HTMLElement) {
                videoWrapperNode.hidden = true;
            }

            if (videoNode instanceof HTMLIFrameElement && videoNode.src !== '') {
                videoNode.src = '';
            }

            audioNode.hidden = false;

            if (streamChanged && streamUrl !== '') {
                audioNode.pause();
                audioNode.src = streamUrl;
                audioNode.load();
            }

            if (shouldResumePlayback) {
                audioNode.play().catch(() => {
                    // Ignore autoplay rejections and leave controls available for manual play.
                });
            }
        }
    };

    stationButtons.forEach((buttonNode) => {
        if (!(buttonNode instanceof HTMLButtonElement)) {
            return;
        }

        buttonNode.addEventListener('click', () => {
            applyRadioStation(buttonNode, { autoplay: true });
        });
    });
});

const panelGroups = document.querySelectorAll('[data-community-panels]');
const modalCloseParamMap = {
    compose: ['edit'],
    goal: ['edit_goal'],
    event: ['edit_event'],
    note: ['edit', 'verse_id'],
};
const modalOpenerMap = new WeakMap();

const syncModalBodyLock = () => {
    const hasOpenModal = Array.from(document.querySelectorAll('[data-panel-modal]')).some((panel) => !panel.hidden);
    document.body.classList.toggle('modal-open', hasOpenModal);
};

const getFocusableModalElements = (panel) => {
    if (!(panel instanceof HTMLElement)) {
        return [];
    }

    return Array.from(panel.querySelectorAll(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => element instanceof HTMLElement && !element.hidden && element.offsetParent !== null);
};

const focusModalPanel = (panel) => {
    if (!(panel instanceof HTMLElement)) {
        return;
    }

    window.requestAnimationFrame(() => {
        const focusableElements = getFocusableModalElements(panel);
        const firstFocusableElement = focusableElements[0];

        if (firstFocusableElement instanceof HTMLElement) {
            firstFocusableElement.focus();
            return;
        }

        panel.setAttribute('tabindex', '-1');
        panel.focus();
    });
};

const rememberModalOpener = (panel, opener) => {
    if (panel instanceof HTMLElement && opener instanceof HTMLElement) {
        modalOpenerMap.set(panel, opener);
    }
};

const restoreModalFocus = (panel) => {
    if (!(panel instanceof HTMLElement)) {
        return;
    }

    const opener = modalOpenerMap.get(panel);

    if (opener instanceof HTMLElement) {
        opener.focus();
        modalOpenerMap.delete(panel);
    }
};

const syncCloseParamsFromUrl = (panelName) => {
    const paramsToRemove = panelName ? modalCloseParamMap[panelName] : null;

    if (!paramsToRemove || typeof window.history.replaceState !== 'function') {
        return;
    }

    const url = new URL(window.location.href);
    let didChange = false;

    paramsToRemove.forEach((paramName) => {
        if (url.searchParams.has(paramName)) {
            url.searchParams.delete(paramName);
            didChange = true;
        }
    });

    if (didChange) {
        window.history.replaceState({}, '', url.toString());
    }
};

panelGroups.forEach((panelGroup) => {
    const panelNodes = Array.from(panelGroup.querySelectorAll('[data-community-panel]'));
    const toggleButtons = Array.from(panelGroup.querySelectorAll('[data-community-panel-toggle]'));
    const closeButtons = Array.from(panelGroup.querySelectorAll('[data-community-panel-close]'));
    const closeParamMap = {
        compose: ['edit'],
        goal: ['edit_goal'],
        event: ['edit_event'],
        note: ['edit', 'verse_id'],
    };

    const setPanelVisibility = (panel, shouldOpen) => {
        panel.hidden = !shouldOpen;
        panel.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
        panel.style.display = shouldOpen ? (panel.hasAttribute('data-panel-modal') ? 'flex' : '') : 'none';

        if (panel.hasAttribute('data-panel-modal')) {
            if (shouldOpen) {
                focusModalPanel(panel);
            } else {
                restoreModalFocus(panel);
            }
        }

        syncModalBodyLock();
    };

    const syncUrlOnClose = (panelName) => {
        const paramsToRemove = closeParamMap[panelName];

        if (!paramsToRemove || typeof window.history.replaceState !== 'function') {
            return;
        }

        const url = new URL(window.location.href);
        let didChange = false;

        paramsToRemove.forEach((paramName) => {
            if (url.searchParams.has(paramName)) {
                url.searchParams.delete(paramName);
                didChange = true;
            }
        });

        if (didChange) {
            window.history.replaceState({}, '', url.toString());
        }
    };

    const setPanelState = (panelName, shouldOpen) => {
        panelNodes.forEach((panel) => {
            const isTarget = panel.getAttribute('data-community-panel') === panelName;

            if (isTarget) {
                setPanelVisibility(panel, shouldOpen);
            } else if (shouldOpen) {
                setPanelVisibility(panel, false);
            }
        });

        toggleButtons.forEach((button) => {
            const isTarget = button.getAttribute('data-community-panel-toggle') === panelName;

            if (isTarget) {
                button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            } else if (shouldOpen) {
                button.setAttribute('aria-expanded', 'false');
            }
        });

        if (!shouldOpen) {
            syncUrlOnClose(panelName);
        }
    };

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const panelName = button.getAttribute('data-community-panel-toggle');

            if (!panelName) {
                return;
            }

            const panel = panelGroup.querySelector(`[data-community-panel="${panelName}"]`);

            if (!(panel instanceof HTMLElement)) {
                return;
            }

            const shouldOpen = panel.hidden;

            if (shouldOpen && panel.hasAttribute('data-panel-modal')) {
                rememberModalOpener(panel, button);
            }

            setPanelState(panelName, shouldOpen);

            if (shouldOpen && !panel.hasAttribute('data-panel-modal')) {
                panel.scrollIntoView({
                    block: 'start',
                    behavior: 'smooth',
                });
            }
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const panelName = button.getAttribute('data-community-panel-close');

            if (!panelName) {
                return;
            }

            setPanelState(panelName, false);
        });
    });

    panelNodes.forEach((panel) => {
        setPanelVisibility(panel, !panel.hidden);
    });
});

document.querySelectorAll('[data-panel-modal]').forEach((modalPanel) => {
    modalPanel.addEventListener('click', (event) => {
        const modalContent = modalPanel.querySelector('[data-panel-modal-content]');
        const targetNode = event.target;

        if (modalContent instanceof HTMLElement && targetNode instanceof Node && modalContent.contains(targetNode)) {
            return;
        }

        modalPanel.hidden = true;
        modalPanel.setAttribute('aria-hidden', 'true');
        modalPanel.style.display = 'none';

        const panelName = modalPanel.getAttribute('data-community-panel');
        const panelGroup = modalPanel.closest('[data-community-panels]');

        if (panelName && panelGroup) {
            const toggle = panelGroup.querySelector(`[data-community-panel-toggle="${panelName}"]`);

            if (toggle instanceof HTMLButtonElement) {
                toggle.setAttribute('aria-expanded', 'false');
            }

            syncCloseParamsFromUrl(panelName);
        }

        restoreModalFocus(modalPanel);
        syncModalBodyLock();
    });
});

// ── Edit-event modal ───────────────────────────────────────────────────────
(function () {
    const getPanelGroup = (el) =>
        el?.closest('[data-community-panels]') || document.querySelector('[data-community-panels]');

    const openComposePanel = (panelGroup, openerBtn) => {
        const composePanel = panelGroup?.querySelector('[data-community-panel="compose"]');
        const composeToggle = panelGroup?.querySelector('[data-community-panel-toggle="compose"]');
        if (!composePanel) return;

        if (composePanel.hidden) {
            if (openerBtn) rememberModalOpener(composePanel, openerBtn);
            composeToggle?.click();
        } else {
            const content = composePanel.querySelector('[data-panel-modal-content]');
            if (content) content.scrollTop = 0;
        }
    };

    const setField = (form, name, value) => {
        const field = form.querySelector(`[name="${name}"]`);
        if (!field) return;
        if (field.type === 'checkbox') {
            field.checked = Boolean(value);
        } else {
            field.value = value ?? '';
        }
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const getOrCreateHidden = (form, name) => {
        let field = form.querySelector(`[name="${name}"]`);
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = name;
            form.prepend(field);
        }
        return field;
    };

    const resetToCreateMode = (panelGroup) => {
        const form = panelGroup?.querySelector('[data-community-event-form]');
        const composePanel = panelGroup?.querySelector('[data-community-panel="compose"]');
        const composeToggle = panelGroup?.querySelector('[data-community-panel-toggle="compose"]');
        if (!form) return;

        getOrCreateHidden(form, 'action').value = 'create-event';

        const eventIdField = form.querySelector('[name="event_id"]');
        if (eventIdField) eventIdField.remove();

        const title = composePanel?.querySelector('#community-compose-modal-title');
        if (title) title.textContent = 'Create event';

        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.textContent = 'Create Event';

        if (composeToggle) composeToggle.textContent = 'Create Event';
    };

    // Reset form to create mode when "Create Event" button opens the modal
    document.querySelectorAll('[data-compose-create]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const panelGroup = getPanelGroup(btn);
            const composePanel = panelGroup?.querySelector('[data-community-panel="compose"]');
            // Only reset when we're about to open (currently hidden)
            if (composePanel?.hidden) {
                resetToCreateMode(panelGroup);
            }
        });
    });

    // Open and populate the compose modal with event data for editing
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-edit-event]');
        if (!btn) return;

        let data;
        try {
            data = JSON.parse(btn.getAttribute('data-edit-event') || '{}');
        } catch {
            return;
        }

        const panelGroup = getPanelGroup(btn);
        const form = panelGroup?.querySelector('[data-community-event-form]');
        const composePanel = panelGroup?.querySelector('[data-community-panel="compose"]');
        if (!form || !composePanel) return;

        // Switch to update mode
        getOrCreateHidden(form, 'action').value = 'update-event';
        getOrCreateHidden(form, 'event_id').value = String(data.id ?? '');

        // Populate all fields
        const fields = [
            'title', 'category_id', 'event_type', 'event_format', 'visibility',
            'image_url', 'location_name', 'location_address', 'meeting_url',
            'start_at', 'end_at', 'description', 'status',
            'custom_options_text', 'potluck_items_text',
        ];
        fields.forEach((name) => setField(form, name, data[name] ?? ''));

        const checkboxes = [
            'is_featured', 'reminder_three_days', 'reminder_same_day',
            'potluck_allow_self_pick', 'potluck_allow_custom_items', 'potluck_allow_host_assign',
        ];
        checkboxes.forEach((name) => setField(form, name, data[name] ?? false));

        // Update modal heading and submit button text
        const title = composePanel.querySelector('#community-compose-modal-title');
        if (title) title.textContent = 'Edit event';

        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.textContent = 'Update Event';

        // Close the manage panel if open, then open compose
        const managePanel = panelGroup?.querySelector('[data-community-panel="manage"]');
        if (managePanel && !managePanel.hidden) {
            panelGroup?.querySelector('[data-community-panel-close="manage"]')?.click();
        }

        openComposePanel(panelGroup, btn);
    });
}());

// ── Keyboard navigation ─────────────────────────────────────────────────────
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        if (event.key !== 'Tab') {
            return;
        }

        const openModals = Array.from(document.querySelectorAll('[data-panel-modal]')).filter((panel) => !panel.hidden);
        const lastOpenModal = openModals[openModals.length - 1];

        if (!(lastOpenModal instanceof HTMLElement)) {
            return;
        }

        const focusableElements = getFocusableModalElements(lastOpenModal);

        if (focusableElements.length === 0) {
            event.preventDefault();
            return;
        }

        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];
        const activeElement = document.activeElement;

        if (event.shiftKey && activeElement === firstFocusableElement) {
            event.preventDefault();
            lastFocusableElement.focus();
        } else if (!event.shiftKey && activeElement === lastFocusableElement) {
            event.preventDefault();
            firstFocusableElement.focus();
        }

        return;
    }

    const openModals = Array.from(document.querySelectorAll('[data-panel-modal]')).filter((panel) => !panel.hidden);
    const lastOpenModal = openModals[openModals.length - 1];

    if (!(lastOpenModal instanceof HTMLElement)) {
        return;
    }

    lastOpenModal.hidden = true;
    lastOpenModal.setAttribute('aria-hidden', 'true');
    lastOpenModal.style.display = 'none';

    const panelName = lastOpenModal.getAttribute('data-community-panel');
    const panelGroup = lastOpenModal.closest('[data-community-panels]');

    if (panelName && panelGroup) {
        const toggle = panelGroup.querySelector(`[data-community-panel-toggle="${panelName}"]`);

        if (toggle instanceof HTMLButtonElement) {
            toggle.setAttribute('aria-expanded', 'false');
        }

        syncCloseParamsFromUrl(panelName);
    }

    restoreModalFocus(lastOpenModal);
    syncModalBodyLock();
});

const createVoiceRecorder = ({
    triggerButton,
    stopButton,
    statusNode,
    csrfInput,
    onTranscript,
    onRecordingStateChange,
    listeningMessage,
    successMessage,
    unsupportedMessage,
    maxDurationMs = 0,
    maxDurationReachedMessage = 'Recording limit reached. Turning your words into text...',
    onDurationTick = null,
}) => {
    const MediaRecorderApi = window.MediaRecorder;
    const mediaDevices = navigator.mediaDevices;
    const canRecord = Boolean(MediaRecorderApi && mediaDevices && typeof mediaDevices.getUserMedia === 'function');
    let mediaRecorder = null;
    let mediaStream = null;
    let audioChunks = [];
    let maxDurationTimeoutId = null;
    let durationIntervalId = null;

    const setStatus = (message) => {
        if (statusNode instanceof HTMLElement) {
            statusNode.textContent = message;
        }
    };

    const stopMediaTracks = () => {
        if (mediaStream instanceof MediaStream) {
            mediaStream.getTracks().forEach((track) => track.stop());
        }

        mediaStream = null;
    };

    const clearMaxDurationTimer = () => {
        if (maxDurationTimeoutId !== null) {
            window.clearTimeout(maxDurationTimeoutId);
            maxDurationTimeoutId = null;
        }

        if (durationIntervalId !== null) {
            window.clearInterval(durationIntervalId);
            durationIntervalId = null;
        }
    };

    const setRecordingState = (isRecording) => {
        if (triggerButton instanceof HTMLButtonElement) {
            triggerButton.disabled = !canRecord;
            triggerButton.hidden = Boolean(stopButton) && isRecording;
            triggerButton.classList.toggle('is-recording', isRecording);
            triggerButton.setAttribute('aria-pressed', isRecording ? 'true' : 'false');
        }

        if (stopButton instanceof HTMLButtonElement) {
            stopButton.hidden = !isRecording;
        }

        if (typeof onRecordingStateChange === 'function') {
            onRecordingStateChange(isRecording);
        }

        if (!isRecording) {
            clearMaxDurationTimer();
        }
    };

    const pickRecordingMimeType = () => {
        const candidates = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/mpeg',
        ];

        for (const candidate of candidates) {
            if (typeof MediaRecorderApi?.isTypeSupported === 'function' && MediaRecorderApi.isTypeSupported(candidate)) {
                return candidate;
            }
        }

        return '';
    };

    const uploadRecording = async () => {
        if (!(csrfInput instanceof HTMLInputElement) || audioChunks.length === 0) {
            throw new Error('Record a short message first, then try again.');
        }

        const mimeType = mediaRecorder?.mimeType || pickRecordingMimeType() || 'audio/webm';
        const extension = mimeType.includes('mp4')
            ? 'mp4'
            : (mimeType.includes('mpeg') ? 'mp3' : 'webm');
        const formData = new FormData();
        const audioBlob = new Blob(audioChunks, { type: mimeType });

        formData.set('csrf_token', csrfInput.value);
        formData.set('audio', audioBlob, `voice-recording.${extension}`);

        const response = await fetch('voice-transcribe.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const rawText = await response.text();
        let payload = null;

        try {
            payload = rawText ? JSON.parse(rawText) : {};
        } catch (parseError) {
            throw new Error(rawText.trim() || 'We could not read the voice response just yet.');
        }

        if (!response.ok) {
            throw new Error(payload.error || 'We could not finish the voice transcription right now.');
        }

        const transcript = String(payload.text || '').trim();

        if (transcript === '') {
            throw new Error('We did not hear anything yet. Try speaking again.');
        }

        onTranscript(transcript, payload);
        setStatus(successMessage(payload));
    };

    if (!(triggerButton instanceof HTMLButtonElement)) {
        return {
            isSupported: false,
            setUnsupported: () => {},
        };
    }

    if (!canRecord) {
        triggerButton.disabled = true;
        setStatus(unsupportedMessage);

        return {
            isSupported: false,
            setUnsupported: () => setStatus(unsupportedMessage),
        };
    }

    triggerButton.addEventListener('click', async () => {
        if (!(stopButton instanceof HTMLButtonElement) && mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
            return;
        }

        try {
            audioChunks = [];
            mediaStream = await mediaDevices.getUserMedia({ audio: true });

            const preferredMimeType = pickRecordingMimeType();
            mediaRecorder = preferredMimeType !== ''
                ? new MediaRecorderApi(mediaStream, { mimeType: preferredMimeType })
                : new MediaRecorderApi(mediaStream);

            mediaRecorder.addEventListener('dataavailable', (event) => {
                if (event.data && event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            });

            mediaRecorder.addEventListener('stop', async () => {
                setRecordingState(false);
                stopMediaTracks();
                setStatus('Turning your words into text...');

                try {
                    await uploadRecording();
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'We could not finish the voice transcription right now.';
                    setStatus(message);
                } finally {
                    audioChunks = [];
                    mediaRecorder = null;
                }
            });

            setRecordingState(true);
            setStatus(listeningMessage);
            mediaRecorder.start();

            if (Number.isFinite(maxDurationMs) && maxDurationMs > 0) {
                clearMaxDurationTimer();
                const startedAt = Date.now();

                if (typeof onDurationTick === 'function') {
                    onDurationTick(Math.ceil(maxDurationMs / 1000));
                }

                durationIntervalId = window.setInterval(() => {
                    if (typeof onDurationTick !== 'function') {
                        return;
                    }

                    const elapsedMs = Date.now() - startedAt;
                    const remainingMs = Math.max(0, maxDurationMs - elapsedMs);
                    onDurationTick(Math.ceil(remainingMs / 1000));
                }, 250);

                maxDurationTimeoutId = window.setTimeout(() => {
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        setStatus(maxDurationReachedMessage);
                        mediaRecorder.stop();
                    }
                }, maxDurationMs);
            }
        } catch (error) {
            stopMediaTracks();
            clearMaxDurationTimer();
            const message = error instanceof Error ? error.message : 'Microphone access was not granted yet.';
            setStatus(message);
            setRecordingState(false);
        }
    });

    stopButton?.addEventListener('click', () => {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    });

    setRecordingState(false);

    return {
        isSupported: true,
        setUnsupported: () => setStatus(unsupportedMessage),
    };
};

const aiEventBuilders = document.querySelectorAll('[data-ai-event-builder]');

aiEventBuilders.forEach((aiEventBuilder) => {
    const panel = aiEventBuilder.closest('[data-community-panel]');
    const linkedForm = panel?.querySelector('[data-ai-event-form]') || document.querySelector('[data-community-event-form]');

    if (!(linkedForm instanceof HTMLFormElement)) {
        return;
    }

    const endpoint = aiEventBuilder.getAttribute('data-ai-endpoint') || '';
    const promptField = aiEventBuilder.querySelector('[data-ai-prompt]');
    const generateButton = aiEventBuilder.querySelector('[data-ai-generate]');
    const voiceStartButton = aiEventBuilder.querySelector('[data-ai-voice-start]');
    const voiceStopButton = aiEventBuilder.querySelector('[data-ai-voice-stop]');
    const statusNode = aiEventBuilder.querySelector('[data-ai-status]');
    const csrfInput = linkedForm.querySelector('input[name="csrf_token"]');
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;

    const setStatus = (message) => {
        if (statusNode) {
            statusNode.textContent = message;
        }
    };

    const setGeneratingState = (isGenerating) => {
        if (generateButton instanceof HTMLButtonElement) {
            generateButton.disabled = isGenerating;
            generateButton.textContent = isGenerating ? 'Drafting...' : 'Create Draft';
        }

        if (voiceStartButton instanceof HTMLButtonElement) {
            voiceStartButton.disabled = isGenerating;
        }

        if (voiceStopButton instanceof HTMLButtonElement) {
            voiceStopButton.disabled = isGenerating;
        }
    };

    const fillDraftFields = (draft) => {
        Object.entries(draft).forEach(([key, value]) => {
            const field = linkedForm.querySelector(`[data-ai-field="${key}"]`);

            if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLTextAreaElement) && !(field instanceof HTMLSelectElement)) {
                return;
            }

            if (field instanceof HTMLInputElement && field.type === 'checkbox') {
                field.checked = String(value) === '1' || value === true;
            } else {
                field.value = String(value ?? '');
            }

            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    };

    generateButton?.addEventListener('click', async () => {
        if (!(promptField instanceof HTMLTextAreaElement) || !(csrfInput instanceof HTMLInputElement) || endpoint === '') {
            return;
        }

        const prompt = promptField.value.trim();

        if (prompt === '') {
            setStatus('Add a prompt first.');
            promptField.focus();
            return;
        }

        setGeneratingState(true);
        setStatus('Building an event draft...');

        const formData = new FormData();
        formData.set('csrf_token', csrfInput.value);
        formData.set('prompt', prompt);
        const contextFields = linkedForm.querySelectorAll('[data-ai-context-field]');

        contextFields.forEach((field) => {
            const fieldName = field.getAttribute('data-ai-context-field');

            if (!fieldName) {
                return;
            }

            if ((field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) && field.value.trim() !== '') {
                formData.set(fieldName, field.value.trim());
            }
        });

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const rawText = await response.text();
            let payload = null;

            try {
                payload = rawText ? JSON.parse(rawText) : {};
            } catch (parseError) {
                throw new Error(rawText.trim() || 'The AI draft response was not valid JSON.');
            }

            if (!response.ok) {
                throw new Error(payload.error || 'The AI draft could not be created.');
            }

            fillDraftFields(payload.draft || {});
            setStatus(`Draft ready from ${payload.model || 'OpenAI'}. Review the fields before saving.`);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'The AI draft could not be created.';
            setStatus(message);
        } finally {
            setGeneratingState(false);
        }
    });

    const voiceRecorder = createVoiceRecorder({
        triggerButton: voiceStartButton instanceof HTMLButtonElement ? voiceStartButton : null,
        stopButton: voiceStopButton instanceof HTMLButtonElement ? voiceStopButton : null,
        statusNode,
        csrfInput: csrfInput instanceof HTMLInputElement ? csrfInput : null,
        onTranscript: (transcript) => {
            if (!(promptField instanceof HTMLTextAreaElement)) {
                return;
            }

            const promptBase = promptField.value.trim();
            promptField.value = [promptBase, transcript].filter(Boolean).join(promptBase && transcript ? ' ' : '');
        },
        listeningMessage: 'Listening... speak your event details.',
        successMessage: (payload) => `Voice input captured with ${payload.model || 'OpenAI'}. You can edit the prompt or create a draft.`,
        unsupportedMessage: 'Voice input is not supported in this browser. You can still type a prompt and create a draft.',
    });

    if (SpeechRecognitionApi && promptField instanceof HTMLTextAreaElement) {
        recognition = new SpeechRecognitionApi();
        recognition.lang = 'en-US';
        recognition.interimResults = true;
        recognition.continuous = true;

        let transcriptBase = '';

        recognition.addEventListener('start', () => {
            transcriptBase = promptField.value.trim();

            if (voiceStartButton instanceof HTMLButtonElement) {
                voiceStartButton.hidden = true;
            }

            if (voiceStopButton instanceof HTMLButtonElement) {
                voiceStopButton.hidden = false;
            }

            setStatus('Listening... speak your event details.');
        });

        recognition.addEventListener('result', (event) => {
            const transcript = Array.from(event.results)
                .map((result) => result[0]?.transcript || '')
                .join(' ')
                .trim();

            promptField.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? ' ' : '');
        });

        recognition.addEventListener('end', () => {
            if (voiceStartButton instanceof HTMLButtonElement) {
                voiceStartButton.hidden = false;
            }

            if (voiceStopButton instanceof HTMLButtonElement) {
                voiceStopButton.hidden = true;
            }

            setStatus('Voice input stopped. You can edit the prompt or create a draft.');
        });

        recognition.addEventListener('error', (event) => {
            setStatus(`Voice input error: ${event.error}`);
        });

        voiceStartButton?.addEventListener('click', () => {
            try {
                recognition?.start();
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Voice input could not start.';
                setStatus(message);
            }
        });

        voiceStopButton?.addEventListener('click', () => {
            recognition?.stop();
        });
    } else if (!voiceRecorder.isSupported) {
        voiceRecorder.setUnsupported();
    }
});

document.querySelectorAll('[data-community-event-form]').forEach((communityEventForm) => {
    const formatField = communityEventForm.querySelector('[data-community-event-format]');
    const potluckPanel = communityEventForm.querySelector('[data-community-potluck-options]');
    const potluckSeedBuilder = communityEventForm.querySelector('[data-community-potluck-seed-builder]');
    const potluckPresetButtons = communityEventForm.querySelectorAll('[data-potluck-preset]');
    const potluckSeedList = communityEventForm.querySelector('[data-potluck-seed-list]');
    const potluckSeedOutput = communityEventForm.querySelector('[data-potluck-seed-output]');
    const potluckSeedAddButton = communityEventForm.querySelector('[data-potluck-seed-add]');
    const aiPromptField = document.querySelector('[data-ai-event-builder] [data-ai-prompt]');

    if (!(formatField instanceof HTMLSelectElement) || !(potluckPanel instanceof HTMLElement)) {
        return;
    }

    const potluckFields = potluckPanel.querySelectorAll('input, textarea, select');
    let isSyncingPotluckRows = false;
    const potluckPresets = {
        community: [
            ['Main dish', 'Lasagna'],
            ['Side', 'Garden salad'],
            ['Dessert', 'Brownies'],
            ['Drinks', 'Lemonade and water'],
            ['Supplies', 'Plates and napkins'],
            ['Utensils', 'Forks and serving spoons'],
        ],
        bbq: [
            ['Main dish', 'Burgers'],
            ['Main dish', 'Hot dogs'],
            ['Side', 'Potato salad'],
            ['Appetizer', 'Chips and dip'],
            ['Drinks', 'Soda and water'],
            ['Condiments', 'Ketchup, mustard, and relish'],
            ['Ice', 'Cooler ice'],
            ['Supplies', 'Plates, napkins, and utensils'],
        ],
        picnic: [
            ['Main dish', 'Sandwich tray'],
            ['Side', 'Pasta salad'],
            ['Fruit', 'Watermelon slices'],
            ['Snacks', 'Chips'],
            ['Dessert', 'Cookies'],
            ['Drinks', 'Tea and bottled water'],
            ['Supplies', 'Cups, plates, and napkins'],
        ],
        thanksgiving: [
            ['Main dish', 'Turkey'],
            ['Side', 'Dressing'],
            ['Side', 'Mashed potatoes'],
            ['Vegetable', 'Green bean casserole'],
            ['Bread', 'Dinner rolls'],
            ['Dessert', 'Pumpkin pie'],
            ['Drinks', 'Sweet tea and cider'],
            ['Supplies', 'Serving trays and utensils'],
        ],
        christmas: [
            ['Main dish', 'Ham'],
            ['Side', 'Mac and cheese'],
            ['Side', 'Roasted vegetables'],
            ['Bread', 'Dinner rolls'],
            ['Dessert', 'Christmas cookies'],
            ['Dessert', 'Cake'],
            ['Drinks', 'Punch and water'],
            ['Supplies', 'Plates, napkins, and serving spoons'],
        ],
        brunch: [
            ['Main dish', 'Breakfast casserole'],
            ['Pastry', 'Muffins'],
            ['Fruit', 'Fresh fruit tray'],
            ['Side', 'Bagels and cream cheese'],
            ['Drinks', 'Coffee'],
            ['Drinks', 'Orange juice'],
            ['Supplies', 'Cups, plates, and napkins'],
        ],
        chili: [
            ['Chili entry', 'Classic beef chili'],
            ['Chili entry', 'White chicken chili'],
            ['Toppings', 'Cheese, onions, and sour cream'],
            ['Side', 'Cornbread'],
            ['Dessert', 'Cookies'],
            ['Drinks', 'Tea and water'],
            ['Supplies', 'Bowls, spoons, and napkins'],
        ],
        pizza: [
            ['Main dish', 'Pepperoni pizza'],
            ['Main dish', 'Cheese pizza'],
            ['Main dish', 'Veggie pizza'],
            ['Side', 'Garden salad'],
            ['Dessert', 'Brownie bites'],
            ['Drinks', 'Soda and water'],
            ['Supplies', 'Plates, cups, and napkins'],
        ],
        celebration: [
            ['Main dish', 'Party tray'],
            ['Appetizer', 'Veggie tray'],
            ['Dessert', 'Celebration cake'],
            ['Dessert', 'Cupcakes'],
            ['Drinks', 'Punch and water'],
            ['Supplies', 'Plates, napkins, and candles'],
        ],
    };

    const createPotluckSeedRow = (typeValue = '', detailValue = '') => {
        const row = document.createElement('div');
        row.className = 'community-potluck-seed-row';
        row.innerHTML = `
            <input type="text" data-potluck-seed-type placeholder="Type" maxlength="160">
            <input type="text" data-potluck-seed-detail placeholder="Detail" maxlength="255">
            <button class="button button-secondary" type="button" data-potluck-seed-remove>Remove</button>
        `;

        const typeInput = row.querySelector('[data-potluck-seed-type]');
        const detailInput = row.querySelector('[data-potluck-seed-detail]');
        const removeButton = row.querySelector('[data-potluck-seed-remove]');

        if (typeInput instanceof HTMLInputElement) {
            typeInput.value = typeValue;
            typeInput.name = 'potluck_seed_type[]';
            typeInput.addEventListener('input', () => {
                syncPotluckSeedOutput();
            });
        }

        if (detailInput instanceof HTMLInputElement) {
            detailInput.value = detailValue;
            detailInput.name = 'potluck_seed_detail[]';
            detailInput.addEventListener('input', () => {
                syncPotluckSeedOutput();
            });
        }

        removeButton?.addEventListener('click', () => {
            row.remove();
            ensureMinimumPotluckRows();
            syncPotluckSeedOutput();
        });

        return row;
    };

    const parsePotluckSeedOutput = () => String(
        potluckSeedOutput instanceof HTMLTextAreaElement ? potluckSeedOutput.value : ''
    )
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .map((line) => {
            const [typePart, detailPart] = line.split('|', 2);
            return {
                type: String(typePart || '').trim(),
                detail: String(detailPart || '').trim(),
            };
        });

    const syncPotluckSeedOutput = () => {
        if (!(potluckSeedList instanceof HTMLElement) || !(potluckSeedOutput instanceof HTMLTextAreaElement)) {
            return;
        }

        const rows = Array.from(potluckSeedList.querySelectorAll('.community-potluck-seed-row'));
        const lines = rows.map((row) => {
            const typeInput = row.querySelector('[data-potluck-seed-type]');
            const detailInput = row.querySelector('[data-potluck-seed-detail]');
            const typeValue = typeInput instanceof HTMLInputElement ? typeInput.value.trim() : '';
            const detailValue = detailInput instanceof HTMLInputElement ? detailInput.value.trim() : '';

            if (typeValue === '' && detailValue === '') {
                return '';
            }

            const normalizedType = typeValue === '' ? 'Item' : typeValue;

            return detailValue === '' ? normalizedType : `${normalizedType} | ${detailValue}`;
        }).filter((line) => line !== '');

        isSyncingPotluckRows = true;
        potluckSeedOutput.value = lines.join('\n');
        potluckSeedOutput.dispatchEvent(new Event('input', { bubbles: true }));
        potluckSeedOutput.dispatchEvent(new Event('change', { bubbles: true }));
        isSyncingPotluckRows = false;
    };

    const ensureMinimumPotluckRows = () => {
        if (!(potluckSeedList instanceof HTMLElement)) {
            return;
        }

        const minimumRows = 4;

        while (potluckSeedList.children.length < minimumRows) {
            potluckSeedList.append(createPotluckSeedRow());
        }
    };

    const syncPotluckSeedRowsFromOutput = () => {
        if (isSyncingPotluckRows || !(potluckSeedList instanceof HTMLElement) || !(potluckSeedOutput instanceof HTMLTextAreaElement)) {
            return;
        }

        const parsedRows = parsePotluckSeedOutput();
        potluckSeedList.innerHTML = '';

        if (parsedRows.length === 0) {
            ensureMinimumPotluckRows();
            return;
        }

        parsedRows.forEach((row) => {
            potluckSeedList.append(createPotluckSeedRow(row.type, row.detail));
        });

        ensureMinimumPotluckRows();
    };

    const applyPotluckPreset = (presetKey) => {
        if (!(potluckSeedList instanceof HTMLElement) || !(presetKey in potluckPresets)) {
            return;
        }

        potluckSeedList.innerHTML = '';
        potluckPresets[presetKey].forEach(([typeValue, detailValue]) => {
            potluckSeedList.append(createPotluckSeedRow(typeValue, detailValue));
        });
        ensureMinimumPotluckRows();
        syncPotluckSeedOutput();
    };

    if (potluckSeedAddButton instanceof HTMLButtonElement && potluckSeedList instanceof HTMLElement) {
        potluckSeedAddButton.addEventListener('click', () => {
            potluckSeedList.append(createPotluckSeedRow());
        });
    }

    potluckPresetButtons.forEach((buttonNode) => {
        buttonNode.addEventListener('click', () => {
            const presetKey = buttonNode.getAttribute('data-potluck-preset');

            if (presetKey) {
                applyPotluckPreset(presetKey);
            }
        });
    });

    if (potluckSeedOutput instanceof HTMLTextAreaElement) {
        syncPotluckSeedRowsFromOutput();
        potluckSeedOutput.addEventListener('input', syncPotluckSeedRowsFromOutput);
        potluckSeedOutput.addEventListener('change', syncPotluckSeedRowsFromOutput);
    }

    const syncPotluckOptions = () => {
        const isPotluck = formatField.value === 'potluck';

        potluckPanel.hidden = !isPotluck;
        potluckPanel.setAttribute('aria-hidden', isPotluck ? 'false' : 'true');

        potluckFields.forEach((field) => {
            if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                field.disabled = !isPotluck;
            }
        });

        if (aiPromptField instanceof HTMLTextAreaElement) {
            aiPromptField.placeholder = isPotluck
                ? 'Example: Create a church potluck after Sunday service with starter items like Main dish | Lasagna, Dessert | Brownies, Drinks | Lemonade, and Supplies | Plates.'
                : 'Example: Create a Wednesday Bible study with a theme verse, fellowship time, and reminder emails.';
        }
    };

    syncPotluckOptions();
    formatField.addEventListener('change', syncPotluckOptions);
});

const plannerQuickTriggers = document.querySelectorAll('[data-planner-quick-event]');

plannerQuickTriggers.forEach((button) => {
    button.addEventListener('click', () => {
        const root = button.closest('[data-community-panels]')
            || document.querySelector('[data-planner-page][data-community-panels], [data-community-panels]');

        if (!(root instanceof HTMLElement)) {
            return;
        }

        const eventToggle = root.querySelector('[data-community-panel-toggle="event"]');
        const eventPanel = root.querySelector('[data-community-panel="event"]');
        const eventForm = root.querySelector('[data-planner-event-form]');
        const heading = root.querySelector('[data-planner-event-heading]');
        const submitButton = root.querySelector('[data-planner-event-submit]');
        const actionInput = root.querySelector('[data-planner-event-action]');
        const eventIdInput = root.querySelector('[data-planner-event-id]');
        const promptField = root.querySelector('[data-ai-prompt]');
        const voiceStartButton = root.querySelector('[data-ai-voice-start]');
        const requestedDate = button.getAttribute('data-planner-event-date') || '';
        const mode = button.getAttribute('data-planner-quick-mode') || 'manual';

        if (eventPanel instanceof HTMLElement) {
            rememberModalOpener(eventPanel, button);
            eventPanel.hidden = false;
            eventPanel.setAttribute('aria-hidden', 'false');
            eventPanel.style.display = eventPanel.hasAttribute('data-panel-modal') ? 'flex' : '';
            focusModalPanel(eventPanel);
        }

        if (eventToggle instanceof HTMLButtonElement) {
            eventToggle.setAttribute('aria-expanded', 'true');
            eventToggle.textContent = '+ Add Event';
        }

        root.querySelectorAll('[data-community-panel]').forEach((panelNode) => {
            if (panelNode !== eventPanel && panelNode instanceof HTMLElement) {
                panelNode.hidden = true;
                panelNode.setAttribute('aria-hidden', 'true');
                panelNode.style.display = 'none';
            }
        });

        root.querySelectorAll('[data-community-panel-toggle]').forEach((toggleNode) => {
            if (toggleNode !== eventToggle && toggleNode instanceof HTMLButtonElement) {
                toggleNode.setAttribute('aria-expanded', 'false');
            }
        });

        if (actionInput instanceof HTMLInputElement) {
            actionInput.value = actionInput.dataset.createValue || 'create-event';
        }

        if (eventIdInput instanceof HTMLInputElement) {
            eventIdInput.value = '';
        }

        if (heading instanceof HTMLElement) {
            heading.textContent = 'Add planner event';
        }

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.textContent = 'Add Event';
        }

        if (promptField instanceof HTMLTextAreaElement) {
            promptField.value = '';
        }

        if (eventForm instanceof HTMLFormElement) {
            eventForm.querySelectorAll('[data-default-value]').forEach((field) => {
                const defaultValue = field.getAttribute('data-default-value') ?? '';

                if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
                    field.value = defaultValue;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            const eventDateField = eventForm.querySelector('[name="event_date"]');

            if (requestedDate !== '' && eventDateField instanceof HTMLInputElement) {
                eventDateField.value = requestedDate;
            }
        }

        if (typeof window.history.replaceState === 'function') {
            const url = new URL(window.location.href);
            url.searchParams.delete('edit_event');
            window.history.replaceState({}, '', url.toString());
        }

        syncModalBodyLock();

        if (eventPanel instanceof HTMLElement) {
            const modalContent = eventPanel.querySelector('[data-panel-modal-content]');

            if (modalContent instanceof HTMLElement) {
                modalContent.scrollTop = 0;
            } else {
                eventPanel.scrollIntoView({
                    block: 'start',
                    behavior: 'smooth',
                });
            }
        }

        if (mode === 'voice' && voiceStartButton instanceof HTMLButtonElement && !voiceStartButton.disabled) {
            window.setTimeout(() => {
                voiceStartButton.click();
            }, 250);
            return;
        }

        if (mode === 'voice' && promptField instanceof HTMLTextAreaElement) {
            promptField.focus();
            return;
        }

        const titleField = eventForm?.querySelector('[name="title"]');

        if (titleField instanceof HTMLInputElement) {
            titleField.focus();
        }
    });
});

const voiceSearchGroups = document.querySelectorAll('[data-voice-search]');

voiceSearchGroups.forEach((group) => {
    const input = group.querySelector('[data-voice-search-input]');
    const startButton = group.querySelector('[data-voice-search-start]');
    const statusNode = group.parentElement?.querySelector('[data-voice-search-status]');
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let isListening = false;

    const microphoneIconMarkup = `
        <path d="M12 4a3 3 0 0 1 3 3v5a3 3 0 0 1-6 0V7a3 3 0 0 1 3-3Z" />
        <path d="M19 11a7 7 0 0 1-14 0" />
        <path d="M12 18v3" />
        <path d="M9 21h6" />
    `;
    const stopIconMarkup = `
        <rect x="7" y="7" width="10" height="10" rx="2" ry="2" />
    `;

    const setVoiceSearchListeningState = (listening) => {
        isListening = listening;

        if (!(startButton instanceof HTMLButtonElement)) {
            return;
        }

        startButton.classList.toggle('is-recording', listening);
        startButton.setAttribute('aria-pressed', listening ? 'true' : 'false');
        startButton.setAttribute('aria-label', listening ? 'Stop voice search' : 'Speak your Bible search');

        const icon = startButton.querySelector('.voice-search-icon');

        if (icon instanceof SVGElement) {
            icon.innerHTML = listening ? stopIconMarkup : microphoneIconMarkup;
        }
    };

    const setStatus = (message) => {
        if (statusNode instanceof HTMLElement) {
            statusNode.textContent = message;
        }
    };

    if (!(input instanceof HTMLInputElement) || !(startButton instanceof HTMLButtonElement)) {
        return;
    }

    const searchForm = group.closest('form');
    const csrfInput = searchForm?.querySelector('input[name="csrf_token"]');
    const voiceRecorder = createVoiceRecorder({
        triggerButton: startButton,
        stopButton: null,
        statusNode,
        csrfInput: csrfInput instanceof HTMLInputElement ? csrfInput : null,
        onTranscript: (transcript) => {
            const transcriptBase = input.value.trim();
            input.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? ' ' : '');
        },
        onRecordingStateChange: setVoiceSearchListeningState,
        listeningMessage: 'Listening now. Say a verse, passage, or keyword when you are ready.',
        successMessage: (payload) => `Your search is ready with ${payload.model || 'OpenAI'}. Press Search when you would like to continue.`,
        unsupportedMessage: 'Voice search is not available in this browser yet.',
    });

    if (!SpeechRecognitionApi) {
        if (!voiceRecorder.isSupported) {
            voiceRecorder.setUnsupported();
        }

        return;
    }

    recognition = new SpeechRecognitionApi();
    recognition.lang = 'en-US';
    recognition.interimResults = true;
    recognition.continuous = false;

    let transcriptBase = '';

    recognition.addEventListener('start', () => {
        transcriptBase = input.value.trim();
        setVoiceSearchListeningState(true);
        setStatus('Listening now. Say a verse, passage, or keyword when you are ready.');
    });

    recognition.addEventListener('result', (event) => {
        const transcript = Array.from(event.results)
            .map((result) => result[0]?.transcript || '')
            .join(' ')
            .trim();

        input.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? ' ' : '');
    });

    recognition.addEventListener('end', () => {
        setVoiceSearchListeningState(false);
        setStatus('Your search is ready. Press Search when you would like to continue.');
    });

    recognition.addEventListener('error', (event) => {
        setStatus(`Voice search ran into an issue: ${event.error}`);
    });

    startButton.addEventListener('click', () => {
        if (isListening) {
            recognition?.stop();
            return;
        }

        input.focus();
        try {
            recognition?.start();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Voice search could not start yet.';
            setStatus(message);
        }
    });
});

const voiceComposeGroups = document.querySelectorAll('[data-voice-compose]');

voiceComposeGroups.forEach((group) => {
    const startButton = group.querySelector('[data-voice-compose-start]');
    const stopButton = group.querySelector('[data-voice-compose-stop]');
    const statusNode = group.querySelector('[data-voice-compose-status]');
    const containerLabel = group.closest('label');
    const textarea = containerLabel?.querySelector('textarea');
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    const maxDurationSeconds = Number(group.getAttribute('data-voice-compose-max-seconds') || '0') || 0;
    const maxDurationMs = maxDurationSeconds > 0 ? maxDurationSeconds * 1000 : 0;
    let recognition = null;
    let recognitionTimeoutId = null;
    let isListening = false;
    let statusMessageBase = maxDurationSeconds > 0
        ? `Speak for up to ${maxDurationSeconds} seconds and we will place your words into the prayer details.`
        : 'Listening now. Share your note in your own words.';

    const setStatus = (message) => {
        if (statusNode instanceof HTMLElement) {
            statusNode.textContent = message;
        }
    };

    const setCountdownStatus = (remainingSeconds) => {
        if (maxDurationSeconds <= 0) {
            return;
        }

        const normalizedSeconds = Math.max(0, Math.ceil(Number(remainingSeconds) || 0));
        setStatus(`${statusMessageBase} ${normalizedSeconds}s left.`);
    };

    const clearRecognitionTimer = () => {
        if (recognitionTimeoutId !== null) {
            window.clearTimeout(recognitionTimeoutId);
            recognitionTimeoutId = null;
        }
    };

    const setComposeListeningState = (listening) => {
        isListening = listening;
        startButton.classList.toggle('is-recording', listening);
        startButton.setAttribute('aria-pressed', listening ? 'true' : 'false');

        if (stopButton instanceof HTMLButtonElement) {
            startButton.hidden = listening;
            stopButton.hidden = !listening;
        } else {
            startButton.hidden = false;
        }
    };

    if (!(startButton instanceof HTMLButtonElement) || !(textarea instanceof HTMLTextAreaElement)) {
        return;
    }

    const composeForm = group.closest('form');
    const csrfInput = composeForm?.querySelector('input[name="csrf_token"]');
    const voiceRecorder = createVoiceRecorder({
        triggerButton: startButton,
        stopButton: stopButton instanceof HTMLButtonElement ? stopButton : null,
        statusNode,
        csrfInput: csrfInput instanceof HTMLInputElement ? csrfInput : null,
        onTranscript: (transcript) => {
            const transcriptBase = textarea.value.trim();
            textarea.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? '\n\n' : '');
        },
        listeningMessage: 'Listening now. Share your note in your own words.',
        successMessage: (payload) => `Your note was captured with ${payload.model || 'OpenAI'}. Keep shaping it or save when you are ready.`,
        unsupportedMessage: 'Voice notes are not available in this browser yet.',
        maxDurationMs,
        maxDurationReachedMessage: maxDurationSeconds > 0
            ? `${maxDurationSeconds}-second limit reached. Turning your words into text...`
            : 'Recording limit reached. Turning your words into text...',
        onDurationTick: maxDurationSeconds > 0
            ? (remainingSeconds) => {
                setCountdownStatus(remainingSeconds);
            }
            : null,
    });

    if (!SpeechRecognitionApi) {
        if (!voiceRecorder.isSupported) {
            voiceRecorder.setUnsupported();
        }

        return;
    }

    recognition = new SpeechRecognitionApi();
    recognition.lang = 'en-US';
    recognition.interimResults = true;
    recognition.continuous = true;

    let transcriptBase = '';

    recognition.addEventListener('start', () => {
        transcriptBase = textarea.value.trim();
        setComposeListeningState(true);

        setCountdownStatus(maxDurationSeconds);

        if (maxDurationMs > 0) {
            clearRecognitionTimer();
            recognitionTimeoutId = window.setTimeout(() => {
                setStatus(`${maxDurationSeconds}-second limit reached. Finishing your voice note...`);
                recognition?.stop();
            }, maxDurationMs);
        }
    });

    recognition.addEventListener('result', (event) => {
        const transcript = Array.from(event.results)
            .map((result) => result[0]?.transcript || '')
            .join(' ')
            .trim();

        textarea.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? '\n\n' : '');
    });

    recognition.addEventListener('end', () => {
        clearRecognitionTimer();
        setComposeListeningState(false);

        setStatus('Your note is ready. Keep editing it or save when you are ready.');
    });

    recognition.addEventListener('error', (event) => {
        clearRecognitionTimer();
        setComposeListeningState(false);
        setStatus(`Voice note ran into an issue: ${event.error}`);
    });

    startButton.addEventListener('click', () => {
        if (isListening) {
            clearRecognitionTimer();
            recognition?.stop();
            return;
        }

        textarea.focus();
        try {
            recognition?.start();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Voice note could not start yet.';
            setStatus(message);
        }
    });

    stopButton?.addEventListener('click', () => {
        clearRecognitionTimer();
        recognition?.stop();
    });

    setComposeListeningState(false);
});

const profileForm = document.querySelector('[data-profile-form]');
const passwordForm = document.querySelector('[data-password-form]');

if (profileForm && passwordForm) {
    const profileEditButton = profileForm.querySelector('[data-profile-edit-toggle]');
    const profileCancelButton = profileForm.querySelector('[data-profile-edit-cancel]');
    const profileSaveButton = profileForm.querySelector('[data-profile-save]');
    const profileFields = profileForm.querySelector('[data-profile-fields]');
    const profileEditableFields = profileForm.querySelectorAll('input[name="name"], input[name="email"], input[name="city"], input[name="avatar_url"], input[name="primary_flag"]');

    const passwordEditButton = passwordForm.querySelector('[data-password-edit-toggle]');
    const passwordCancelButton = passwordForm.querySelector('[data-password-edit-cancel]');
    const passwordSaveButton = passwordForm.querySelector('[data-password-save]');
    const passwordFields = passwordForm.querySelector('[data-password-fields]');
    const passwordEditableFields = passwordForm.querySelectorAll('input[name="password"], input[name="password_confirm"]');

    let profileEditing = profileFields ? !profileFields.hidden : false;
    let passwordEditing = passwordFields ? !passwordFields.hidden : false;

    const setSectionState = (fields, editableFields, isEditing) => {
        if (fields) {
            fields.hidden = !isEditing;
            fields.setAttribute('aria-hidden', isEditing ? 'false' : 'true');
            fields.style.display = isEditing ? '' : 'none';
        }

        editableFields.forEach((field) => {
            field.disabled = !isEditing;
        });
    };

    const setButtonState = (button, isVisible) => {
        if (!button) {
            return;
        }

        button.hidden = !isVisible;
        button.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
        button.style.display = isVisible ? '' : 'none';
    };

    const renderProfileState = () => {
        setSectionState(profileFields, profileEditableFields, profileEditing);
        setButtonState(profileEditButton, !profileEditing && !passwordEditing);
        setButtonState(profileCancelButton, profileEditing);
        setButtonState(profileSaveButton, profileEditing);
    };

    const renderPasswordState = () => {
        setSectionState(passwordFields, passwordEditableFields, passwordEditing);
        setButtonState(passwordEditButton, !passwordEditing && !profileEditing);
        setButtonState(passwordCancelButton, passwordEditing);
        setButtonState(passwordSaveButton, passwordEditing);
    };

    const renderAccountModes = () => {
        renderProfileState();
        renderPasswordState();
    };

    profileEditButton?.addEventListener('click', () => {
        profileEditing = true;
        passwordEditing = false;
        renderAccountModes();
    });

    profileCancelButton?.addEventListener('click', () => {
        window.location.reload();
    });

    passwordEditButton?.addEventListener('click', () => {
        passwordEditing = true;
        profileEditing = false;
        renderAccountModes();
    });

    passwordCancelButton?.addEventListener('click', () => {
        window.location.reload();
    });

    renderAccountModes();
}

// ── Flag picker ─────────────────────────────────────────────────────────────
(function () {
    const grid = document.querySelector('[data-flag-grid]');
    if (!grid) return;

    const input = document.querySelector('[data-flag-input="primary"]');

    const syncHighlights = () => {
        const current = input?.value?.trim() || '';
        grid.querySelectorAll('.flag-option').forEach((btn) => {
            btn.classList.toggle('is-primary', btn.getAttribute('data-flag-option') === current && current !== '');
        });
    };

    // Tap to set; tap same flag again to clear
    grid.addEventListener('click', (e) => {
        const btn = e.target.closest('.flag-option');
        if (!btn || !input) return;
        const flag = btn.getAttribute('data-flag-option') || '';
        input.value = input.value.trim() === flag ? '' : flag;
        syncHighlights();
    });

    // Clear button
    document.querySelector('[data-flag-clear="primary"]')?.addEventListener('click', () => {
        if (input) input.value = '';
        syncHighlights();
    });

    input?.addEventListener('input', syncHighlights);
    syncHighlights();
}());

document.querySelectorAll('[data-reader-nav]').forEach((readerNav) => {
    if (!(readerNav instanceof HTMLFormElement)) {
        return;
    }

    const bookSelect = readerNav.querySelector('[data-reader-select="book"]');
    const chapterSelect = readerNav.querySelector('[data-reader-select="chapter"]');
    const verseSelect = readerNav.querySelector('[data-reader-select="verse"]');
    const verseEndSelect = readerNav.querySelector('[data-reader-select="verse-end"]');
    const buildReaderNavigationUrl = (form, verseValue) => {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        const formData = new FormData(form);

        url.search = '';

        formData.forEach((value, key) => {
            const normalizedValue = String(value).trim();

            if (normalizedValue !== '') {
                url.searchParams.set(key, normalizedValue);
            }
        });

        if (verseValue !== '') {
            url.hash = `verse-${verseValue}`;
            window.sessionStorage.setItem('reader-smooth-scroll-target', `#verse-${verseValue}`);
        } else {
            url.hash = '';
            window.sessionStorage.removeItem('reader-smooth-scroll-target');
        }

        return url.toString();
    };

    const submitNav = () => {
        const verseValue = verseSelect instanceof HTMLSelectElement ? verseSelect.value.trim() : '';

        if (verseValue !== '') {
            window.location.assign(buildReaderNavigationUrl(readerNav, verseValue));
            return;
        }

        readerNav.requestSubmit();
    };

    bookSelect?.addEventListener('change', () => {
        if (chapterSelect) {
            chapterSelect.value = '';
        }

        if (verseSelect) {
            verseSelect.value = '';
        }

        if (verseEndSelect) {
            verseEndSelect.value = '';
        }

        submitNav();
    });

    chapterSelect?.addEventListener('change', () => {
        if (verseSelect) {
            verseSelect.value = '';
        }

        if (verseEndSelect) {
            verseEndSelect.value = '';
        }

        submitNav();
    });

    verseSelect?.addEventListener('change', submitNav);
    verseEndSelect?.addEventListener('change', submitNav);
});

const mobileBibleNav = document.querySelector('[data-mobile-bible-nav]');
const mobileBibleNavToggle = document.querySelector('[data-mobile-bible-nav-toggle]');

if (mobileBibleNav instanceof HTMLElement && mobileBibleNavToggle instanceof HTMLButtonElement) {
    const setMobileBibleNavOpen = (isOpen) => {
        mobileBibleNav.classList.toggle('is-open', isOpen);
        mobileBibleNavToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        mobileBibleNavToggle.setAttribute('aria-label', isOpen ? 'Close Bible navigation' : 'Open Bible navigation');
    };

    mobileBibleNavToggle.addEventListener('click', () => {
        setMobileBibleNavOpen(!mobileBibleNav.classList.contains('is-open'));
    });

    document.addEventListener('click', (event) => {
        if (!mobileBibleNav.classList.contains('is-open')) {
            return;
        }

        if (event.target instanceof Node && mobileBibleNav.contains(event.target)) {
            return;
        }

        setMobileBibleNavOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMobileBibleNavOpen(false);
        }
    });
}

document.querySelectorAll('.chapter-jump-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const verseField = form.querySelector('select[name="verse"]');
        const verseValue = verseField instanceof HTMLSelectElement ? verseField.value.trim() : '';

        if (verseValue === '') {
            window.sessionStorage.removeItem('reader-smooth-scroll-target');
            return;
        }

        event.preventDefault();

        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        const formData = new FormData(form);
        url.search = '';

        formData.forEach((value, key) => {
            const normalizedValue = String(value).trim();

            if (normalizedValue !== '') {
                url.searchParams.set(key, normalizedValue);
            }
        });

        url.hash = `verse-${verseValue}`;
        window.sessionStorage.setItem('reader-smooth-scroll-target', `#verse-${verseValue}`);
        window.location.assign(url.toString());
    });
});

document.querySelectorAll('[data-translation-switch-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const translationSelect = form.querySelector('[data-translation-switch]');

    if (!(translationSelect instanceof HTMLSelectElement)) {
        return;
    }

    translationSelect.addEventListener('change', () => {
        const verseField = form.querySelector('[name="verse"]');
        const verseValue = verseField instanceof HTMLInputElement || verseField instanceof HTMLSelectElement
            ? verseField.value.trim()
            : '';

        if (verseValue !== '') {
            const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
            const formData = new FormData(form);

            url.search = '';

            formData.forEach((value, key) => {
                const normalizedValue = String(value).trim();

                if (normalizedValue !== '') {
                    url.searchParams.set(key, normalizedValue);
                }
            });

            url.hash = `verse-${verseValue}`;
            window.sessionStorage.setItem('reader-smooth-scroll-target', `#verse-${verseValue}`);
            window.location.assign(url.toString());
            return;
        }

        window.sessionStorage.removeItem('reader-smooth-scroll-target');
        form.requestSubmit();
    });
});

const chapterReader = document.querySelector('[data-chapter-reader]');
const bookmarkPopup = document.querySelector('[data-bookmark-popup]');
const bookmarkPopupForm = document.querySelector('[data-bookmark-popup-form]');

if (chapterReader) {
    const scrollToTargetVerse = () => {
        const pendingHash = window.sessionStorage.getItem('reader-smooth-scroll-target') || '';
        const hash = (pendingHash || window.location.hash).trim();

        if (!hash.startsWith('#verse-')) {
            return;
        }

        const targetVerse = chapterReader.querySelector(hash);

        if (!(targetVerse instanceof HTMLElement)) {
            return;
        }

        window.setTimeout(() => {
            window.requestAnimationFrame(() => {
                targetVerse.scrollIntoView({
                    block: 'center',
                    behavior: 'smooth',
                });
            });
        }, 90);

        if (pendingHash !== '') {
            window.sessionStorage.removeItem('reader-smooth-scroll-target');
        }
    };

    scrollToTargetVerse();
    window.addEventListener('hashchange', scrollToTargetVerse);
}

if (chapterReader && bookmarkPopup) {
    const popupModeLabel = bookmarkPopup.querySelector('[data-popup-mode-label]');
    const popupReference = bookmarkPopup.querySelector('[data-popup-reference]');
    const popupPreview = bookmarkPopup.querySelector('[data-popup-preview]');
    const popupClose = bookmarkPopup.querySelector('[data-popup-close]');
    const popupClear = bookmarkPopup.querySelector('[data-popup-clear]');
    const popupNoteLink = bookmarkPopup.querySelector('[data-popup-note-link]');
    const colorPicker = bookmarkPopup.querySelector('[data-color-picker]');
    const colorInput = bookmarkPopupForm?.querySelector('input[name="highlight_color"]') || null;
    const actionInput = bookmarkPopupForm?.querySelector('input[name="action"]') || null;
    const verseIdInput = bookmarkPopupForm?.querySelector('input[name="verse_id"]') || null;
    const selectedTextInput = bookmarkPopupForm?.querySelector('input[name="selected_text"]') || null;
    const selectionStartInput = bookmarkPopupForm?.querySelector('input[name="selection_start"]') || null;
    const selectionEndInput = bookmarkPopupForm?.querySelector('input[name="selection_end"]') || null;
    const rangeStartVerseIdInput = bookmarkPopupForm?.querySelector('input[name="range_start_verse_id"]') || null;
    const rangeEndVerseIdInput = bookmarkPopupForm?.querySelector('input[name="range_end_verse_id"]') || null;
    const rangeStartOffsetInput = bookmarkPopupForm?.querySelector('input[name="range_start_offset"]') || null;
    const rangeEndOffsetInput = bookmarkPopupForm?.querySelector('input[name="range_end_offset"]') || null;
    const tagInput = bookmarkPopupForm?.querySelector('input[name="tag"]') || null;
    const noteInput = bookmarkPopupForm?.querySelector('textarea[name="note"]') || null;

    const closestMatch = (node, selector) => {
        if (node instanceof Element) {
            return node.closest(selector);
        }

        return node?.parentElement?.closest(selector) || null;
    };

    const textOffsetWithin = (root, container, offset) => {
        if (!(root instanceof Element) || !(container instanceof Node)) {
            return null;
        }

        if (container !== root && !root.contains(container.nodeType === Node.TEXT_NODE ? container.parentNode : container)) {
            return null;
        }

        try {
            const prefixRange = document.createRange();
            prefixRange.selectNodeContents(root);
            prefixRange.setEnd(container, offset);
            return prefixRange.toString().length;
        } catch (error) {
            return null;
        }
    };

    const resetPopupFields = () => {
        if (actionInput) {
            actionInput.value = 'save-bookmark';
        }
        if (verseIdInput) {
            verseIdInput.value = '';
        }
        if (selectedTextInput) {
            selectedTextInput.value = '';
        }
        if (selectionStartInput) {
            selectionStartInput.value = '';
        }
        if (selectionEndInput) {
            selectionEndInput.value = '';
        }
        if (rangeStartVerseIdInput) {
            rangeStartVerseIdInput.value = '';
        }
        if (rangeEndVerseIdInput) {
            rangeEndVerseIdInput.value = '';
        }
        if (rangeStartOffsetInput) {
            rangeStartOffsetInput.value = '';
        }
        if (rangeEndOffsetInput) {
            rangeEndOffsetInput.value = '';
        }

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

    const positionPopup = (anchorVerseCard) => {
        if (!(anchorVerseCard instanceof HTMLElement) || !(bookmarkPopup instanceof HTMLElement)) {
            return;
        }

        const anchorRect = anchorVerseCard.getBoundingClientRect();
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        const horizontalInset = viewportWidth <= 760 ? 16 : 24;
        const topInset = 96;
        const preferredWidth = Math.min(416, viewportWidth - (horizontalInset * 2));

        bookmarkPopup.style.width = `${preferredWidth}px`;
        bookmarkPopup.style.left = '';
        bookmarkPopup.style.right = '';
        bookmarkPopup.style.top = '';
        bookmarkPopup.style.bottom = '';

        const popupHeight = bookmarkPopup.offsetHeight || 320;
        const gap = 14;
        const spaceBelow = viewportHeight - anchorRect.bottom - horizontalInset;
        const spaceAbove = anchorRect.top - topInset;
        let top = anchorRect.bottom + gap;

        if (spaceBelow < popupHeight && spaceAbove > spaceBelow) {
            top = anchorRect.top - popupHeight - gap;
        }

        top = Math.max(topInset, Math.min(top, viewportHeight - popupHeight - horizontalInset));

        if (viewportWidth <= 760) {
            bookmarkPopup.style.left = `${horizontalInset}px`;
            bookmarkPopup.style.right = `${horizontalInset}px`;
            bookmarkPopup.style.width = 'auto';
        } else {
            const preferredLeft = anchorRect.right - preferredWidth;
            const left = Math.max(
                horizontalInset,
                Math.min(preferredLeft, viewportWidth - preferredWidth - horizontalInset)
            );
            bookmarkPopup.style.left = `${left}px`;
        }

        bookmarkPopup.style.top = `${top}px`;
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

    const buildVerseReference = (startVerseCard, endVerseCard) => {
        const startReference = startVerseCard.getAttribute('data-verse-reference') || 'Verse';
        const endReference = endVerseCard.getAttribute('data-verse-reference') || 'Verse';

        if (startVerseCard === endVerseCard) {
            return startReference;
        }

        return `${startReference} - ${endReference}`;
    };

    const openPopupForSelection = ({
        startVerseCard,
        endVerseCard = startVerseCard,
        selectedText = '',
        selectionStart = '',
        selectionEnd = '',
        rangeStartOffset = '',
        rangeEndOffset = '',
    }) => {
        const verseId = startVerseCard.getAttribute('data-verse-id') || '';
        const endVerseId = endVerseCard.getAttribute('data-verse-id') || verseId;
        const verseReference = buildVerseReference(startVerseCard, endVerseCard);
        const verseText = startVerseCard.getAttribute('data-verse-text') || '';

        if (verseIdInput) {
            verseIdInput.value = verseId;
        }
        if (selectedTextInput) {
            selectedTextInput.value = selectedText;
        }
        if (selectionStartInput) {
            selectionStartInput.value = selectionStart;
        }
        if (selectionEndInput) {
            selectionEndInput.value = selectionEnd;
        }
        if (rangeStartVerseIdInput) {
            rangeStartVerseIdInput.value = verseId;
        }
        if (rangeEndVerseIdInput) {
            rangeEndVerseIdInput.value = endVerseId;
        }
        if (rangeStartOffsetInput) {
            rangeStartOffsetInput.value = rangeStartOffset;
        }
        if (rangeEndOffsetInput) {
            rangeEndOffsetInput.value = rangeEndOffset;
        }
        if (actionInput) {
            actionInput.value = selectedText ? 'save-section' : 'save-bookmark';
        }

        if (popupModeLabel) {
            popupModeLabel.textContent = selectedText ? 'Highlight Selection' : 'Save Verse';
        }

        if (popupReference) {
            popupReference.textContent = verseReference;
        }

        if (popupPreview) {
            popupPreview.textContent = selectedText
                ? `"${selectedText}"`
                : (startVerseCard === endVerseCard ? verseText : `${verseReference}`);
        }

        if (popupNoteLink) {
            popupNoteLink.setAttribute('href', `/notes.php?verse_id=${encodeURIComponent(verseId)}`);
        }

        bookmarkPopup.hidden = false;
        positionPopup(startVerseCard);
    };

    const updateSelection = () => {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
            return;
        }

        const range = selection.getRangeAt(0);
        const startVerseCard = closestMatch(range.startContainer, '[data-verse-card]');
        const endVerseCard = closestMatch(range.endContainer, '[data-verse-card]');

        if (!startVerseCard || !endVerseCard) {
            return;
        }

        const selectedText = selection.toString().trim();

        if (!selectedText) {
            return;
        }

        const startVerseText = startVerseCard.querySelector('.reader-verse-text');
        const endVerseText = endVerseCard.querySelector('.reader-verse-text');

        if (!(startVerseText instanceof Element) || !(endVerseText instanceof Element)) {
            return;
        }

        let start = textOffsetWithin(startVerseText, range.startContainer, range.startOffset);
        let end = textOffsetWithin(endVerseText, range.endContainer, range.endOffset);

        if (start === null || end === null) {
            if (startVerseCard !== endVerseCard) {
                return;
            }

            const verseTextValue = startVerseCard.getAttribute('data-verse-text') || '';
            const fallbackStart = verseTextValue.indexOf(selectedText);

            if (fallbackStart === -1) {
                return;
            }

            start = fallbackStart;
            end = fallbackStart + selectedText.length;
        }

        if (startVerseCard === endVerseCard && end <= start) {
            return;
        }

        openPopupForSelection({
            startVerseCard,
            endVerseCard,
            selectedText,
            selectionStart: String(start),
            selectionEnd: String(end),
            rangeStartOffset: String(start),
            rangeEndOffset: String(end),
        });
    };

    chapterReader.querySelectorAll('[data-verse-card]').forEach((verseCard) => {
        verseCard.addEventListener('click', (event) => {
            const target = event.target;

            if (target instanceof Element && target.closest('a, button')) {
                return;
            }

            const selection = window.getSelection();

            if (selection && !selection.isCollapsed) {
                return;
            }

            openPopupForSelection({ startVerseCard: verseCard });
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

    if (bookmarkPopupForm && verseIdInput && actionInput && selectedTextInput) {
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
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            window.getSelection()?.removeAllRanges();
            hidePopup();
        }
    });

    resetPopupFields();
    setActiveColor(colorInput?.value || 'neon-yellow');
}

const shareComposer = document.querySelector('[data-share-composer]');
const shareComposerToggle = document.querySelector('[data-share-composer-toggle]');

if (shareComposer && shareComposerToggle) {
    const shareComposerClose = shareComposer.querySelector('[data-share-composer-close]');
    const sharePayloadNode = shareComposer.querySelector('[data-share-payload]');
    const shareForm = shareComposer.querySelector('[data-share-composer-form]');
    const previewShell = shareComposer.querySelector('[data-share-preview-shell]');
    const previewCard = shareComposer.querySelector('[data-share-preview-card]');
    const previewKicker = shareComposer.querySelector('[data-share-preview-kicker]');
    const previewReference = shareComposer.querySelector('[data-share-preview-reference]');
    const previewText = shareComposer.querySelector('[data-share-preview-text]');
    const previewFooter = shareComposer.querySelector('[data-share-preview-footer]');
    const previewBrand = shareComposer.querySelector('[data-share-preview-brand]');
    const shareStatus = shareComposer.querySelector('[data-share-status]');
    const shareCanvas = shareComposer.querySelector('[data-share-canvas]');
    const templateSelect = shareComposer.querySelector('[data-share-template]');
    const themeSelect = shareComposer.querySelector('[data-share-theme]');
    const fontSelect = shareComposer.querySelector('[data-share-font]');
    const brandingSelect = shareComposer.querySelector('[data-share-branding]');
    const headlineInput = shareComposer.querySelector('[data-share-headline]');
    const footerInput = shareComposer.querySelector('[data-share-footer]');
    const captionInput = shareComposer.querySelector('[data-share-caption]');
    const downloadButton = shareComposer.querySelector('[data-share-download]');
    const nativeShareButton = shareComposer.querySelector('[data-share-native]');
    const copyCaptionButton = shareComposer.querySelector('[data-share-copy]');
    const randomizeButton = shareComposer.querySelector('[data-share-randomize]');
    let sharePayload = null;

    try {
        sharePayload = sharePayloadNode?.textContent ? JSON.parse(sharePayloadNode.textContent) : null;
    } catch (error) {
        sharePayload = null;
    }

    const setShareStatus = (message) => {
        if (shareStatus instanceof HTMLElement) {
            shareStatus.textContent = message;
        }
    };

    if (sharePayload && shareForm && previewShell && previewCard && previewKicker && previewReference && previewText && previewFooter && previewBrand && shareCanvas instanceof HTMLCanvasElement) {
        const templatePresets = {
            story: {
                width: 1080,
                height: 1920,
                aspectRatio: '9 / 16',
                padding: 92,
                kickerSize: 42,
                referenceSize: 32,
                quoteSize: 86,
                quoteMin: 42,
                quoteLineHeight: 1.15,
                quoteMaxLines: 14,
                quoteMaxChars: 780,
            },
            square: {
                width: 1080,
                height: 1080,
                aspectRatio: '1 / 1',
                padding: 84,
                kickerSize: 36,
                referenceSize: 28,
                quoteSize: 68,
                quoteMin: 34,
                quoteLineHeight: 1.14,
                quoteMaxLines: 10,
                quoteMaxChars: 430,
            },
        };

        const themePresets = {
            'good-news-bible': {
                gradient: ['#7fc0df', '#f8eeb8', '#fff6dc'],
                orbs: ['rgba(255, 231, 150, 0.42)', 'rgba(78, 162, 48, 0.18)', 'rgba(205, 38, 42, 0.14)'],
                text: '#4c260d',
                muted: 'rgba(76, 38, 13, 0.74)',
                panel: 'rgba(255, 249, 230, 0.46)',
                panelBorder: 'rgba(131, 69, 24, 0.16)',
            },
            'slate-glow': {
                gradient: ['#0a0908', '#22333b', '#5e503f'],
                orbs: ['rgba(198, 172, 143, 0.30)', 'rgba(234, 224, 213, 0.14)', 'rgba(198, 172, 143, 0.18)'],
                text: '#f8f3ed',
                muted: 'rgba(248, 243, 237, 0.78)',
                panel: 'rgba(234, 224, 213, 0.12)',
                panelBorder: 'rgba(234, 224, 213, 0.16)',
            },
            'earth-canvas': {
                gradient: ['#5e503f', '#c6ac8f', '#eae0d5'],
                orbs: ['rgba(10, 9, 8, 0.10)', 'rgba(34, 51, 59, 0.10)', 'rgba(234, 224, 213, 0.30)'],
                text: '#0a0908',
                muted: 'rgba(10, 9, 8, 0.62)',
                panel: 'rgba(255, 252, 248, 0.24)',
                panelBorder: 'rgba(10, 9, 8, 0.10)',
            },
            'light-sermon': {
                gradient: ['#fbf8f3', '#eae0d5', '#c6ac8f'],
                orbs: ['rgba(34, 51, 59, 0.12)', 'rgba(94, 80, 63, 0.10)', 'rgba(255, 255, 255, 0.32)'],
                text: '#0a0908',
                muted: 'rgba(10, 9, 8, 0.66)',
                panel: 'rgba(255, 255, 255, 0.28)',
                panelBorder: 'rgba(34, 51, 59, 0.10)',
            },
            'midnight-gospel': {
                gradient: ['#0b1320', '#22333b', '#0a0908'],
                orbs: ['rgba(198, 172, 143, 0.18)', 'rgba(117, 181, 214, 0.14)', 'rgba(234, 224, 213, 0.10)'],
                text: '#f5efe7',
                muted: 'rgba(245, 239, 231, 0.72)',
                panel: 'rgba(255, 255, 255, 0.08)',
                panelBorder: 'rgba(255, 255, 255, 0.12)',
            },
        };

        const fontPresets = {
            editorial: {
                previewClass: 'share-font-editorial',
                kicker: '"Manrope", sans-serif',
                quote: '"Outfit", sans-serif',
                reference: '"Manrope", sans-serif',
                footer: '"Manrope", sans-serif',
            },
            modern: {
                previewClass: 'share-font-modern',
                kicker: '"Manrope", sans-serif',
                quote: '"Manrope", sans-serif',
                reference: '"Outfit", sans-serif',
                footer: '"Manrope", sans-serif',
            },
            classic: {
                previewClass: 'share-font-classic',
                kicker: '"Manrope", sans-serif',
                quote: 'Georgia, serif',
                reference: 'Georgia, serif',
                footer: '"Manrope", sans-serif',
            },
        };

        const createSeededRandom = (seed) => {
            let current = seed >>> 0;

            return () => {
                current = (current * 1664525 + 1013904223) >>> 0;
                return current / 4294967296;
            };
        };

        const truncateAtWord = (text, maxChars) => {
            const normalized = String(text || '').replace(/\s+/g, ' ').trim();

            if (normalized.length <= maxChars) {
                return normalized;
            }

            const shortened = normalized.slice(0, maxChars - 1);
            const boundary = shortened.lastIndexOf(' ');
            return `${(boundary > 120 ? shortened.slice(0, boundary) : shortened).trim()}...`;
        };

        const buildShareBody = (template) => {
            const verses = Array.isArray(sharePayload.verses) ? sharePayload.verses : [];

            if (verses.length === 0) {
                return '';
            }

            const text = verses.length === 1
                ? String(verses[0].text || '').trim()
                : verses.map((verse) => `${verse.number} ${String(verse.text || '').trim()}`).join(' ');

            return truncateAtWord(text, templatePresets[template].quoteMaxChars);
        };

        const buildDefaultCaption = () => {
            const reference = String(sharePayload.reference || 'Scripture').trim();
            const text = truncateAtWord(String(sharePayload.text || '').trim(), 1100);
            const url = String(sharePayload.url || '').trim();

            return [reference, text, url].filter(Boolean).join('\n\n');
        };

        const buildBackgroundCss = (themeKey, seed) => {
            const theme = themePresets[themeKey] || themePresets['good-news-bible'];
            const random = createSeededRandom(seed);
            const orbOne = `${12 + Math.round(random() * 20)}% ${8 + Math.round(random() * 18)}%`;
            const orbTwo = `${70 + Math.round(random() * 18)}% ${10 + Math.round(random() * 20)}%`;
            const orbThree = `${18 + Math.round(random() * 60)}% ${62 + Math.round(random() * 22)}%`;

            return [
                `radial-gradient(circle at ${orbOne}, ${theme.orbs[0]}, transparent 28%)`,
                `radial-gradient(circle at ${orbTwo}, ${theme.orbs[1]}, transparent 30%)`,
                `radial-gradient(circle at ${orbThree}, ${theme.orbs[2]}, transparent 34%)`,
                `linear-gradient(160deg, ${theme.gradient[0]} 0%, ${theme.gradient[1]} 48%, ${theme.gradient[2]} 100%)`,
            ].join(', ');
        };

        const buildCanvasBackdrop = (ctx, width, height, themeKey, seed) => {
            const theme = themePresets[themeKey] || themePresets['good-news-bible'];
            const gradient = ctx.createLinearGradient(0, 0, width, height);
            gradient.addColorStop(0, theme.gradient[0]);
            gradient.addColorStop(0.5, theme.gradient[1]);
            gradient.addColorStop(1, theme.gradient[2]);
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, width, height);

            const random = createSeededRandom(seed);
            theme.orbs.forEach((color, index) => {
                const x = width * (0.15 + random() * 0.7);
                const y = height * (0.12 + random() * 0.7);
                const radius = Math.min(width, height) * (0.18 + random() * 0.1 + index * 0.02);
                const orb = ctx.createRadialGradient(x, y, 0, x, y, radius);
                orb.addColorStop(0, color);
                orb.addColorStop(1, 'rgba(255,255,255,0)');
                ctx.fillStyle = orb;
                ctx.beginPath();
                ctx.arc(x, y, radius, 0, Math.PI * 2);
                ctx.fill();
            });
        };

        const wrapText = (ctx, text, maxWidth) => {
            const words = String(text || '').split(/\s+/).filter(Boolean);
            const lines = [];
            let current = '';

            words.forEach((word) => {
                const attempt = current === '' ? word : `${current} ${word}`;

                if (ctx.measureText(attempt).width <= maxWidth) {
                    current = attempt;
                    return;
                }

                if (current !== '') {
                    lines.push(current);
                }

                current = word;
            });

            if (current !== '') {
                lines.push(current);
            }

            return lines;
        };

        const fitQuoteLayout = (ctx, quote, templateKey, fontKey, maxWidth) => {
            const template = templatePresets[templateKey];
            const font = fontPresets[fontKey] || fontPresets.editorial;
            let size = template.quoteSize;

            while (size >= template.quoteMin) {
                ctx.font = `700 ${size}px ${font.quote}`;
                const lines = wrapText(ctx, quote, maxWidth);

                if (lines.length <= template.quoteMaxLines) {
                    return {
                        size,
                        lines,
                        lineHeight: Math.round(size * template.quoteLineHeight),
                    };
                }

                size -= 2;
            }

            ctx.font = `700 ${template.quoteMin}px ${font.quote}`;
            return {
                size: template.quoteMin,
                lines: wrapText(ctx, quote, maxWidth).slice(0, template.quoteMaxLines),
                lineHeight: Math.round(template.quoteMin * template.quoteLineHeight),
            };
        };

        const renderPreview = (state) => {
            const template = templatePresets[state.template];
            const theme = themePresets[state.theme] || themePresets['good-news-bible'];
            const font = fontPresets[state.font] || fontPresets.editorial;
            const quote = buildShareBody(state.template);

            previewShell.dataset.template = state.template;
            previewShell.style.setProperty('--share-preview-aspect', template.aspectRatio);
            previewCard.className = `share-preview-card ${font.previewClass}`;
            previewCard.style.background = buildBackgroundCss(state.theme, state.seed);
            previewCard.style.color = theme.text;
            previewCard.style.setProperty('--share-panel-bg', theme.panel);
            previewCard.style.setProperty('--share-panel-border', theme.panelBorder);
            previewCard.style.setProperty('--share-text-muted', theme.muted);
            previewCard.style.setProperty('--share-text-main', theme.text);
            previewKicker.textContent = state.headline.trim() || 'Share the Good News';
            previewReference.textContent = String(sharePayload.reference || '').trim();
            previewText.textContent = quote;
            previewFooter.textContent = state.footer.trim();
            previewFooter.hidden = state.footer.trim() === '';
            previewBrand.textContent = state.branding === 'good-news' ? 'Good News Bible' : '';
            previewBrand.hidden = state.branding !== 'good-news';
        };

        const renderCanvas = (state) => {
            const template = templatePresets[state.template];
            const theme = themePresets[state.theme] || themePresets['good-news-bible'];
            const font = fontPresets[state.font] || fontPresets.editorial;
            const ctx = shareCanvas.getContext('2d');

            if (!ctx) {
                throw new Error('Canvas rendering is unavailable.');
            }

            shareCanvas.width = template.width;
            shareCanvas.height = template.height;
            ctx.clearRect(0, 0, template.width, template.height);

            buildCanvasBackdrop(ctx, template.width, template.height, state.theme, state.seed);

            const padding = template.padding;
            const cardWidth = template.width - (padding * 2);
            const cardHeight = template.height - (padding * 2);

            ctx.fillStyle = theme.panel;
            ctx.strokeStyle = theme.panelBorder;
            ctx.lineWidth = 2;
            const radius = 42;
            ctx.beginPath();
            ctx.moveTo(padding + radius, padding);
            ctx.lineTo(padding + cardWidth - radius, padding);
            ctx.quadraticCurveTo(padding + cardWidth, padding, padding + cardWidth, padding + radius);
            ctx.lineTo(padding + cardWidth, padding + cardHeight - radius);
            ctx.quadraticCurveTo(padding + cardWidth, padding + cardHeight, padding + cardWidth - radius, padding + cardHeight);
            ctx.lineTo(padding + radius, padding + cardHeight);
            ctx.quadraticCurveTo(padding, padding + cardHeight, padding, padding + cardHeight - radius);
            ctx.lineTo(padding, padding + radius);
            ctx.quadraticCurveTo(padding, padding, padding + radius, padding);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();

            const innerX = padding + 56;
            const innerY = padding + 58;
            const innerWidth = cardWidth - 112;
            let cursorY = innerY;

            ctx.fillStyle = theme.muted;
            ctx.font = `700 ${template.kickerSize}px ${font.kicker}`;
            ctx.textBaseline = 'top';
            ctx.fillText(state.headline.trim() || 'Share the Good News', innerX, cursorY, innerWidth);

            cursorY += template.kickerSize + 34;

            ctx.fillStyle = theme.text;
            ctx.font = `600 ${template.referenceSize}px ${font.reference}`;
            ctx.fillText(String(sharePayload.reference || '').trim(), innerX, cursorY, innerWidth);

            cursorY += template.referenceSize + 36;

            const quote = buildShareBody(state.template);
            const quoteLayout = fitQuoteLayout(ctx, quote, state.template, state.font, innerWidth);
            ctx.font = `700 ${quoteLayout.size}px ${font.quote}`;
            ctx.fillStyle = theme.text;

            quoteLayout.lines.forEach((line) => {
                ctx.fillText(line, innerX, cursorY, innerWidth);
                cursorY += quoteLayout.lineHeight;
            });

            const footerY = template.height - padding - 74;

            ctx.fillStyle = theme.muted;
            ctx.font = `600 26px ${font.footer}`;

            if (state.footer.trim() !== '') {
                ctx.fillText(state.footer.trim(), innerX, footerY, innerWidth * 0.7);
            }

            if (state.branding === 'good-news') {
                ctx.textAlign = 'right';
                ctx.fillText('Good News Bible', template.width - innerX, footerY, innerWidth * 0.4);
                ctx.textAlign = 'left';
            }
        };

        const currentState = () => ({
            template: templateSelect instanceof HTMLSelectElement ? templateSelect.value : 'story',
            theme: themeSelect instanceof HTMLSelectElement ? themeSelect.value : 'good-news-bible',
            font: fontSelect instanceof HTMLSelectElement ? fontSelect.value : 'editorial',
            branding: brandingSelect instanceof HTMLSelectElement ? brandingSelect.value : 'good-news',
            headline: headlineInput instanceof HTMLInputElement ? headlineInput.value : 'Share the Good News',
            footer: footerInput instanceof HTMLInputElement ? footerInput.value : 'Faith for today',
            caption: captionInput instanceof HTMLTextAreaElement ? captionInput.value : '',
            seed: Number(shareComposer.dataset.shareSeed || '1') || 1,
        });

        const rerenderComposer = () => {
            const state = currentState();
            renderPreview(state);
            renderCanvas(state);
        };

        const exportPngBlob = async () => {
            rerenderComposer();

            return new Promise((resolve, reject) => {
                shareCanvas.toBlob((blob) => {
                    if (blob) {
                        resolve(blob);
                        return;
                    }

                    reject(new Error('The share image could not be generated.'));
                }, 'image/png');
            });
        };

        if (captionInput instanceof HTMLTextAreaElement) {
            captionInput.value = buildDefaultCaption();
        }

        shareComposer.dataset.shareSeed = String(Math.floor(Date.now() % 100000));
        rerenderComposer();
        shareComposerToggle.dataset.state = 'closed';
        shareComposer.hidden = true;
        shareComposerToggle.setAttribute('aria-expanded', 'false');

        const toggleComposer = (shouldOpen) => {
            shareComposer.hidden = !shouldOpen;
            shareComposerToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            shareComposerToggle.dataset.state = shouldOpen ? 'open' : 'closed';

            if (shouldOpen) {
                shareComposer.scrollIntoView({
                    block: 'start',
                    behavior: 'smooth',
                });
            }
        };

        shareComposerToggle.addEventListener('click', () => {
            toggleComposer(shareComposer.hidden);
        });

        shareComposerClose?.addEventListener('click', () => {
            toggleComposer(false);
            shareComposerToggle.focus();
        });

        shareForm.querySelectorAll('input, select, textarea').forEach((field) => {
            field.addEventListener('input', rerenderComposer);
            field.addEventListener('change', rerenderComposer);
        });

        randomizeButton?.addEventListener('click', () => {
            shareComposer.dataset.shareSeed = String(Math.floor(Math.random() * 1000000) + 1);
            rerenderComposer();
            setShareStatus('Background refreshed for a new post variation.');
        });

        copyCaptionButton?.addEventListener('click', async () => {
            if (!(captionInput instanceof HTMLTextAreaElement) || !navigator.clipboard?.writeText) {
                setShareStatus('Copy is not available in this browser.');
                return;
            }

            try {
                await navigator.clipboard.writeText(captionInput.value);
                setShareStatus('Caption copied. Paste it into your public post.');
            } catch (error) {
                setShareStatus('The caption could not be copied.');
            }
        });

        downloadButton?.addEventListener('click', async () => {
            try {
                const blob = await exportPngBlob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const reference = String(sharePayload.reference || 'scripture').toLowerCase().replace(/[^a-z0-9]+/g, '-');

                link.href = url;
                link.download = `${reference}-${currentState().template}.png`;
                link.click();
                URL.revokeObjectURL(url);
                setShareStatus('PNG downloaded for your post.');
            } catch (error) {
                const message = error instanceof Error ? error.message : 'The share image could not be downloaded.';
                setShareStatus(message);
            }
        });

        nativeShareButton?.addEventListener('click', async () => {
            if (!(captionInput instanceof HTMLTextAreaElement) || !navigator.share) {
                setShareStatus('Native sharing is not available here. Use Download PNG instead.');
                return;
            }

            try {
                const blob = await exportPngBlob();
                const reference = String(sharePayload.reference || 'scripture').toLowerCase().replace(/[^a-z0-9]+/g, '-');
                const file = new File([blob], `${reference}-${currentState().template}.png`, { type: 'image/png' });
                const shareData = {
                    title: String(sharePayload.reference || 'Scripture'),
                    text: captionInput.value,
                    files: [file],
                };

                if (navigator.canShare && !navigator.canShare({ files: [file] })) {
                    setShareStatus('This browser cannot share image files directly. Use Download PNG instead.');
                    return;
                }

                await navigator.share(shareData);
                setShareStatus('Ready to share the Good News.');
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    setShareStatus('Share canceled.');
                    return;
                }

                setShareStatus('The post could not be shared directly. Download the PNG instead.');
            }
        });
    } else {
        shareComposerToggle.setAttribute('hidden', 'hidden');
    }
}

document.querySelectorAll('[data-mobile-reader-focus]').forEach((button) => {
    button.addEventListener('click', () => {
        const mobileNav = document.querySelector('[data-mobile-bible-nav]');
        const bookSelect = mobileNav?.querySelector('[data-reader-select="book"]');

        if (mobileNav instanceof HTMLElement) {
            mobileNav.classList.add('is-open');
            mobileBibleNavToggle?.setAttribute('aria-expanded', 'true');
            mobileBibleNavToggle?.setAttribute('aria-label', 'Close Bible navigation');
        }

        if (bookSelect instanceof HTMLSelectElement) {
            window.setTimeout(() => bookSelect.focus(), 180);
        }
    });
});

document.querySelectorAll('[data-mobile-share-open]').forEach((button) => {
    button.addEventListener('click', () => {
        if (shareComposerToggle instanceof HTMLButtonElement && !shareComposerToggle.hidden) {
            shareComposerToggle.click();
        }
    });
});

document.querySelectorAll('[data-mobile-highlight-tip]').forEach((button) => {
    button.addEventListener('click', () => {
        if (chapterReader instanceof HTMLElement) {
            chapterReader.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }

        if (button instanceof HTMLButtonElement) {
            const originalLabel = button.textContent || 'Highlight';
            button.textContent = 'Tap verse';
            window.setTimeout(() => {
                button.textContent = originalLabel;
            }, 1800);
        }
    });
});

// ── Bookmark edit-panel toggle (bookmarks.php) ──────────────────────────
document.querySelectorAll('.bookmark-edit-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
        var panelId = toggle.getAttribute('aria-controls');
        var panel = panelId ? document.getElementById(panelId) : null;

        if (!panel) {
            return;
        }

        var isOpen = !panel.hidden;
        panel.hidden = isOpen;
        toggle.setAttribute('aria-expanded', String(!isOpen));
        toggle.classList.toggle('is-active', !isOpen);
    });
});

// Study builder day/item board controls.
if (document.querySelector('form [data-study-day]')) {
    var updateStudyBuilderNames = function (form) {
        form.querySelectorAll('[data-study-day]').forEach(function (day, dayIndex) {
            var dayNumber = day.querySelector('input[name="step_day_number[]"]');
            var dayPill = day.querySelector(':scope > .panel-heading .pill');
            var dayTitle = day.querySelector('input[name="step_title[]"]');
            var dayHeading = day.querySelector(':scope > .panel-heading h3');

            if (dayNumber instanceof HTMLInputElement && dayNumber.value === '') {
                dayNumber.value = String(dayIndex + 1);
            }

            if (dayPill) {
                dayPill.textContent = 'Day ' + (dayNumber instanceof HTMLInputElement && dayNumber.value !== '' ? dayNumber.value : String(dayIndex + 1));
            }

            if (dayHeading && dayTitle instanceof HTMLInputElement) {
                dayHeading.textContent = dayTitle.value || 'New study day';
            }

            day.querySelectorAll('[data-study-item]').forEach(function (item, itemIndex) {
                item.querySelectorAll('[name^="step_item_"]').forEach(function (field) {
                    var name = field.getAttribute('name') || '';
                    var base = name.replace(/^([^\[]+).*/, '$1');

                    if (!base) {
                        return;
                    }

                    if (field instanceof HTMLInputElement && field.type === 'checkbox') {
                        field.setAttribute('name', base + '[' + dayIndex + '][' + itemIndex + ']');
                    } else {
                        field.setAttribute('name', base + '[' + dayIndex + '][]');
                    }
                });

                var pill = item.querySelector('.pill');
                if (pill) {
                    pill.textContent = 'Item ' + (itemIndex + 1);
                }
            });
        });
    };

    document.querySelectorAll('form').forEach(function (form) {
        if (!form.querySelector('[data-study-day]')) {
            return;
        }

        form.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null;
            var dayAdd = target?.closest('[data-study-day-add]');
            var dayRemove = target?.closest('[data-study-day-remove]');
            var dayMove = target?.closest('[data-study-day-move]');
            var itemAdd = target?.closest('[data-study-item-add]');
            var itemRemove = target?.closest('[data-study-item-remove]');
            var itemMove = target?.closest('[data-study-item-move]');

            if (dayAdd) {
                var days = form.querySelectorAll('[data-study-day]');
                var lastDay = days[days.length - 1];
                if (lastDay) {
                    var clone = lastDay.cloneNode(true);
                    clone.querySelectorAll('input, textarea').forEach(function (field) {
                        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
                            field.checked = true;
                        } else if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
                            field.value = '';
                        }
                    });
                    clone.querySelectorAll('select').forEach(function (field) {
                        field.selectedIndex = 0;
                    });
                    lastDay.after(clone);
                    updateStudyBuilderNames(form);
                }
            }

            if (dayRemove) {
                var day = dayRemove.closest('[data-study-day]');
                if (day && form.querySelectorAll('[data-study-day]').length > 1) {
                    day.remove();
                    updateStudyBuilderNames(form);
                }
            }

            if (dayMove) {
                var moveDay = dayMove.closest('[data-study-day]');
                var dir = dayMove.getAttribute('data-study-day-move');
                if (moveDay && dir === 'up' && moveDay.previousElementSibling?.matches('[data-study-day]')) {
                    moveDay.previousElementSibling.before(moveDay);
                } else if (moveDay && dir === 'down' && moveDay.nextElementSibling?.matches('[data-study-day]')) {
                    moveDay.nextElementSibling.after(moveDay);
                }
                updateStudyBuilderNames(form);
            }

            if (itemAdd) {
                var list = itemAdd.closest('[data-study-items]');
                var lastItem = list?.querySelector('[data-study-item]:last-of-type');
                if (list && lastItem) {
                    var itemClone = lastItem.cloneNode(true);
                    itemClone.querySelectorAll('input, textarea').forEach(function (field) {
                        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
                            field.checked = true;
                        } else if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
                            field.value = '';
                        }
                    });
                    itemClone.querySelectorAll('select').forEach(function (field) {
                        field.selectedIndex = 0;
                    });
                    list.appendChild(itemClone);
                    updateStudyBuilderNames(form);
                }
            }

            if (itemRemove) {
                var item = itemRemove.closest('[data-study-item]');
                var itemList = itemRemove.closest('[data-study-items]');
                if (item && itemList && itemList.querySelectorAll('[data-study-item]').length > 1) {
                    item.remove();
                    updateStudyBuilderNames(form);
                }
            }

            if (itemMove) {
                var moveItem = itemMove.closest('[data-study-item]');
                var itemDir = itemMove.getAttribute('data-study-item-move');
                if (moveItem && itemDir === 'up' && moveItem.previousElementSibling?.matches('[data-study-item]')) {
                    moveItem.previousElementSibling.before(moveItem);
                } else if (moveItem && itemDir === 'down' && moveItem.nextElementSibling?.matches('[data-study-item]')) {
                    moveItem.nextElementSibling.after(moveItem);
                }
                updateStudyBuilderNames(form);
            }
        });

        form.addEventListener('submit', function () {
            updateStudyBuilderNames(form);
        });
    });
}
