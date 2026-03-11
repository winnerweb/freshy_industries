(function adminProductsModule() {
  const tbody = document.getElementById('adminProductsTbody');
  const form = document.getElementById('adminProductCreateForm');
  if (!tbody || !form) return;

  const categorySelect = document.getElementById('apCategory');
  const nameInput = document.getElementById('apName');
  const priceInput = document.getElementById('apPriceCents');
  const stockInput = document.getElementById('apStockQty');
  const variantLabelInput = document.getElementById('apVariantLabel');
  const statusSelect = document.getElementById('apStatus');
  const isNewCheckbox = document.getElementById('apIsNew');
  const shortDescInput = document.getElementById('apShortDesc');

  const statuses = ['active', 'inactive', 'draft'];

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_products_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const loadProducts = async () => {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Chargement...</td></tr>';
    try {
      const response = await fetch('../api/admin_products.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.error || 'Erreur chargement produits');

      const categories = Array.isArray(payload?.meta?.categories) ? payload.meta.categories : [];
      const products = Array.isArray(payload?.data) ? payload.data : [];

      if (categorySelect && categorySelect.options.length <= 1) {
        categories.forEach((cat) => {
          const option = document.createElement('option');
          option.value = String(cat.id);
          option.textContent = cat.name;
          categorySelect.appendChild(option);
        });
      }

      if (!products.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Aucun produit.</td></tr>';
        return;
      }

      tbody.innerHTML = products.map((p) => {
        const statusOptions = statuses.map((s) => `<option value="${s}" ${p.status === s ? 'selected' : ''}>${s}</option>`).join('');
        return `
          <tr data-product-id="${p.id}" data-variant-id="${p.variant_id || ''}">
            <td>#${p.id}</td>
            <td>
              <input class="form-control form-control-sm ap-name" value="${(p.name || '').replace(/"/g, '&quot;')}">
              <small class="text-muted">${p.slug || ''}</small>
            </td>
            <td>${p.category_name || '-'}</td>
            <td><input type="number" min="1" class="form-control form-control-sm ap-price" value="${Number(p.price_cents || 0)}"></td>
            <td><input type="number" min="0" class="form-control form-control-sm ap-stock" value="${Number(p.stock_qty || 0)}"></td>
            <td>
              <select class="form-select form-select-sm ap-status">
                ${statusOptions}
              </select>
            </td>
            <td><input type="checkbox" class="form-check-input ap-is-new" ${Number(p.is_new) === 1 ? 'checked' : ''}></td>
            <td class="d-flex gap-1">
              <button type="button" class="btn btn-sm btn-primary ap-save">Sauver</button>
              <button type="button" class="btn btn-sm btn-outline-danger ap-archive">Archiver</button>
            </td>
          </tr>
        `;
      }).join('');
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Impossible de charger les produits.</td></tr>';
      notify('error', error.message || 'Erreur chargement produits');
    }
  };

  const createProduct = async () => {
    const name = nameInput?.value.trim() || '';
    const priceCents = Number(priceInput?.value || 0);
    const stockQty = Number(stockInput?.value || 0);
    if (!name || !Number.isFinite(priceCents) || priceCents <= 0) {
      notify('error', 'Nom et prix valides requis.');
      return;
    }

    const payload = {
      action: 'create',
      name,
      category_id: categorySelect?.value ? Number(categorySelect.value) : null,
      status: statusSelect?.value || 'active',
      is_new: Boolean(isNewCheckbox?.checked),
      short_description: shortDescInput?.value?.trim() || '',
      variant_label: variantLabelInput?.value?.trim() || 'Standard',
      price_cents: Math.floor(priceCents),
      stock_qty: Math.max(0, Math.floor(stockQty || 0)),
    };

    const response = await fetch('../api/admin_products.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || window.ADMIN_CSRF_TOKEN || '' },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok) {
      throw new Error(result?.error || 'Création impossible');
    }
  };

  const saveProductRow = async (row) => {
    const productId = Number(row.dataset.productId || 0);
    const variantId = Number(row.dataset.variantId || 0);
    const name = row.querySelector('.ap-name')?.value?.trim() || '';
    const price = Number(row.querySelector('.ap-price')?.value || 0);
    const stock = Number(row.querySelector('.ap-stock')?.value || 0);
    const status = row.querySelector('.ap-status')?.value || 'active';
    const isNew = Boolean(row.querySelector('.ap-is-new')?.checked);

    const response = await fetch('../api/admin_products.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || window.ADMIN_CSRF_TOKEN || '' },
      body: JSON.stringify({
        action: 'update',
        product_id: productId,
        variant_id: variantId > 0 ? variantId : null,
        name,
        status,
        is_new: isNew,
        price_cents: Math.floor(price),
        stock_qty: Math.max(0, Math.floor(stock)),
      }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.error || 'Mise à jour impossible');
  };

  const archiveProductRow = async (row) => {
    const productId = Number(row.dataset.productId || 0);
    const response = await fetch('../api/admin_products.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || window.ADMIN_CSRF_TOKEN || '' },
      body: JSON.stringify({ action: 'archive', product_id: productId }),
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.error || 'Archivage impossible');
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await createProduct();
      notify('success', 'Produit créé.');
      form.reset();
      if (stockInput) stockInput.value = '10';
      if (variantLabelInput) variantLabelInput.value = 'Standard';
      if (statusSelect) statusSelect.value = 'active';
      await loadProducts();
    } catch (error) {
      notify('error', error.message || 'Erreur création produit');
    }
  });

  tbody.addEventListener('click', async (event) => {
    const row = event.target.closest('tr[data-product-id]');
    if (!row) return;

    if (event.target.closest('.ap-save')) {
      try {
        await saveProductRow(row);
        notify('success', 'Produit mis à jour.');
        await loadProducts();
      } catch (error) {
        notify('error', error.message || 'Erreur mise à jour');
      }
      return;
    }

    if (event.target.closest('.ap-archive')) {
      try {
        await archiveProductRow(row);
        notify('info', 'Produit archivé (status inactive).');
        await loadProducts();
      } catch (error) {
        notify('error', error.message || 'Erreur archivage');
      }
    }
  });

  loadProducts();
})();



