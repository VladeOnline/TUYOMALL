(function () {
  const storageKey = 'tuyomall_theme';

  function getTheme() {
    return localStorage.getItem(storageKey) || 'light';
  }

  function setButtonIcon(isDark) {
    const moonSvg = '<svg class="theme-symbol" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 14.2A8.2 8.2 0 0 1 9.8 3a7.8 7.8 0 1 0 11.2 11.2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    const sunSvg = '<svg class="theme-symbol" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    document.querySelectorAll('[data-theme-toggle], #themeToggle').forEach((button) => {
      const icon = button.querySelector('.ti');
      const symbol = button.querySelector('.theme-symbol');

      if (icon) icon.className = `ti ${isDark ? 'ti-sun' : 'ti-moon'}`;
      if (symbol) {
        symbol.outerHTML = isDark ? sunSvg : moonSvg;
      }
      button.setAttribute('aria-label', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
      button.setAttribute('title', isDark ? 'Modo claro' : 'Modo oscuro');
    });
  }

  function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('tm-dark', isDark);
    document.body.classList.toggle('dark-mode', isDark);
    localStorage.setItem(storageKey, isDark ? 'dark' : 'light');
    setButtonIcon(isDark);
  }

  function toggleTheme() {
    applyTheme(document.documentElement.classList.contains('tm-dark') ? 'light' : 'dark');
  }

  function ensureThemeButton() {
    if (document.querySelector('[data-theme-toggle], #themeToggle')) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'global-theme-toggle';
    button.setAttribute('data-theme-toggle', '');
    button.innerHTML = '<svg class="theme-symbol" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 14.2A8.2 8.2 0 0 1 9.8 3a7.8 7.8 0 1 0 11.2 11.2Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.body.appendChild(button);
  }

  window.applyTheme = applyTheme;
  window.toggleTheme = toggleTheme;

  document.documentElement.classList.toggle('tm-dark', getTheme() === 'dark');

  document.addEventListener('DOMContentLoaded', function () {
    ensureThemeButton();
    document.querySelectorAll('[data-theme-toggle], #themeToggle:not([onclick])').forEach((button) => {
      if (!button.dataset.themeReady) {
        button.dataset.themeReady = 'true';
        button.addEventListener('click', toggleTheme);
      }
    });
    applyTheme(getTheme());
  });
})();
