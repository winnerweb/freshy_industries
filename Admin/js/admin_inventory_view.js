(function adminInventoryView() {
  const tbody = document.getElementById('inventoryTableBody');
  const addBtn = document.getElementById('addInventoryBtn');
  if (!tbody) return;

  const data = ((window.AdminMockData && window.AdminMockData.inventory) || []).slice();

  const render = () => {
    tbody.innerHTML = data.map((r) => `
      <tr>
        <td>${r.product}</td>
        <td>${r.qty}</td>
        <td>${r.warehouse}</td>
        <td><span class="admin-status admin-status--${r.status}">${r.status}</span></td>
        <td>${r.updated}</td>
        <td><button class="admin-btn" type="button">Voir</button> <button class="admin-btn" type="button">Modifier</button></td>
      </tr>
    `).join('');
  };

  const openCreateModal = () => {
    if (!window.AdminModal) return;
    window.AdminModal.open({
      title: 'Creer un stock',
      content: `
        <form id="inventoryModalForm" class="admin-form-grid" novalidate>
          <div class="admin-form-group admin-form-group--full">
            <label for="inventoryProduct">Produit</label>
            <input class="admin-input" id="inventoryProduct" name="product" type="text" required autofocus>
          </div>
          <div class="admin-form-group">
            <label for="inventoryQty">Quantite</label>
            <input class="admin-input" id="inventoryQty" name="qty" type="number" min="0" required value="0">
          </div>
          <div class="admin-form-group">
            <label for="inventoryWarehouse">Entrepot</label>
            <input class="admin-input" id="inventoryWarehouse" name="warehouse" type="text" required>
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="inventoryStatus">Statut stock</label>
            <select class="admin-select" id="inventoryStatus" name="status">
              <option value="in-stock">in-stock</option>
              <option value="low">low</option>
              <option value="out">out</option>
            </select>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--primary" type="submit">Valider</button>
          </div>
        </form>
      `,
      onOpen: () => {
        const form = document.getElementById('inventoryModalForm');
        if (!form) return;
        form.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        form.addEventListener('submit', (event) => {
          event.preventDefault();
          const formData = new FormData(form);
          const product = String(formData.get('product') || '').trim();
          const qty = Number(formData.get('qty'));
          const warehouse = String(formData.get('warehouse') || '').trim();
          const status = String(formData.get('status') || 'in-stock');
          if (!product || !Number.isFinite(qty) || qty < 0 || !warehouse) return;
          data.unshift({
            product,
            qty: Math.floor(qty),
            warehouse,
            status,
            updated: new Date().toISOString().slice(0, 10),
          });
          render();
          window.AdminModal.close();
        });
      },
    });
  };

  addBtn?.addEventListener('click', openCreateModal);
  render();
})();

