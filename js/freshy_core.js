// Core JS (recovered minimal set)
window.FreshyCart = window.FreshyCart || {};

window.FreshyCart.loadItems = window.FreshyCart.loadItems || function () {
  try {
    return JSON.parse(localStorage.getItem('cartItems')) || [];
  } catch (error) {
    return [];
  }
};

window.FreshyCart.saveItems = window.FreshyCart.saveItems || function (items) {
  localStorage.setItem('cartItems', JSON.stringify(items));
};

window.FreshyCart.getValidItems = window.FreshyCart.getValidItems || function () {
  // Defensive read: accept only array payloads and keep line items with quantity > 0.
  const items = window.FreshyCart.loadItems?.();
  if (!Array.isArray(items)) return [];
  return items.filter((item) => {
    if (!item || typeof item !== 'object') return false;
    const qty = Number(item.quantity ?? 0);
    return Number.isFinite(qty) && qty > 0;
  });
};

window.FreshyCart.formatCurrency = window.FreshyCart.formatCurrency || function (value) {
  if (Number.isNaN(value)) return '0 Fcfa';
  return `${value.toLocaleString('fr-FR')} Fcfa`;
};

window.FreshyCart.parsePriceFromText = window.FreshyCart.parsePriceFromText || function (text) {
  const normalized = (text || '').replace(/\u202f/g, ' ');
  const match = normalized.match(/([0-9 ]+)\s*Fcfa/i);
  if (!match) return 0;
  return parseInt(match[1].replace(/\s+/g, ''), 10) || 0;
};

window.FreshyCart.notify = window.FreshyCart.notify || function (type, message, options) {
  if (typeof window.showToast === 'function') {
    window.showToast(type, message, options);
  }
};

window.FreshyCart.getCsrfToken = window.FreshyCart.getCsrfToken || function () {
  return String(window.FRESHY_CSRF_TOKEN || '');
};

window.FreshyCart.setCsrfToken = window.FreshyCart.setCsrfToken || function (token) {
  const next = String(token || '').trim();
  if (!next) return;
  window.FRESHY_CSRF_TOKEN = next;
};

window.FreshyCart.refreshCsrfToken = window.FreshyCart.refreshCsrfToken || async function () {
  const response = await fetch('api/csrf.php', {
    headers: { Accept: 'application/json' },
    cache: 'no-store',
  });
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload?.error || 'csrf_refresh_failed');
  }
  const token = String(payload?.data?.csrf_token || '').trim();
  if (!token) {
    throw new Error('csrf_refresh_failed');
  }
  window.FreshyCart.setCsrfToken(token);
  return token;
};

window.FreshyCart.postJsonWithCsrf = window.FreshyCart.postJsonWithCsrf || async function (url, body, options) {
  const doRequest = (token) => fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': token,
    },
    body: JSON.stringify(body || {}),
  });

  let response = await doRequest(window.FreshyCart.getCsrfToken());
  if (response.status === 419 && options?.retryOnCsrf !== false) {
    try {
      const freshToken = await window.FreshyCart.refreshCsrfToken();
      response = await doRequest(freshToken);
    } catch (error) {
      return response;
    }
  }
  return response;
};

window.updateCheckoutState = window.updateCheckoutState || function () {
  const payButton = document.getElementById('modalCheckoutBtn');
  if (!payButton) return false;

  const hasItems = window.FreshyCart.getValidItems().length > 0;
  const disabled = !hasItems;

  payButton.disabled = disabled;
  payButton.classList.toggle('btn-disabled', disabled);
  payButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');

  return hasItems;
};

window.FreshyCart.handleCheckoutVerification = window.FreshyCart.handleCheckoutVerification || function () {
  // Root cause of false "empty cart" toasts:
  // reading an unvalidated cart source (null/shape mismatch/qty 0 lines).
  // Fix: derive a strict, validated view of the cart before decision.
  const validItems = window.FreshyCart.getValidItems();
  if (validItems.length === 0) {
    window.FreshyCart.notify?.('warning', 'Votre panier est vide.', { key: 'checkout_empty_cart' });
    return false;
  }
  return true;
};

window.handleCheckoutVerification = window.handleCheckoutVerification || function () {
  return window.FreshyCart.handleCheckoutVerification();
};

window.FreshyCart.applyServerCart = window.FreshyCart.applyServerCart || function (serverItems) {
  const items = Array.isArray(serverItems) ? serverItems : [];
  const local = window.FreshyCart.loadItems();

  const mapped = items
    .map((item) => {
      const variantId = parseInt(item?.variant_id, 10);
      if (!Number.isFinite(variantId)) return null;

      const previous = local.find((entry) => Number(entry?.variant_id) === variantId) || {};
      const title = item?.name || previous.title || 'Produit';
      const titleNormalized = title.toLowerCase();
      const fallbackDecor = titleNormalized.includes('citron')
        ? 'images/citron_menthe_epicerie.webp'
        : 'images/noix_epicerie.webp';
      const unitPriceCents = Number(item?.unit_price_cents) || 0;

      return {
        id: `${item?.product_id || 'product'}|${variantId}`,
        title,
        image: window.FreshyCatalog.resolveAssetUrl(item?.image_url || '') || previous.image || '',
        decorImage: window.FreshyCatalog.resolveAssetUrl(item?.decor_image_url || '') || previous.decorImage || fallbackDecor,
        variant: item?.variant_label || previous.variant || '',
        price: Math.max(0, Math.floor(unitPriceCents / 100)),
        variant_id: variantId,
        sale_mode: String(item?.sale_mode || previous.sale_mode || 'unit').toLowerCase() === 'carton' ? 'carton' : 'unit',
        quantity: Math.max(1, parseInt(item?.quantity, 10) || 1),
      };
    })
    .filter(Boolean);

  window.FreshyCart.saveItems(mapped);

  if (document.getElementById('modalCartItems')) {
    window.FreshyCart.renderModalCartItems?.({ buildItem: (it) => window.FreshyCart.buildModalItem(it) });
  }
  window.FreshyCart.renderRecapSection?.();
  window.updateCheckoutState?.();
  window.dispatchEvent(new CustomEvent('freshy:cart-updated', { detail: { items: mapped } }));

  return mapped;
};

window.FreshyCart.loadServerCart = window.FreshyCart.loadServerCart || async function (options) {
  const notifyOnError = Boolean(options?.notifyOnError);

  try {
    const response = await fetch('api/cart.php', { headers: { Accept: 'application/json' } });
    const payload = await response.json();

    if (!response.ok) {
      return { ok: false, error: payload?.error || 'cart_load_failed' };
    }

    window.FreshyCart.applyServerCart(payload?.data || []);
    return { ok: true, data: payload?.data || [] };
  } catch (error) {
    if (notifyOnError) {
      window.FreshyCart.notify('warning', 'Impossible de charger le panier serveur.', { key: 'cart_load_error' });
    }
    return { ok: false, error: 'network_error' };
  }
};

window.FreshyCart.upsertItem = window.FreshyCart.upsertItem || function (product) {
  if (!product || !product.id) return;

  const items = window.FreshyCart.loadItems();
  const existing = items.find((entry) => entry.id === product.id);
  if (existing) {
    existing.quantity += product.quantity || 1;
  } else {
    items.push(product);
  }
  window.FreshyCart.saveItems(items);

  if (!Number.isFinite(product.variant_id)) {
    return;
  }

  window.FreshyCart.postJsonWithCsrf('api/cart.php', { action: 'add', variant_id: product.variant_id, quantity: product.quantity || 1 })
    .then(async (response) => {
      let payload = {};
      try { payload = await response.json(); } catch (error) {}

      if (!response.ok) {
        const err = payload?.error || 'Impossible de mettre à jour le panier.';
        window.FreshyCart.notify('error', err === 'Insufficient stock' ? 'Stock insuffisant pour cette quantité.' : err, { key: 'cart_add_error' });
        await window.FreshyCart.loadServerCart();
        return;
      }

      window.FreshyCart.applyServerCart(payload?.data || []);
      window.FreshyCart.notify('success', 'Produit ajouté au panier.', { delay: 2200, key: 'cart_add_success' });
    })
    .catch(async () => {
      window.FreshyCart.notify('warning', 'Impossible de joindre le serveur panier.', { key: 'cart_network_error' });
      await window.FreshyCart.loadServerCart();
    });
};

window.FreshyCart.updateQuantity = window.FreshyCart.updateQuantity || function (id, delta) {
  if (!id || !delta) return;

  const items = window.FreshyCart.loadItems();
  const target = items.find((entry) => entry.id === id);
  if (!target) return;

  target.quantity = Math.max(1, target.quantity + delta);
  window.FreshyCart.saveItems(items);

  if (!Number.isFinite(target.variant_id)) {
    return;
  }

  window.FreshyCart.postJsonWithCsrf('api/cart.php', { action: 'update', variant_id: target.variant_id, quantity: target.quantity })
    .then(async (response) => {
      let payload = {};
      try { payload = await response.json(); } catch (error) {}

      if (!response.ok) {
        const err = payload?.error || 'Impossible de modifier la quantité.';
        window.FreshyCart.notify('error', err === 'Insufficient stock' ? 'Stock insuffisant pour cette quantité.' : err, { key: 'cart_update_error' });
        await window.FreshyCart.loadServerCart();
        return;
      }

      window.FreshyCart.applyServerCart(payload?.data || []);
    })
    .catch(async () => {
      window.FreshyCart.notify('warning', 'Erreur réseau pendant la mise à jour du panier.', { key: 'cart_update_network_error' });
      await window.FreshyCart.loadServerCart();
    });
};

window.FreshyCart.removeItem = window.FreshyCart.removeItem || function (id) {
  if (!id) return;

  const items = window.FreshyCart.loadItems();
  const index = items.findIndex((entry) => entry.id === id);
  if (index === -1) return;

  const removed = items[index];
  items.splice(index, 1);
  window.FreshyCart.saveItems(items);

  if (!Number.isFinite(removed.variant_id)) {
    return;
  }

  window.FreshyCart.postJsonWithCsrf('api/cart.php', { action: 'remove', variant_id: removed.variant_id })
    .then(async (response) => {
      let payload = {};
      try { payload = await response.json(); } catch (error) {}

      if (!response.ok) {
        const err = payload?.error || 'Impossible de retirer le produit.';
        window.FreshyCart.notify('warning', err, { key: 'cart_remove_error' });
        await window.FreshyCart.loadServerCart();
        return;
      }

      window.FreshyCart.applyServerCart(payload?.data || []);
    })
    .catch(async () => {
      window.FreshyCart.notify('warning', 'Erreur réseau pendant la suppression du produit.', { key: 'cart_remove_network_error' });
      await window.FreshyCart.loadServerCart();
    });
};

window.FreshyCart.syncServerCart = window.FreshyCart.syncServerCart || async function (items) {
  const payloadItems = (items || []).filter((item) => Number.isFinite(item.variant_id)).map((item) => ({
    variant_id: item.variant_id,
    quantity: item.quantity || 1,
  }));

  if (!payloadItems.length) {
    return { ok: false, error: 'missing_variants' };
  }

  try {
    const response = await window.FreshyCart.postJsonWithCsrf('api/cart.php', { action: 'replace', items: payloadItems });

    const data = await response.json();
    if (!response.ok) {
      await window.FreshyCart.loadServerCart();
      return { ok: false, error: data.error || 'sync_failed' };
    }

    window.FreshyCart.applyServerCart(data?.data || []);
    return { ok: true, data };
  } catch (error) {
    await window.FreshyCart.loadServerCart();
    return { ok: false, error: 'network_error' };
  }
};

