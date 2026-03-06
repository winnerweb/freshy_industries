(function adminUsersLive() {
  const tbody = document.getElementById('usersTableBody');
  const searchInput = document.getElementById('adminTopbarSearch');
  const addBtn = document.getElementById('addUserBtn');
  const selectAll = document.getElementById('usersSelectAll');
  const bulkBar = document.getElementById('usersBulkBar');
  const bulkCount = document.getElementById('usersSelectedCount');
  const bulkDisableBtn = document.getElementById('usersBulkDisableBtn');
  const bulkDeleteBtn = document.getElementById('usersBulkDeleteBtn');
  if (!tbody) return;

  let users = [];
  let currentUserId = 0;
  const selectedIds = new Set();

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_users_live_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const syncBulkUi = () => {
    const selectedCount = selectedIds.size;
    if (bulkCount) bulkCount.textContent = String(selectedCount);
    if (bulkBar) {
      bulkBar.classList.toggle('is-visible', selectedCount > 0);
      bulkBar.setAttribute('aria-hidden', selectedCount > 0 ? 'false' : 'true');
    }
    if (bulkDisableBtn) bulkDisableBtn.disabled = selectedCount === 0;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = selectedCount === 0;

    const total = users.length;
    if (selectAll) {
      selectAll.checked = total > 0 && selectedCount === total;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < total;
    }
  };

  const render = () => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const visibleUsers = users.filter((u) => {
      if (!q) return true;
      const haystack = `${u.name || ''} ${u.email || ''} ${u.role || ''} ${u.status || ''}`.toLowerCase();
      return haystack.includes(q);
    });

    if (!visibleUsers.length) {
      tbody.innerHTML = '<tr><td colspan="6">Aucun utilisateur.</td></tr>';
      syncBulkUi();
      return;
    }

    tbody.innerHTML = visibleUsers.map((u) => `
      <tr data-user-id="${u.id}" class="${selectedIds.has(Number(u.id)) ? 'admin-table__row--selected' : ''}">
        <td>
          <input type="checkbox"
                 class="admin-checkbox"
                 data-action="select-row"
                 data-user-id="${u.id}"
                 aria-label="Selectionner ${u.name || 'utilisateur'}"
                 ${selectedIds.has(Number(u.id)) ? 'checked' : ''}
                 ${Number(u.id) === currentUserId ? 'disabled title="Impossible de modifier votre propre compte en masse"' : ''}>
        </td>
        <td>${u.name}</td>
        <td>${u.email}</td>
        <td>${u.role}</td>
        <td><span class="admin-status admin-status--${u.status}">${u.status}</span></td>
        <td>
          <div class="admin-row-actions" role="group" aria-label="Actions utilisateur">
            <button class="admin-icon-btn admin-icon-btn--edit" type="button" data-action="toggle-status" title="${u.status === 'active' ? 'Desactiver' : 'Activer'}" aria-label="${u.status === 'active' ? 'Desactiver' : 'Activer'} ${u.name}" ${Number(u.id) === currentUserId ? 'disabled title="Action desactivee sur votre compte"' : ''}>
              <i class="fa-solid fa-power-off" aria-hidden="true"></i>
            </button>
            <button class="admin-icon-btn admin-icon-btn--delete" type="button" data-action="delete" title="Supprimer" aria-label="Supprimer ${u.name}" ${Number(u.id) === currentUserId ? 'disabled title="Action desactivee sur votre compte"' : ''}>
              <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
            </button>
          </div>
        </td>
      </tr>
    `).join('');
    syncBulkUi();
  };

  const load = async () => {
    tbody.innerHTML = '<tr><td colspan="6">Chargement...</td></tr>';
    try {
      const response = await fetch('../api/admin_users.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.error || 'Erreur chargement utilisateurs');
      users = Array.isArray(payload?.data) ? payload.data : [];
      currentUserId = Number(payload?.meta?.current_user_id || 0);
      const existing = new Set(users.map((u) => Number(u.id)));
      [...selectedIds].forEach((id) => { if (!existing.has(id)) selectedIds.delete(id); });
      render();
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="6">Impossible de charger les utilisateurs.</td></tr>';
      notify('error', error.message || 'Erreur chargement utilisateurs');
    }
  };

  const post = async (payload) => {
    const response = await fetch('../api/admin_users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || '' },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data?.error || 'Operation impossible');
    return data;
  };

  const openCreateModal = () => {
    if (!window.AdminModal) return;
    window.AdminModal.open({
      title: 'Ajouter utilisateur',
      content: `
        <form id="userModalForm" class="admin-form-grid" novalidate>
          <div class="admin-form-group">
            <label for="userName">Nom</label>
            <input class="admin-input" id="userName" name="name" type="text" required autofocus>
          </div>
          <div class="admin-form-group">
            <label for="userEmail">Email</label>
            <input class="admin-input" id="userEmail" name="email" type="email" required>
          </div>
          <div class="admin-form-group">
            <label for="userRole">Role</label>
            <select class="admin-select" id="userRole" name="role">
              <option value="admin">admin</option>
              <option value="manager">manager</option>
              <option value="operator" selected>operator</option>
            </select>
          </div>
          <div class="admin-form-group">
            <label for="userPassword">Mot de passe</label>
            <input class="admin-input" id="userPassword" name="password" type="password" minlength="8" required>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--primary" type="submit">Creer</button>
          </div>
        </form>`,
      onOpen: () => {
        const form = document.getElementById('userModalForm');
        if (!form) return;
        form.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        form.addEventListener('submit', async (event) => {
          event.preventDefault();
          const formData = new FormData(form);
          const name = String(formData.get('name') || '').trim();
          const email = String(formData.get('email') || '').trim().toLowerCase();
          const role = String(formData.get('role') || '').trim();
          const password = String(formData.get('password') || '');
          if (!name || !email || !email.includes('@') || password.length < 8) {
            notify('error', 'Formulaire utilisateur invalide.');
            return;
          }
          try {
            await post({ action: 'create', name, email, role, password });
            notify('success', 'Utilisateur cree.');
            window.AdminModal.close();
            await load();
          } catch (error) {
            notify('error', error.message || 'Creation impossible');
          }
        });
      },
    });
  };

  const openDeleteModal = (ids) => {
    if (!window.AdminModal || !ids.length) return;
    const targets = users.filter((u) => ids.includes(Number(u.id)));
    const preview = targets.slice(0, 5).map((u) => `<li>${u.name} (${u.email})</li>`).join('');
    const more = targets.length > 5 ? `<li>+${targets.length - 5} autre(s)...</li>` : '';
    window.AdminModal.open({
      title: 'Confirmer suppression utilisateurs',
      content: `
        <div class="admin-form-grid">
          <div class="admin-form-group admin-form-group--full">
            <p style="margin:0;color:var(--admin-text);">Suppression irreversible de <strong>${ids.length}</strong> utilisateur(s).</p>
            <ul style="margin:10px 0 0 18px;padding:0;color:var(--admin-text);">${preview}${more}</ul>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--danger" type="button" data-confirm-delete>Supprimer</button>
          </div>
        </div>`,
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
            notify('success', `${ids.length} utilisateur(s) supprime(s).`);
            window.AdminModal.close();
            await load();
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
    const id = Number(checkbox.dataset.userId || 0);
    if (id <= 0) return;
    if (checkbox.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    render();
  });

  tbody.addEventListener('click', async (event) => {
    const btn = event.target.closest('button[data-action]');
    if (!btn) return;
    const row = btn.closest('tr[data-user-id]');
    if (!row) return;
    const id = Number(row.dataset.userId || 0);
    const user = users.find((u) => Number(u.id) === id);
    if (!user) return;

    if (btn.dataset.action === 'toggle-status') {
      const nextStatus = user.status === 'active' ? 'inactive' : 'active';
      try {
        await post({ action: 'update_status', id, status: nextStatus });
        notify('success', 'Statut utilisateur mis a jour.');
        await load();
      } catch (error) {
        notify('error', error.message || 'Mise a jour impossible');
      }
      return;
    }

    if (btn.dataset.action === 'delete') {
      openDeleteModal([id]);
    }
  });

  selectAll?.addEventListener('change', () => {
    const shouldSelect = Boolean(selectAll.checked);
    users.forEach((u) => {
      const id = Number(u.id);
      if (id === currentUserId) return;
      if (shouldSelect) selectedIds.add(id);
      else selectedIds.delete(id);
    });
    render();
  });

  bulkDisableBtn?.addEventListener('click', async () => {
    const allSelected = [...selectedIds];
    const ids = allSelected.filter((id) => id !== currentUserId);
    if (!ids.length) {
      if (allSelected.length > 0) notify('warning', 'Selection invalide: vous ne pouvez pas desactiver votre propre compte.');
      return;
    }
    try {
      await post({ action: 'update_status_many', ids, status: 'inactive' });
      notify('success', `${ids.length} utilisateur(s) desactive(s).`);
      selectedIds.clear();
      await load();
    } catch (error) {
      notify('error', error.message || 'Operation impossible');
    }
  });

  bulkDeleteBtn?.addEventListener('click', () => {
    const allSelected = [...selectedIds];
    const ids = allSelected.filter((id) => id !== currentUserId);
    if (!ids.length) {
      if (allSelected.length > 0) notify('warning', 'Selection invalide: vous ne pouvez pas supprimer votre propre compte.');
      return;
    }
    openDeleteModal(ids);
  });

  addBtn?.addEventListener('click', openCreateModal);
  searchInput?.addEventListener('input', render);
  load();
})();

