(function adminCommon() {
  const themeFab = document.getElementById('globalAdminThemeToggle');

  const updateThemeButtons = (theme) => {
    const isDark = theme === 'dark';
    const iconClass = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    const label = isDark ? 'Activer le mode clair' : 'Activer le mode sombre';

    const fabIcon = themeFab?.querySelector('i');
    if (fabIcon) fabIcon.className = iconClass;
    if (themeFab) themeFab.setAttribute('aria-label', label);

    const dashboardBtn = document.getElementById('dashboardThemeToggle');
    const dashboardIcon = dashboardBtn?.querySelector('i');
    if (dashboardIcon) dashboardIcon.className = iconClass;
  };

  const applyTheme = (theme, options = {}) => {
    const dark = String(theme || '').toLowerCase() === 'dark';
    const normalized = dark ? 'dark' : 'light';
    document.body.classList.toggle('admin-theme-dark', dark);
    updateThemeButtons(normalized);
    if (options.dispatch !== false) {
      document.dispatchEvent(new CustomEvent('admin:theme-changed', {
        detail: { theme: normalized },
      }));
    }
  };

  window.getAdminCsrfToken = function getAdminCsrfToken() {
    const token = window.ADMIN_CSRF_TOKEN
      || document.querySelector('meta[name="admin-csrf-token"]')?.getAttribute('content')
      || '';
    return String(token || '');
  };

  const persistThemePreference = async (theme) => {
    try {
      await fetch('../api/admin_dashboard_preferences.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': window.getAdminCsrfToken(),
        },
        body: JSON.stringify({ theme }),
      });
    } catch (e) {
      // Ignore remote persistence errors; local storage still keeps UX.
    }
  };

  window.setAdminTheme = async function setAdminTheme(theme, options = {}) {
    const normalized = String(theme || '').toLowerCase() === 'dark' ? 'dark' : 'light';
    applyTheme(normalized);
    try {
      window.localStorage?.setItem('admin_theme', normalized);
    } catch (e) {
      // Ignore localStorage access errors.
    }
    if (options.persistRemote !== false) {
      await persistThemePreference(normalized);
    }
    return normalized;
  };

  try {
    const cachedTheme = window.localStorage?.getItem('admin_theme') || '';
    if (cachedTheme) applyTheme(cachedTheme);
  } catch (e) {
    // Ignore localStorage access errors.
  }

  const loadPersistedTheme = async () => {
    try {
      const response = await fetch('../api/admin_dashboard_preferences.php', {
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json();
      if (!response.ok) return;
      const theme = String(payload?.data?.theme || 'light');
      applyTheme(theme);
      try {
        window.localStorage?.setItem('admin_theme', theme);
      } catch (e) {
        // Ignore localStorage access errors.
      }
    } catch (e) {
      // Keep current theme if API is unavailable.
    }
  };

  loadPersistedTheme();

  themeFab?.addEventListener('click', async () => {
    const isDark = document.body.classList.contains('admin-theme-dark');
    await window.setAdminTheme(isDark ? 'light' : 'dark');
  });

  const sidebar = document.getElementById('adminSidebar');
  const toggle = document.getElementById('adminMenuToggle');
  if (sidebar && toggle) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('is-open');
    });

    document.addEventListener('click', (event) => {
      if (window.innerWidth > 900) return;
      if (sidebar.contains(event.target) || toggle.contains(event.target)) return;
      sidebar.classList.remove('is-open');
    });
  }

  const body = document.body;
  const topbarSearch = document.getElementById('adminTopbarSearch');
  const overlay = document.createElement('div');
  overlay.className = 'admin-modal-overlay';
  overlay.setAttribute('aria-hidden', 'true');

  const modal = document.createElement('div');
  modal.className = 'admin-modal';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.innerHTML = [
    '<div class="admin-modal__header">',
    '  <h2 class="admin-modal__title" id="adminModalTitle"></h2>',
    '  <button type="button" class="admin-modal__close" id="adminModalClose" aria-label="Fermer">',
    '    <i class="fa-solid fa-xmark" aria-hidden="true"></i>',
    '  </button>',
    '</div>',
    '<div class="admin-modal__body" id="adminModalBody"></div>',
  ].join('');
  overlay.appendChild(modal);
  body.appendChild(overlay);

  const titleNode = modal.querySelector('#adminModalTitle');
  const bodyNode = modal.querySelector('#adminModalBody');
  const closeBtn = modal.querySelector('#adminModalClose');
  let opener = null;

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',');

  const trapFocus = (event) => {
    if (!overlay.classList.contains('is-open') || event.key !== 'Tab') return;
    const focusables = [...modal.querySelectorAll(focusableSelector)];
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
      return;
    }
    if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };

  const close = () => {
    if (!overlay.classList.contains('is-open')) return;
    overlay.classList.remove('is-open');
    overlay.classList.add('is-closing');
    body.classList.remove('admin-no-scroll');

    window.setTimeout(() => {
      overlay.classList.remove('is-closing');
      overlay.setAttribute('aria-hidden', 'true');
      bodyNode.innerHTML = '';
      titleNode.textContent = '';
      if (opener && typeof opener.focus === 'function') opener.focus();
      opener = null;
    }, 220);
  };

  const open = ({ title, content, onOpen }) => {
    opener = document.activeElement;
    titleNode.textContent = title || '';
    bodyNode.innerHTML = '';
    if (typeof content === 'string') {
      bodyNode.innerHTML = content;
    } else if (content instanceof Node) {
      bodyNode.appendChild(content);
    }

    overlay.setAttribute('aria-hidden', 'false');
    overlay.classList.remove('is-closing');
    overlay.classList.add('is-open');
    body.classList.add('admin-no-scroll');

    window.requestAnimationFrame(() => {
      const autofocus = modal.querySelector('[autofocus]') || modal.querySelector(focusableSelector);
      if (autofocus && typeof autofocus.focus === 'function') autofocus.focus();
      if (typeof onOpen === 'function') onOpen();
    });
  };

  closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
    trapFocus(event);
  });

  window.AdminModal = { open, close };

  const applyGlobalAdminSearch = (query) => {
    const normalized = String(query || '').trim().toLowerCase();
    const rows = Array.from(document.querySelectorAll('.admin-content .admin-table tbody tr'));
    rows.forEach((row) => {
      const text = (row.textContent || '').toLowerCase();
      row.style.display = (!normalized || text.includes(normalized)) ? '' : 'none';
    });
  };

  topbarSearch?.addEventListener('input', () => {
    const q = topbarSearch.value || '';
    document.dispatchEvent(new CustomEvent('admin:topbar-search', { detail: { query: q } }));
    applyGlobalAdminSearch(q);
  });
})();