window.FreshyCart.renderRecapSection = window.FreshyCart.renderRecapSection || function () {
  const recapItemsContainer = document.getElementById('recapItems');
  const recapTotalProducts = document.getElementById('recapTotalProducts');
  const recapTotalAmount = document.getElementById('recapTotalAmount');
  const orderSummaryTotal = document.getElementById('orderSummaryTotal');
  if (!recapItemsContainer || !recapTotalProducts || !recapTotalAmount) return;
  const items = window.FreshyCart.loadItems();
  recapItemsContainer.innerHTML = '';
  if (!items.length) {
    recapTotalProducts.textContent = '0';
    recapTotalAmount.textContent = window.FreshyCart.formatCurrency(0);
    if (orderSummaryTotal) orderSummaryTotal.textContent = window.FreshyCart.formatCurrency(0);
    return;
  }
  let totalProducts = 0;
  let totalAmount = 0;
  items.forEach((item) => {
    totalProducts += 1;
    totalAmount += item.price * item.quantity;
    const row = document.createElement('div');
    row.className = 'recap-article-item';
    row.innerHTML = `
      <div class="item-visual-info">
        <div class="item-image-wrapper item-image-wrapper-recap ${item.title && item.title.toLowerCase().includes('citronnade') ? 'item-image-wrapper--citronnade' : ''}">
          ${item.decorImage ? `<img src="${item.decorImage}" alt="Décor produit" class="recap-item-decor">` : ''}
          <span class="quantity-badge">${item.quantity}</span>
          <img src="${item.image}" alt="${item.title}" class="recap-item-image ${item.title && item.title.toLowerCase().includes('huile') ? 'recap-item-image--oil' : ''}">
        </div>
        <div class="item-text-details">
          <h3 class="item-title">${item.title}</h3>
          <p class="item-weight">${item.variant || ''}</p>
          ${item.sale_mode === 'carton' ? '<p class="recap-item-sale-mode">Mode: Carton</p>' : ''}
          ${item.sale_mode === 'carton' ? `<p class="recap-item-unit-price">Prix unitaire carton: ${window.FreshyCart.formatCurrency(item.price)}</p>` : ''}
        </div>
      </div>
      <span class="item-price">${window.FreshyCart.formatCurrency(item.price * item.quantity)}</span>
    `;
    recapItemsContainer.appendChild(row);
  });
  recapTotalProducts.textContent = String(totalProducts);
  recapTotalAmount.textContent = window.FreshyCart.formatCurrency(totalAmount);
  if (orderSummaryTotal) orderSummaryTotal.textContent = window.FreshyCart.formatCurrency(totalAmount);
};

window.FreshyCart.renderCartItems = window.FreshyCart.renderCartItems || function (options) {
  const { containerId, dividerId, emptyMessage = 'Votre panier est vide.', emptyClass = 'panier-empty', buildItem, afterRender } = options || {};
  if (!containerId) return;
  const container = document.getElementById(containerId);
  if (!container) return;
  const items = window.FreshyCart.loadItems();
  container.innerHTML = '';
  if (!items.length) {
    const emptyState = document.createElement('p');
    emptyState.className = emptyClass;
    emptyState.textContent = emptyMessage;
    container.appendChild(emptyState);
    if (dividerId) document.getElementById(dividerId)?.classList.add('hidden');
  } else {
    if (dividerId) document.getElementById(dividerId)?.classList.remove('hidden');
    items.forEach((item) => {
      const node = typeof buildItem === 'function' ? buildItem(item, items) : null;
      if (node) container.appendChild(node);
    });
  }
  if (typeof afterRender === 'function') afterRender(items);
};

window.FreshyCart.bindCartPageHandlers = window.FreshyCart.bindCartPageHandlers || function (options) {
  const { containerId, onUpdated } = options || {};
  if (!containerId) return;
  const container = document.getElementById(containerId);
  if (!container) return;
  container.addEventListener('click', (event) => {
    const decreaseBtn = event.target.closest('[data-action="decrease"]');
    const increaseBtn = event.target.closest('[data-action="increase"]');
    const removeBtn = event.target.closest('.quantite-supprimer');
    const article = event.target.closest('.panier-article');
    const id = article?.dataset?.id;
    if (!id) return;
    if (decreaseBtn) window.FreshyCart.updateQuantity(id, -1);
    else if (increaseBtn) window.FreshyCart.updateQuantity(id, 1);
    else if (removeBtn) window.FreshyCart.removeItem(id);
    else return;
    if (typeof onUpdated === 'function') onUpdated();
  });
};

window.FreshyCart.bindCheckoutNavigation = window.FreshyCart.bindCheckoutNavigation || function (options) {
  const { triggerSelector, targetSelector = '.page-container-flex', sections = ['.hero-section', '.filters-toolbar', '.products-grid-section', '#section_creme', '#panierSection', '.page-container-flex', '.navbar', '.footer-section'] } = options || {};
  const trigger = document.querySelector(triggerSelector);
  const target = document.querySelector(targetSelector);
  if (!trigger || !target) return;
  const mainSections = sections.map((selector) => document.querySelector(selector)).filter(Boolean);
  const setActiveMainSection = (activeSection) => {
    mainSections.forEach((section) => section.classList.add('hidden'));
    activeSection?.classList.remove('hidden');
  };
  trigger.addEventListener('click', (event) => {
    event.preventDefault();
    // Always verify against validated cart lines, not raw array length.
    if (!window.FreshyCart.handleCheckoutVerification?.()) {
      window.updateCheckoutState?.();
      return;
    }
    setActiveMainSection(target);
    if (typeof window.toggleCartModal === 'function') window.toggleCartModal(false);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
};
window.FreshyCart.buildProductFromCard = window.FreshyCart.buildProductFromCard || function (card) {
  if (!card) return null;
  const title = card.querySelector('h3')?.textContent.trim() || 'Produit';
  const image = card.querySelector('.product-card__image')?.getAttribute('src') || '';
  const decorImage = card.querySelector('.product-card__decor')?.getAttribute('src') || '';
  const select = card.querySelector('.product-card__select[data-role="unit-format"]') || card.querySelector('.product-card__select');
  const selectedOption = select ? select.options[select.selectedIndex] : null;
  const variant = selectedOption?.textContent.trim() || card.querySelector('p strong')?.textContent.trim() || 'Format standard';
  const selectedPrice = Number(selectedOption?.dataset?.price);
  const price = Number.isFinite(selectedPrice)
    ? Math.max(0, Math.floor(selectedPrice))
    : window.FreshyCart.parsePriceFromText(selectedOption?.textContent || card.querySelector('p strong')?.textContent || '0 Fcfa');
  const formatValue = selectedOption?.value || 'default';
  const variantIdRaw = selectedOption?.dataset?.variantId;
  const variantId = variantIdRaw ? parseInt(variantIdRaw, 10) : undefined;
  return { id: `${title}|${formatValue}`, title, image, decorImage, variant, price: price || 0, variant_id: Number.isFinite(variantId) ? variantId : undefined, quantity: 1 };
};

window.FreshyCart.buildCartonRequestFromCard = window.FreshyCart.buildCartonRequestFromCard || function (card) {
  if (!card) return null;
  const select = card.querySelector('.product-card__select[data-role="unit-format"]') || card.querySelector('.product-card__select[data-role="carton-format"]') || card.querySelector('.product-card__select');
  if (!select) return null;
  const selectedOption = select.options?.[select.selectedIndex];
  const variantId = parseInt(String(selectedOption?.dataset?.variantId || ''), 10);
  if (!Number.isFinite(variantId) || variantId <= 0) return null;

  return {
    product_id: parseInt(String(card.dataset.productId || ''), 10) || null,
    variant_id: variantId,
    product_name: card.querySelector('h3')?.textContent?.trim() || 'Produit',
    variant_label: selectedOption?.textContent?.trim() || '',
    cartons_qty: 1,
  };
};

window.FreshyCart.buildProductFromCremeSection = window.FreshyCart.buildProductFromCremeSection || function (section) {
  if (!section) return null;
  const title = section.querySelector('h1')?.textContent.trim() || 'Produit';
  const image = section.querySelector('.visual-panel img')?.getAttribute('src') || '';
  const decorImage = 'images/noix_epicerie.webp';
  const priceText = section.querySelector('.price')?.textContent || '0 Fcfa';
  const price = window.FreshyCart.parsePriceFromText(priceText);
  const activeFormatBtn = section.querySelector('.format-button.active');
  const variant = activeFormatBtn?.textContent.trim() || 'Format standard';
  const quantityInput = section.querySelector('.quantity-field');
  const rawQuantity = quantityInput ? parseInt(quantityInput.value, 10) : 1;
  const quantity = Number.isNaN(rawQuantity) || rawQuantity < 1 ? 1 : rawQuantity;
  const formatValue = activeFormatBtn?.textContent.trim() || 'default';
  const variantIdRaw = activeFormatBtn?.dataset?.variantId;
  const variantId = variantIdRaw ? parseInt(variantIdRaw, 10) : undefined;
  return { id: `${title}|${formatValue}`, title, image, decorImage, variant, price: price || 0, variant_id: Number.isFinite(variantId) ? variantId : undefined, quantity };
};

window.FreshyCart.buildModalItem = window.FreshyCart.buildModalItem || function (item) {
  const wrapper = document.createElement('div');
  wrapper.className = 'panier-item';
  wrapper.dataset.id = item.id;
  wrapper.innerHTML = `
    <div class="item-image-wrapper">
      ${item.decorImage ? `<img src="${item.decorImage}" alt="Décor produit" class="item-image-decor" />` : ''}
      <img src="${item.image}" alt="${item.title}" class="item-image" />
    </div>
    <div class="item-details">
      <h2 class="item-name">${item.title}</h2>
      <p class="item-weight">${item.variant || ''}</p>
      ${item.sale_mode === 'carton' ? '<p class="item-sale-mode">Mode: Carton</p>' : ''}
      ${item.sale_mode === 'carton' ? `<p class="item-unit-price">Prix unitaire carton: ${window.FreshyCart.formatCurrency(item.price)}</p>` : ''}
      <p class="item-price">${window.FreshyCart.formatCurrency(item.price)}</p>
      <div class="item-actions">
        <div class="quantity-control">
          <button class="qty-btn qty-decrease">-</button>
          <span class="qty-value">${item.quantity}</span>
          <button class="qty-btn qty-increase">+</button>
        </div>
        <button class="btn-supprimer" type="button" aria-label="Supprimer ce produit du panier">
          <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
          <span class="btn-supprimer__label">Supprimer</span>
        </button>
      </div>
    </div>
  `;
  wrapper.querySelector('.qty-decrease')?.addEventListener('click', () => {
    window.FreshyCart.updateQuantity(item.id, -1);
    window.FreshyCart.renderModalCartItems({ buildItem: (it) => window.FreshyCart.buildModalItem(it) });
  });
  wrapper.querySelector('.qty-increase')?.addEventListener('click', () => {
    window.FreshyCart.updateQuantity(item.id, 1);
    window.FreshyCart.renderModalCartItems({ buildItem: (it) => window.FreshyCart.buildModalItem(it) });
  });
  wrapper.querySelector('.btn-supprimer')?.addEventListener('click', () => {
    window.FreshyCart.removeItem(item.id);
    window.FreshyCart.renderModalCartItems({ buildItem: (it) => window.FreshyCart.buildModalItem(it) });
  });
  return wrapper;
};

window.FreshyCart.renderModalCartItems = window.FreshyCart.renderModalCartItems || function (options) {
  const { containerId = 'modalCartItems', totalId = 'modalCartTotal', payButtonId = 'modalCheckoutBtn', buildItem } = options || {};
  const updateModalTotal = () => {
    const totalEl = document.getElementById(totalId);
    if (!totalEl) return;
    const items = window.FreshyCart.loadItems();
    const total = items.reduce((sum, entry) => sum + entry.price * entry.quantity, 0);
    totalEl.textContent = window.FreshyCart.formatCurrency(total);
    const payButton = document.getElementById(payButtonId);
    if (payButton) {
      payButton.textContent = total > 0 ? `Payer • ${window.FreshyCart.formatCurrency(total)}` : 'Payer';
    }
    window.updateCheckoutState?.();
  };
  window.FreshyCart.renderCartItems({ containerId, buildItem, afterRender: () => { updateModalTotal(); window.FreshyCart.renderRecapSection(); } });
};


window.FreshyCatalog = window.FreshyCatalog || {};
window.FreshyCatalog.fetchProducts = window.FreshyCatalog.fetchProducts || async function () {
  if (window.FreshyCatalog._cache) return window.FreshyCatalog._cache;
  const response = await fetch('api/products.php', { headers: { Accept: 'application/json' } });
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error || 'catalog_fetch_failed');
  }
  const products = Array.isArray(payload.data) ? payload.data : [];
  window.FreshyCatalog._cache = products;
  return products;
};

