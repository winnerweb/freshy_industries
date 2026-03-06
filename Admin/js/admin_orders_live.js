(function adminOrdersLive() {
  const tbody = document.getElementById('ordersTableBody');
  const filter = document.getElementById('ordersStatusFilter');
  const searchInput = document.getElementById('adminTopbarSearch');
  if (!tbody) return;

  let allRows = [];

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_orders_live_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const money = (cents, currency) => {
    const value = Number.isFinite(Number(cents)) ? Math.floor(Number(cents) / 100) : 0;
    return `${value.toLocaleString('fr-FR')} ${currency || 'XOF'}`;
  };

  const render = () => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const rows = allRows.filter((row) => {
      if (!q) return true;
      const haystack = `${row.order_number || ''} ${row.customer_name || ''} ${row.status || ''} ${(row.created_at || '').slice(0, 10)}`.toLowerCase();
      return haystack.includes(q);
    });

    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="7">Aucune commande trouvee.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((row) => `
      <tr>
        <td>${row.order_number || '-'}</td>
        <td>${row.customer_name || '-'}</td>
        <td>${(row.created_at || '').slice(0, 10)}</td>
        <td>${money(row.total_cents, row.currency)}</td>
        <td><span class="admin-status admin-status--${row.status}">${row.status}</span></td>
        <td>Simulator</td>
        <td><button class="admin-btn" type="button" data-order-id="${row.id}">Voir details</button></td>
      </tr>
    `).join('');
  };

  const load = async () => {
    tbody.innerHTML = '<tr><td colspan="7">Chargement...</td></tr>';
    const status = filter?.value || '';
    const query = status ? `?status=${encodeURIComponent(status)}` : '';
    try {
      const response = await fetch(`../api/admin_orders.php${query}`, { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.error || 'Erreur chargement commandes');

      allRows = Array.isArray(payload?.data) ? payload.data : [];
      render();
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="7">Impossible de charger les commandes.</td></tr>';
      notify('error', error.message || 'Erreur chargement commandes');
    }
  };

  filter?.addEventListener('change', load);
  searchInput?.addEventListener('input', render);
  load();
})();

