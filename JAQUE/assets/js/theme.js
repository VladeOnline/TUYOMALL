(function () {
  const storageKey = 'tuyomall_theme';

  function getTheme() {
    return localStorage.getItem(storageKey) || 'light';
  }

  function setButtonIcon(isDark) {
    document.querySelectorAll('[data-theme-toggle], #themeToggle').forEach((button) => {
      const icon = button.querySelector('.ti');
      const symbol = button.querySelector('.theme-symbol');

      if (icon) icon.className = `ti ${isDark ? 'ti-sun' : 'ti-moon'}`;
      if (symbol) symbol.textContent = isDark ? 'D' : 'N';
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
    button.innerHTML = '<span class="theme-symbol">N</span>';
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