window.FreshyCatalog.fetchRecommendedProducts = window.FreshyCatalog.fetchRecommendedProducts || async function (limit = 6) {
  const normalizedLimit = Number.isFinite(limit) ? Math.max(1, Math.min(6, Math.floor(limit))) : 6;
  const cacheKey = `rec_${normalizedLimit}`;
  window.FreshyCatalog._recommendedCache = window.FreshyCatalog._recommendedCache || {};
  if (window.FreshyCatalog._recommendedCache[cacheKey]) {
    return window.FreshyCatalog._recommendedCache[cacheKey];
  }

  const response = await fetch(`api/products_recommended.php?limit=${normalizedLimit}`, { headers: { Accept: 'application/json' } });
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error || 'recommended_catalog_fetch_failed');
  }
  const products = Array.isArray(payload.data) ? payload.data : [];
  window.FreshyCatalog._recommendedCache[cacheKey] = products;
  return products;
};

window.FreshyCatalog.formatPriceCents = window.FreshyCatalog.formatPriceCents || function (value) {
  const amount = Number.isFinite(value) ? Math.max(0, Math.floor(value / 100)) : 0;
  return `${amount.toLocaleString('fr-FR')} Fcfa`;
};

window.FreshyCatalog.normalizeSlug = window.FreshyCatalog.normalizeSlug || function (value) {
  return (value || '').toString().trim().toLowerCase();
};

