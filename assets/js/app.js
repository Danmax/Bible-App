const menuToggle = document.querySelector('.menu-toggle');
const primaryNav = document.querySelector('.primary-nav');

if (menuToggle && primaryNav) {
    menuToggle.addEventListener('click', () => {
        const isOpen = primaryNav.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
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
    listeningMessage,
    successMessage,
    unsupportedMessage,
}) => {
    const MediaRecorderApi = window.MediaRecorder;
    const mediaDevices = navigator.mediaDevices;
    const canRecord = Boolean(MediaRecorderApi && mediaDevices && typeof mediaDevices.getUserMedia === 'function');
    let mediaRecorder = null;
    let mediaStream = null;
    let audioChunks = [];

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

    const setRecordingState = (isRecording) => {
        if (triggerButton instanceof HTMLButtonElement) {
            triggerButton.hidden = isRecording;
            triggerButton.disabled = !canRecord;
        }

        if (stopButton instanceof HTMLButtonElement) {
            stopButton.hidden = !isRecording;
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
            throw new Error('Record audio first, then try again.');
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
            throw new Error(rawText.trim() || 'The voice transcription response was not valid JSON.');
        }

        if (!response.ok) {
            throw new Error(payload.error || 'The voice transcription could not be completed.');
        }

        const transcript = String(payload.text || '').trim();

        if (transcript === '') {
            throw new Error('The voice transcription was empty.');
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
                setStatus('Transcribing audio...');

                try {
                    await uploadRecording();
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'The voice transcription could not be completed.';
                    setStatus(message);
                } finally {
                    audioChunks = [];
                    mediaRecorder = null;
                }
            });

            setRecordingState(true);
            setStatus(listeningMessage);
            mediaRecorder.start();
        } catch (error) {
            stopMediaTracks();
            const message = error instanceof Error ? error.message : 'Microphone access was not granted.';
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
    const stopButton = group.querySelector('[data-voice-search-stop]');
    const statusNode = group.parentElement?.querySelector('[data-voice-search-status]');
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;

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
        stopButton: stopButton instanceof HTMLButtonElement ? stopButton : null,
        statusNode,
        csrfInput: csrfInput instanceof HTMLInputElement ? csrfInput : null,
        onTranscript: (transcript) => {
            const transcriptBase = input.value.trim();
            input.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? ' ' : '');
        },
        listeningMessage: 'Listening... say a verse reference or keyword.',
        successMessage: (payload) => `Voice search captured with ${payload.model || 'OpenAI'}. Press Search when ready.`,
        unsupportedMessage: 'Voice search is not supported in this browser.',
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
        startButton.hidden = true;

        if (stopButton instanceof HTMLButtonElement) {
            stopButton.hidden = false;
        }

        setStatus('Listening... say a verse reference or keyword.');
    });

    recognition.addEventListener('result', (event) => {
        const transcript = Array.from(event.results)
            .map((result) => result[0]?.transcript || '')
            .join(' ')
            .trim();

        input.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? ' ' : '');
    });

    recognition.addEventListener('end', () => {
        startButton.hidden = false;

        if (stopButton instanceof HTMLButtonElement) {
            stopButton.hidden = true;
        }

        setStatus('Voice search captured. Press Search when ready.');
    });

    recognition.addEventListener('error', (event) => {
        setStatus(`Voice search error: ${event.error}`);
    });

    startButton.addEventListener('click', () => {
        input.focus();
        try {
            recognition?.start();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Voice search could not start.';
            setStatus(message);
        }
    });

    stopButton?.addEventListener('click', () => {
        recognition?.stop();
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
    let recognition = null;

    const setStatus = (message) => {
        if (statusNode instanceof HTMLElement) {
            statusNode.textContent = message;
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
        listeningMessage: 'Listening... speak your note.',
        successMessage: (payload) => `Voice note captured with ${payload.model || 'OpenAI'}. Keep editing or save when ready.`,
        unsupportedMessage: 'Voice notes are not supported in this browser.',
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
        startButton.hidden = true;

        if (stopButton instanceof HTMLButtonElement) {
            stopButton.hidden = false;
        }

        setStatus('Listening... speak your note.');
    });

    recognition.addEventListener('result', (event) => {
        const transcript = Array.from(event.results)
            .map((result) => result[0]?.transcript || '')
            .join(' ')
            .trim();

        textarea.value = [transcriptBase, transcript].filter(Boolean).join(transcriptBase && transcript ? '\n\n' : '');
    });

    recognition.addEventListener('end', () => {
        startButton.hidden = false;

        if (stopButton instanceof HTMLButtonElement) {
            stopButton.hidden = true;
        }

        setStatus('Voice note captured. Keep editing or save when ready.');
    });

    recognition.addEventListener('error', (event) => {
        setStatus(`Voice note error: ${event.error}`);
    });

    startButton.addEventListener('click', () => {
        textarea.focus();
        try {
            recognition?.start();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Voice note could not start.';
            setStatus(message);
        }
    });

    stopButton?.addEventListener('click', () => {
        recognition?.stop();
    });
});

const profileForm = document.querySelector('[data-profile-form]');
const passwordForm = document.querySelector('[data-password-form]');

if (profileForm && passwordForm) {
    const profileEditButton = profileForm.querySelector('[data-profile-edit-toggle]');
    const profileCancelButton = profileForm.querySelector('[data-profile-edit-cancel]');
    const profileSaveButton = profileForm.querySelector('[data-profile-save]');
    const profileFields = profileForm.querySelector('[data-profile-fields]');
    const profileEditableFields = profileForm.querySelectorAll('input[name="name"], input[name="email"], input[name="city"], input[name="avatar_url"]');

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

const readerNav = document.querySelector('[data-reader-nav]');

if (readerNav) {
    const bookSelect = readerNav.querySelector('[data-reader-select="book"]');
    const chapterSelect = readerNav.querySelector('[data-reader-select="chapter"]');
    const verseSelect = readerNav.querySelector('[data-reader-select="verse"]');
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
        if (readerNav instanceof HTMLFormElement) {
            const verseValue = verseSelect instanceof HTMLSelectElement ? verseSelect.value.trim() : '';

            if (verseValue !== '') {
                window.location.assign(buildReaderNavigationUrl(readerNav, verseValue));
                return;
            }

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
