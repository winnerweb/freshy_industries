(function actualiteShareModule() {
  const detailPage = document.querySelector('.article-detail-page');
  const shareRoot = document.querySelector('[data-share-root]');
  if (!detailPage || !shareRoot) return;

  const titleNode = document.getElementById('articleDetailTitle');
  const metaNode = document.getElementById('articleDetailMeta');
  const introNode = document.querySelector('#articleDetailIntro p');
  const imageNode = document.getElementById('articleDetailImage');
  const bodyNode = document.getElementById('articleDetailBody');
  const backLink = detailPage.querySelector('.back-link');
  const readButtons = Array.from(document.querySelectorAll('.articles-section .btn-read-article'));
  const shareButtons = Array.from(shareRoot.querySelectorAll('.share-button[data-network]'));

  const getArticleState = () => ({
    title: (titleNode?.textContent || '').trim() || document.title,
    description: (introNode?.textContent || '').trim().slice(0, 260),
    image: imageNode?.src || '',
    meta: (metaNode?.textContent || '').trim(),
    slug: new URL(window.location.href).searchParams.get('article') || '',
  });

  const slugify = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 120);

  const isValidPublicUrl = (urlString) => {
    try {
      const url = new URL(urlString, window.location.origin);
      if (!/^https?:$/.test(url.protocol)) return false;
      if (url.pathname.toLowerCase().includes('/admin/')) return false;
      return true;
    } catch (e) {
      return false;
    }
  };

  const getCurrentArticleURL = (slug) => {
    const url = new URL(window.location.href);
    url.searchParams.set('article', slug || 'article');
    if (!isValidPublicUrl(url.toString())) {
      const fallback = new URL(window.location.origin + '/site_test/actualite.php');
      fallback.searchParams.set('article', slug || 'article');
      return fallback.toString();
    }
    return url.toString();
  };

  const setMetaTag = (selector, attrName, attrValue, content) => {
    let node = document.head.querySelector(selector);
    if (!node) {
      node = document.createElement('meta');
      node.setAttribute(attrName, attrValue);
      document.head.appendChild(node);
    }
    node.setAttribute('content', content);
  };

  const updateSocialMeta = (state, articleUrl) => {
    const title = state.title || document.title;
    const description = state.description || 'Actualite Freshy Industries';
    const image = state.image || '';

    document.title = `${title} | Freshy Industries`;
    setMetaTag('meta[property="og:title"]', 'property', 'og:title', title);
    setMetaTag('meta[property="og:description"]', 'property', 'og:description', description);
    setMetaTag('meta[property="og:url"]', 'property', 'og:url', articleUrl);
    setMetaTag('meta[property="og:type"]', 'property', 'og:type', 'article');
    if (image) setMetaTag('meta[property="og:image"]', 'property', 'og:image', image);

    setMetaTag('meta[name="twitter:card"]', 'name', 'twitter:card', 'summary_large_image');
    setMetaTag('meta[name="twitter:title"]', 'name', 'twitter:title', title);
    setMetaTag('meta[name="twitter:description"]', 'name', 'twitter:description', description);
    if (image) setMetaTag('meta[name="twitter:image"]', 'name', 'twitter:image', image);
  };

  const buildShareUrl = (network, articleUrl, title) => {
    const url = encodeURIComponent(articleUrl);
    const text = encodeURIComponent(title);
    switch (network) {
      case 'facebook':
        return `https://www.facebook.com/sharer/sharer.php?u=${url}`;
      case 'x':
        return `https://twitter.com/intent/tweet?url=${url}&text=${text}`;
      case 'whatsapp':
        return `https://wa.me/?text=${encodeURIComponent(`${title} ${articleUrl}`)}`;
      case 'linkedin':
        return `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
      default:
        return articleUrl;
    }
  };

  const openCenteredPopup = (url) => {
    const width = 600;
    const height = 500;
    const left = Math.max(0, Math.round(window.screenX + (window.outerWidth - width) / 2));
    const top = Math.max(0, Math.round(window.screenY + (window.outerHeight - height) / 2));
    const popup = window.open(
      url,
      '_blank',
      `noopener,noreferrer,width=${width},height=${height},left=${left},top=${top}`
    );
    if (!popup) {
      window.open(url, '_blank', 'noopener,noreferrer');
      if (typeof window.showToast === 'function') {
        window.showToast('warning', 'Popup bloquee. Ouverture dans un nouvel onglet.');
      }
      return;
    }
    popup.focus();
  };

  const updateShareLinks = (state) => {
    const slug = state.slug || slugify(state.title);
    const articleUrl = getCurrentArticleURL(slug);
    const safeTitle = state.title || 'Actualite Freshy Industries';

    updateSocialMeta({ ...state, slug }, articleUrl);
    history.replaceState({}, '', articleUrl);

    shareButtons.forEach((button) => {
      const network = String(button.getAttribute('data-network') || '').toLowerCase();
      const href = buildShareUrl(network, articleUrl, safeTitle);
      button.setAttribute('href', href);
      button.dataset.shareUrl = href;
    });
  };

  readButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const data = {
        slug: String(button.dataset.articleSlug || '').trim(),
        title: String(button.dataset.articleTitle || '').trim(),
        meta: String(button.dataset.articleMeta || '').trim(),
        image: String(button.dataset.articleImage || '').trim(),
        intro: String(button.dataset.articleIntro || '').trim(),
        body1: String(button.dataset.articleBody1 || '').trim(),
        body2: String(button.dataset.articleBody2 || '').trim(),
      };
      if (titleNode && data.title) titleNode.textContent = data.title;
      if (metaNode && data.meta) metaNode.textContent = data.meta;
      if (introNode && data.intro) introNode.textContent = data.intro;
      if (bodyNode && (data.body1 || data.body2)) {
        const p1 = bodyNode.querySelector('p:nth-child(1)');
        const p2 = bodyNode.querySelector('p:nth-child(2)');
        if (p1 && data.body1) p1.textContent = data.body1;
        if (p2 && data.body2) p2.textContent = data.body2;
      }
      if (imageNode && data.image) {
        imageNode.setAttribute('src', data.image);
        imageNode.setAttribute('alt', data.title ? `Image de ${data.title}` : "Image de l'article");
      }
      const state = getArticleState();
      state.slug = data.slug || state.slug;
      updateShareLinks(state);
    });
  });

  shareButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      const url = button.dataset.shareUrl || button.getAttribute('href') || '';
      if (!url) return;

      button.classList.add('is-clicked');
      window.setTimeout(() => button.classList.remove('is-clicked'), 180);

      const network = String(button.getAttribute('data-network') || '').toLowerCase();
      if (network === 'whatsapp' && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        window.open(url, '_blank', 'noopener,noreferrer');
      } else {
        openCenteredPopup(url);
      }

      if (typeof window.showToast === 'function') {
        window.showToast('success', 'Lien pret a etre partage.');
      }
    });

    button.addEventListener('keydown', (event) => {
      if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        button.click();
      }
    });
  });

  backLink?.addEventListener('click', () => {
    const cleanUrl = `${window.location.origin}${window.location.pathname}`;
    history.replaceState({}, '', cleanUrl);
  });

  updateShareLinks(getArticleState());
})();