window.FreshyCatalog.resolveAssetUrl = window.FreshyCatalog.resolveAssetUrl || function (value) {
  const raw = (value || '').toString().trim();
  if (!raw) return '';
  if (/^(https?:)?\/\//i.test(raw) || raw.startsWith('data:')) return raw;
  const looksLikeLegacyUpload = /^product_[a-z0-9_-]+\.(webp|png|jpe?g)$/i.test(raw);
  const normalizedRaw = looksLikeLegacyUpload ? `uploads/products/${raw}` : raw;
  if (!normalizedRaw.startsWith('/')) return normalizedRaw;
  const firstSegment = (window.location.pathname.split('/').filter(Boolean)[0] || '').trim();
  return firstSegment ? `/${firstSegment}${normalizedRaw}` : normalizedRaw;
};

window.FreshyCatalog.getVisualConfig = window.FreshyCatalog.getVisualConfig || function (product) {
  const categorySlug = window.FreshyCatalog.normalizeSlug(product?.category?.slug);
  const image = window.FreshyCatalog.resolveAssetUrl(product?.image || '');
  const decorImage = window.FreshyCatalog.resolveAssetUrl(product?.decor_image || '');
  if (categorySlug.includes('huile')) {
    return { image: image || 'images/huile_epicerie.webp', decor: decorImage || 'images/noix_epicerie.webp', alt: 'Huile de palme' };
  }
  if (categorySlug.includes('boisson') || categorySlug.includes('citron')) {
    return { image: image || 'images/citronnade_booste.webp', decor: decorImage || 'images/citron_menthe_epicerie.webp', alt: 'Boisson Freshy' };
  }
  return { image: image || 'images/concentre_epicerie.webp', decor: decorImage || 'images/noix_epicerie.webp', alt: 'Crème concentrée de noix de palme' };
};

window.FreshyCatalog.renderCard = window.FreshyCatalog.renderCard || function (product) {
  const article = document.createElement('article');
  article.className = 'product-card';
  const categorySlug = window.FreshyCatalog.normalizeSlug(product?.category?.slug);
  article.dataset.category = categorySlug || 'autres';
  article.dataset.productId = String(product?.id || '');
  const visuals = window.FreshyCatalog.getVisualConfig(product);
  const variants = Array.isArray(product?.variants) ? product.variants : [];
  const productStatus = String(product?.product_status || '').toUpperCase();
  const isSoldOut = productStatus === 'OUT_OF_STOCK' || !variants.length;
  const isNew = productStatus === 'NEW' || (!productStatus && Boolean(product?.is_new) && !isSoldOut);

  if (isNew) {
    article.classList.add('product-card--new');
  }
  if (isSoldOut) {
    article.classList.add('product-card--soldout');
  }

  const orderedVariants = [...variants].sort((a, b) => {
    const priceA = Number(a?.price_cents) || 0;
    const priceB = Number(b?.price_cents) || 0;
    if (priceA !== priceB) return priceA - priceB;
    const labelA = String(a?.label || '');
    const labelB = String(b?.label || '');
    return labelA.localeCompare(labelB, 'fr', { sensitivity: 'base', numeric: true });
  });

  const variantPricesCents = orderedVariants
    .map((variant) => Number(variant.price_cents))
    .filter((value) => Number.isFinite(value) && value > 0);
  const minPrice = variantPricesCents.length ? Math.min(...variantPricesCents) : 0;
  const selectOptions = orderedVariants.length
    ? orderedVariants
        .map((variant, index) => {
          const variantId = parseInt(variant.id, 10);
          const label = variant.label || variant.sku || 'Format';
          const priceFcfa = Math.max(0, Math.floor((Number(variant.price_cents) || 0) / 100));
          const selectedAttr = index === 0 ? ' selected' : '';
          return `<option value="${variant.sku || variantId}" data-variant-id="${variantId}" data-price="${priceFcfa}"${selectedAttr}>${label} - ${window.FreshyCatalog.formatPriceCents(Number(variant.price_cents) || 0)}</option>`;
        })
        .join('')
    : '<option value="" disabled>Aucun format disponible</option>';

  const badgeHtml = isSoldOut
    ? '<span class="product-card__badge--soldout badge--soldout">En rupture</span>'
    : (isNew ? '<span class="product-card__badge--new badge--new">Nouveau</span>' : '');

  const mediaTag = categorySlug.includes('creme')
    ? '<a class="product-card__media" data-target="section_creme" href="#section_creme">'
    : '<div class="product-card__media">';
  const mediaCloseTag = categorySlug.includes('creme') ? '</a>' : '</div>';
  const showCartonAction = categorySlug.includes('boisson');

  const purchaseUi = showCartonAction
    ? `
      <div class="product-card__purchase" aria-label="Mode d'achat">
        <div class="product-card__mode-switch" role="tablist" aria-label="Choisir un mode d'achat">
          <button type="button" class="product-card__mode-btn is-active" data-mode-btn="unit" role="tab" aria-selected="true">Unite</button>
          <button type="button" class="product-card__mode-btn" data-mode-btn="carton" role="tab" aria-selected="false">Carton</button>
        </div>
        <div class="product-card__mode-panel is-active" data-mode-panel="unit">
          <select class="product-card__select" data-role="unit-format" aria-label="Choisir un format" ${variants.length ? '' : 'disabled'}>
            ${selectOptions}
          </select>
          <button class="product-card__cta" type="button" ${variants.length ? '' : 'disabled'}>
            Ajouter au panier
            <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
          </button>
        </div>
        <div class="product-card__mode-panel" data-mode-panel="carton">
          <button class="product-card__cta--carton" type="button" data-action="carton" aria-label="Commander ce produit en carton">Commander en carton</button>
        </div>
      </div>
    `
    : `
      <select class="product-card__select" aria-label="Choisir un format" ${variants.length ? '' : 'disabled'}>
        ${selectOptions}
      </select>
      <button class="product-card__cta" type="button" ${variants.length ? '' : 'disabled'}>
        Ajouter au panier
        <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
      </button>
    `;

  article.innerHTML = `
    ${badgeHtml}
    ${mediaTag}
      <img src="${visuals.decor}" alt="Décor ${product?.name || 'produit'}" class="product-card__decor product-decorative-image" loading="lazy" decoding="async" />
      <img src="${visuals.image}" alt="${product?.name || visuals.alt}" class="product-card__image product-card-image" loading="lazy" decoding="async" />
    ${mediaCloseTag}
    <h3>${product?.name || 'Produit'}</h3>
    <p><strong>A partir de ${window.FreshyCatalog.formatPriceCents(minPrice)}</strong></p>
    <div class="product-card__overlay" role="group" aria-label="Options d'achat">
      ${purchaseUi}
    </div>
  `;

  return article;
};

window.FreshyCatalog.renderContainers = window.FreshyCatalog.renderContainers || function (products) {
  const containers = Array.from(document.querySelectorAll('[data-catalog-source="products-api"]'));
  if (!containers.length) return;
  containers.forEach((container) => {
    const context = String(container.dataset.catalogContext || '').toLowerCase();
    const sourceProducts = context === 'panier-related'
      ? (window.FreshyCatalog._recommendedCurrent || [])
      : products;
    const limitRaw = parseInt(container.dataset.catalogLimit || '0', 10);
    const limit = Number.isFinite(limitRaw) && limitRaw > 0 ? limitRaw : 0;
    const list = limit ? sourceProducts.slice(0, limit) : sourceProducts;
    container.innerHTML = '';
    if (!list.length) {
      const empty = document.createElement('p');
      empty.className = 'panier-empty';
      empty.textContent = 'Aucun produit disponible pour le moment.';
      container.appendChild(empty);
      return;
    }
    list.forEach((product) => container.appendChild(window.FreshyCatalog.renderCard(product)));
  });
  window.FreshyCart.bindAddToCartHandlers?.();
};

window.FreshyCatalog.init = window.FreshyCatalog.init || async function () {
  const containers = Array.from(document.querySelectorAll('[data-catalog-source="products-api"]'));
  if (!containers.length) return;

  containers.forEach((container) => {
    if (!container.querySelector('[data-catalog-loading="true"]')) {
      container.innerHTML = '<p class="panier-empty" data-catalog-loading="true">Chargement des produits...</p>';
    }
  });

  try {
    const products = await window.FreshyCatalog.fetchProducts();
    try {
      const recommended = await window.FreshyCatalog.fetchRecommendedProducts(6);
      window.FreshyCatalog._recommendedCurrent = recommended;
    } catch (_) {
      window.FreshyCatalog._recommendedCurrent = products;
    }
    window.FreshyCatalog.renderContainers(products);
  } catch (error) {
    console.error('Erreur de chargement catalogue', error);
    containers.forEach((container) => {
      container.innerHTML = '<p class="panier-empty">Impossible de charger les produits pour le moment.</p>';
    });
    window.FreshyCart?.notify?.('error', 'Impossible de charger le catalogue.');
  }
};

window.FreshyPayment = window.FreshyPayment || {};

window.FreshyPayment.startFedaPayCheckout = window.FreshyPayment.startFedaPayCheckout || async function (orderData) {
  const orderId = Number(orderData?.order_id) || 0;
  if (orderId <= 0) return { ok: false, error: 'invalid_order' };

  const response = await window.FreshyCart.postJsonWithCsrf('api/fedapay_checkout.php', { order_id: orderId });
  const raw = await response.text();
  let payload = {};
  try {
    payload = raw ? JSON.parse(raw) : {};
  } catch (_) {
    payload = {};
  }
  if (!response.ok) {
    const detail = String(payload?.detail || '').trim();
    return { ok: false, error: payload?.error || 'payment_init_failed', detail, status: response.status };
  }
  const checkoutUrl = String(payload?.data?.checkout_url || '');
  if (!checkoutUrl) {
    return { ok: false, error: 'payment_url_missing' };
  }
  return { ok: true, data: payload.data || {} };
};

window.FreshyCart.bindAddToCartHandlers = window.FreshyCart.bindAddToCartHandlers || function () {
  const cartModal = document.getElementById('panierModal');
  const modalCartItems = document.getElementById('modalCartItems');
  const cartCtas = document.querySelectorAll('.product-card__cta');
  const cartonCtas = document.querySelectorAll('.product-card__cta--carton');
  const cartCtastwo = document.querySelectorAll('.cta-button');

  const toggleCartModal = (show = true) => {
    if (!cartModal) return;
    if (show) {
      window.FreshyCart.renderModalCartItems({ buildItem: (item) => window.FreshyCart.buildModalItem(item) });
      cartModal.classList.remove('hidden');
      cartModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    } else {
      cartModal.classList.add('hidden');
      cartModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }
  };

  // Keep carton / mode handlers active even on pages without the cart modal (ex: panier.php).
  if (cartModal && modalCartItems) {
    window.toggleCartModal = toggleCartModal;
  }

  const handleAddToCartClick = (event, button) => {
    event.preventDefault();
    let product = null;
    const card = button.closest('.product-card');
    if (card) product = window.FreshyCart.buildProductFromCard(card);
    else {
      const cremeSection = button.closest('#section_creme');
      if (cremeSection) product = window.FreshyCart.buildProductFromCremeSection(cremeSection);
    }
    if (!product) return;
    window.FreshyCart.upsertItem(product);
    toggleCartModal(true);
  };

  const attachCartHandlers = (buttons) => {
    buttons.forEach((btn) => {
      const isSoldOut = btn.closest('.product-card--soldout');
      if (btn.dataset.cartBound === 'true') return;
      btn.dataset.cartBound = 'true';
      if (isSoldOut) {
        btn.addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); return false; });
      } else {
        btn.addEventListener('click', (event) => handleAddToCartClick(event, btn));
      }
    });
  };

  const attachVariantPriceAnimation = () => {
    const cards = Array.from(document.querySelectorAll('.product-card'));
    cards.forEach((card) => {
      const select = card.querySelector('.product-card__select[data-role="unit-format"]') || card.querySelector('.product-card__select');
      const priceNode = card.querySelector('p strong');
      if (!select || !priceNode) return;
      if (select.dataset.priceBound === 'true') return;
      select.dataset.priceBound = 'true';

      const optionPrices = Array.from(select.options || [])
        .map((opt) => {
          const dataPrice = Number(opt?.dataset?.price);
          if (Number.isFinite(dataPrice) && dataPrice > 0) return dataPrice;
          const parsed = window.FreshyCart.parsePriceFromText(opt?.textContent || '');
          if (parsed > 0) {
            opt.dataset.price = String(parsed);
            return parsed;
          }
          return 0;
        })
        .filter((v) => Number.isFinite(v) && v > 0);
      if (optionPrices.length) {
        const minPrice = Math.min(...optionPrices);
        priceNode.textContent = `A partir de ${window.FreshyCart.formatCurrency(minPrice)}`;
      }

      select.addEventListener('change', () => {
        const selected = select.options?.[select.selectedIndex];
        const dataPrice = Number(selected?.dataset?.price);
        const nextPrice = (Number.isFinite(dataPrice) && dataPrice > 0)
          ? dataPrice
          : window.FreshyCart.parsePriceFromText(selected?.textContent || '');
        const nextText = `A partir de ${window.FreshyCart.formatCurrency(nextPrice)}`;
        if (priceNode.textContent?.trim() === nextText) return;

        // Immediate text update on variant change, then subtle visual feedback.
        priceNode.textContent = nextText;
        priceNode.classList.remove('price-animate-out', 'price-animate-in', 'price-animate-prepare');
        priceNode.classList.add('price-animate-prepare');
        requestAnimationFrame(() => {
          priceNode.classList.add('price-animate-in');
          priceNode.classList.remove('price-animate-prepare');
          const clearClass = () => {
            priceNode.classList.remove('price-animate-in');
            priceNode.removeEventListener('transitionend', clearClass);
          };
          priceNode.addEventListener('transitionend', clearClass);
        });
      });
    });
  };

  const attachPurchaseModeToggle = () => {
    const cards = Array.from(document.querySelectorAll('.product-card'));
    cards.forEach((card) => {
      if (card.dataset.purchaseModeBound === 'true') return;
      card.dataset.purchaseModeBound = 'true';

      const unitBtn = card.querySelector('[data-mode-btn="unit"]');
      const cartonBtn = card.querySelector('[data-mode-btn="carton"]');
      const unitPanel = card.querySelector('[data-mode-panel="unit"]');
      const cartonPanel = card.querySelector('[data-mode-panel="carton"]');
      if (!unitBtn || !cartonBtn || !unitPanel || !cartonPanel) return;

      const setMode = (mode) => {
        const isCarton = mode === 'carton';
        unitBtn.classList.toggle('is-active', !isCarton);
        cartonBtn.classList.toggle('is-active', isCarton);
        unitBtn.setAttribute('aria-selected', (!isCarton).toString());
        cartonBtn.setAttribute('aria-selected', isCarton.toString());
        unitPanel.classList.toggle('is-active', !isCarton);
        cartonPanel.classList.toggle('is-active', isCarton);
      };

      unitBtn.addEventListener('click', () => setMode('unit'));
      cartonBtn.addEventListener('click', () => setMode('carton'));

    });
  };

  // Delegation fallback for dynamically injected cards that may miss direct bindings.
  if (!window.FreshyCart._cartonDelegationBound) {
    window.FreshyCart._cartonDelegationBound = true;
    document.addEventListener('click', (event) => {
      const modeBtn = event.target.closest('.product-card [data-mode-btn]');
      if (modeBtn) {
        const card = modeBtn.closest('.product-card');
        if (!card || card.dataset.purchaseModeBound === 'true') return;
        const mode = String(modeBtn.getAttribute('data-mode-btn') || 'unit');
        const unitBtn = card.querySelector('[data-mode-btn="unit"]');
        const cartonBtn = card.querySelector('[data-mode-btn="carton"]');
        const unitPanel = card.querySelector('[data-mode-panel="unit"]');
        const cartonPanel = card.querySelector('[data-mode-panel="carton"]');
        if (!unitBtn || !cartonBtn || !unitPanel || !cartonPanel) return;
        const isCarton = mode === 'carton';
        unitBtn.classList.toggle('is-active', !isCarton);
        cartonBtn.classList.toggle('is-active', isCarton);
        unitBtn.setAttribute('aria-selected', (!isCarton).toString());
        cartonBtn.setAttribute('aria-selected', isCarton.toString());
        unitPanel.classList.toggle('is-active', !isCarton);
        cartonPanel.classList.toggle('is-active', isCarton);
        return;
      }

      const cartonBtn = event.target.closest('.product-card__cta--carton');
      if (!cartonBtn) return;
      if (cartonBtn.dataset.cartonBound === 'true') return;
      event.preventDefault();
      const card = cartonBtn.closest('.product-card');
      const payload = window.FreshyCart.buildCartonRequestFromCard(card);
      if (!payload) {
        window.FreshyCart.notify?.('warning', 'Format indisponible pour la commande en carton.');
        return;
      }
      window.dispatchEvent(new CustomEvent('freshy:carton-order-request', { detail: payload }));
    });
  }

  const ensureCartonModal = () => {
    let modal = document.getElementById('cartonOrderModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'cartonOrderModal';
    modal.className = 'carton-modal hidden';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
      <div class="carton-modal__backdrop" data-action="close-cm"></div>
      <div class="carton-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cartonModalTitle">
        <button class="carton-modal__close" type="button" aria-label="Fermer" data-action="close-cm">&times;</button>
        <h3 id="cartonModalTitle">Commander en carton</h3>
        <p class="carton-modal__product" data-role="cm-product">Produit</p>
        <p class="carton-modal__variant" data-role="cm-variant">Format</p>
        <label class="carton-modal__label" for="cartonModalQty">Nombre de cartons</label>
        <div class="carton-modal__qty">
          <button type="button" data-action="cm-minus" aria-label="Diminuer">-</button>
          <input id="cartonModalQty" type="number" min="1" step="1" value="1" data-role="cm-qty">
          <button type="button" data-action="cm-plus" aria-label="Augmenter">+</button>
        </div>
        <p class="carton-modal__hint">Le tarif est calcule dynamiquement selon la quantite.</p>
        <div class="carton-modal__actions">
          <button type="button" class="carton-modal__btn carton-modal__btn--ghost" data-action="close-cm">Annuler</button>
          <button type="button" class="carton-modal__btn carton-modal__btn--primary" data-action="confirm-cm">Valider la commande carton</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    return modal;
  };

  const bindCartonModal = () => {
    if (window.FreshyCart._cartonModalBound) return;
    window.FreshyCart._cartonModalBound = true;
    const modal = ensureCartonModal();
    const qtyInput = modal.querySelector('[data-role="cm-qty"]');
    const productNode = modal.querySelector('[data-role="cm-product"]');
    const variantNode = modal.querySelector('[data-role="cm-variant"]');
    let currentPayload = null;

    const closeModal = () => {
      const active = document.activeElement;
      if (active && modal.contains(active) && typeof active.blur === 'function') {
        active.blur();
      }
      modal.classList.add('hidden');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('has-carton-modal');
    };

    const openModal = (payload) => {
      currentPayload = payload;
      if (productNode) productNode.textContent = String(payload?.product_name || 'Produit');
      if (variantNode) variantNode.textContent = String(payload?.variant_label || '');
      if (qtyInput) qtyInput.value = '1';
      modal.classList.remove('hidden');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('has-carton-modal');
    };

    modal.addEventListener('click', (event) => {
      const target = event.target.closest('[data-action]');
      if (!target) return;
      const action = target.dataset.action;
      if (action === 'close-cm') {
        closeModal();
      } else if (action === 'cm-minus' && qtyInput) {
        qtyInput.value = String(Math.max(1, (parseInt(qtyInput.value, 10) || 1) - 1));
      } else if (action === 'cm-plus' && qtyInput) {
        qtyInput.value = String(Math.max(1, (parseInt(qtyInput.value, 10) || 1) + 1));
      } else if (action === 'confirm-cm') {
        const qty = Math.max(1, parseInt(String(qtyInput?.value || '1'), 10) || 1);
        const confirmed = { ...(currentPayload || {}), cartons_qty: qty };
        window.dispatchEvent(new CustomEvent('freshy:carton-order-confirm', { detail: confirmed }));
        closeModal();
      }
    });

    qtyInput?.addEventListener('blur', () => {
      qtyInput.value = String(Math.max(1, parseInt(String(qtyInput.value || '1'), 10) || 1));
    });

    window.addEventListener('freshy:carton-order-request', (event) => {
      openModal(event.detail || {});
    });
  };

  const bindCartonOrderSubmit = () => {
    if (window.FreshyCart._cartonSubmitBound) return;
    window.FreshyCart._cartonSubmitBound = true;

    window.addEventListener('freshy:carton-order-confirm', async (event) => {
      const payload = event.detail || {};
      const variantId = Number(payload.variant_id) || 0;
      const cartonsQty = Math.max(1, Number(payload.cartons_qty) || 1);
      if (variantId <= 0) {
        window.FreshyCart.notify?.('warning', 'Format carton invalide.');
        return;
      }

      try {
        const response = await window.FreshyCart.postJsonWithCsrf('api/cart.php', {
          action: 'add_carton',
          variant_id: variantId,
          cartons_qty: cartonsQty,
        });
        let data = {};
        try { data = await response.json(); } catch (_) {}
        if (!response.ok) {
          const err = String(data?.error || 'Erreur commande carton');
          const detail = String(data?.detail || '').trim();
          const msg = err === 'Insufficient stock'
            ? 'Stock insuffisant pour cette quantite de cartons.'
            : (detail ? `Commande carton echouee: ${detail}` : `Commande carton echouee: ${err}`);
          window.FreshyCart.notify?.('error', msg, { key: 'carton_add_error' });
          await window.FreshyCart.loadServerCart();
          return;
        }

        window.FreshyCart.applyServerCart(data?.data || []);
        window.FreshyCart.notify?.('success', 'Commande carton ajoutee au panier.', { key: 'carton_add_success' });
        if (typeof window.toggleCartModal === 'function') {
          window.toggleCartModal(true);
        }
      } catch (error) {
        window.FreshyCart.notify?.('warning', 'Erreur reseau pendant la commande carton.', { key: 'carton_add_network_error' });
      }
    });
  };

  attachCartHandlers(Array.from(cartCtas));
  attachCartHandlers(Array.from(cartCtastwo));
  cartonCtas.forEach((btn) => {
    if (btn.dataset.cartonBound === 'true') return;
    btn.dataset.cartonBound = 'true';
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      const card = btn.closest('.product-card');
      const payload = window.FreshyCart.buildCartonRequestFromCard(card);
      if (!payload) {
        window.FreshyCart.notify?.('warning', 'Format indisponible pour la commande en carton.');
        return;
      }

      const cartonEvent = new CustomEvent('freshy:carton-order-request', { detail: payload });
      window.dispatchEvent(cartonEvent);
    });
  });
  attachPurchaseModeToggle();
  bindCartonModal();
  bindCartonOrderSubmit();
  attachVariantPriceAnimation();
};

