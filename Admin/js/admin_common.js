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
  const notifBtn = document.getElementById('adminNotifBtn');
  const notifBadge = document.getElementById('adminNotifBadge');
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

  let notificationsState = { count: 0, items: [], sources: { orders: 0, newsletter: 0 } };
  let notifPollTimer = null;
  let notificationsPollingDisabled = false;

  const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const formatNotifDate = (value) => {
    if (!value) return '-';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const updateNotifBadge = (count) => {
    if (!notifBadge || !notifBtn) return;
    const safeCount = Math.max(0, Number(count || 0));
    if (safeCount <= 0) {
      notifBadge.hidden = true;
      notifBadge.textContent = '0';
      notifBtn.setAttribute('aria-label', 'Notifications');
      return;
    }
    notifBadge.hidden = false;
    notifBadge.textContent = safeCount > 99 ? '99+' : String(safeCount);
    notifBtn.setAttribute('aria-label', `${safeCount} notifications non lues`);
  };

  const fetchNotifications = async () => {
    if (!notifBtn) return;
    if (notificationsPollingDisabled) return;
    const response = await fetch('../api/admin_notifications.php', {
      headers: { Accept: 'application/json' },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 401 || response.status === 403) {
        // Session expired or unauthorized: stop noisy polling until a page reload/login.
        notificationsPollingDisabled = true;
        if (notifPollTimer) {
          window.clearInterval(notifPollTimer);
          notifPollTimer = null;
        }
      }
      throw new Error(payload?.error || 'Notifications indisponibles');
    }
    notificationsState = payload?.data || notificationsState;
    updateNotifBadge(notificationsState.count || 0);
  };

  const markNotificationsRead = async () => {
    const response = await fetch('../api/admin_notifications.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': window.getAdminCsrfToken(),
      },
      body: JSON.stringify({ action: 'mark_read' }),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload?.error || 'Impossible de marquer comme lu');
    }
  };

  const openNotificationsModal = async () => {
    if (!window.AdminModal || !notifBtn) return;
    const items = Array.isArray(notificationsState.items) ? notificationsState.items : [];
    const sources = notificationsState.sources || {};
    const rowsHtml = items.length
      ? items.map((item) => {
        const isOrder = String(item.type || '') === 'order';
        const icon = isOrder ? 'fa-bag-shopping' : 'fa-envelope-open-text';
        const source = isOrder ? 'Commandes' : 'Newsletter';
        const target = String(item.target_url || '').trim();
        return `
          <li class="admin-notif-item">
            <div class="admin-notif-item__icon"><i class="fa-solid ${icon}" aria-hidden="true"></i></div>
            <div class="admin-notif-item__content">
              <strong>${escapeHtml(item.title || 'Notification')}</strong>
              <p>${escapeHtml(item.message || '')}</p>
              <small>${escapeHtml(source)} • ${escapeHtml(formatNotifDate(item.created_at))}</small>
            </div>
            ${target ? `<a class="admin-btn admin-btn--chip admin-notif-item__link" href="${escapeHtml(target)}">Voir</a>` : ''}
          </li>
        `;
      }).join('')
      : '<li class="admin-notif-empty">Aucune nouvelle notification.</li>';

    window.AdminModal.open({
      title: 'Notifications',
      content: `
        <div class="admin-notif-modal">
          <div class="admin-notif-modal__summary">
            <span><strong>${Number(sources.orders || 0)}</strong> commande(s)</span>
            <span><strong>${Number(sources.newsletter || 0)}</strong> abonne(s) newsletter</span>
          </div>
          <ul class="admin-notif-list">${rowsHtml}</ul>
        </div>
      `,
      onOpen: async () => {
        notifBtn.setAttribute('aria-expanded', 'true');
        if ((notificationsState.count || 0) > 0) {
          try {
            await markNotificationsRead();
            notificationsState = { ...notificationsState, count: 0, items: [] };
            updateNotifBadge(0);
          } catch (error) {
            // Keep UX non-blocking if mark-read fails.
          }
        }
      },
    });
  };

  notifBtn?.addEventListener('click', async () => {
    try {
      await fetchNotifications();
      await openNotificationsModal();
    } catch (error) {
      if (typeof window.showToast === 'function') {
        window.showToast('error', error.message || 'Notifications indisponibles', { key: 'admin_notif_error' });
      }
    }
  });

  overlay.addEventListener('click', (event) => {
    if (event.target === overlay) {
      notifBtn?.setAttribute('aria-expanded', 'false');
    }
  });
  closeBtn.addEventListener('click', () => {
    notifBtn?.setAttribute('aria-expanded', 'false');
  });

  const startNotificationsPolling = async () => {
    if (!notifBtn) return;
    try {
      await fetchNotifications();
    } catch (error) {
      // Silent background retry.
    }
    if (notifPollTimer) window.clearInterval(notifPollTimer);
    notifPollTimer = window.setInterval(async () => {
      if (notificationsPollingDisabled) return;
      try {
        await fetchNotifications();
      } catch (error) {
        // Keep polling silent for transient network failures.
      }
    }, 25000);
  };
  startNotificationsPolling();

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
