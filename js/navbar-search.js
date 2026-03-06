(function navbarSearch() {
  const panel = document.getElementById('globalSearchPanel');
  const toggleBtn = document.getElementById('globalSearchToggle');
  const closeBtn = document.getElementById('globalSearchClose');
  const input = document.getElementById('globalSearchInput');
  const list = document.getElementById('globalSearchSuggestions');
  if (!panel || !toggleBtn || !closeBtn || !input || !list) return;

  const normalize = (value) =>
    String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();

  let activeIndex = -1;
  let suggestions = [];
  let productIndex = [];
  let closeTimer = null;

  const staticEntries = [
    { label: 'Accueil', type: 'Page', url: 'index.php' },
    { label: 'Actualite', type: 'Page', url: 'actualite.php' },
    { label: 'Epicerie du terroir', type: 'Page', url: 'epicerie_terroire.php' },
    { label: 'Freshy Palm', type: 'Page', url: 'freshy_palm_page.php' },
    { label: 'Freshy le Fruit Booste', type: 'Page', url: 'freshy_fruit_boosté.php' },
    { label: 'Demander un devis', type: 'Page', url: 'devis.php' },
    { label: 'Contact', type: 'Page', url: 'contact.php' },
    { label: 'Points de vente', type: 'Page', url: 'point_vente.php' },
  ];

  const setOpen = (open) => {
    if (closeTimer) {
      window.clearTimeout(closeTimer);
      closeTimer = null;
    }

    panel.classList.toggle('is-open', open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      window.requestAnimationFrame(() => input.focus());
    } else {
      closeTimer = window.setTimeout(() => {
        input.value = '';
        list.innerHTML = '';
        activeIndex = -1;
      }, 220);
    }
  };

  const render = (rows) => {
    suggestions = rows.slice(0, 8);
    if (!suggestions.length) {
      list.innerHTML = '<li><button type="button" disabled>Aucun resultat</button></li>';
      return;
    }
    list.innerHTML = suggestions.map((item, index) => `
      <li>
        <button type="button" data-index="${index}" aria-selected="${index === activeIndex ? 'true' : 'false'}">
          <span>${item.label}</span>
          <span class="global-search-suggestion-type">${item.type}</span>
        </button>
      </li>
    `).join('');
  };

  const buildSuggestions = (query) => {
    const q = normalize(query);
    if (!q) return staticEntries.slice(0, 6);

    const fromPages = staticEntries.filter((item) => normalize(item.label).includes(q));
    const fromProducts = productIndex
      .filter((item) => normalize(item.label).includes(q))
      .map((item) => ({
        label: item.label,
        type: 'Produit',
        url: `epicerie_terroire.php?search=${encodeURIComponent(item.label)}`,
      }));

    return [...fromPages, ...fromProducts];
  };

  const goTo = (item) => {
    if (!item || !item.url) return;
    window.location.href = item.url;
  };

  const applyLocalProductFilter = (query) => {
    if (!/epicerie_terroire\.php$/i.test(window.location.pathname)) return;
    const q = normalize(query);
    const cards = Array.from(document.querySelectorAll('.product-card'));
    if (!cards.length) return;
    cards.forEach((card) => {
      const title = normalize(card.querySelector('h3')?.textContent || '');
      card.style.display = !q || title.includes(q) ? '' : 'none';
    });
  };

  const onInput = () => {
    activeIndex = -1;
    const rows = buildSuggestions(input.value);
    render(rows);
    applyLocalProductFilter(input.value);
  };

  const loadProducts = async () => {
    try {
      const response = await fetch('api/products.php?minimal=1', { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) return;
      productIndex = Array.isArray(payload?.data)
        ? payload.data.map((p) => ({ label: String(p.name || '') })).filter((p) => p.label !== '')
        : [];
    } catch (e) {
      productIndex = [];
    }
  };

  list.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-index]');
    if (!button) return;
    const index = Number(button.dataset.index);
    if (!Number.isFinite(index)) return;
    goTo(suggestions[index]);
  });

  input.addEventListener('input', onInput);
  input.addEventListener('keydown', (event) => {
    if (!suggestions.length) return;
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      activeIndex = Math.min(suggestions.length - 1, activeIndex + 1);
      render(suggestions);
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      activeIndex = Math.max(0, activeIndex - 1);
      render(suggestions);
      return;
    }
    if (event.key === 'Enter') {
      event.preventDefault();
      if (activeIndex >= 0) goTo(suggestions[activeIndex]);
      else if (suggestions[0]) goTo(suggestions[0]);
    }
  });

  toggleBtn.addEventListener('click', () => {
    const isOpen = panel.classList.contains('is-open');
    setOpen(!isOpen);
    if (!isOpen) render(buildSuggestions(''));
  });

  closeBtn.addEventListener('click', () => setOpen(false));
  panel.addEventListener('click', (event) => {
    if (event.target === panel) setOpen(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });

  const params = new URLSearchParams(window.location.search);
  const preloadSearch = params.get('search');
  if (preloadSearch) {
    applyLocalProductFilter(preloadSearch);
  }

  loadProducts();
})();