window.FreshyCart.initCartPage = window.FreshyCart.initCartPage || function () {
  const cartArticlesContainer = document.getElementById('panierArticles');
  const cartTotalAmount = document.getElementById('cartTotalAmount');
  if (!cartArticlesContainer || !cartTotalAmount) return;
  if (window.FreshyCart._cartPageBound) return;
  window.FreshyCart._cartPageBound = true;

  const cartDivider = document.getElementById('cartDivider');
  const suggestionCtas = document.querySelectorAll('.product-card__cta');
  const carouselContainer = document.querySelector('.enregistré .carousel-container.products-grid');

  const updateCartTotals = (items) => {
    const total = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    cartTotalAmount.textContent = window.FreshyCart.formatCurrency(total);
  };

  const buildCartArticle = (item) => {
    const article = document.createElement('div');
    article.className = 'panier-article';
    article.dataset.id = item.id;
    article.innerHTML = `
      <div class="article-info">
        <div class="article-media">
          ${item.decorImage ? `<img src="${item.decorImage}" alt="Décor produit" class="article-decor" />` : ''}
          <img src="${item.image}" alt="${item.title}" class="article-image" />
        </div>
        <div class="article-details">
          <p class="article-nom">${item.title}</p>
          <p class="article-poids">${item.variant}</p>
          <p class="article-prix-unite">${window.FreshyCart.formatCurrency(item.price)}</p>
        </div>
      </div>
      <div class="article-quantite-controle">
        <div class="quantite-boutons">
          <button class="quantite-btn" data-action="decrease">-</button>
          <span class="quantite-valeur">${item.quantity}</span>
          <button class="quantite-btn" data-action="increase">+</button>
        </div>
        <button class="quantite-supprimer">Supprimer</button>
      </div>
      <span class="article-total">${window.FreshyCart.formatCurrency(item.price * item.quantity)}</span>
    `;
    return article;
  };

  const renderCartItems = () => {
    window.FreshyCart.renderCartItems({
      containerId: 'panierArticles',
      dividerId: 'cartDivider',
      buildItem: (item) => buildCartArticle(item),
      afterRender: (items) => { updateCartTotals(items); window.FreshyCart.renderRecapSection(); },
    });
  };

  if (carouselContainer) carouselContainer.style.scrollBehavior = 'smooth';

  if (carouselContainer && !carouselContainer.dataset.suggestionBound) {
    carouselContainer.dataset.suggestionBound = 'true';
    carouselContainer.addEventListener('click', (event) => {
      const cta = event.target.closest('.product-card__cta');
      if (!cta) return;
      event.preventDefault();
      const card = cta.closest('.product-card');
      if (!card) return;
      const product = window.FreshyCart.buildProductFromCard(card);
      if (!product) return;
      window.FreshyCart.upsertItem(product);
      renderCartItems();
    });
  }

  window.FreshyCart.bindCheckoutNavigation({ triggerSelector: '.footer-btn-verifier', targetSelector: '.page-container-flex' });
  window.FreshyCart.bindCartPageHandlers({ containerId: 'panierArticles', onUpdated: renderCartItems });

  // Keep panier page synced with async cart updates (ex: add_carton flow).
  window.addEventListener('freshy:cart-updated', renderCartItems);

  renderCartItems();
  window.FreshyCart.renderRecapSection();
};
window.FreshyFilters = window.FreshyFilters || {};
window.FreshyFilters.bindFilters = window.FreshyFilters.bindFilters || function (options) {
  const { listSelector = '.filtre-options-list', resetSelector = '.btn.btn-reinitialiser', applySelector = '.btn.btn-appliquer', labelSelector = '.section.filtre .texte', onApply, onReset } = options || {};
  const list = document.querySelector(listSelector);
  if (!list) return;
  const resetButton = document.querySelector(resetSelector);
  const applyButton = document.querySelector(applySelector);
  const label = document.querySelector(labelSelector);
  const normalizeFilterLabel = (value = '') => value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const getActiveLabel = () => {
    const active = list.querySelector('.option-item.active');
    return active ? normalizeFilterLabel(active.textContent) : null;
  };
  const updateLabel = (count = 0) => {
    if (!label) return;
    if (count > 0) { label.textContent = `Filtre : (${count})`; label.style.color = '#83BA3A'; }
    else { label.textContent = 'Filtre'; label.style.color = ''; }
  };
  updateLabel(0);
  list.addEventListener('click', (event) => {
    const selectedItem = event.target.closest('.option-item');
    if (!selectedItem || !list.contains(selectedItem)) return;
    list.querySelectorAll('.option-item.active').forEach((item) => {
      if (item !== selectedItem) item.classList.remove('active');
    });
    selectedItem.classList.add('active');
  });
  resetButton?.addEventListener('click', () => {
    list.querySelectorAll('.option-item.active').forEach((item) => item.classList.remove('active'));
    updateLabel(0);
    if (typeof onReset === 'function') onReset();
  });
  applyButton?.addEventListener('click', () => {
    const selected = getActiveLabel();
    const count = selected ? 1 : 0;
    updateLabel(count);
    if (typeof onApply === 'function') onApply(selected, count);
  });
};

window.FreshyFilters.bindSortOptions = window.FreshyFilters.bindSortOptions || function (options) {
  const { listSelector = '.option-item-trier', gridSelector = '.products-grid', modalSelector = '#modalTrierPar' } = options || {};
  const sortModal = document.querySelector(modalSelector);
  const optionsList = sortModal?.querySelector('.options-list') || document;
  const defaultOrderByGrid = new WeakMap();
  let currentSort = '';

  const normalizeLabel = (value = '') => value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9: ]/g, ' ').replace(/\s+/g, ' ');
  const getSortOptions = () => Array.from(optionsList.querySelectorAll(listSelector));
  const getVisibleGrid = () => {
    const grids = Array.from(document.querySelectorAll(gridSelector));
    return grids.find((grid) => {
      if (grid.closest('#section_creme.hidden')) return false;
      if (grid.closest('.hidden')) return false;
      const section = grid.closest('.products-grid-section');
      if (section?.classList.contains('hidden')) return false;
      return true;
    }) || grids[0] || null;
  };

  const snapshotDefaultOrder = (grid) => {
    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.product-card'));
    if (!defaultOrderByGrid.has(grid) || (defaultOrderByGrid.get(grid)?.length || 0) !== cards.length) {
      defaultOrderByGrid.set(grid, cards);
    }
  };

  const restoreDefaultOrder = (grid) => {
    if (!grid) return;
    snapshotDefaultOrder(grid);
    const defaultCards = defaultOrderByGrid.get(grid) || [];
    defaultCards.forEach((card) => {
      if (card && card.parentElement === grid) grid.appendChild(card);
    });
  };

  const readCardPrice = (card) => {
    const select = card.querySelector('.product-card__select');
    const option = select?.options?.[select.selectedIndex] || select?.options?.[0];
    const optionText = option?.textContent || '';
    const fallback = card.querySelector('p strong')?.textContent || '';
    return window.FreshyCart?.parsePriceFromText?.(optionText || fallback) || 0;
  };

  const sortProducts = (grid, mode) => {
    if (!grid) return;
    if (!mode || mode === 'default') {
      restoreDefaultOrder(grid);
      return;
    }
    const cards = Array.from(grid.querySelectorAll('.product-card'));
    const byTitle = (a, b, dir) => {
      const titleA = normalizeLabel(a.querySelector('h3')?.textContent || '');
      const titleB = normalizeLabel(b.querySelector('h3')?.textContent || '');
      if (titleA < titleB) return dir === 'asc' ? -1 : 1;
      if (titleA > titleB) return dir === 'asc' ? 1 : -1;
      return 0;
    };

    cards.sort((a, b) => {
      if (mode === 'alpha_asc') return byTitle(a, b, 'asc');
      if (mode === 'alpha_desc') return byTitle(a, b, 'desc');
      if (mode === 'price_asc') return readCardPrice(a) - readCardPrice(b);
      if (mode === 'price_desc') return readCardPrice(b) - readCardPrice(a);
      if (mode === 'date_oldest') return (parseInt(a.dataset.productId || '0', 10) || 0) - (parseInt(b.dataset.productId || '0', 10) || 0);
      if (mode === 'date_newest') return (parseInt(b.dataset.productId || '0', 10) || 0) - (parseInt(a.dataset.productId || '0', 10) || 0);
      return 0;
    });

    cards.forEach((card) => grid.appendChild(card));
  };

  const resolveSortMode = (option) => {
    const explicit = option?.dataset?.sort || '';
    if (explicit) return explicit;
    const value = normalizeLabel(option?.textContent || '');
    if ((value.includes('alphabetique') || value.includes('alphab')) && /a\s*(a\s*)?z/.test(value) && !/z\s*(a\s*)?a/.test(value)) return 'alpha_asc';
    if ((value.includes('alphabetique') || value.includes('alphab')) && /z\s*(a\s*)?a/.test(value)) return 'alpha_desc';
    if (value.includes('prix') && value.includes('faible') && value.includes('eleve')) return 'price_asc';
    if (value.includes('prix') && value.includes('eleve') && value.includes('faible')) return 'price_desc';
    if (value.includes('date') && value.includes('plus ancienne')) return 'date_oldest';
    if (value.includes('date') && value.includes('plus recente')) return 'date_newest';
    return '';
  };

  const clearSortUi = () => {
    getSortOptions().forEach((option) => {
      option.classList.remove('active');
      option.removeAttribute('aria-current');
    });
  };

  const updateSortUi = (activeOption) => {
    clearSortUi();
    if (!activeOption || currentSort === '' || currentSort === 'default') return;
    activeOption.classList.add('active');
    activeOption.setAttribute('aria-current', 'true');
  };

  const applySort = (option) => {
    const grid = getVisibleGrid();
    if (!grid) {
      console.warn('[sort] grille introuvable');
      return;
    }
    snapshotDefaultOrder(grid);
    const mode = resolveSortMode(option);
    if (!mode) {
      console.warn('[sort] option non prise en charge:', normalizeLabel(option?.textContent || ''));
      return;
    }
    currentSort = mode;
    sortProducts(grid, mode);
    updateSortUi(option);
    console.debug('[sort] applied:', mode);
  };

  const resetSort = () => {
    const grid = getVisibleGrid();
    if (!grid) return;
    currentSort = '';
    restoreDefaultOrder(grid);
    clearSortUi();
    console.debug('[sort] reset');
  };

  getSortOptions().forEach((option) => {
    if (option.querySelector('.sort-reset-btn')) return;
    const resetBtn = document.createElement('button');
    resetBtn.type = 'button';
    resetBtn.className = 'sort-reset-btn';
    resetBtn.setAttribute('aria-label', 'Réinitialiser le tri');
    resetBtn.innerHTML = '&#x267B;';
    option.appendChild(resetBtn);
  });

  optionsList.addEventListener('click', (event) => {
    const resetBtn = event.target.closest('.sort-reset-btn');
    if (resetBtn) {
      event.preventDefault();
      event.stopPropagation();
      resetSort();
      if (sortModal) sortModal.style.display = 'none';
      return;
    }

    const option = event.target.closest(listSelector);
    if (!option) return;
    applySort(option);
    if (sortModal) sortModal.style.display = 'none';
  });
};

