(() => {
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
    let activeTheme = defaultTheme;

    try {
        const savedTheme = window.localStorage.getItem('app-theme');

        if (savedTheme && Object.prototype.hasOwnProperty.call(themeMetaColors, savedTheme)) {
            activeTheme = savedTheme;
        }
    } catch (error) {
        activeTheme = defaultTheme;
    }

    document.documentElement.setAttribute('data-theme', activeTheme);

    const themeColorMeta = document.querySelector('meta[name="theme-color"]');

    if (themeColorMeta && Object.prototype.hasOwnProperty.call(themeMetaColors, activeTheme)) {
        themeColorMeta.setAttribute('content', themeMetaColors[activeTheme]);
    }
})();
