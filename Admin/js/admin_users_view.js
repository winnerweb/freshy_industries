(function adminUsersView() {
  const tbody = document.getElementById('usersTableBody');
  const addBtn = document.getElementById('addUserBtn');
  if (!tbody) return;

  const data = ((window.AdminMockData && window.AdminMockData.users) || []).slice();

  const render = () => {
    tbody.innerHTML = data.map((u) => `
      <tr>
        <td>${u.name}</td>
        <td>${u.email}</td>
        <td>${u.role}</td>
        <td><span class="admin-status admin-status--${u.status}">${u.status}</span></td>
        <td><button class="admin-btn" type="button">Modifier</button></td>
      </tr>
    `).join('');
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
              <option value="operator">operator</option>
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
        </form>
      `,
      onOpen: () => {
        const form = document.getElementById('userModalForm');
        if (!form) return;
        form.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          const formData = new FormData(form);
          const name = String(formData.get('name') || '').trim();
          const email = String(formData.get('email') || '').trim().toLowerCase();
          const role = String(formData.get('role') || '').trim();
          const password = String(formData.get('password') || '');
          if (!name || !email || !email.includes('@') || password.length < 8) return;
          data.unshift({ name, email, role, status: 'active' });
          render();
          window.AdminModal.close();
        });
      },
    });
  };

  addBtn?.addEventListener('click', openCreateModal);
  render();
})();