window.FreshyCarousel = window.FreshyCarousel || {};
window.FreshyCarousel.initCremeCarousel = window.FreshyCarousel.initCremeCarousel || function () {
  const sectionCreme = document.getElementById('section_creme');
  if (!sectionCreme) return;
  const images = ['images/concentre_epicerie (2).webp','images/motif_concentre_epicerie.webp','images/motif_concentre_epicerie (2).webp'];
  let currentImageIndex = 0;
  const mainImage = sectionCreme.querySelector('.section-wrapper .visual-panel img');
  const prevBtn = sectionCreme.querySelector('.creme-carousel-arrow--prev');
  const nextBtn = sectionCreme.querySelector('.creme-carousel-arrow--next');
  const dots = sectionCreme.querySelectorAll('.creme-carousel-dot');
  const changeImage = (index) => {
    if (!mainImage || !images[index]) return;
    mainImage.src = images[index];
    currentImageIndex = index;
    dots.forEach((dot, i) => { dot.classList.toggle('active', i === index); dot.setAttribute('aria-current', i === index ? 'true' : 'false'); });
    const thumbs = sectionCreme.querySelectorAll('.thumbnail-list button');
    thumbs.forEach((thumb, i) => { thumb.classList.toggle('active', i === index); thumb.setAttribute('aria-pressed', i === index ? 'true' : 'false'); });
  };
  prevBtn?.addEventListener('click', () => changeImage(currentImageIndex === 0 ? images.length - 1 : currentImageIndex - 1));
  nextBtn?.addEventListener('click', () => changeImage((currentImageIndex + 1) % images.length));
  dots.forEach((dot, index) => dot.addEventListener('click', () => changeImage(index)));
  sectionCreme.querySelectorAll('.thumbnail-list button').forEach((btn, index) => btn.addEventListener('click', () => changeImage(index)));
  let touchStartX = 0; let touchEndX = 0;
  const handleSwipe = () => {
    if (touchEndX < touchStartX - 50) changeImage((currentImageIndex + 1) % images.length);
    if (touchEndX > touchStartX + 50) changeImage(currentImageIndex === 0 ? images.length - 1 : currentImageIndex - 1);
  };
  mainImage?.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0].screenX; });
  mainImage?.addEventListener('touchend', (event) => { touchEndX = event.changedTouches[0].screenX; handleSwipe(); });
  changeImage(0);
};

window.FreshySectionNav = window.FreshySectionNav || {};
window.FreshySectionNav.initCremeSection = window.FreshySectionNav.initCremeSection || function () {
  const cremeSection = document.getElementById('section_creme');
  if (!cremeSection) return;
  if (window.FreshySectionNav._cremeBound) return;
  window.FreshySectionNav._cremeBound = true;
  const formatButtons = cremeSection.querySelectorAll('.format-button');
  formatButtons.forEach((btn) => btn.addEventListener('click', () => { formatButtons.forEach((b) => b.classList.remove('active')); btn.classList.add('active'); }));
  window.FreshyCarousel.initCremeCarousel?.();
  const sectionsToHide = [document.querySelector('.filters-toolbar'), document.querySelector('.products-grid-section')];
  const manageCremeSectionDisplay = (show) => document.body.classList.toggle('showing-creme-section', !!show);
  document.addEventListener('click', (event) => {
    const media = event.target.closest('.product-card__media');
    if (media) {
      event.preventDefault();
      const explicitTarget = media.getAttribute('data-target');
      const hrefTarget = media.getAttribute('href')?.replace('#', '');
      const targetId = explicitTarget || hrefTarget || 'section_creme';
      const targetSection = document.getElementById(targetId);
      if (!targetSection) return;
      sectionsToHide.forEach((section) => section?.classList.add('hidden'));
      targetSection.classList.remove('hidden');
      manageCremeSectionDisplay(true);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    const backLink = event.target.closest('.back-link');
    if (backLink) {
      event.preventDefault();
      cremeSection.classList.add('hidden');
      sectionsToHide.forEach((section) => section?.classList.remove('hidden'));
      manageCremeSectionDisplay(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
};

window.FreshySalesPoints = window.FreshySalesPoints || {};
window.FreshySalesPoints.initCityTabs = window.FreshySalesPoints.initCityTabs || function () {
  const villeLinks = document.querySelectorAll('.villes-navigation .ville-link');
  const villeGroups = document.querySelectorAll('.ville-points');
  if (!villeLinks.length || !villeGroups.length) return;
  if (window.FreshySalesPoints._bound) return;
  window.FreshySalesPoints._bound = true;
  const showVille = (slug) => {
    villeGroups.forEach((group) => { const groupVille = group.getAttribute('data-ville'); group.style.display = groupVille === slug ? 'block' : 'none'; });
    villeLinks.forEach((link) => { const href = link.getAttribute('href') || ''; const targetSlug = href.startsWith('#') ? href.substring(1) : href; link.classList.toggle('active', targetSlug === slug); });
  };
  villeLinks.forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); const href = link.getAttribute('href') || ''; const slug = href.startsWith('#') ? href.substring(1) : href; showVille(slug); }));
  showVille('cotonou');
};

window.FreshyEpicerie = window.FreshyEpicerie || {};
window.FreshyEpicerie.initPage = window.FreshyEpicerie.initPage || function () {
  const filtersModal = document.getElementById('filtersModal');
  const dropdowns = document.querySelectorAll('.filter-dropdown, .filter-dropdown-vente');
  const productCards = document.querySelectorAll('.products-grid .product-card');
  if (!filtersModal && !dropdowns.length && !productCards.length) return;
  if (window.FreshyEpicerie._bound) return;
  window.FreshyEpicerie._bound = true;
  const activeFiltersContainer = document.getElementById('activeFilters');
  const resetFiltersBtn = document.getElementById('resetFilters');
  const modalCloseBtn = document.querySelector('.close-button');
  const filtreTrigger = document.querySelector('.section.filtre');
  const cartModal = document.getElementById('panierModal');
  const cartCloseBtn = document.querySelector('.close-panier-button');
  const resetFiltersButton = document.querySelector('.btn.btn-reinitialiser');
  const normalizeLabel = (value = '') => value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[–—-]/g, '-').replace(/\s+/g, ' ');
  const defaultValues = {}; const selectedLabels = {};
  dropdowns.forEach((dropdown) => { const filterKey = dropdown.dataset.filter; defaultValues[filterKey] = dropdown.dataset.defaultValue; const initialLabel = dropdown.querySelector('.filter-dropdown__option.active'); if (initialLabel) selectedLabels[filterKey] = initialLabel.textContent.trim(); });
  const filterState = { ...defaultValues };
  const dispatchFiltersChange = () => { document.dispatchEvent(new CustomEvent('filters:change', { detail: { ...filterState } })); };
  const resetFilter = (filterKey) => {
    const dropdown = document.querySelector(`.filter-dropdown[data-filter="${filterKey}"], .filter-dropdown-vente[data-filter="${filterKey}"]`);
    if (!dropdown) return;
    filterState[filterKey] = defaultValues[filterKey];
    dropdown.querySelectorAll('.filter-dropdown__option, .filter-dropdown-vente__option').forEach((option) => {
      const isDefault = option.dataset.value === defaultValues[filterKey];
      option.classList.toggle('active', isDefault);
      option.setAttribute('aria-selected', isDefault ? 'true' : 'false');
      if (isDefault) selectedLabels[filterKey] = option.textContent.trim();
    });
    dropdown.classList.remove('open');
    dropdown.querySelector('.filter-dropdown__trigger, .filter-dropdown-vente__trigger').setAttribute('aria-expanded', 'false');
    renderActiveFilters();
    dispatchFiltersChange();
  };
  const renderActiveFilters = () => {
    if (!activeFiltersContainer) return;
    activeFiltersContainer.innerHTML = '';
    Object.entries(filterState).forEach(([key, value]) => {
      if (value !== defaultValues[key]) {
        const chip = document.createElement('span');
        chip.className = 'filter-chip';
        chip.innerHTML = `${selectedLabels[key] || ''}<button type="button" aria-label="Retirer ${selectedLabels[key] || 'filtre'}">&times;</button>`;
        chip.querySelector('button').addEventListener('click', () => resetFilter(key));
        activeFiltersContainer.appendChild(chip);
      }
    });
  };
  const closeAllDropdowns = (exception = null) => {
    dropdowns.forEach((dropdown) => {
      if (dropdown !== exception) {
        dropdown.classList.remove('open');
        dropdown.querySelector('.filter-dropdown__trigger, .filter-dropdown-vente__trigger').setAttribute('aria-expanded', 'false');
      }
    });
  };
  dropdowns.forEach((dropdown) => {
    const trigger = dropdown.querySelector('.filter-dropdown__trigger, .filter-dropdown-vente__trigger');
    trigger?.addEventListener('click', (event) => { event.stopPropagation(); const isOpen = dropdown.classList.contains('open'); closeAllDropdowns(dropdown); dropdown.classList.toggle('open', !isOpen); trigger.setAttribute('aria-expanded', (!isOpen).toString()); });
    dropdown.querySelectorAll('.filter-dropdown__option, .filter-dropdown-vente__option').forEach((option) => {
      option.addEventListener('click', () => {
        dropdown.querySelectorAll('.filter-dropdown__option, .filter-dropdown-vente__option').forEach((opt) => { opt.classList.remove('active'); opt.setAttribute('aria-selected', 'false'); });
        option.classList.add('active'); option.setAttribute('aria-selected', 'true');
        const filterKey = dropdown.dataset.filter; filterState[filterKey] = option.dataset.value; selectedLabels[filterKey] = option.textContent.trim();
        dropdown.classList.remove('open'); trigger?.setAttribute('aria-expanded', 'false');
        renderActiveFilters(); dispatchFiltersChange();
      });
    });
  });
  document.addEventListener('click', () => closeAllDropdowns());
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeAllDropdowns(); });
  resetFiltersBtn?.addEventListener('click', () => { Object.keys(filterState).forEach(resetFilter); });
  renderActiveFilters(); dispatchFiltersChange();
  const normalizeProductText = (value = '') => normalizeLabel(value).replace(/[^a-z0-9\s-]/g, ' ').replace(/\s+/g, ' ').trim();
  const getProductCards = () => Array.from(document.querySelectorAll('.products-grid .product-card'));
  const detectProductCategory = (card) => {
    const categoryAttr = normalizeProductText(card.dataset.category || '');
    if (categoryAttr.includes('creme')) return 'cremes';
    if (categoryAttr.includes('huile')) return 'huiles';
    if (categoryAttr.includes('boisson') || categoryAttr.includes('citron')) return 'boissons';
    const title = normalizeProductText(card.querySelector('h3')?.textContent || '');
    if (title.includes('creme')) return 'cremes';
    if (title.includes('huile')) return 'huiles';
    if (title.includes('citronnade') || title.includes('boisson')) return 'boissons';
    return 'autres';
  };
  const showAllProducts = () => getProductCards().forEach((card) => { card.style.display = ''; });
  const showOnlyNewProducts = () => getProductCards().forEach((card) => { const isNew = card.classList.contains('product-card--new'); card.style.display = isNew ? '' : 'none'; });
  const showByCategory = (category) => {
    getProductCards().forEach((card) => {
      card.style.display = detectProductCategory(card) === category ? '' : 'none';
    });
  };
  const toggleResetButtonVisibility = (shouldShow) => { if (!resetFiltersButton) return; resetFiltersButton.style.display = shouldShow ? 'block' : 'none'; };
  const hasActiveFilters = () => Object.keys(filterState).some((key) => filterState[key] !== defaultValues[key]);
  const hasActiveModalFilter = () => !!document.querySelector('.filtre-options-list .option-item.active');
  const syncResetVisibility = () => toggleResetButtonVisibility(hasActiveFilters() || hasActiveModalFilter());
  const filterHandlers = {
    nouveau: () => { showOnlyNewProducts(); toggleResetButtonVisibility(true); },
    cremes: () => { showByCategory('cremes'); toggleResetButtonVisibility(true); },
    creme: () => { showByCategory('cremes'); toggleResetButtonVisibility(true); },
    huiles: () => { showByCategory('huiles'); toggleResetButtonVisibility(true); },
    huile: () => { showByCategory('huiles'); toggleResetButtonVisibility(true); },
    boissons: () => { showByCategory('boissons'); toggleResetButtonVisibility(true); },
    boisson: () => { showByCategory('boissons'); toggleResetButtonVisibility(true); },
  };
  const normalizeFilterKey = (value = '') => normalizeLabel(value).replace(/[^a-z0-9]/g, '');
  window.FreshyFilters.bindFilters({
    onReset: () => { showAllProducts(); syncResetVisibility(); },
    onApply: (selectedFilter) => {
      const normalizedFilter = normalizeFilterKey(selectedFilter || '');
      let handler = null;

      if (normalizedFilter.includes('nouveau')) handler = filterHandlers.nouveau;
      else if (normalizedFilter.includes('creme') || normalizedFilter.includes('crme') || normalizedFilter.includes('crm')) handler = filterHandlers.creme;
      else if (normalizedFilter.includes('huile')) handler = filterHandlers.huile;
      else if (normalizedFilter.includes('boisson')) handler = filterHandlers.boisson;

      if (typeof handler === 'function') {
        handler();
        const visibleCount = getProductCards().filter((card) => card.style.display !== 'none').length;
        console.debug('[filter] applied:', normalizedFilter, 'visible:', visibleCount);
      } else {
        if (normalizedFilter) console.warn('[filter] filtre non reconnu:', normalizedFilter);
        showAllProducts();
        toggleResetButtonVisibility(false);
      }
      window.toggleFiltersModal?.(false); syncResetVisibility();
    },
  });
  window.toggleFiltersModal = (show = true) => {
    if (!filtersModal) return;
    if (show) {
      syncResetVisibility();
      if (window.innerWidth >= 600) {
        filtersModal.classList.remove('hidden');
        filtersModal.classList.add('active');
      } else {
        filtersModal.classList.remove('hidden');
      }
      filtersModal.removeAttribute('inert');
      filtersModal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    } else {
      const focused = document.activeElement;
      if (focused && filtersModal.contains(focused) && typeof focused.blur === 'function') {
        focused.blur();
      }
      if (window.innerWidth >= 600) {
        filtersModal.classList.remove('active');
        setTimeout(() => {
          if (!filtersModal.classList.contains('active')) {
            filtersModal.classList.add('hidden');
            filtersModal.setAttribute('inert', '');
          }
        }, 400);
      } else {
        filtersModal.classList.add('hidden');
        filtersModal.setAttribute('inert', '');
      }
      filtersModal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      if (filtreTrigger && typeof filtreTrigger.focus === 'function') {
        filtreTrigger.focus();
      }
    }
  };
  modalCloseBtn?.addEventListener('click', () => window.toggleFiltersModal(false));
  filtersModal?.addEventListener('click', (event) => { if (event.target === filtersModal) window.toggleFiltersModal(false); });
  filtreTrigger?.addEventListener('click', () => window.toggleFiltersModal(true));
  document.querySelector('.filtre-options-list')?.addEventListener('click', (event) => { if (event.target.closest('.option-item')) syncResetVisibility(); });
  cartCloseBtn?.addEventListener('click', () => window.toggleCartModal?.(false));
  cartModal?.addEventListener('click', (event) => { if (event.target === cartModal) window.toggleCartModal?.(false); });
  window.FreshyCart.bindCheckoutNavigation({ triggerSelector: '.btn-payer', targetSelector: '.page-container-flex' });
  syncResetVisibility();
};

