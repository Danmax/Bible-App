(() => {
    const track = document.querySelector('[data-gospel-track]');

    if (!(track instanceof HTMLElement)) {
        return;
    }

    const tabs = Array.from(track.querySelectorAll('[data-gospel-step]'));
    const panels = Array.from(track.querySelectorAll('[data-gospel-panel]'));
    const previousButton = track.querySelector('[data-gospel-previous]');
    const nextButton = track.querySelector('[data-gospel-next]');
    const currentLabel = track.querySelector('[data-gospel-current]');
    const progress = track.querySelector('[data-gospel-progress]');
    let currentStep = 0;

    const showStep = (index, moveFocus = false) => {
        const safeIndex = Math.max(0, Math.min(index, tabs.length - 1));
        currentStep = safeIndex;

        tabs.forEach((tab, tabIndex) => {
            const isActive = tabIndex === safeIndex;
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        panels.forEach((panel, panelIndex) => {
            panel.hidden = panelIndex !== safeIndex;
        });

        if (previousButton instanceof HTMLButtonElement) {
            previousButton.disabled = safeIndex === 0;
        }

        if (nextButton instanceof HTMLButtonElement) {
            nextButton.disabled = safeIndex === tabs.length - 1;
        }

        if (currentLabel instanceof HTMLElement) {
            currentLabel.textContent = String(safeIndex + 1);
        }

        if (progress instanceof HTMLElement) {
            progress.style.width = `${((safeIndex + 1) / tabs.length) * 100}%`;
        }

        if (moveFocus && tabs[safeIndex] instanceof HTMLButtonElement) {
            tabs[safeIndex].focus();
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => showStep(index));
        tab.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                showStep((index + 1) % tabs.length, true);
            }

            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                showStep((index - 1 + tabs.length) % tabs.length, true);
            }
        });
    });

    previousButton?.addEventListener('click', () => showStep(currentStep - 1));
    nextButton?.addEventListener('click', () => showStep(currentStep + 1));
    showStep(0);

    const choiceResponse = track.querySelector('[data-gospel-choice-response]');
    const choiceMessages = {
        curious: 'Curiosity is a meaningful beginning. Start with the four-part story below and follow any Scripture link that catches your attention.',
        restart: 'You are not disqualified by your past. The Gospel is an invitation to receive grace and begin again with God.',
        questions: 'Your questions are welcome. Explore the story, then open the newcomer questions below—there is no pressure to rush.',
    };

    track.querySelectorAll('[data-gospel-choice]').forEach((button) => {
        button.addEventListener('click', () => {
            const choice = button.getAttribute('data-gospel-choice') || '';

            track.querySelectorAll('[data-gospel-choice]').forEach((item) => {
                item.classList.toggle('is-selected', item === button);
                item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
            });

            if (choiceResponse instanceof HTMLElement && choice in choiceMessages) {
                choiceResponse.textContent = choiceMessages[choice];
            }
        });
    });
})();
