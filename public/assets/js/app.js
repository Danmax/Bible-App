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