window.FreshyCheckout = window.FreshyCheckout || {};

window.FreshyCheckout.validateField = window.FreshyCheckout.validateField || function (field, config) {
  if (!field) return { valid: true, message: '' };

  const options = config || {};
  const value = (field.value || '').trim();
  let valid = true;
  let message = '';

  if (options.required && value === '') {
    valid = false;
    message = options.requiredMessage || 'Ce champ est requis.';
  } else if (value !== '' && typeof options.validator === 'function') {
    valid = Boolean(options.validator(value));
    if (!valid) {
      message = options.invalidMessage || 'Valeur invalide.';
    }
  }

  const fieldId = field.id || field.name || 'checkout_field';
  const errorId = `${fieldId}Error`;
  const fieldContainer = field.closest('.dropdown-wrapper') || field;
  const group = field.closest('.form-section-group') || field.parentElement;

  let errorEl = group?.querySelector(`#${errorId}`);
  if (!errorEl && group) {
    errorEl = document.createElement('p');
    errorEl.id = errorId;
    errorEl.className = 'form-field-error';
    fieldContainer.insertAdjacentElement('afterend', errorEl);
  }

  const descriptors = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
  descriptors.add(errorId);

  if (field.id === 'checkoutPhone') {
    descriptors.add('checkoutPhoneHint');
  }

  field.setAttribute('aria-describedby', Array.from(descriptors).join(' '));

  const phoneHint = document.getElementById('checkoutPhoneHint');

  if (!valid) {
    field.classList.add('is-invalid');
    field.setAttribute('aria-invalid', 'true');
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.classList.add('is-visible');
    }
    if (phoneHint && field.id === 'checkoutPhone') {
      phoneHint.classList.remove('form-hint--hidden');
      phoneHint.classList.add('is-visible');
    }
  } else {
    field.classList.remove('is-invalid');
    field.setAttribute('aria-invalid', 'false');
    if (errorEl) {
      errorEl.textContent = '';
      errorEl.classList.remove('is-visible');
    }
    if (phoneHint && field.id === 'checkoutPhone') {
      phoneHint.classList.remove('is-visible');
      phoneHint.classList.add('form-hint--hidden');
    }
  }

  return { valid, message };
};

window.validateField = window.validateField || function (field, config) {
  return window.FreshyCheckout.validateField(field, config);
};

