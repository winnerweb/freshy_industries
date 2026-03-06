(function adminInventoryLive() {
  const tbody = document.getElementById('inventoryTableBody');
  const searchInput = document.getElementById('adminTopbarSearch');
  const pagination = document.getElementById('inventoryPagination');
  const prevBtn = document.getElementById('inventoryPrevBtn');
  const nextBtn = document.getElementById('inventoryNextBtn');
  const pageMeta = document.getElementById('inventoryPageMeta');
  if (!tbody) return;

  const PAGE_SIZE = 12;
  let lastSignature = '';
  let allRows = [];
  let currentPage = 1;

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_inventory_live_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const statusLabel = (status) => {
    if (status === 'out') return 'Rupture';
    if (status === 'low') return 'Stock faible';
    return 'En stock';
  };

  const renderPagination = (totalRows) => {
    const totalPages = Math.max(1, Math.ceil(totalRows / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;

    if (pagination) pagination.hidden = totalRows <= PAGE_SIZE;
    if (pageMeta) pageMeta.textContent = `Page ${currentPage} / ${totalPages}`;
    if (prevBtn) prevBtn.disabled = currentPage <= 1;
    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

    return totalPages;
  };

  const render = (rows) => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const filtered = rows.filter((r) => {
      if (!q) return true;
      const haystack = `${r.product_name || ''} ${r.variant_label || ''} ${r.stock_status || ''} ${r.warehouse || ''}`.toLowerCase();
      return haystack.includes(q);
    });
    const totalPages = renderPagination(filtered.length);
    const start = (currentPage - 1) * PAGE_SIZE;
    const pagedRows = filtered.slice(start, start + PAGE_SIZE);

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="5">Aucune ligne inventaire.</td></tr>';
      return;
    }

    tbody.innerHTML = pagedRows.map((r) => `
      <tr>
        <td>${r.product_name}${r.variant_label ? ` (${r.variant_label})` : ''}</td>
        <td>${Number(r.stock_qty || 0)}</td>
        <td>${r.warehouse || 'Principal'}</td>
        <td><span class="admin-status admin-status--${r.stock_status}">${statusLabel(r.stock_status)}</span></td>
        <td>${String(r.updated_at || '').slice(0, 19).replace('T', ' ')}</td>
      </tr>
    `).join('');

    if (pageMeta && totalPages > 1) {
      const from = start + 1;
      const to = Math.min(start + PAGE_SIZE, filtered.length);
      pageMeta.textContent = `Page ${currentPage} / ${totalPages} • ${from}-${to} sur ${filtered.length}`;
    }
  };

  const load = async (silent) => {
    if (!silent) {
      tbody.innerHTML = '<tr><td colspan="5">Chargement...</td></tr>';
    }
    try {
      const response = await fetch('../api/admin_inventory.php', { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.error || 'Erreur inventaire');
      const rows = Array.isArray(payload?.data) ? payload.data : [];
      allRows = rows;
      const signature = JSON.stringify(rows.map((r) => [r.variant_id, r.stock_qty, r.updated_at, r.stock_status]));
      if (!silent || signature !== lastSignature) {
        render(rows);
        lastSignature = signature;
      }
    } catch (error) {
      if (!silent) {
        tbody.innerHTML = '<tr><td colspan="5">Impossible de charger l\'inventaire.</td></tr>';
      }
      notify('error', error.message || 'Erreur inventaire');
    }
  };

  searchInput?.addEventListener('input', () => {
    currentPage = 1;
    render(allRows);
  });

  prevBtn?.addEventListener('click', () => {
    if (currentPage <= 1) return;
    currentPage -= 1;
    render(allRows);
  });

  nextBtn?.addEventListener('click', () => {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const filteredCount = allRows.filter((r) => {
      if (!q) return true;
      const haystack = `${r.product_name || ''} ${r.variant_label || ''} ${r.stock_status || ''} ${r.warehouse || ''}`.toLowerCase();
      return haystack.includes(q);
    }).length;
    const totalPages = Math.max(1, Math.ceil(filteredCount / PAGE_SIZE));
    if (currentPage >= totalPages) return;
    currentPage += 1;
    render(allRows);
  });

  load(false);
  window.setInterval(() => { load(true); }, 8000);
})();

