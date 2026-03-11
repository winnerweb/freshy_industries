(function adminDashboardLive() {
  const REFRESH_MIN = 15;
  const REFRESH_MAX = 300;
  const DEFAULT_PREFS = {
    theme: 'light',
    layout: {
      refresh_sec: 45,
      widgets_order: ['stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients'],
      dashboard_widgets_order: ['sales_chart', 'business_alerts', 'recent_orders', 'activity_feed'],
      widgets_visible: {
        stat_ventes: true,
        stat_revenus: true,
        stat_commandes: true,
        stat_clients: true,
        sales_chart: true,
        business_alerts: true,
        recent_orders: true,
        activity_feed: true,
      },
    },
  };

  const state = {
    prefs: JSON.parse(JSON.stringify(DEFAULT_PREFS)),
    period: 'month',
    cachedRecentOrders: [],
    chartInstance: null,
    refreshTimer: null,
    loading: false,
  };

  const els = {
    periodSelect: document.getElementById('dashboardPeriodSelect'),
    autoRefreshSelect: document.getElementById('dashboardAutoRefreshSelect'),
    themeToggle: document.getElementById('dashboardThemeToggle'),
    manageWidgetsBtn: document.getElementById('dashboardManageWidgetsBtn'),
    refreshBtn: document.getElementById('dashboardRefreshBtn'),
    searchInput: document.getElementById('adminTopbarSearch'),
    statsGrid: document.getElementById('dashboardStatsGrid'),
    widgetBoard: document.getElementById('dashboardWidgetBoard'),
    alerts: document.getElementById('dashboardAlerts'),
    activity: document.getElementById('dashboardActivity'),
    recentOrders: document.getElementById('dashboardRecentOrders'),
    chartPeriodBtn: document.getElementById('adminChartPeriodBtn'),
    chartPeriodLabel: document.getElementById('adminChartPeriodLabel'),
    chartPeriodMenu: document.getElementById('adminChartPeriodMenu'),
  };

  const money = (cents) => `${Math.floor((Number(cents) || 0) / 100).toLocaleString('fr-FR')} Fcfa`;
  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `dashboard_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const setTheme = (theme) => {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';
    if (typeof window.setAdminTheme === 'function') {
      window.setAdminTheme(nextTheme, { persistRemote: false });
    } else {
      document.body.classList.toggle('admin-theme-dark', nextTheme === 'dark');
    }
    state.prefs.theme = nextTheme;
    try {
      window.localStorage?.setItem('admin_theme', nextTheme);
    } catch (e) {
      // ignore storage errors
    }
    const icon = els.themeToggle?.querySelector('i');
    if (icon) {
      icon.className = nextTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
  };

  const normalizeStatus = (status) => {
    if (status === 'paid') return 'paid';
    if (status === 'pending') return 'pending';
    if (status === 'canceled') return 'inactive';
    return 'active';
  };

  const applyWidgetVisibility = () => {
    const visible = state.prefs.layout.widgets_visible || {};
    document.querySelectorAll('[data-widget-id]').forEach((node) => {
      const widgetId = node.getAttribute('data-widget-id') || '';
      node.classList.toggle('is-hidden', visible[widgetId] === false);
    });
  };

  const applyStatsOrder = () => {
    const grid = els.statsGrid;
    if (!grid) return;
    const order = Array.isArray(state.prefs.layout.widgets_order) ? state.prefs.layout.widgets_order : [];
    order.forEach((widgetId) => {
      const card = grid.querySelector(`[data-widget-id="${widgetId}"]`);
      if (card) grid.appendChild(card);
    });
  };

  const applyBoardOrder = () => {
    const board = els.widgetBoard;
    if (!board) return;
    const order = Array.isArray(state.prefs.layout.dashboard_widgets_order) ? state.prefs.layout.dashboard_widgets_order : [];
    order.forEach((widgetId) => {
      const card = board.querySelector(`[data-widget-id="${widgetId}"]`);
      if (card) board.appendChild(card);
    });
  };

  const applyPrefsToUI = () => {
    const refreshSec = Number(state.prefs.layout.refresh_sec) || 45;
    if (els.autoRefreshSelect) {
      els.autoRefreshSelect.value = String(refreshSec);
    }
    applyStatsOrder();
    applyBoardOrder();
    applyWidgetVisibility();
    setTheme(state.prefs.theme);
    setupAutoRefresh();
  };

  const setLoadingState = (isLoading) => {
    state.loading = isLoading;
    document.querySelectorAll('.admin-panel, .admin-stat-card').forEach((node) => {
      node.classList.toggle('is-loading', isLoading);
    });
    if (els.refreshBtn) {
      els.refreshBtn.disabled = isLoading;
    }
  };

  const periodLabelMap = { week: '/semaine', month: '/mois', year: '/annee' };

  const syncChartPeriodUi = () => {
    if (els.chartPeriodLabel) {
      els.chartPeriodLabel.textContent = periodLabelMap[state.period] || '/mois';
    }
    const buttons = els.chartPeriodMenu?.querySelectorAll('button[data-period]') || [];
    buttons.forEach((btn) => {
      const active = btn.getAttribute('data-period') === state.period;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-checked', active ? 'true' : 'false');
    });
    if (els.periodSelect && els.periodSelect.value !== state.period) {
      els.periodSelect.value = state.period;
    }
  };

  const getChartTickLayout = (labelCount) => {
    const viewport = Math.max(320, window.innerWidth || 1024);
    const isMobile = viewport <= 560;
    const isTablet = viewport <= 900;

    if (isMobile) {
      return {
        autoSkip: false,
        maxTicksLimit: labelCount,
        maxRotation: 55,
        minRotation: 55,
        fontSize: 11,
      };
    }
    if (isTablet) {
      return {
        autoSkip: true,
        maxTicksLimit: Math.min(8, Math.max(5, labelCount)),
        maxRotation: 28,
        minRotation: 28,
        fontSize: 13,
      };
    }
    return {
      autoSkip: false,
      maxTicksLimit: labelCount,
      maxRotation: 0,
      minRotation: 0,
      fontSize: 14,
    };
  };

  const renderChart = (labels, achatsSeries, ventesSeries) => {
    const canvas = document.getElementById('salesChart');
    if (!canvas || typeof window.Chart === 'undefined') return;

    const nextLabels = Array.isArray(labels) ? labels : [];
    const nextAchats = Array.isArray(achatsSeries) ? achatsSeries.map((v) => Number(v || 0)) : [];
    const nextVentes = Array.isArray(ventesSeries) ? ventesSeries.map((v) => Number(v || 0)) : [];
    const allValues = [...nextAchats, ...nextVentes].filter((v) => Number.isFinite(v));
    const maxValue = allValues.length ? Math.max(...allValues) : 0;
    const hasData = maxValue > 0;
    const yMin = hasData ? 0 : 0;
    const rawTop = hasData ? Math.ceil(maxValue * 1.15) : 60000;
    const yMax = Math.max(10000, Math.ceil(rawTop / 1000) * 1000);
    const yStep = Math.max(1000, Math.ceil((yMax - yMin) / 6 / 1000) * 1000);
    const tickLayout = getChartTickLayout(nextLabels.length);

    if (state.chartInstance) {
      state.chartInstance.data.labels = nextLabels;
      state.chartInstance.data.datasets[0].data = nextAchats;
      state.chartInstance.data.datasets[1].data = nextVentes;
      state.chartInstance.options.scales.x.ticks.autoSkip = tickLayout.autoSkip;
      state.chartInstance.options.scales.x.ticks.maxTicksLimit = tickLayout.maxTicksLimit;
      state.chartInstance.options.scales.x.ticks.maxRotation = tickLayout.maxRotation;
      state.chartInstance.options.scales.x.ticks.minRotation = tickLayout.minRotation;
      state.chartInstance.options.scales.x.ticks.font.size = tickLayout.fontSize;
      state.chartInstance.options.scales.y.min = yMin;
      state.chartInstance.options.scales.y.max = yMax;
      state.chartInstance.options.scales.y.ticks.stepSize = yStep;
      state.chartInstance.update('active');
      const wrapNode = canvas.closest('.admin-chart-wrap');
      wrapNode?.classList.toggle('is-empty', !hasData);
      return;
    }

    // Pixel-close chart style requested by product design reference.
    const monthLabels = nextLabels;
    const achats = nextAchats;
    const ventes = nextVentes;
    const isDark = document.body.classList.contains('admin-theme-dark');
    const tickColor = isDark ? '#cbd5e1' : '#6b7280';
    const gridColor = isDark ? 'rgba(148,163,184,0.28)' : '#c4c7ce';

    state.chartInstance = new window.Chart(canvas, {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [
          {
            label: 'Commandes',
            data: achats,
            backgroundColor: '#23245d',
            borderColor: '#23245d',
            borderWidth: 0,
            borderRadius: 999,
            borderSkipped: false,
            barPercentage: 0.45,
            categoryPercentage: 0.70,
            maxBarThickness: 18,
          },
          {
            label: 'Ventes',
            data: ventes,
            backgroundColor: '#83BA3A',
            borderColor: '#83BA3A',
            borderWidth: 0,
            borderRadius: 999,
            borderSkipped: false,
            barPercentage: 0.45,
            categoryPercentage: 0.70,
            maxBarThickness: 18,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { top: 4, right: 8, bottom: 0, left: 8 },
        },
        animation: {
          duration: 420,
          easing: 'easeOutQuart',
        },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            align: 'start',
            labels: {
              color: tickColor,
              usePointStyle: true,
              pointStyle: 'circle',
              boxWidth: 10,
              boxHeight: 10,
              padding: 18,
              font: { size: 13, family: 'Poppins, sans-serif', weight: 500 },
            },
          },
          tooltip: { enabled: false },
        },
        scales: {
          x: {
            grid: { display: false, drawBorder: false },
            border: { display: false },
            ticks: {
              color: tickColor,
              maxRotation: tickLayout.maxRotation,
              minRotation: tickLayout.minRotation,
              autoSkip: tickLayout.autoSkip,
              maxTicksLimit: tickLayout.maxTicksLimit,
              font: { size: tickLayout.fontSize, family: 'Poppins, sans-serif', weight: 500 },
              padding: 8,
            },
          },
          y: {
            min: yMin,
            max: yMax,
            grid: { color: gridColor, drawBorder: false, lineWidth: 1 },
            border: { display: false },
            ticks: {
              stepSize: yStep,
              color: tickColor,
              padding: 8,
              callback: (value) => Number(value).toLocaleString('en-US'),
              font: { size: 13, family: 'Poppins, sans-serif', weight: 500 },
            },
          },
        },
      },
    });

    const wrapNode = canvas.closest('.admin-chart-wrap');
    wrapNode?.classList.toggle('is-empty', !hasData);
  };

  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  };

  const setDelta = (id, value) => {
    const el = document.getElementById(id);
    if (!el) return;
    const num = Number(value || 0);
    const sign = num > 0 ? '+' : '';
    el.textContent = `${sign}${num.toFixed(1)}%`;
    el.classList.toggle('is-up', num > 0);
    el.classList.toggle('is-down', num < 0);
  };

  const renderAlerts = (alerts) => {
    if (!els.alerts) return;
    const rows = Array.isArray(alerts) ? alerts : [];
    if (!rows.length) {
      els.alerts.innerHTML = '<li class="admin-empty-state">Aucune alerte critique.</li>';
      return;
    }
    els.alerts.innerHTML = rows.map((row) => {
      const severity = String(row.severity || 'info');
      const title = String(row.title || 'Alerte');
      const detail = String(row.detail || '');
      const actionUrl = String(row.action_url || '#');
      return `
        <li class="admin-alert admin-alert--${severity}">
          <div>
            <strong>${title}</strong>
            <p>${detail}</p>
          </div>
          <a href="${actionUrl}" class="admin-alert__link">Voir</a>
        </li>
      `;
    }).join('');
  };

  const renderActivity = (events) => {
    if (!els.activity) return;
    const rows = Array.isArray(events) ? events : [];
    if (!rows.length) {
      els.activity.innerHTML = '<li class="admin-empty-state">Aucune activite recente.</li>';
      return;
    }
    els.activity.innerHTML = rows.map((row) => {
      const description = String(row.description || '');
      const createdAt = String(row.created_at || '');
      return `<li><span>${description}</span><small>${createdAt}</small></li>`;
    }).join('');
  };

  const renderRecentOrders = (orders) => {
    const q = (els.searchInput?.value || '').trim().toLowerCase();
    const filteredOrders = (Array.isArray(orders) ? orders : []).filter((order) => {
      if (!q) return true;
      const haystack = `${order.order_number || ''} ${order.customer_name || ''} ${order.status || ''}`.toLowerCase();
      return haystack.includes(q);
    });

    if (!els.recentOrders) return;
    if (!filteredOrders.length) {
      els.recentOrders.innerHTML = '<tr><td colspan="4">Aucune commande recente.</td></tr>';
      return;
    }
    els.recentOrders.innerHTML = filteredOrders.map((order) => `
      <tr>
        <td>${order.order_number || '-'}</td>
        <td>${order.customer_name || 'Client'}</td>
        <td>${money(order.total_cents)}</td>
        <td><span class="admin-status admin-status--${normalizeStatus(order.status)}">${order.status}</span></td>
      </tr>
    `).join('');
  };

  const fetchPrefs = async () => {
    const response = await fetch('../api/admin_dashboard_preferences.php', { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.error || 'Preferences error');
    state.prefs = {
      theme: payload?.data?.theme || DEFAULT_PREFS.theme,
      layout: Object.assign({}, DEFAULT_PREFS.layout, payload?.data?.layout || {}),
    };
  };

  const savePrefs = async () => {
    const response = await fetch('../api/admin_dashboard_preferences.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': (window.getAdminCsrfToken?.() || ''),
      },
      body: JSON.stringify(state.prefs),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.error || 'Save preferences error');
    state.prefs = {
      theme: payload?.data?.theme || state.prefs.theme,
      layout: Object.assign({}, state.prefs.layout, payload?.data?.layout || {}),
    };
  };

  const loadDashboard = async () => {
    if (state.loading) return;
    setLoadingState(true);
    try {
      const query = new URLSearchParams({ period: state.period }).toString();
      const response = await fetch(`../api/admin_dashboard.php?${query}`, { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.error || 'Erreur dashboard');

      const stats = payload?.data?.stats || {};
      const statsDelta = payload?.data?.stats_delta || {};
      setText('statVentes', String(stats.ventes || 0));
      setText('statRevenus', money(stats.revenus || 0));
      setText('statCommandes', String(stats.commandes || 0));
      setText('statClients', String(stats.clients || 0));
      setDelta('statVentesDelta', statsDelta.ventes || 0);
      setDelta('statRevenusDelta', statsDelta.revenus || 0);
      setDelta('statCommandesDelta', statsDelta.commandes || 0);
      setDelta('statClientsDelta', statsDelta.clients || 0);

      const chart = payload?.data?.chart || {};
      const labels = Array.isArray(chart.labels) ? chart.labels : [];
      const achats = Array.isArray(chart.series_achats) ? chart.series_achats : [];
      const ventes = Array.isArray(chart.series_ventes) ? chart.series_ventes : Array.isArray(chart.series) ? chart.series : [];
      renderChart(labels, achats, ventes);

      state.cachedRecentOrders = payload?.data?.recent_orders || [];
      renderRecentOrders(state.cachedRecentOrders);
      renderAlerts(payload?.data?.alerts || []);
      renderActivity(payload?.data?.activity || []);
    } catch (error) {
      notify('error', error.message || 'Erreur dashboard');
    } finally {
      setLoadingState(false);
    }
  };

  const setupAutoRefresh = () => {
    if (state.refreshTimer) {
      window.clearInterval(state.refreshTimer);
      state.refreshTimer = null;
    }
    const sec = Math.max(REFRESH_MIN, Math.min(REFRESH_MAX, Number(state.prefs.layout.refresh_sec) || 45));
    state.refreshTimer = window.setInterval(loadDashboard, sec * 1000);
  };

  const getContainerOrder = (container) => {
    if (!container) return [];
    return [...container.querySelectorAll('[data-widget-id]')]
      .map((node) => String(node.getAttribute('data-widget-id') || '').trim())
      .filter(Boolean);
  };

  const persistOrder = async (containerKey, container) => {
    state.prefs.layout[containerKey] = getContainerOrder(container);
    try {
      await savePrefs();
      notify('success', 'Ordre des widgets enregistre.');
    } catch (error) {
      notify('error', error.message || 'Impossible de sauvegarder l\'ordre.');
    }
  };

  const setupDragAndDrop = (container, containerKey) => {
    if (!container) return;
    let dragged = null;

    const items = () => [...container.querySelectorAll('[data-widget-id]')];

    items().forEach((item) => {
      item.setAttribute('draggable', 'true');

      item.addEventListener('dragstart', (event) => {
        dragged = item;
        item.classList.add('is-dragging');
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', item.getAttribute('data-widget-id') || '');
        }
      });

      item.addEventListener('dragend', async () => {
        item.classList.remove('is-dragging');
        container.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
        if (dragged) {
          await persistOrder(containerKey, container);
        }
        dragged = null;
      });

      item.addEventListener('dragover', (event) => {
        if (!dragged || dragged === item) return;
        event.preventDefault();
        const rect = item.getBoundingClientRect();
        const midpoint = rect.top + rect.height / 2;
        const shouldInsertAfter = event.clientY > midpoint;
        item.classList.add('is-drop-target');
        if (shouldInsertAfter) {
          container.insertBefore(dragged, item.nextSibling);
        } else {
          container.insertBefore(dragged, item);
        }
      });

      item.addEventListener('dragleave', () => {
        item.classList.remove('is-drop-target');
      });
    });
  };

  const openWidgetManager = () => {
    if (!window.AdminModal?.open) return;

    const visible = state.prefs.layout.widgets_visible || {};
    const checked = (id) => (visible[id] === false ? '' : 'checked');
    const order = Array.isArray(state.prefs.layout.widgets_order) ? state.prefs.layout.widgets_order : DEFAULT_PREFS.layout.widgets_order;
    const orderIndex = (id) => Math.max(1, order.indexOf(id) + 1);

    const html = `
      <form id="dashboardWidgetPrefsForm" class="admin-form-grid" novalidate>
        <div class="admin-form-group admin-form-group--full">
          <label><input type="checkbox" name="stat_ventes" ${checked('stat_ventes')}> KPI ventes</label>
          <label><input type="checkbox" name="stat_revenus" ${checked('stat_revenus')}> KPI revenus</label>
          <label><input type="checkbox" name="stat_commandes" ${checked('stat_commandes')}> KPI commandes</label>
          <label><input type="checkbox" name="stat_clients" ${checked('stat_clients')}> KPI clients</label>
          <label><input type="checkbox" name="sales_chart" ${checked('sales_chart')}> Graphique des ventes</label>
          <label><input type="checkbox" name="business_alerts" ${checked('business_alerts')}> Alertes business</label>
          <label><input type="checkbox" name="recent_orders" ${checked('recent_orders')}> Commandes recentes</label>
          <label><input type="checkbox" name="activity_feed" ${checked('activity_feed')}> Activite recente</label>
        </div>
        <div class="admin-form-group admin-form-group--full">
          <label for="dashboardStatsOrderInput">Ordre KPI (IDs separes par virgule)</label>
          <input id="dashboardStatsOrderInput" class="admin-input" type="text" value="${order.join(', ')}">
        </div>
        <div class="admin-form-actions">
          <button class="admin-btn" type="button" data-close-modal>Annuler</button>
          <button class="admin-btn admin-btn--primary" type="submit">Enregistrer</button>
        </div>
      </form>
    `;

    window.AdminModal.open({
      title: 'Preferences dashboard',
      content: html,
      onOpen: () => {
        const form = document.getElementById('dashboardWidgetPrefsForm');
        const closeButton = form?.querySelector('[data-close-modal]');
        closeButton?.addEventListener('click', () => window.AdminModal.close());
        form?.addEventListener('submit', async (event) => {
          event.preventDefault();
          const fd = new FormData(form);
          const nextVisible = {};
          Object.keys(DEFAULT_PREFS.layout.widgets_visible).forEach((widgetId) => {
            nextVisible[widgetId] = fd.get(widgetId) === 'on';
          });

          const orderInput = String(document.getElementById('dashboardStatsOrderInput')?.value || '');
          const orderTokens = orderInput.split(',').map((v) => v.trim()).filter(Boolean);
          const nextOrder = [];
          orderTokens.forEach((token) => {
            if (['stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients'].includes(token) && !nextOrder.includes(token)) {
              nextOrder.push(token);
            }
          });
          ['stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients'].forEach((fallback) => {
            if (!nextOrder.includes(fallback)) nextOrder.push(fallback);
          });

          state.prefs.layout.widgets_visible = nextVisible;
          state.prefs.layout.widgets_order = nextOrder;

          try {
            await savePrefs();
            applyPrefsToUI();
            window.AdminModal.close();
            notify('success', 'Preferences enregistrees.');
          } catch (error) {
            notify('error', error.message || 'Impossible d\'enregistrer.');
          }
        });
      },
    });
  };

  const bindEvents = () => {
    els.searchInput?.addEventListener('input', () => renderRecentOrders(state.cachedRecentOrders));

    els.periodSelect?.addEventListener('change', async () => {
      state.period = String(els.periodSelect.value || 'month');
      syncChartPeriodUi();
      await loadDashboard();
    });

    els.autoRefreshSelect?.addEventListener('change', async () => {
      state.prefs.layout.refresh_sec = Math.max(REFRESH_MIN, Math.min(REFRESH_MAX, Number(els.autoRefreshSelect.value) || 45));
      setupAutoRefresh();
      try {
        await savePrefs();
      } catch (error) {
        notify('error', error.message || 'Auto-refresh non enregistre');
      }
    });

    els.themeToggle?.addEventListener('click', async () => {
      setTheme(state.prefs.theme === 'dark' ? 'light' : 'dark');
      try {
        await savePrefs();
      } catch (error) {
        notify('error', error.message || 'Theme non enregistre');
      }
    });

    els.manageWidgetsBtn?.addEventListener('click', openWidgetManager);
    els.refreshBtn?.addEventListener('click', () => loadDashboard());

    document.querySelectorAll('[data-refresh-widget]').forEach((btn) => {
      btn.addEventListener('click', () => loadDashboard());
    });

    els.chartPeriodBtn?.addEventListener('click', (event) => {
      event.preventDefault();
      const open = !els.chartPeriodMenu?.classList.contains('is-open');
      els.chartPeriodMenu?.classList.toggle('is-open', open);
      els.chartPeriodBtn?.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    els.chartPeriodMenu?.addEventListener('click', async (event) => {
      const option = event.target.closest('button[data-period]');
      if (!option) return;
      const period = String(option.getAttribute('data-period') || 'month');
      if (!['week', 'month', 'year'].includes(period)) return;
      state.period = period;
      syncChartPeriodUi();
      els.chartPeriodMenu?.classList.remove('is-open');
      els.chartPeriodBtn?.setAttribute('aria-expanded', 'false');
      await loadDashboard();
    });

    document.addEventListener('click', (event) => {
      if (!els.chartPeriodMenu || !els.chartPeriodBtn) return;
      if (els.chartPeriodMenu.contains(event.target) || els.chartPeriodBtn.contains(event.target)) return;
      els.chartPeriodMenu.classList.remove('is-open');
      els.chartPeriodBtn.setAttribute('aria-expanded', 'false');
    });

    setupDragAndDrop(els.statsGrid, 'widgets_order');
    setupDragAndDrop(els.widgetBoard, 'dashboard_widgets_order');

    document.addEventListener('admin:theme-changed', (event) => {
      const theme = String(event?.detail?.theme || 'light');
      state.prefs.theme = theme === 'dark' ? 'dark' : 'light';
    });

    let resizeTimer = null;
    window.addEventListener('resize', () => {
      if (!state.chartInstance) return;
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(() => {
        const labels = state.chartInstance?.data?.labels || [];
        const tickLayout = getChartTickLayout(Array.isArray(labels) ? labels.length : 0);
        state.chartInstance.options.scales.x.ticks.autoSkip = tickLayout.autoSkip;
        state.chartInstance.options.scales.x.ticks.maxTicksLimit = tickLayout.maxTicksLimit;
        state.chartInstance.options.scales.x.ticks.maxRotation = tickLayout.maxRotation;
        state.chartInstance.options.scales.x.ticks.minRotation = tickLayout.minRotation;
        state.chartInstance.options.scales.x.ticks.font.size = tickLayout.fontSize;
        state.chartInstance.update('none');
      }, 120);
    });
  };

  const boot = async () => {
    try {
      await fetchPrefs();
    } catch (error) {
      notify('warning', 'Preferences par defaut appliquees.');
    }
    state.period = String(els.periodSelect?.value || 'month');
    syncChartPeriodUi();
    applyPrefsToUI();
    bindEvents();
    await loadDashboard();
  };

  boot();
})();