window.FreshyCheckout.handleEmailAutoFill = window.FreshyCheckout.handleEmailAutoFill || function (emailField, recipientField) {
  if (!emailField || !recipientField) return;

  // Avoid duplicate listeners if init runs more than once on the same DOM.
  if (emailField.dataset.autofillBound === 'true') return;
  emailField.dataset.autofillBound = 'true';

  // If the recipient already has a value, treat it as user-provided.
  // If empty, allow auto-fill updates from email.
  recipientField.dataset.userEdited = recipientField.value.trim() ? 'true' : 'false';

  const deriveNameFromEmail = (emailValue) => {
    const localPart = (emailValue || '').split('@')[0] || '';
    if (!localPart) return '';

    const normalized = localPart
      .replace(/[._-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (!normalized) return '';

    return normalized
      .split(' ')
      .filter(Boolean)
      .map((token) => token.charAt(0).toUpperCase() + token.slice(1).toLowerCase())
      .join(' ');
  };

  // User typing in recipient immediately disables auto-fill.
  const markRecipientEdited = (event) => {
    if (!event.isTrusted) return;
    recipientField.dataset.userEdited = 'true';
  };
  recipientField.addEventListener('input', markRecipientEdited);
  recipientField.addEventListener('change', markRecipientEdited);
  recipientField.addEventListener('paste', markRecipientEdited);

  const applyAutoFillFromEmail = (event) => {
    if (!event.isTrusted) return;
    if (recipientField.dataset.userEdited === 'true') return;

    const pretty = deriveNameFromEmail(emailField.value.trim());
    recipientField.value = pretty;
  };

  // Update on every email keystroke while recipient is still auto-managed.
  emailField.addEventListener('input', applyAutoFillFromEmail);
  emailField.addEventListener('change', applyAutoFillFromEmail);
};

window.handleEmailAutoFill = window.handleEmailAutoFill || function (emailField, recipientField) {
  return window.FreshyCheckout.handleEmailAutoFill(emailField, recipientField);
};

window.FreshyCheckout.renderFinalRecap = window.FreshyCheckout.renderFinalRecap || function (payload) {
  const order = payload?.order || {};
  const items = Array.isArray(payload?.items) ? payload.items : [];
  const context = payload?.context || {};

  const finalSection = document.getElementById('checkoutFinalRecap');
  const finalItems = document.getElementById('finalOrderItems');
  const finalNumber = document.getElementById('finalOrderNumber');
  const finalStatus = document.getElementById('finalOrderStatus');
  const finalDelivery = document.getElementById('finalOrderDelivery');
  const finalTotal = document.getElementById('finalOrderTotal');
  const finalLead = document.getElementById('finalRecapLead');
  const formSection = document.getElementById('formulaireLivraison');
  const recapSection = document.getElementById('orderRecapSection');
  const recapToggle = document.querySelector('.recap-toggle');

  if (!finalSection || !finalItems || !finalNumber || !finalStatus || !finalDelivery || !finalTotal) {
    window.FreshyCart.notify?.('warning', 'Récapitulatif final indisponible sur cette page.');
    console.warn('[checkout] final recap nodes missing');
    return;
  }

  const escapeHtml = (value) => String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  finalNumber.textContent = order.order_number || '-';
  finalStatus.textContent = order.status || 'pending';

  const deliveryParts = [context.recipient, context.neighborhood, context.city, context.country].filter(Boolean);
  finalDelivery.textContent = deliveryParts.length ? deliveryParts.join(', ') : '-';

  const totalCents = Number(order.total_cents);
  const totalAmount = Number.isFinite(totalCents)
    ? window.FreshyCart.formatCurrency(Math.floor(totalCents / 100))
    : '0 Fcfa';
  finalTotal.textContent = totalAmount;

  if (finalLead) {
    finalLead.textContent = order.order_number
      ? `Votre commande ${order.order_number} a bien été enregistrée.`
      : 'Votre commande a bien été enregistrée.';
  }

  finalItems.innerHTML = '';
  if (!items.length) {
    const empty = document.createElement('p');
    empty.className = 'panier-empty';
    empty.textContent = 'Aucun article à afficher.';
    finalItems.appendChild(empty);
  } else {
    items.forEach((item) => {
      const qty = Math.max(1, parseInt(item?.quantity, 10) || 1);
      const unitPriceCents = Number(item?.unit_price_cents) || 0;
      const lineTotal = Math.floor((unitPriceCents * qty) / 100);
      const row = document.createElement('div');
      row.className = 'recap-article-item';
      row.innerHTML = `
        <div class="item-visual-info">
          <div class="item-text-details">
            <h3 class="item-title">${escapeHtml(item?.name || 'Produit')}</h3>
            <p class="item-weight">${escapeHtml(item?.variant_label || '')} · Qté ${qty}</p>
          </div>
        </div>
        <span class="item-price">${window.FreshyCart.formatCurrency(lineTotal)}</span>
      `;
      finalItems.appendChild(row);
    });
  }

  formSection?.classList.add('hidden');
  recapSection?.classList.add('hidden');
  recapToggle?.classList.add('hidden');
  finalSection.classList.remove('hidden');

  window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.FreshyCheckout.bindNeighborhoodPreview = window.FreshyCheckout.bindNeighborhoodPreview || function (neighborhoodInput, shippingValueEl) {
  if (!neighborhoodInput || !shippingValueEl) return;
  if (neighborhoodInput.dataset.neighborhoodBound === 'true') return;
  neighborhoodInput.dataset.neighborhoodBound = 'true';

  const fallbackText = '**********';
  const updateShippingPreview = () => {
    const value = (neighborhoodInput.value || '').trim();
    shippingValueEl.textContent = value !== '' ? value : fallbackText;
  };

  // Keep recap shipping line in sync with user input.
  neighborhoodInput.addEventListener('input', updateShippingPreview);
  updateShippingPreview();
};

window.FreshyCheckout.initOrderForm = window.FreshyCheckout.initOrderForm || function () {
  const formSection = document.querySelector('.page-container-flex .form-section');
  const submitBtn = document.querySelector('.btn-payer-form');
  if (!formSection || !submitBtn) return;
  if (window.FreshyCheckout._bound) return;
  window.FreshyCheckout._bound = true;

  const setMessage = (text, type = 'error') => {
    const toastType = type === 'success' ? 'success' : 'error';
    if (typeof window.showToast === 'function') {
      window.showToast(toastType, text, { key: `checkout_${toastType}` });
      return;
    }
    window.FreshyCart?.notify?.(toastType, text, { key: `checkout_${toastType}` });
  };

  const normalize = (value = '') => value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const getFieldByPlaceholder = (needle) => {
    const inputs = Array.from(formSection.querySelectorAll('input'));
    return inputs.find((input) => normalize(input.placeholder || '').includes(needle));
  };

  const phoneInput = formSection.querySelector('#checkoutPhone') || formSection.querySelector('input[type="tel"]');
  const emailInput = formSection.querySelector('#checkoutEmail') || formSection.querySelector('input[type="email"]');
  const countrySelect = formSection.querySelector('#country') || formSection.querySelectorAll('.form-select')[0];
  const citySelect = formSection.querySelector('#city') || formSection.querySelectorAll('.form-select')[1];
  const neighborhoodInput = formSection.querySelector('#checkoutNeighborhood') || getFieldByPlaceholder('quartier');
  const recipientInput = formSection.querySelector('#checkoutRecipient') || getFieldByPlaceholder('recep') || getFieldByPlaceholder('reception') || getFieldByPlaceholder('nom');
  const shippingValueEl = document.getElementById('recapShippingNeighborhood');

  const phoneConfig = {
    required: true,
    requiredMessage: 'Le numéro de téléphone est obligatoire.',
    invalidMessage: 'Entrer un numéro fonctionnel pour appel svp',
    validator: (value) => /^\d{10,}$/.test(value.replace(/\D+/g, '')),
  };

  const emailConfig = {
    required: false,
    invalidMessage: 'Adresse email invalide.',
    validator: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
  };

  const recipientConfig = {
    required: true,
    requiredMessage: 'Le nom du réceptionnaire est obligatoire.',
    invalidMessage: 'Nom du réceptionnaire invalide.',
    validator: (value) => value.length >= 3,
  };

  const countryConfig = {
    required: true,
    requiredMessage: 'Le pays est obligatoire.',
  };

  const cityConfig = {
    required: true,
    requiredMessage: 'La ville est obligatoire.',
  };

  window.handleEmailAutoFill?.(emailInput, recipientInput);
  window.FreshyCheckout.bindNeighborhoodPreview?.(neighborhoodInput, shippingValueEl);

  const bindLiveValidation = (field, config) => {
    if (!field) return;
    const eventName = field.tagName === 'SELECT' ? 'change' : 'input';
    field.addEventListener(eventName, () => {
      window.validateField?.(field, config);
    });
    field.addEventListener('blur', () => {
      window.validateField?.(field, config);
    });
  };

  bindLiveValidation(phoneInput, phoneConfig);
  bindLiveValidation(emailInput, emailConfig);
  bindLiveValidation(recipientInput, recipientConfig);
  bindLiveValidation(countrySelect, countryConfig);
  bindLiveValidation(citySelect, cityConfig);

  submitBtn.addEventListener('click', async (event) => {
    event.preventDefault();

    const phoneResult = window.validateField?.(phoneInput, phoneConfig) || { valid: true };
    const emailResult = window.validateField?.(emailInput, emailConfig) || { valid: true };
    const recipientResult = window.validateField?.(recipientInput, recipientConfig) || { valid: true };
    const countryResult = window.validateField?.(countrySelect, countryConfig) || { valid: true };
    const cityResult = window.validateField?.(citySelect, cityConfig) || { valid: true };

    const isFormValid = [phoneResult, emailResult, recipientResult, countryResult, cityResult].every((r) => r.valid);
    if (!isFormValid) {
      setMessage('Veuillez corriger les champs en erreur.');
      return;
    }

    const phone = phoneInput?.value.trim() || '';
    const email = emailInput?.value.trim() || '';
    const country = countrySelect?.value || '';
    const city = citySelect?.value || '';
    const neighborhood = neighborhoodInput?.value.trim() || '';
    const recipient = recipientInput?.value.trim() || '';

    // Use the same validated checkout guard everywhere (modal + form submit).
    const localItems = window.FreshyCart.getValidItems();
    if (!window.FreshyCart.handleCheckoutVerification?.() || !localItems.length) {
      window.updateCheckoutState?.();
      return;
    }

    submitBtn.disabled = true;
    const originalLabel = submitBtn.textContent;
    submitBtn.textContent = 'Validation en cours...';

    try {
      const syncResult = await window.FreshyCart.syncServerCart(localItems);
      if (!syncResult.ok) {
        if (syncResult.error === 'missing_variants') {
          setMessage('Veuillez sélectionner un format valide pour chaque produit.');
        } else if (syncResult.error === 'Insufficient stock') {
          setMessage('Stock insuffisant pour finaliser la commande.');
        } else {
          setMessage('Impossible de synchroniser le panier.');
        }
        return;
      }

      const confirmedItems = Array.isArray(syncResult?.data?.data) ? syncResult.data.data : [];
      const checkoutContext = { recipient, neighborhood, city, country };

      const response = await window.FreshyCart.postJsonWithCsrf('api/order.php', {
        customer: { full_name: recipient, phone, email },
        address: { country, city, neighborhood, recipient_name: recipient },
      });

      const payload = await response.json();

      if (!response.ok) {
        if (response.status === 409) {
          setMessage('Stock insuffisant. Veuillez ajuster votre panier.');
        } else if (response.status === 422) {
          setMessage(payload.error || 'Certaines informations du checkout sont invalides.');
        } else if (response.status === 404) {
          setMessage('Une variante sélectionnée est indisponible.');
        } else {
          setMessage(payload.error || 'Erreur lors de la commande.');
        }
        await window.FreshyCart.loadServerCart();
        return;
      }

      const orderData = payload?.data || {};
      const paymentResult = await window.FreshyPayment.startFedaPayCheckout(orderData);
      if (!paymentResult.ok) {
        if (paymentResult.error === 'payment_url_missing') {
          setMessage('Paiement initialisé sans URL de redirection.');
        } else if (paymentResult.status === 422) {
          setMessage(paymentResult.detail || paymentResult.error || 'Parametres de paiement invalides.');
        } else if (paymentResult.status === 409) {
          setMessage('Cette commande n’est plus payable.');
        } else if (paymentResult.status === 500) {
          const msg = paymentResult.detail || '';
          const sdkMissing = /vendor\/autoload\.php|sdk missing|composer install/i.test(msg);
          setMessage(sdkMissing
            ? 'Paiement indisponible: SDK FedaPay non deployee sur le serveur.'
            : (msg !== '' ? `Paiement indisponible: ${msg}` : 'Paiement indisponible (erreur serveur).'));
        } else {
          setMessage(paymentResult.error || 'Impossible de lancer le paiement FedaPay.');
        }
        await window.FreshyCart.loadServerCart();
        return;
      }

      const redirectUrl = String(paymentResult.data?.checkout_url || '');
      if (!redirectUrl) {
        setMessage('URL de paiement indisponible.');
        await window.FreshyCart.loadServerCart();
        return;
      }

      setMessage('Redirection vers FedaPay...', 'success');
      window.location.assign(redirectUrl);
      return;
    } catch (error) {
      setMessage('Erreur réseau. Réessayez.');
      await window.FreshyCart.loadServerCart();
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = originalLabel;
    }
  });
};
(function bootstrapFreshyCore() {
  const initModules = async () => {
    await window.FreshyCart.loadServerCart?.();
    window.updateCheckoutState?.();
    window.FreshyCatalog.init?.();
    window.FreshyCart.bindAddToCartHandlers?.();
    window.FreshyCart.initCartPage?.();
    window.FreshyFilters.bindSortOptions?.();
    window.FreshySectionNav.initCremeSection?.();
    window.FreshySalesPoints.initCityTabs?.();
    window.FreshyEpicerie.initPage?.();
    window.FreshyCheckout.initOrderForm?.();
    window.FreshyCart.renderRecapSection?.();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModules, { once: true });
  } else {
    initModules();
  }
})();



















