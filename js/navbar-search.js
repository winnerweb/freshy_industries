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
      .replace(/\s+/g, ' ')
      .trim();

  const tokenize = (value) => normalize(value).split(' ').filter(Boolean);

  const dedupeEntries = (rows) => {
    const seen = new Set();
    const out = [];
    rows.forEach((row) => {
      const label = String(row?.label || '').trim();
      const url = String(row?.url || '').trim();
      if (!label || !url) return;
      const key = `${normalize(label)}|${url}`;
      if (seen.has(key)) return;
      seen.add(key);
      out.push({
        label,
        type: String(row?.type || 'Resultat'),
        url,
        keywords: String(row?.keywords || ''),
      });
    });
    return out;
  };

  const extractNavEntries = () => {
    const anchors = Array.from(document.querySelectorAll('.nav-links a[href], .sidebar-nav a[href]'));
    return dedupeEntries(
      anchors.map((a) => {
        const href = a.getAttribute('href') || '';
        const label = a.textContent || '';
        return { label, type: 'Page', url: href };
      })
    );
  };

  const extractSectionEntries = () => {
    const containers = Array.from(document.querySelectorAll('main, section, article, .container'));
    const headings = containers.flatMap((c) => Array.from(c.querySelectorAll('h1, h2, h3')));
    return dedupeEntries(
      headings.map((h) => {
        const text = String(h.textContent || '').trim();
        if (!text) return null;
        if (!h.id) {
          h.id = normalize(text).replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90);
        }
        return {
          label: text,
          type: 'Section',
          url: `${window.location.pathname}${window.location.search}#${h.id}`,
        };
      }).filter(Boolean)
    );
  };

  let remoteEntries = [];
  let localEntries = dedupeEntries([...extractNavEntries(), ...extractSectionEntries()]);
  let allEntries = dedupeEntries([...localEntries]);
  let activeIndex = -1;
  let suggestions = [];
  let closeTimer = null;

  const scoreEntry = (entry, tokens) => {
    const label = normalize(entry.label);
    const type = normalize(entry.type);
    const keywords = normalize(entry.keywords);
    const haystack = `${label} ${type} ${keywords}`;

    const allPresent = tokens.every((t) => haystack.includes(t));
    if (!allPresent) return -1;

    let score = 0;
    tokens.forEach((t) => {
      if (label.startsWith(t)) score += 9;
      else if (label.includes(t)) score += 6;
      else if (keywords.includes(t)) score += 3;
      else score += 1;
    });
    if (entry.type === 'Page') score += 1;
    if (entry.type === 'Actualite') score += 2;
    return score;
  };

  const buildSuggestions = (query) => {
    const tokens = tokenize(query);
    if (!tokens.length) {
      return allEntries.slice(0, 8);
    }
    return allEntries
      .map((entry) => ({ entry, score: scoreEntry(entry, tokens) }))
      .filter((x) => x.score >= 0)
      .sort((a, b) => b.score - a.score || a.entry.label.localeCompare(b.entry.label, 'fr'))
      .map((x) => x.entry)
      .slice(0, 10);
  };

  const render = (rows) => {
    suggestions = rows;
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

  const loadRemoteIndex = async () => {
    try {
      const response = await fetch('api/search_index.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) return;
      remoteEntries = Array.isArray(payload?.data) ? dedupeEntries(payload.data) : [];
      localEntries = dedupeEntries([...extractNavEntries(), ...extractSectionEntries()]);
      allEntries = dedupeEntries([...localEntries, ...remoteEntries]);
      if (panel.classList.contains('is-open')) {
        render(buildSuggestions(input.value));
      }
    } catch (e) {
      localEntries = dedupeEntries([...extractNavEntries(), ...extractSectionEntries()]);
      allEntries = dedupeEntries([...localEntries, ...remoteEntries]);
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

  loadRemoteIndex();
})();

