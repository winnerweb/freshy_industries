(function adminProductsLive() {
  const tbody = document.getElementById('productsTableBody');
  const searchInput = document.getElementById('productsSearchInput') || document.getElementById('adminTopbarSearch');
  const reloadBtn = document.getElementById('productsReloadBtn');
  const addBtn = document.getElementById('addProductBtn');
  const selectAllCheckbox = document.getElementById('productsSelectAll');
  const bulkBar = document.getElementById('productsBulkBar');
  const bulkCount = document.getElementById('productsSelectedCount');
  const bulkDeleteBtn = document.getElementById('productsBulkDeleteBtn');
  if (!tbody) return;

  let allProducts = [];
  let categories = [];
  let catalogConfig = {};
  let visibleRows = [];
  const selectedIds = new Set();

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_products_live_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const parseJsonResponse = async (response) => {
    const raw = await response.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      const sanitized = raw.replace(/<[^>]*>/g, ' ').trim();
      throw new Error(sanitized || 'Reponse serveur invalide');
    }
  };

  const uploadAdminImage = async (file) => {
    if (!(file instanceof File)) return null;
    const form = new FormData();
    form.append('image', file);
    const response = await fetch('../api/admin_upload_image.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': window.getAdminCsrfToken?.() || '' },
      body: form,
    });
    const payload = await parseJsonResponse(response);
    if (!response.ok) {
      throw new Error(payload?.error || 'Upload image impossible');
    }
    return payload?.data || null;
  };

  const money = (cents) => `${Math.floor((Number(cents) || 0) / 100).toLocaleString('fr-FR')} Fcfa`;
  const resolveAssetUrl = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('data:')) return raw;
    const looksLikeLegacyUpload = /^product_[a-z0-9_-]+\.(webp|png|jpe?g)$/i.test(raw);
    const normalizedRaw = looksLikeLegacyUpload ? `uploads/products/${raw}` : raw;
    const siteBase = String(window.ADMIN_SITE_BASE || '').replace(/\/+$/, '');
    if (!normalizedRaw.startsWith('/')) {
      return siteBase ? `${siteBase}/${normalizedRaw}` : `/${normalizedRaw}`;
    }
    const firstSegment = (window.location.pathname.split('/').filter(Boolean)[0] || '').trim();
    return firstSegment ? `/${firstSegment}${normalizedRaw}` : normalizedRaw;
  };
  const normalizeKey = (value) =>
    String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');

  const getCategoryCatalogKey = (categoryId) => {
    const category = categories.find((c) => Number(c.id) === Number(categoryId));
    if (!category) return '';
    const slugKey = normalizeKey(category.slug || '');
    const nameKey = normalizeKey(category.name || '');
    if (catalogConfig[slugKey]) return slugKey;
    if (catalogConfig[nameKey]) return nameKey;
    const keys = Object.keys(catalogConfig);
    const resolved = keys.find((key) => {
      const labels = Array.isArray(catalogConfig[key]?.category_labels) ? catalogConfig[key].category_labels : [];
      return labels.some((label) => normalizeKey(label) === slugKey || normalizeKey(label) === nameKey);
    }) || '';
    if (resolved) return resolved;

    const combined = `${slugKey} ${nameKey}`;
    if (combined.includes('crem')) return 'creme';
    if (combined.includes('boiss')) return 'boisson';
    if (combined.includes('huil')) return 'huile';
    return '';
  };

  const getAllowedVariantRows = (categoryKey, creamType) => {
    if (!categoryKey || !catalogConfig[categoryKey]) return [];
    if (categoryKey === 'creme') {
      const typeKey = normalizeKey(creamType);
      return Array.isArray(catalogConfig[categoryKey]?.types?.[typeKey]) ? catalogConfig[categoryKey].types[typeKey] : [];
    }
    return Array.isArray(catalogConfig[categoryKey]?.formats) ? catalogConfig[categoryKey].formats : [];
  };

  const getSelectedProductIds = () => {
    const existingIds = new Set(allProducts.map((p) => Number(p.id)));
    return [...selectedIds].filter((id) => existingIds.has(Number(id)));
  };

  const syncSelectionUi = () => {
    const selectedVisibleCount = visibleRows.filter((p) => selectedIds.has(Number(p.id))).length;
    const visibleCount = visibleRows.length;
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = visibleCount > 0 && selectedVisibleCount === visibleCount;
      selectAllCheckbox.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleCount;
    }

    const selectedTotal = getSelectedProductIds().length;
    if (bulkCount) bulkCount.textContent = String(selectedTotal);
    if (bulkBar) {
      bulkBar.classList.toggle('is-visible', selectedTotal > 0);
      bulkBar.setAttribute('aria-hidden', selectedTotal > 0 ? 'false' : 'true');
    }
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = selectedTotal === 0;
  };

  const rowMarkup = (p) => {
    const id = Number(p.id);
    const selected = selectedIds.has(id);
    const stockStatus = String(p.stock_status || '');
    const stockLabel = stockStatus === 'out' ? 'Rupture' : stockStatus === 'low' ? 'Stock faible' : 'En stock';
    const displayedStock = Number(
      p.stock_total !== undefined && p.stock_total !== null ? p.stock_total : (p.stock_qty || 0)
    );
    const imageHtml = p.image_url
      ? `<img src="${resolveAssetUrl(p.image_url)}" alt="${p.name || 'Produit'}" class="admin-product-thumb">`
      : '<div class="admin-product-thumb admin-product-thumb--placeholder" aria-hidden="true"></div>';
    return `
      <tr data-product-id="${id}" data-variant-id="${p.variant_id || ''}" class="${selected ? 'admin-table__row--selected' : ''}">
        <td>
          <input type="checkbox"
                 class="admin-checkbox"
                 data-action="select-row"
                 data-product-id="${id}"
                  aria-label="Selectionner ${p.name || 'produit'}"
                  ${selected ? 'checked' : ''}>
        </td>
        <td>${imageHtml}</td>
        <td>${p.name || '-'}</td>
        <td>${p.category_name || '-'}</td>
        <td>${money(p.price_cents)}</td>
        <td>${displayedStock}</td>
        <td><span class="admin-status admin-status--${stockStatus || 'in-stock'}">${stockLabel}</span></td>
        <td>
          <div class="admin-row-actions" role="group" aria-label="Actions produit">
            <button class="admin-icon-btn admin-icon-btn--view" type="button" data-action="view" title="Voir" aria-label="Voir ${p.name || 'produit'}">
              <i class="fa-regular fa-eye" aria-hidden="true"></i>
            </button>
            <button class="admin-icon-btn admin-icon-btn--edit" type="button" data-action="edit" title="Modifier" aria-label="Modifier ${p.name || 'produit'}">
              <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
            </button>
            <button class="admin-icon-btn admin-icon-btn--delete" type="button" data-action="delete" title="Supprimer" aria-label="Supprimer ${p.name || 'produit'}">
              <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  };

  const render = () => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    visibleRows = allProducts.filter((p) => {
      if (!q) return true;
      return `${p.name || ''} ${p.category_name || ''} ${p.slug || ''}`.toLowerCase().includes(q);
    });

    tbody.innerHTML = visibleRows.length
      ? visibleRows.map(rowMarkup).join('')
      : '<tr><td colspan="8">Aucun produit trouve.</td></tr>';
    syncSelectionUi();
  };

  const fetchProducts = async () => {
    tbody.innerHTML = '<tr><td colspan="8">Chargement...</td></tr>';
    try {
      const response = await fetch('../api/admin_products.php', { headers: { Accept: 'application/json' } });
      const payload = await parseJsonResponse(response);
      if (!response.ok) throw new Error(payload?.error || 'Erreur chargement produits');
      allProducts = Array.isArray(payload?.data) ? payload.data : [];
      categories = Array.isArray(payload?.meta?.categories) ? payload.meta.categories : [];
      catalogConfig = payload?.meta?.catalog_config && typeof payload.meta.catalog_config === 'object'
        ? payload.meta.catalog_config
        : {};
      const existingIds = new Set(allProducts.map((p) => Number(p.id)));
      [...selectedIds].forEach((id) => {
        if (!existingIds.has(Number(id))) selectedIds.delete(id);
      });
      render();
    } catch (error) {
      tbody.innerHTML = '<tr><td colspan="8">Impossible de charger les produits.</td></tr>';
      notify('error', error.message || 'Erreur chargement produits');
    }
  };

  const productFormMarkup = (product = null) => {
    const categoryOptions = [
      '<option value="">Sans categorie</option>',
      ...categories.map((c) => `<option value="${c.id}">${c.name}</option>`),
    ].join('');
    const isEdit = Boolean(product);
    const defaultPriceFcfa = Math.floor((Number(product?.price_cents || 0)) / 100);
    return `
      <form id="productModalForm" class="admin-form-grid" novalidate>
        <div class="admin-form-group admin-form-group--full">
          <label for="modalProductName">Nom produit</label>
          <input class="admin-input" id="modalProductName" name="name" type="text" required autofocus value="${product?.name || ''}">
        </div>
        <div class="admin-form-group admin-form-group--full">
          <label for="modalPrimaryImage">Image principale</label>
          <input class="admin-input" id="modalPrimaryImage" name="primary_image" type="file" accept="image/jpeg,image/png,image/webp" ${isEdit ? '' : 'required'}>
          <input type="hidden" name="primary_image_url" value="${product?.image_url || ''}">
          <div class="admin-image-preview admin-image-preview--main" id="modalPrimaryPreview">${product?.image_url ? `<img src="${resolveAssetUrl(product.image_url)}" alt="Apercu image principale">` : ''}</div>
        </div>
        <div class="admin-form-group admin-form-group--full">
          <label for="modalDecorImage">Image decorative</label>
          <input class="admin-input" id="modalDecorImage" name="decor_image" type="file" accept="image/jpeg,image/png,image/webp">
          <input type="hidden" name="decor_image_url" value="${product?.decor_image_url || ''}">
          <div class="admin-image-preview admin-image-preview--decor" id="modalDecorPreview">${product?.decor_image_url ? `<img src="${resolveAssetUrl(product.decor_image_url)}" alt="Apercu image decorative">` : ''}</div>
        </div>
        <div class="admin-form-group">
          <label for="modalProductCategory">Categorie</label>
          <select class="admin-select" id="modalProductCategory" name="category_id">${categoryOptions}</select>
        </div>
        <div class="admin-form-group">
          <label for="modalProductStatus">Statut</label>
          <select class="admin-select" id="modalProductStatus" name="status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
            <option value="draft">draft</option>
          </select>
        </div>
        <div class="admin-form-group" id="modalCreamTypeGroup" hidden>
          <label for="modalProductCreamType">Type creme</label>
          <select class="admin-select" id="modalProductCreamType" name="cream_type">
            <option value="">Selectionner</option>
            <option value="concentre">Concentre</option>
            <option value="non_concentre">Non concentre</option>
          </select>
        </div>
        <div class="admin-form-group">
          <label for="modalProductVariantLabel">Format</label>
          <select class="admin-select" id="modalProductVariantLabel" name="variant_label">
            <option value="">Selectionner</option>
          </select>
        </div>
        <div class="admin-form-group admin-form-group--full">
          <label><input type="checkbox" name="all_formats" checked> Generer tous les formats autorises</label>
        </div>
        <div class="admin-form-group">
          <label for="modalProductPrice">Prix (FCFA)</label>
          <input class="admin-input" id="modalProductPrice" name="price_fcfa" type="number" min="1" required value="${defaultPriceFcfa > 0 ? defaultPriceFcfa : ''}">
        </div>
        <div class="admin-form-group">
          <label for="modalProductStock">Quantite</label>
          <input class="admin-input" id="modalProductStock" name="stock_qty" type="number" min="0" required value="${product?.stock_qty || 0}" placeholder="Ex: 50 (Seuil minimum recommande : 20)">
        </div>
        <div class="admin-form-group admin-form-group--full">
          <label><input type="checkbox" name="is_new" ${product?.is_new ? 'checked' : ''}> Marquer comme Nouveau</label>
        </div>
        <div class="admin-form-actions">
          <button class="admin-btn" type="button" data-close-modal>Annuler</button>
          <button class="admin-btn admin-btn--primary" type="submit">${isEdit ? 'Enregistrer' : 'Enregistrer'}</button>
        </div>
      </form>
    `;
  };

  const openProductModal = (mode, product) => {
    if (!window.AdminModal) return;
    window.AdminModal.open({
      title: mode === 'create' ? 'Ajouter Produit' : 'Modifier Produit',
      content: productFormMarkup(product),
      onOpen: () => {
        const form = document.getElementById('productModalForm');
        if (!form) return;

        const categoryField = form.querySelector('[name="category_id"]');
        const statusField = form.querySelector('[name="status"]');
        const primaryImageInput = form.querySelector('[name="primary_image"]');
        const decorImageInput = form.querySelector('[name="decor_image"]');
        const primaryImageUrlField = form.querySelector('[name="primary_image_url"]');
        const decorImageUrlField = form.querySelector('[name="decor_image_url"]');
        const primaryPreview = form.querySelector('#modalPrimaryPreview');
        const decorPreview = form.querySelector('#modalDecorPreview');
        const creamTypeField = form.querySelector('[name="cream_type"]');
        const creamTypeGroup = form.querySelector('#modalCreamTypeGroup');
        const variantField = form.querySelector('[name="variant_label"]');
        const priceField = form.querySelector('[name="price_fcfa"]');
        const allFormatsField = form.querySelector('[name="all_formats"]');

        const inferCreamTypeFromProduct = () => {
          const label = String(product?.variant_label || '');
          const price = Number(product?.price_cents || 0);
          const cremeConfig = catalogConfig?.creme?.types || {};
          const typeKeys = Object.keys(cremeConfig);
          for (const typeKey of typeKeys) {
            const rows = Array.isArray(cremeConfig[typeKey]) ? cremeConfig[typeKey] : [];
            const match = rows.find((row) => String(row.label) === label && Number(row.price_cents) === price);
            if (match) return typeKey;
          }
          const text = normalizeKey(String(product?.name || '') + ' ' + label);
          return text.includes('non_concentre') ? 'non_concentre' : 'concentre';
        };

        const syncPriceFromVariant = () => {
          const selectedOption = variantField?.options?.[variantField.selectedIndex];
          const selectedPriceCents = Number(selectedOption?.dataset?.priceCents || 0);
          if (priceField) {
            priceField.value = selectedPriceCents > 0 ? String(Math.floor(selectedPriceCents / 100)) : '';
            priceField.readOnly = Boolean(allFormatsField?.checked);
          }
        };

        const updateVariantOptions = () => {
          const categoryKey = getCategoryCatalogKey(categoryField.value);
          const isCreme = categoryKey === 'creme';
          if (creamTypeGroup) creamTypeGroup.hidden = !isCreme;

          if (isCreme && creamTypeField && !creamTypeField.value) {
            creamTypeField.value = 'concentre';
          }
          const creamType = isCreme ? String(creamTypeField?.value || 'concentre') : '';
          const rows = getAllowedVariantRows(categoryKey, creamType);
          const currentLabel = String(variantField?.value || '');
          const existingVariants = Array.isArray(product?.variants) ? product.variants : [];
          const existingVariantByLabel = new Map(
            existingVariants.map((v) => [normalizeKey(v?.label || ''), Number(v?.id || 0)])
          );
          if (variantField) {
            variantField.innerHTML = rows.length
              ? rows.map((row) => {
                const label = String(row.label || '');
                const norm = normalizeKey(label);
                const existingVariantId = existingVariantByLabel.get(norm) || 0;
                const priceCents = Number(row.price_cents) || 0;
                const priceFcfa = Math.floor(priceCents / 100).toLocaleString('fr-FR');
                return `<option value="${label}" data-price-cents="${priceCents}" data-variant-id="${existingVariantId > 0 ? existingVariantId : ''}">${label} - ${priceFcfa} Fcfa</option>`;
              }).join('')
              : '<option value="">Selectionner</option>';
            if (rows.length) {
              const exact = rows.find((row) => String(row.label) === currentLabel);
              variantField.value = exact ? exact.label : rows[0].label;
            }
          }
          syncPriceFromVariant();
        };

        const bindPreview = (input, previewNode) => {
          input?.addEventListener('change', () => {
            const file = input.files && input.files[0] ? input.files[0] : null;
            if (!previewNode) return;
            if (!file) {
              previewNode.innerHTML = '';
              return;
            }
            const objectUrl = URL.createObjectURL(file);
            previewNode.innerHTML = `<img src="${objectUrl}" alt="Apercu">`;
          });
        };

        if (product) {
          const productCategoryId = Number(product.category_id || 0);
          if (productCategoryId > 0) {
            categoryField.value = String(productCategoryId);
          } else {
            const match = categories.find((c) => (c.name || '').toLowerCase() === (product.category_name || '').toLowerCase());
            categoryField.value = String(match?.id || '');
          }
          statusField.value = product.status || 'active';
          if (creamTypeField) {
            creamTypeField.value = inferCreamTypeFromProduct();
          }
          updateVariantOptions();
          if (allFormatsField) allFormatsField.checked = true;
          if (variantField && product.variant_label) {
            const available = [...variantField.options].some((opt) => opt.value === String(product.variant_label));
            if (available) {
              variantField.value = String(product.variant_label);
              variantField.dispatchEvent(new Event('change'));
            }
          }
        } else {
          statusField.value = 'active';
          if (creamTypeField) creamTypeField.value = '';
          if (allFormatsField) allFormatsField.checked = true;
          updateVariantOptions();
        }

        categoryField?.addEventListener('change', updateVariantOptions);
        creamTypeField?.addEventListener('change', updateVariantOptions);
        variantField?.addEventListener('change', syncPriceFromVariant);
        allFormatsField?.addEventListener('change', syncPriceFromVariant);
        bindPreview(primaryImageInput, primaryPreview);
        bindPreview(decorImageInput, decorPreview);

        form.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        form.addEventListener('submit', async (event) => {
          event.preventDefault();
          const formData = new FormData(form);
          const name = String(formData.get('name') || '').trim();
          const priceFcfa = Number(formData.get('price_fcfa'));
          const stock = Number(formData.get('stock_qty'));
          const status = String(formData.get('status') || '').trim().toLowerCase();
          const categoryId = Number(formData.get('category_id')) || null;
          const variantLabel = String(formData.get('variant_label') || '').trim();
          const creamType = String(formData.get('cream_type') || '').trim();
          const allFormats = Boolean(formData.get('all_formats'));
          const selectedVariantOption = variantField?.options?.[variantField.selectedIndex] || null;
          const selectedVariantId = Number(selectedVariantOption?.dataset?.variantId || 0);
          const primaryImageFile = primaryImageInput?.files?.[0] || null;
          const decorImageFile = decorImageInput?.files?.[0] || null;
          let primaryImageUrl = String(primaryImageUrlField?.value || '').trim();
          let decorImageUrl = String(decorImageUrlField?.value || '').trim();

          if (!name) {
            notify('error', 'Nom produit requis.');
            return;
          }
          if (!categoryId) {
            notify('error', 'Selectionne une categorie.');
            return;
          }
          if (!variantLabel) {
            notify('error', 'Selectionne un format valide.');
            return;
          }
          if ((!allFormats && (!Number.isFinite(priceFcfa) || priceFcfa <= 0)) || !Number.isFinite(stock) || stock < 0) {
            notify('error', 'Prix ou stock invalide.');
            return;
          }

          if (mode === 'create' && !primaryImageFile && !primaryImageUrl) {
            notify('error', 'Image principale requise.');
            return;
          }

          const payload = {
            action: mode === 'create' ? 'create' : 'update',
            name,
            category_id: categoryId,
            status,
            is_new: Boolean(formData.get('is_new')),
            variant_label: variantLabel,
            cream_type: creamType,
            all_formats: allFormats,
            price_fcfa: Math.floor(priceFcfa || 0),
            stock_qty: Math.floor(stock),
          };
          if (mode === 'edit' && product) {
            payload.product_id = Number(product.id);
            payload.variant_id = selectedVariantId > 0
              ? selectedVariantId
              : (Number(product.variant_id || 0) || null);
          }

          try {
            if (primaryImageFile) {
              const primaryUpload = await uploadAdminImage(primaryImageFile);
              primaryImageUrl = String(primaryUpload?.versions?.medium || primaryUpload?.image_url || '').trim();
              if (primaryImageUrlField) primaryImageUrlField.value = primaryImageUrl;
            }
            if (decorImageFile) {
              const decorUpload = await uploadAdminImage(decorImageFile);
              decorImageUrl = String(decorUpload?.versions?.thumb || decorUpload?.image_url || '').trim();
              if (decorImageUrlField) decorImageUrlField.value = decorImageUrl;
            }

            if (primaryImageUrl) payload.primary_image_url = primaryImageUrl;
            if (decorImageUrl) payload.decor_image_url = decorImageUrl;

            const response = await fetch('../api/admin_products.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || '' },
              body: JSON.stringify(payload),
            });
            const result = await parseJsonResponse(response);
            if (!response.ok) throw new Error(result?.error || 'Operation impossible');
            notify('success', mode === 'create' ? 'Produit cree.' : 'Produit mis a jour.');
            window.AdminModal.close();
            await fetchProducts();
          } catch (error) {
            notify('error', error.message || 'Operation impossible');
          }
        });
      },
    });
  };

  const openDeleteModal = (productsToDelete) => {
    if (!window.AdminModal || !Array.isArray(productsToDelete) || !productsToDelete.length) return;
    const isBulk = productsToDelete.length > 1;
    const preview = productsToDelete.slice(0, 5).map((p) => `<li>${p.name || `Produit #${p.id}`}</li>`).join('');
    const hiddenCount = productsToDelete.length > 5 ? `<li>+${productsToDelete.length - 5} autre(s)...</li>` : '';

    window.AdminModal.open({
      title: isBulk ? 'Confirmer la suppression multiple' : 'Confirmer la suppression',
      content: `
        <div class="admin-form-grid">
          <div class="admin-form-group admin-form-group--full">
            <p style="margin:0;color:var(--admin-text);">
              Cette action est irreversible. Vous allez supprimer
              <strong>${productsToDelete.length}</strong> produit(s).
            </p>
            <ul style="margin:10px 0 0 18px;padding:0;color:var(--admin-text);">
              ${preview}
              ${hiddenCount}
            </ul>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--danger" type="button" data-confirm-delete>Supprimer</button>
          </div>
        </div>
      `,
      onOpen: () => {
        const closeBtn = document.querySelector('[data-close-modal]');
        const confirmBtn = document.querySelector('[data-confirm-delete]');
        closeBtn?.addEventListener('click', () => window.AdminModal.close());

        confirmBtn?.addEventListener('click', async () => {
          if (confirmBtn.disabled) return;
          confirmBtn.disabled = true;
          confirmBtn.textContent = 'Suppression...';
          try {
            const productIds = productsToDelete.map((p) => Number(p.id)).filter((id) => Number.isFinite(id) && id > 0);
            const payload = isBulk
              ? { action: 'delete_many', product_ids: productIds }
              : { action: 'delete', product_id: productIds[0] };

            const response = await fetch('../api/admin_products.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getAdminCsrfToken?.() || '' },
              body: JSON.stringify(payload),
            });
            const result = await parseJsonResponse(response);
            if (!response.ok) throw new Error(result?.error || 'Suppression impossible');

            productIds.forEach((id) => selectedIds.delete(id));
            allProducts = allProducts.filter((p) => !productIds.includes(Number(p.id)));
            render();
            window.AdminModal.close();
            notify('success', isBulk ? `${productIds.length} produit(s) supprime(s).` : 'Produit supprime.');
          } catch (error) {
            notify('error', error.message || 'Suppression impossible');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Supprimer';
          }
        });
      },
    });
  };

  tbody.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const row = button.closest('tr[data-product-id]');
    if (!row) return;

    const productId = Number(row.dataset.productId || 0);
    const product = allProducts.find((p) => Number(p.id) === productId);
    if (!product) return;

    if (button.dataset.action === 'view') {
      window.AdminModal?.open({
        title: 'Details produit',
        content: `
          <div class="admin-form-grid">
            <div class="admin-form-group admin-form-group--full"><strong>${product.name}</strong></div>
            <div class="admin-form-group"><label>Categorie</label><div>${product.category_name || '-'}</div></div>
            <div class="admin-form-group"><label>Statut</label><div>${product.status}</div></div>
            <div class="admin-form-group"><label>Prix</label><div>${money(product.price_cents)}</div></div>
            <div class="admin-form-group"><label>Stock</label><div>${Number(product.stock_qty || 0)}</div></div>
            <div class="admin-form-actions"><button class="admin-btn" type="button" data-close-modal>Fermer</button></div>
          </div>`,
        onOpen: () => {
          document.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        },
      });
      return;
    }
    if (button.dataset.action === 'edit') {
      openProductModal('edit', product);
      return;
    }
    if (button.dataset.action === 'delete') {
      openDeleteModal([product]);
    }
  });

  tbody.addEventListener('change', (event) => {
    const checkbox = event.target.closest('input[data-action="select-row"]');
    if (!checkbox) return;
    const productId = Number(checkbox.dataset.productId || 0);
    if (productId <= 0) return;
    if (checkbox.checked) selectedIds.add(productId);
    else selectedIds.delete(productId);
    render();
  });

  selectAllCheckbox?.addEventListener('change', () => {
    const shouldSelect = Boolean(selectAllCheckbox.checked);
    visibleRows.forEach((p) => {
      const id = Number(p.id);
      if (!Number.isFinite(id) || id <= 0) return;
      if (shouldSelect) selectedIds.add(id);
      else selectedIds.delete(id);
    });
    render();
  });

  bulkDeleteBtn?.addEventListener('click', () => {
    const ids = getSelectedProductIds();
    if (!ids.length) return;
    const products = allProducts.filter((p) => ids.includes(Number(p.id)));
    openDeleteModal(products);
  });

  addBtn?.addEventListener('click', () => openProductModal('create'));
  reloadBtn?.addEventListener('click', fetchProducts);
  searchInput?.addEventListener('input', render);

  fetchProducts();
})();
