(function adminClientsLive() {
  const tbody = document.getElementById('clientsTableBody');
  const searchInput = document.getElementById('adminTopbarSearch');
  const selectAll = document.getElementById('clientsSelectAll');
  const exportCsvBtn = document.getElementById('clientsExportCsvBtn');
  const bulkBar = document.getElementById('clientsBulkBar');
  const bulkCount = document.getElementById('clientsSelectedCount');
  const bulkDeleteBtn = document.getElementById('clientsBulkDeleteBtn');
  if (!tbody) return;

  let clients = [];
  let visibleRows = [];
  const selectedIds = new Set();

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_clients_live_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const formatMoney = (cents) => `${Math.floor((Number(cents) || 0) / 100).toLocaleString('fr-FR')} Fcfa`;

  const syncBulkUi = () => {
    const selectedVisible = visibleRows.filter((c) => selectedIds.has(Number(c.id))).length;
    const selectedTotal = selectedIds.size;
    if (selectAll) {
      selectAll.checked = visibleRows.length > 0 && selectedVisible === visibleRows.length;
      selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleRows.length;
    }
    if (bulkCount) bulkCount.textContent = String(selectedTotal);
    if (bulkBar) {
      bulkBar.classList.toggle('is-visible', selectedTotal > 0);
      bulkBar.setAttribute('aria-hidden', selectedTotal > 0 ? 'false' : 'true');
    }
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = selectedTotal === 0;
  };

  const render = () => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    visibleRows = clients.filter((c) => {
      if (!q) return true;
      return `${c.full_name || ''} ${c.email || ''} ${c.phone || ''}`.toLowerCase().includes(q);
    });

    if (!visibleRows.length) {
      tbody.innerHTML = '<tr><td colspan="8">Aucun client trouve.</td></tr>';
      syncBulkUi();
      return;
    }

    tbody.innerHTML = visibleRows.map((c) => `
      <tr data-client-id="${c.id}" class="${selectedIds.has(Number(c.id)) ? 'admin-table__row--selected' : ''}">
        <td>
          <input type="checkbox"
                 class="admin-checkbox"
                 data-action="select-row"
                 data-client-id="${c.id}"
                 aria-label="Selectionner ${c.full_name || 'client'}"
                 ${selectedIds.has(Number(c.id)) ? 'checked' : ''}>
        </td>
        <td>${c.full_name || '-'}</td>
        <td>${c.email || '-'}</td>
        <td>${c.phone || '-'}</td>
        <td>${Number(c.orders_count || 0)}</td>
        <td>${formatMoney(c.spent_cents)}</td>
        <td><span class="admin-status admin-status--${c.status}">${c.status}</span></td>
        <td>
          <div class="admin-row-actions" role="group" aria-label="Actions client">
            <button class="admin-icon-btn admin-icon-btn--view" type="button" data-action="view" title="Voir" aria-label="Voir ${c.full_name || 'client'}">
              <i class="fa-regular fa-eye" aria-hidden="true"></i>
            </button>
          </div>
        </td>
      </tr>
    `).join('');
    syncBulkUi();
  };

  const fetchClients = async () => {
    tbody.innerHTML = '<tr><td colspan="8">Chargement...</td></tr>';
    try {
      const response = await fetch('../api/admin_clients.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.error || 'Erreur chargement clients');
      clients = Array.isArray(payload?.data) ? payload.data : [];
      const existing = new Set(clients.map((c) => Number(c.id)));
      [...selectedIds].forEach((id) => { if (!existing.has(id)) selectedIds.delete(id); });
      render();
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="8">Impossible de charger les clients.</td></tr>';
      notify('error', error.message || 'Erreur chargement clients');
    }
  };

  const post = async (payload) => {
    const response = await fetch('../api/admin_clients.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || '' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data?.error || 'Operation impossible');
    return data;
  };

  const openBulkDeleteModal = (ids) => {
    if (!window.AdminModal || !ids.length) return;
    const targets = clients.filter((c) => ids.includes(Number(c.id)));
    const preview = targets.slice(0, 5).map((c) => `<li>${c.full_name || `Client #${c.id}`}</li>`).join('');
    const more = targets.length > 5 ? `<li>+${targets.length - 5} autre(s)...</li>` : '';
    window.AdminModal.open({
      title: 'Confirmer suppression clients',
      content: `
        <div class="admin-form-grid">
          <div class="admin-form-group admin-form-group--full">
            <p style="margin:0;color:#334155;">Suppression irreversible de <strong>${ids.length}</strong> client(s).</p>
            <ul style="margin:10px 0 0 18px;padding:0;color:#475569;">${preview}${more}</ul>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--danger" type="button" data-confirm-delete>Supprimer</button>
          </div>
        </div>
      `,
      onOpen: () => {
        const close = document.querySelector('[data-close-modal]');
        const confirm = document.querySelector('[data-confirm-delete]');
        close?.addEventListener('click', () => window.AdminModal.close());
        confirm?.addEventListener('click', async () => {
          if (confirm.disabled) return;
          confirm.disabled = true;
          try {
            await post({ action: 'delete_many', ids });
            ids.forEach((id) => selectedIds.delete(id));
            notify('success', `${ids.length} client(s) supprime(s).`);
            window.AdminModal.close();
            await fetchClients();
          } catch (error) {
            notify('error', error.message || 'Suppression impossible');
            confirm.disabled = false;
          }
        });
      },
    });
  };

  tbody.addEventListener('change', (event) => {
    const checkbox = event.target.closest('input[data-action="select-row"]');
    if (!checkbox) return;
    const id = Number(checkbox.dataset.clientId || 0);
    if (id <= 0) return;
    if (checkbox.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    render();
  });

  tbody.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-action="view"]');
    if (!btn) return;
    const row = btn.closest('tr[data-client-id]');
    if (!row) return;
    const client = clients.find((c) => Number(c.id) === Number(row.dataset.clientId));
    if (!client || !window.AdminModal) return;

    window.AdminModal.open({
      title: 'Details client',
      content: `
        <div class="admin-form-grid">
          <div class="admin-form-group"><label>Nom</label><div>${client.full_name || '-'}</div></div>
          <div class="admin-form-group"><label>Email</label><div>${client.email || '-'}</div></div>
          <div class="admin-form-group"><label>Telephone</label><div>${client.phone || '-'}</div></div>
          <div class="admin-form-group"><label>Nombre commandes</label><div>${Number(client.orders_count || 0)}</div></div>
          <div class="admin-form-group admin-form-group--full"><label>Total depense</label><div>${formatMoney(client.spent_cents)}</div></div>
          <div class="admin-form-actions"><button class="admin-btn" type="button" data-close-modal>Fermer</button></div>
        </div>`,
      onOpen: () => {
        document.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
      },
    });
  });

  selectAll?.addEventListener('change', () => {
    const shouldSelect = Boolean(selectAll.checked);
    visibleRows.forEach((client) => {
      const id = Number(client.id);
      if (!Number.isFinite(id) || id <= 0) return;
      if (shouldSelect) selectedIds.add(id);
      else selectedIds.delete(id);
    });
    render();
  });

  bulkDeleteBtn?.addEventListener('click', () => {
    const ids = [...selectedIds];
    if (!ids.length) return;
    openBulkDeleteModal(ids);
  });

  exportCsvBtn?.addEventListener('click', () => {
    const params = new URLSearchParams();
    params.set('format', 'csv');
    const q = (searchInput?.value || '').trim();
    if (q) params.set('q', q);
    window.location.href = `../api/admin_clients.php?${params.toString()}`;
  });

  searchInput?.addEventListener('input', render);
  fetchClients();
})();

