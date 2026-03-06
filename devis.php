<?php
declare(strict_types=1);

$allowedProducts = ['palm', 'fruit_booste'];
$productInput = filter_input(INPUT_GET, 'product', FILTER_UNSAFE_RAW);
$selectedProduct = null;

if ($productInput !== null && $productInput !== false && $productInput !== '') {
    $candidate = strtolower(trim((string) $productInput));
    if (!in_array($candidate, $allowedProducts, true)) {
        header('Location: devis.php', true, 302);
        exit();
    }
    $selectedProduct = $candidate;
}

$page_title = 'Demander un Devis';
$additional_css = [];
include 'includes/header.php';
?>
<main style="height: auto; min-height: 1178px;">
    <div class="devis-banner"></div>

    <section class="main-contact-section main-devis-section">
        <div class="contact-form-container">
            <h2 class="form-title">Demander un devis</h2>
            <form id="quoteForm" method="POST" style="border: 1px solid #ddd; padding: 20px;" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($freshyCsrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="product_context" value="<?php echo htmlspecialchars($selectedProduct ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true">

                <div class="form-group">
                    <label for="quoteCustomerName"></label>
                    <input type="text" id="quoteCustomerName" name="customer_name" placeholder="Client / Raison sociale" required minlength="2" maxlength="160">
                    <small class="form-error" data-error-for="customer_name" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <input type="text" id="quotePhone" name="phone" placeholder="Entrez votre numero (Ex : +229 01*********)" required>
                    <small class="form-error" data-error-for="phone" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <label for="quoteEmail"></label>
                    <input type="email" id="quoteEmail" name="email" placeholder="Votre adresse mail" required maxlength="190">
                    <small class="form-error" data-error-for="email" aria-live="polite"></small>
                </div>

                <div class="form-group">
                    <label for="quoteMessage"></label>
                    <textarea id="quoteMessage" name="message" placeholder="Adresse de livraison" rows="5" required minlength="5" maxlength="2000"></textarea>
                    <small class="form-error" data-error-for="message" aria-live="polite"></small>
                </div>

                <div class="form-group quote-products-group">
                    <div class="quote-products-head">
                        <strong>Produits demandes</strong>
                        <button type="button" id="addQuoteProductBtn" class="quote-add-product-btn" aria-label="Ajouter un produit">
                            <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
                            <span>Ajouter</span>
                        </button>
                    </div>
                    <div id="quote-products-container" class="quote-products-container"></div>
                    <small class="form-error" data-error-for="products" aria-live="polite"></small>
                </div>

                <div class="quote-submit-wrap">
                    <button type="submit" id="quoteSubmitBtn" class="btn-send-message-devis">
                        <span class="btn-label">Envoyer <i class="fas fa-arrow-right"></i></span>
                        <span class="btn-spinner" aria-hidden="true" style="display:none;"><i class="fas fa-circle-notch fa-spin"></i></span>
                    </button>
                </div>
            </form>
        </div>

        <div class="contact-details-container">
            <h2 class="details-title">Siege Social</h2>
            <div style="display: flex; gap: 40px;">
                <p class="address-line">Abomey calavi,<br>Tankpe <br> Republique du Benin</p>
                <p class="contact-line">Contact : 0144920824, <br> Email : freshyindustries24@gmail.com </p>
            </div>

            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15843.435002778393!2d2.34865185!3d6.41680505!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x102353a25d266e7b%3A0x6e7e7e7e7e7e7e7e!2sAbomey-Calavi%2C%20B%C3%A9nin!5e0!3m2!1sfr!2sbj!4v1678912345678!5m2!1sfr!2sbj" width="100%" height="545" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>
</main>

<template id="product-row-template">
    <div class="quote-product-row" data-product-row>
        <select class="quote-product-select" required></select>
        <input type="number" class="quote-product-qty" min="1" step="1" placeholder="Quantite" required>
        <button type="button" class="quote-remove-product-btn" aria-label="Supprimer cette ligne">
            <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
        </button>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('quoteForm');
  const container = document.getElementById('quote-products-container');
  const addBtn = document.getElementById('addQuoteProductBtn');
  const submitBtn = document.getElementById('quoteSubmitBtn');
  const template = document.getElementById('product-row-template');
  if (!form || !container || !addBtn || !submitBtn || !template) return;

  const errorNodes = new Map(
    Array.from(form.querySelectorAll('[data-error-for]')).map((node) => [node.getAttribute('data-error-for'), node])
  );

  let productOptions = [];

  const notify = (type, message, key = 'quote_submit') => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key });
      return;
    }
    alert(message);
  };

  const showPremiumSuccessCard = (message) => {
    const existing = document.getElementById('quoteSuccessCard');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.className = 'newsletter-success-overlay';
    overlay.id = 'quoteSuccessCard';
    overlay.innerHTML = `
      <div class="newsletter-success-card" role="dialog" aria-modal="true" aria-label="Demande envoyee avec succes">
        <div class="newsletter-success-burst" aria-hidden="true">
          <span class="newsletter-success-check">✓</span>
        </div>
        <h3>Success!</h3>
        <p>${String(message || 'Votre demande de devis a été envoyée avec succès.').replace(/</g, '&lt;')}</p>
        <button type="button" class="newsletter-success-btn">OK</button>
      </div>
    `;

    const close = () => {
      overlay.classList.remove('is-open');
      window.setTimeout(() => overlay.remove(), 220);
    };

    overlay.querySelector('.newsletter-success-btn')?.addEventListener('click', close);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) close();
    });

    document.body.appendChild(overlay);
    window.requestAnimationFrame(() => overlay.classList.add('is-open'));
  };

  const clearErrors = () => {
    errorNodes.forEach((node, field) => {
      node.textContent = '';
      const input = form.querySelector(`[name="${field}"]`);
      if (input) input.classList.remove('is-invalid');
    });
    container.querySelectorAll('.quote-product-select, .quote-product-qty').forEach((el) => el.classList.remove('is-invalid'));
  };

  const setFieldError = (field, message) => {
    const node = errorNodes.get(field);
    if (node) node.textContent = String(message || '');
    const input = form.querySelector(`[name="${field}"]`);
    if (input) input.classList.add('is-invalid');
  };

  const setLoading = (loading) => {
    const label = submitBtn.querySelector('.btn-label');
    const spinner = submitBtn.querySelector('.btn-spinner');
    submitBtn.disabled = loading;
    if (label) label.style.display = loading ? 'none' : '';
    if (spinner) spinner.style.display = loading ? 'inline-flex' : 'none';
  };

  const buildSelectOptionsHtml = () => {
    const base = '<option value="">Selectionnez le produit</option>';
    const rows = productOptions
      .map((p) => `<option value="${Number(p.id)}">${String(p.name || '').replace(/</g, '&lt;')}</option>`)
      .join('');
    return base + rows;
  };

  const reindexRows = () => {
    const rows = container.querySelectorAll('[data-product-row]');
    rows.forEach((row, index) => {
      const select = row.querySelector('.quote-product-select');
      const qty = row.querySelector('.quote-product-qty');
      if (select) select.setAttribute('name', `products[${index}][product_id]`);
      if (qty) qty.setAttribute('name', `products[${index}][quantity]`);
    });
  };

  const addProductRow = (focus = true) => {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector('[data-product-row]');
    const select = fragment.querySelector('.quote-product-select');
    const qty = fragment.querySelector('.quote-product-qty');
    const removeBtn = fragment.querySelector('.quote-remove-product-btn');

    if (!row || !select || !qty || !removeBtn) return;

    select.innerHTML = buildSelectOptionsHtml();
    if (productOptions.length === 0) {
      select.disabled = true;
      qty.disabled = true;
      removeBtn.disabled = true;
    }

    removeBtn.addEventListener('click', () => {
      row.classList.add('is-removing');
      window.setTimeout(() => {
        row.remove();
        reindexRows();
        if (!container.querySelector('[data-product-row]')) addProductRow(false);
      }, 160);
    });

    container.appendChild(fragment);
    reindexRows();

    if (focus) {
      const addedRow = container.querySelectorAll('[data-product-row]');
      const last = addedRow[addedRow.length - 1];
      const lastSelect = last?.querySelector('.quote-product-select');
      if (lastSelect) lastSelect.focus();
    }
  };

  const fetchProducts = async () => {
    const response = await fetch('api/products.php?minimal=1', { headers: { Accept: 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.error || 'Chargement produits impossible');
    productOptions = Array.isArray(payload?.data) ? payload.data : [];
  };

  const applyServerErrors = (errors) => {
    if (!errors || typeof errors !== 'object') return;
    Object.entries(errors).forEach(([key, message]) => {
      if (key === 'products' || key.startsWith('products.')) {
        const node = errorNodes.get('products');
        if (node && !node.textContent) node.textContent = String(message || 'Lignes produits invalides.');
        return;
      }
      setFieldError(key, String(message || 'Valeur invalide'));
    });
  };

  addBtn.addEventListener('click', () => addProductRow(true));

  form.addEventListener('input', (event) => {
    const field = event.target?.getAttribute?.('name');
    if (field && errorNodes.has(field)) {
      const node = errorNodes.get(field);
      if (node) node.textContent = '';
      event.target.classList.remove('is-invalid');
    }
    if (field && field.startsWith('products[')) {
      const node = errorNodes.get('products');
      if (node) node.textContent = '';
      event.target.classList.remove('is-invalid');
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors();

    const rows = Array.from(container.querySelectorAll('[data-product-row]'));
    const validRows = rows.filter((row) => {
      const select = row.querySelector('.quote-product-select');
      const qty = row.querySelector('.quote-product-qty');
      const productId = Number(select?.value || 0);
      const quantity = Number(qty?.value || 0);
      return productId > 0 && quantity > 0;
    });

    if (validRows.length === 0) {
      const node = errorNodes.get('products');
      if (node) node.textContent = 'Ajoutez au moins un produit valide.';
      notify('error', 'Ajoutez au moins un produit valide.', 'quote_products_required');
      return;
    }

    setLoading(true);
    try {
      const formData = new FormData(form);
      const response = await fetch('handlers/devis_submit.php', {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: formData,
      });
      const payload = await response.json();

      if (!response.ok || payload?.success !== true) {
        applyServerErrors(payload?.errors);
        const firstFieldError = payload?.errors && typeof payload.errors === 'object'
          ? Object.values(payload.errors)[0]
          : '';
        notify('error', firstFieldError || payload?.message || 'Envoi du devis impossible.', 'quote_submit_error');
        return;
      }

      form.reset();
      container.innerHTML = '';
      addProductRow(false);
      showPremiumSuccessCard(payload?.message || 'Votre demande de devis a ete envoyee avec succes.');
    } catch (error) {
      notify('error', 'Erreur reseau. Veuillez reessayer.', 'quote_submit_network');
    } finally {
      setLoading(false);
    }
  });

  (async () => {
    try {
      await fetchProducts();
    } catch (error) {
      notify('error', 'Impossible de charger les produits actifs.', 'quote_products_load_error');
    } finally {
      addProductRow(false);
    }
  })();
});
</script>

<?php include 'includes/footer.php'; ?>
