(function adminNewsletterLive() {
  const subscribersBody = document.getElementById('newsletterSubscribersBody');
  if (!subscribersBody) return;

  const searchInput = document.getElementById('adminTopbarSearch');
  const selectAll = document.getElementById('newsletterSelectAll');
  const bulkBar = document.getElementById('newsletterBulkBar');
  const bulkCount = document.getElementById('newsletterSelectedCount');
  const bulkUnsubscribeBtn = document.getElementById('newsletterBulkUnsubscribeBtn');
  const bulkDeleteBtn = document.getElementById('newsletterBulkDeleteBtn');
  const exportCsvBtn = document.getElementById('newsletterExportCsvBtn');
  const prevPageBtn = document.getElementById('newsletterPrevPageBtn');
  const nextPageBtn = document.getElementById('newsletterNextPageBtn');
  const pageLabel = document.getElementById('newsletterPageLabel');
  const campaignsBody = document.getElementById('newsletterCampaignsBody');
  const campaignForm = document.getElementById('newsletterCampaignForm');
  const saveCampaignBtn = document.getElementById('newsletterSaveCampaignBtn');

  const selectedIds = new Set();
  let subscribers = [];
  let query = '';
  let page = 1;
  const perPage = 12;
  let totalPages = 1;

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `newsletter_${type}_${message}` });
      return;
    }
    console.log(message);
  };

  const apiFetch = async (url, options = {}) => {
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));
    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    const payload = isJson ? await response.json() : {};
    if (!response.ok) {
      throw new Error(payload?.error || 'Operation impossible');
    }
    return payload;
  };

  const syncBulkUi = () => {
    const selectedCount = selectedIds.size;
    if (bulkCount) bulkCount.textContent = String(selectedCount);
    if (bulkBar) {
      bulkBar.classList.toggle('is-visible', selectedCount > 0);
      bulkBar.setAttribute('aria-hidden', selectedCount > 0 ? 'false' : 'true');
    }
    if (bulkUnsubscribeBtn) bulkUnsubscribeBtn.disabled = selectedCount === 0;
    if (bulkDeleteBtn) bulkDeleteBtn.disabled = selectedCount === 0;

    if (selectAll) {
      const totalVisible = subscribers.length;
      selectAll.checked = totalVisible > 0 && selectedCount > 0 && selectedCount === totalVisible;
      selectAll.indeterminate = selectedCount > 0 && selectedCount < totalVisible;
    }
  };

  const renderSubscribers = () => {
    if (!subscribers.length) {
      subscribersBody.innerHTML = '<tr><td colspan="4">Aucun abonne.</td></tr>';
      syncBulkUi();
      return;
    }

    subscribersBody.innerHTML = subscribers.map((item) => {
      const id = Number(item.id || 0);
      const checked = selectedIds.has(id);
      return `
        <tr data-id="${id}" class="${checked ? 'admin-table__row--selected' : ''}">
          <td><input type="checkbox" class="admin-checkbox" data-action="select-row" data-id="${id}" ${checked ? 'checked' : ''}></td>
          <td>${item.email || ''}</td>
          <td><span class="admin-status admin-status--${item.status === 'active' ? 'active' : 'inactive'}">${item.status === 'active' ? 'active' : 'unsubscribed'}</span></td>
          <td>${item.created_at || '-'}</td>
        </tr>
      `;
    }).join('');

    syncBulkUi();
  };

  const renderCampaigns = (campaigns) => {
    if (!campaignsBody) return;
    if (!campaigns.length) {
      campaignsBody.innerHTML = '<tr><td colspan="8">Aucune campagne.</td></tr>';
      return;
    }
    campaignsBody.innerHTML = campaigns.map((c) => `
      <tr data-campaign-id="${Number(c.id || 0)}">
        <td>${Number(c.id || 0)}</td>
        <td>${c.subject || ''}</td>
        <td><span class="admin-status admin-status--${(c.status || 'draft') === 'sent' ? 'active' : 'inactive'}">${c.status || 'draft'}</span></td>
        <td>${Number(c.sent_count || 0)}</td>
        <td>${Number(c.failed_count || 0)}</td>
        <td>${c.created_at || '-'}</td>
        <td>${c.sent_at || '-'}</td>
        <td>
          <button class="admin-btn admin-btn--primary" type="button" data-action="send-campaign" ${String(c.status || '') === 'sending' ? 'disabled' : ''}>
            Envoyer
          </button>
        </td>
      </tr>
    `).join('');
  };

  const loadSubscribers = async () => {
    subscribersBody.innerHTML = '<tr><td colspan="4">Chargement...</td></tr>';
    const params = new URLSearchParams({
      page: String(page),
      per_page: String(perPage),
    });
    if (query) params.set('q', query);
    const payload = await apiFetch(`../api/admin_newsletter_subscribers.php?${params.toString()}`);
    subscribers = Array.isArray(payload?.data) ? payload.data : [];
    const meta = payload?.meta || {};
    totalPages = Math.max(1, Number(meta.total_pages || 1));
    page = Math.max(1, Math.min(Number(meta.page || page), totalPages));
    pageLabel.textContent = `Page ${page} / ${totalPages}`;
    prevPageBtn.disabled = page <= 1;
    nextPageBtn.disabled = page >= totalPages;

    const visibleIds = new Set(subscribers.map((r) => Number(r.id || 0)));
    [...selectedIds].forEach((id) => {
      if (!visibleIds.has(id)) selectedIds.delete(id);
    });

    renderSubscribers();
  };

  const loadCampaigns = async () => {
    const payload = await apiFetch('../api/admin_newsletter_campaigns.php');
    const campaigns = Array.isArray(payload?.data) ? payload.data : [];
    renderCampaigns(campaigns);
  };

  const postSubscribersAction = async (action, ids, status = '') => {
    const payload = { action, ids };
    if (status) payload.status = status;
    await apiFetch('../api/admin_newsletter_subscribers.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.getAdminCsrfToken?.() || '',
      },
      body: JSON.stringify(payload),
    });
  };

  const openConfirmModal = ({ title, message, onConfirm }) => {
    if (!window.AdminModal) return;
    window.AdminModal.open({
      title,
      content: `
        <div class="admin-form-grid">
          <div class="admin-form-group admin-form-group--full">
            <p style="margin:0;color:var(--admin-text);">${message}</p>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--danger" type="button" data-confirm-modal>Confirmer</button>
          </div>
        </div>
      `,
      onOpen: () => {
        document.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        const confirmBtn = document.querySelector('[data-confirm-modal]');
        confirmBtn?.addEventListener('click', async () => {
          if (confirmBtn.disabled) return;
          confirmBtn.disabled = true;
          try {
            await onConfirm();
            window.AdminModal.close();
          } catch (error) {
            notify('error', error.message || 'Operation impossible');
            confirmBtn.disabled = false;
          }
        });
      },
    });
  };

  const bindTabs = () => {
    const tabButtons = Array.from(document.querySelectorAll('#newsletterTabs [data-tab-target]'));
    const panels = Array.from(document.querySelectorAll('.admin-tab-panel'));
    tabButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-tab-target');
        if (!targetId) return;
        tabButtons.forEach((node) => {
          const active = node === btn;
          node.classList.toggle('is-active', active);
          node.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => {
          const active = panel.id === targetId;
          panel.classList.toggle('is-active', active);
          panel.hidden = !active;
        });
      });
    });
  };

  bindTabs();

  subscribersBody.addEventListener('change', (event) => {
    const input = event.target.closest('input[data-action="select-row"]');
    if (!input) return;
    const id = Number(input.dataset.id || 0);
    if (id <= 0) return;
    if (input.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    renderSubscribers();
  });

  selectAll?.addEventListener('change', () => {
    const shouldSelect = Boolean(selectAll.checked);
    subscribers.forEach((row) => {
      const id = Number(row.id || 0);
      if (id <= 0) return;
      if (shouldSelect) selectedIds.add(id);
      else selectedIds.delete(id);
    });
    renderSubscribers();
  });

  bulkUnsubscribeBtn?.addEventListener('click', () => {
    const ids = [...selectedIds];
    if (!ids.length) return;
    openConfirmModal({
      title: 'Confirmer desinscription',
      message: `Desinscrire ${ids.length} abonne(s) selectionne(s).`,
      onConfirm: async () => {
        await postSubscribersAction('update_status_many', ids, 'unsubscribed');
        selectedIds.clear();
        await loadSubscribers();
        notify('success', 'Abonnes desinscrits.');
      },
    });
  });

  bulkDeleteBtn?.addEventListener('click', () => {
    const ids = [...selectedIds];
    if (!ids.length) return;
    openConfirmModal({
      title: 'Confirmer suppression',
      message: `Suppression irreversible de ${ids.length} abonne(s).`,
      onConfirm: async () => {
        await postSubscribersAction('delete_many', ids);
        selectedIds.clear();
        await loadSubscribers();
        notify('success', 'Abonnes supprimes.');
      },
    });
  });

  exportCsvBtn?.addEventListener('click', () => {
    const params = new URLSearchParams();
    params.set('format', 'csv');
    if (query) params.set('q', query);
    window.open(`../api/admin_newsletter_subscribers.php?${params.toString()}`, '_blank', 'noopener');
  });

  prevPageBtn?.addEventListener('click', async () => {
    if (page <= 1) return;
    page -= 1;
    try {
      await loadSubscribers();
    } catch (error) {
      notify('error', error.message || 'Chargement impossible');
    }
  });

  nextPageBtn?.addEventListener('click', async () => {
    if (page >= totalPages) return;
    page += 1;
    try {
      await loadSubscribers();
    } catch (error) {
      notify('error', error.message || 'Chargement impossible');
    }
  });

  let searchTimer = null;
  searchInput?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(async () => {
      query = String(searchInput.value || '').trim();
      page = 1;
      try {
        await loadSubscribers();
      } catch (error) {
        notify('error', error.message || 'Recherche impossible');
      }
    }, 180);
  });

  campaignForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!saveCampaignBtn) return;
    const formData = new FormData(campaignForm);
    const payload = {
      action: 'create',
      subject: String(formData.get('subject') || '').trim(),
      content_html: String(formData.get('content_html') || '').trim(),
      cta_text: String(formData.get('cta_text') || '').trim(),
      cta_url: String(formData.get('cta_url') || '').trim(),
      image_url: String(formData.get('image_url') || '').trim(),
    };
    if (!payload.subject || !payload.content_html) {
      notify('error', 'Sujet et contenu obligatoires.');
      return;
    }
    saveCampaignBtn.disabled = true;
    try {
      await apiFetch('../api/admin_newsletter_campaigns.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getAdminCsrfToken?.() || '',
        },
        body: JSON.stringify(payload),
      });
      notify('success', 'Campagne enregistree.');
      campaignForm.reset();
      await loadCampaigns();
    } catch (error) {
      notify('error', error.message || 'Creation campagne impossible');
    } finally {
      saveCampaignBtn.disabled = false;
    }
  });

  campaignsBody?.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action="send-campaign"]');
    if (!button) return;
    const row = button.closest('tr[data-campaign-id]');
    if (!row) return;
    const campaignId = Number(row.dataset.campaignId || 0);
    if (campaignId <= 0) return;

    button.disabled = true;
    try {
      const result = await apiFetch('../api/admin_newsletter_campaigns.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.getAdminCsrfToken?.() || '',
        },
        body: JSON.stringify({ action: 'send', campaign_id: campaignId }),
      });
      const sent = Number(result?.data?.sent_count || 0);
      const failed = Number(result?.data?.failed_count || 0);
      notify('success', `Campagne envoyee. Succès: ${sent}, echecs: ${failed}.`);
      await loadCampaigns();
    } catch (error) {
      notify('error', error.message || 'Envoi campagne impossible');
      button.disabled = false;
    }
  });

  (async () => {
    try {
      await Promise.all([loadSubscribers(), loadCampaigns()]);
    } catch (error) {
      notify('error', error.message || 'Initialisation newsletter impossible');
    }
  })();
})();

