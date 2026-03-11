(function adminArticlesLive() {
  const tbody = document.getElementById('articlesTableBody');
  const addBtn = document.getElementById('addArticleBtn');
  const searchInput = document.getElementById('adminTopbarSearch');
  if (!tbody) return;

  let articles = [];

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `articles_${type}` });
      return;
    }
    console.log(message);
  };

  const apiFetch = async (url, options = {}) => {
    const headers = Object.assign({ Accept: 'application/json' }, options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload?.error || 'Operation impossible');
    }
    return payload;
  };

  const render = () => {
    const q = String(searchInput?.value || '').trim().toLowerCase();
    const filtered = articles.filter((a) => {
      if (!q) return true;
      const text = `${a.title || ''} ${a.slug || ''} ${a.author || ''} ${a.status || ''}`.toLowerCase();
      return text.includes(q);
    });

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="7">Aucun article.</td></tr>';
      return;
    }

    tbody.innerHTML = filtered.map((a) => {
      const media = a.video_url ? 'video' : (a.image_url ? 'image' : '-');
      return `
        <tr data-article-id="${Number(a.id || 0)}">
          <td>${a.title || ''}</td>
          <td>${a.slug || ''}</td>
          <td>${a.author || ''}</td>
          <td><span class="admin-status admin-status--${a.status === 'published' ? 'active' : 'inactive'}">${a.status || 'draft'}</span></td>
          <td>${a.published_at || '-'}</td>
          <td>${media}</td>
          <td>
            <div class="admin-row-actions">
              <button class="admin-icon-btn admin-icon-btn--edit" type="button" data-action="edit" title="Modifier"><i class="fa-regular fa-pen-to-square"></i></button>
              <button class="admin-icon-btn admin-icon-btn--delete" type="button" data-action="delete" title="Supprimer"><i class="fa-regular fa-trash-can"></i></button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  };

  const load = async () => {
    tbody.innerHTML = '<tr><td colspan="7">Chargement...</td></tr>';
    const payload = await apiFetch('../api/admin_articles.php');
    articles = Array.isArray(payload?.data) ? payload.data : [];
    render();
  };

  const postAction = async (payload) => apiFetch('../api/admin_articles.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.getAdminCsrfToken?.() || '',
    },
    body: JSON.stringify(payload),
  });

  const uploadMedia = async (file) => {
    const fd = new FormData();
    fd.append('media', file);
    const response = await fetch('../api/admin_upload_article_media.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': window.getAdminCsrfToken?.() || '' },
      body: fd,
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload?.error || 'Upload media impossible');
    return payload?.data || {};
  };

  const slugifyPreview = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9\-]+/g, '-')
    .replace(/\-+/g, '-')
    .replace(/^\-+|\-+$/g, '')
    .slice(0, 180);

  const openArticleModal = (article = null) => {
    if (!window.AdminModal) return;
    const isEdit = Boolean(article?.id);
    window.AdminModal.open({
      title: isEdit ? 'Modifier article' : 'Ajouter article',
      content: `
        <form id="articleModalForm" class="admin-form-grid" novalidate>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleTitle">Titre</label>
            <input class="admin-input" id="articleTitle" name="title" type="text" required value="${article?.title || ''}">
            <small id="articleSlugPreview" style="color:var(--admin-muted);font-size:12px;">Slug: ${article?.slug || slugifyPreview(article?.title || '') || '-'}</small>
          </div>
          ${isEdit ? `
          <div class="admin-form-group">
            <label for="articleSlug">Slug</label>
            <input class="admin-input" id="articleSlug" name="slug" type="text" required value="${article?.slug || ''}">
          </div>
          ` : `
          <div class="admin-form-group">
            <label>Slug</label>
            <input class="admin-input" type="text" value="Genere automatiquement depuis le titre" disabled>
          </div>
          `}
          <div class="admin-form-group">
            <label for="articleAuthor">Auteur</label>
            <input class="admin-input" id="articleAuthor" name="author" type="text" value="${article?.author || 'Freshy Industries'}">
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleExcerpt">Resume</label>
            <textarea class="admin-textarea" id="articleExcerpt" name="excerpt" required>${article?.excerpt || ''}</textarea>
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleIntro">Introduction</label>
            <textarea class="admin-textarea" id="articleIntro" name="intro" required>${article?.intro || ''}</textarea>
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleBody1">Contenu bloc 1</label>
            <textarea class="admin-textarea" id="articleBody1" name="body_1" required>${article?.body_1 || ''}</textarea>
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleBody2">Contenu bloc 2</label>
            <textarea class="admin-textarea" id="articleBody2" name="body_2">${article?.body_2 || ''}</textarea>
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleImageUrl">Image URL</label>
            <input class="admin-input" id="articleImageUrl" name="image_url" type="text" value="${article?.image_url || ''}">
            <input class="admin-input" id="articleImageFile" type="file" accept="image/jpeg,image/png,image/webp">
          </div>
          <div class="admin-form-group admin-form-group--full">
            <label for="articleVideoUrl">Video URL</label>
            <input class="admin-input" id="articleVideoUrl" name="video_url" type="text" value="${article?.video_url || ''}">
            <input class="admin-input" id="articleVideoFile" type="file" accept="video/mp4,video/webm,video/ogg,video/quicktime">
          </div>
          <div class="admin-form-group">
            <label for="articleStatus">Statut</label>
            <select class="admin-select" id="articleStatus" name="status">
              <option value="draft" ${article?.status === 'draft' ? 'selected' : ''}>draft</option>
              <option value="published" ${article?.status === 'published' ? 'selected' : ''}>published</option>
            </select>
          </div>
          <div class="admin-form-group">
            <label for="articlePublishedAt">Date publication</label>
            <input class="admin-input" id="articlePublishedAt" name="published_at" type="datetime-local" value="${article?.published_at ? String(article.published_at).replace(' ', 'T').slice(0,16) : ''}">
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--primary" type="submit">${isEdit ? 'Mettre a jour' : 'Enregistrer'}</button>
          </div>
        </form>
      `,
      onOpen: () => {
        const form = document.getElementById('articleModalForm');
        if (!form) return;
        form.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());

        const imageFileInput = document.getElementById('articleImageFile');
        const videoFileInput = document.getElementById('articleVideoFile');
        const imageUrlInput = document.getElementById('articleImageUrl');
        const videoUrlInput = document.getElementById('articleVideoUrl');
        const titleInput = document.getElementById('articleTitle');
        const slugInput = document.getElementById('articleSlug');
        const slugPreview = document.getElementById('articleSlugPreview');

        const refreshSlugPreview = () => {
          if (!slugPreview) return;
          const value = slugInput
            ? String(slugInput.value || '').trim()
            : slugifyPreview(String(titleInput?.value || ''));
          slugPreview.textContent = `Slug: ${value || '-'}`;
        };
        titleInput?.addEventListener('input', refreshSlugPreview);
        slugInput?.addEventListener('input', refreshSlugPreview);
        refreshSlugPreview();

        imageFileInput?.addEventListener('change', async () => {
          const file = imageFileInput.files?.[0];
          if (!file) return;
          try {
            const data = await uploadMedia(file);
            if (data?.media_type !== 'image') throw new Error('Le fichier choisi n est pas une image valide.');
            imageUrlInput.value = data.media_url || '';
            notify('success', 'Image uploadee.');
          } catch (error) {
            notify('error', error.message || 'Upload image impossible');
          }
        });

        videoFileInput?.addEventListener('change', async () => {
          const file = videoFileInput.files?.[0];
          if (!file) return;
          try {
            const data = await uploadMedia(file);
            if (data?.media_type !== 'video') throw new Error('Le fichier choisi n est pas une video valide.');
            videoUrlInput.value = data.media_url || '';
            notify('success', 'Video uploadee.');
          } catch (error) {
            notify('error', error.message || 'Upload video impossible');
          }
        });

        form.addEventListener('submit', async (event) => {
          event.preventDefault();
          const fd = new FormData(form);
          const payload = {
            action: isEdit ? 'update' : 'create',
            id: Number(article?.id || 0),
            title: String(fd.get('title') || '').trim(),
            slug: String(fd.get('slug') || '').trim(),
            author: String(fd.get('author') || '').trim(),
            excerpt: String(fd.get('excerpt') || '').trim(),
            intro: String(fd.get('intro') || '').trim(),
            body_1: String(fd.get('body_1') || '').trim(),
            body_2: String(fd.get('body_2') || '').trim(),
            image_url: String(fd.get('image_url') || '').trim(),
            video_url: String(fd.get('video_url') || '').trim(),
            status: String(fd.get('status') || 'draft').trim(),
            published_at: String(fd.get('published_at') || '').trim().replace('T', ' '),
          };
          if (!payload.title || !payload.excerpt || !payload.intro || !payload.body_1) {
            notify('error', 'Veuillez remplir tous les champs obligatoires.');
            return;
          }
          try {
            await postAction(payload);
            notify('success', isEdit ? 'Article mis a jour.' : 'Article cree.');
            window.AdminModal.close();
            await load();
          } catch (error) {
            notify('error', error.message || 'Enregistrement impossible');
          }
        });
      },
    });
  };

  const openDeleteArticleModal = (article, onConfirm) => {
    if (!window.AdminModal || !article) return;
    window.AdminModal.open({
      title: 'Confirmer suppression',
      content: `
        <div class="admin-form-grid">
          <div class="admin-form-group admin-form-group--full">
            <p style="margin:0;color:var(--admin-text);">
              Cette action est irreversible. Vous allez supprimer l'article :
              <strong>${String(article.title || '').replace(/</g, '&lt;')}</strong>
            </p>
          </div>
          <div class="admin-form-actions">
            <button class="admin-btn" type="button" data-close-modal>Annuler</button>
            <button class="admin-btn admin-btn--danger" type="button" data-confirm-delete>Supprimer</button>
          </div>
        </div>
      `,
      onOpen: () => {
        document.querySelector('[data-close-modal]')?.addEventListener('click', () => window.AdminModal.close());
        const confirmBtn = document.querySelector('[data-confirm-delete]');
        confirmBtn?.addEventListener('click', async () => {
          if (confirmBtn.disabled) return;
          confirmBtn.disabled = true;
          try {
            await onConfirm();
            window.AdminModal.close();
          } catch (error) {
            notify('error', error.message || 'Suppression impossible');
            confirmBtn.disabled = false;
          }
        });
      },
    });
  };

  tbody.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const row = button.closest('tr[data-article-id]');
    if (!row) return;
    const id = Number(row.dataset.articleId || 0);
    const article = articles.find((a) => Number(a.id || 0) === id);
    if (!article) return;

    if (button.dataset.action === 'edit') {
      openArticleModal(article);
      return;
    }

    if (button.dataset.action === 'delete') {
      openDeleteArticleModal(article, async () => {
        await postAction({ action: 'delete', id });
        notify('success', 'Article supprime.');
        await load();
      });
    }
  });

  addBtn?.addEventListener('click', () => openArticleModal(null));
  searchInput?.addEventListener('input', render);
  load().catch((error) => notify('error', error.message || 'Chargement articles impossible'));
})();
