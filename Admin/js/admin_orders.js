(function adminOrdersModule() {
  const tbody = document.getElementById('adminOrdersTbody');
  const filter = document.getElementById('adminOrderStatusFilter');
  const refreshBtn = document.getElementById('adminOrdersRefreshBtn');
  if (!tbody || !filter || !refreshBtn) return;

  const statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'canceled'];

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_orders_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const formatMoney = (cents, currency) => {
    const amount = Number.isFinite(Number(cents)) ? Math.floor(Number(cents) / 100) : 0;
    const symbol = currency || 'XOF';
    return `${amount.toLocaleString('fr-FR')} ${symbol}`;
  };

  const loadOrders = async () => {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Chargement...</td></tr>';
    const query = filter.value ? `?status=${encodeURIComponent(filter.value)}` : '';
    try {
      const response = await fetch(`../api/admin_orders.php${query}`, { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) {
        throw new Error(payload?.error || 'Erreur chargement commandes');
      }

      const rows = Array.isArray(payload?.data) ? payload.data : [];
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucune commande trouvée.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map((row) => {
        const options = statuses.map((s) => `<option value="${s}" ${row.status === s ? 'selected' : ''}>${s}</option>`).join('');
        const customer = [row.customer_name, row.customer_phone].filter(Boolean).join(' · ') || '-';
        return `
          <tr data-order-id="${row.id}">
            <td>#${row.id}</td>
            <td>${row.order_number || '-'}</td>
            <td>${customer}</td>
            <td>${Number(row.items_count || 0)}</td>
            <td>${formatMoney(row.total_cents, row.currency)}</td>
            <td>
              <select class="form-select form-select-sm admin-order-status">
                ${options}
              </select>
            </td>
            <td>${row.created_at || '-'}</td>
            <td>
              <button type="button" class="btn btn-sm btn-primary admin-save-status">Enregistrer</button>
            </td>
          </tr>
        `;
      }).join('');
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Impossible de charger les commandes.</td></tr>';
      notify('error', error.message || 'Erreur chargement commandes');
    }
  };

  const updateOrderStatus = async (orderId, status, button) => {
    if (!orderId || !statuses.includes(status)) {
      notify('error', 'Données de statut invalides.');
      return;
    }
    button.disabled = true;
    const initialLabel = button.textContent;
    button.textContent = '...';
    try {
      const response = await fetch('../api/admin_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || window.ADMIN_CSRF_TOKEN || '' },
        body: JSON.stringify({ action: 'update_status', order_id: Number(orderId), status }),
      });
      const payload = await response.json();
      if (!response.ok) {
        throw new Error(payload?.error || 'Mise à jour impossible');
      }
      notify('success', `Commande #${orderId} mise à jour (${status}).`);
    } catch (error) {
      notify('error', error.message || 'Erreur mise à jour commande');
    } finally {
      button.disabled = false;
      button.textContent = initialLabel;
    }
  };

  tbody.addEventListener('click', (event) => {
    const button = event.target.closest('.admin-save-status');
    if (!button) return;
    const row = button.closest('tr[data-order-id]');
    if (!row) return;
    const select = row.querySelector('.admin-order-status');
    if (!select) return;
    updateOrderStatus(row.dataset.orderId, select.value, button);
  });

  filter.addEventListener('change', loadOrders);
  refreshBtn.addEventListener('click', loadOrders);

  loadOrders();
})();



