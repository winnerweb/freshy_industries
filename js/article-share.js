(function articleShareModule() {
  const shareRoot = document.querySelector('[data-share-root]');
  if (!shareRoot) return;

  const shareButtons = Array.from(shareRoot.querySelectorAll('.share-button[data-network]'));
  if (!shareButtons.length) return;

  const backLink = document.querySelector('.article-detail-page .back-link');
  const titleNode = document.getElementById('articleDetailTitle');
  const introNode = document.querySelector('#articleDetailIntro p');
  const imageNode = document.getElementById('articleDetailImage');

  const showFeedback = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `article_share_${type}` });
    }
  };

  const setMetaTag = (selector, attrName, attrValue, content) => {
    if (!content) return;
    let node = document.head.querySelector(selector);
    if (!node) {
      node = document.createElement('meta');
      node.setAttribute(attrName, attrValue);
      document.head.appendChild(node);
    }
    node.setAttribute('content', content);
  };

  const normalizePublicUrl = (candidate) => {
    try {
      const url = new URL(candidate || window.location.href, window.location.origin);
      if (!/^https?:$/.test(url.protocol)) return window.location.href;
      if (!url.searchParams.get('article')) {
        const currentSlug = new URL(window.location.href).searchParams.get('article');
        if (currentSlug) url.searchParams.set('article', currentSlug);
      }
      return url.toString();
    } catch (e) {
      return window.location.href;
    }
  };

  const getShareState = () => {
    const title = (shareRoot.dataset.shareTitle || titleNode?.textContent || document.title || '').trim();
    const description = (shareRoot.dataset.shareDescription || introNode?.textContent || '').trim().slice(0, 260);
    const image = (shareRoot.dataset.shareImage || imageNode?.src || '').trim();
    const url = normalizePublicUrl(shareRoot.dataset.shareUrl || window.location.href);

    return {
      title: title || document.title,
      description,
      image,
      url,
    };
  };

  const buildShareUrl = (network, state) => {
    const encodedUrl = encodeURIComponent(state.url);
    const encodedTitle = encodeURIComponent(state.title);
    switch (network) {
      case 'facebook':
        return `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
      case 'x':
        return `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`;
      case 'whatsapp':
        return `https://wa.me/?text=${encodeURIComponent(`${state.title} ${state.url}`)}`;
      case 'linkedin':
        return `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`;
      default:
        return state.url;
    }
  };

  const openCenteredPopup = (url) => {
    const width = Math.min(640, Math.max(420, Math.round(window.innerWidth * 0.88)));
    const height = Math.min(560, Math.max(460, Math.round(window.innerHeight * 0.86)));
    const left = Math.max(0, Math.round(window.screenX + (window.outerWidth - width) / 2));
    const top = Math.max(0, Math.round(window.screenY + (window.outerHeight - height) / 2));
    const popup = window.open(
      url,
      '_blank',
      `noopener,noreferrer,width=${width},height=${height},left=${left},top=${top}`
    );
    if (popup && !popup.closed) {
      popup.focus();
      return true;
    }
    return false;
  };

  const refreshShareLinks = () => {
    const state = getShareState();

    setMetaTag('meta[property="og:title"]', 'property', 'og:title', state.title);
    setMetaTag('meta[property="og:description"]', 'property', 'og:description', state.description);
    setMetaTag('meta[property="og:url"]', 'property', 'og:url', state.url);
    setMetaTag('meta[property="og:type"]', 'property', 'og:type', 'article');
    setMetaTag('meta[property="og:image"]', 'property', 'og:image', state.image);
    setMetaTag('meta[name="twitter:card"]', 'name', 'twitter:card', 'summary_large_image');
    setMetaTag('meta[name="twitter:title"]', 'name', 'twitter:title', state.title);
    setMetaTag('meta[name="twitter:description"]', 'name', 'twitter:description', state.description);
    setMetaTag('meta[name="twitter:image"]', 'name', 'twitter:image', state.image);

    shareButtons.forEach((button) => {
      const network = String(button.dataset.network || '').toLowerCase();
      const url = buildShareUrl(network, state);
      button.setAttribute('href', url);
      button.dataset.shareUrl = url;
      button.dataset.shareStateUrl = state.url;
      button.dataset.shareStateTitle = state.title;
      button.dataset.shareStateDescription = state.description;
    });
  };

  const handleShareClick = async (button, event) => {
    event.preventDefault();
    if (!button || button.dataset.sharing === '1') return;

    const network = String(button.dataset.network || '').toLowerCase();
    const href = String(button.dataset.shareUrl || button.getAttribute('href') || '');
    const stateUrl = String(button.dataset.shareStateUrl || window.location.href);
    const stateTitle = String(button.dataset.shareStateTitle || document.title);
    const stateDescription = String(button.dataset.shareStateDescription || '');

    if (!href) return;
    button.dataset.sharing = '1';
    button.classList.add('is-sharing');

    try {
      const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
      if (network === 'whatsapp' && isMobile) {
        window.open(href, '_blank', 'noopener,noreferrer');
      } else if (network === 'x' && isMobile && navigator.share) {
        await navigator.share({
          title: stateTitle,
          text: stateDescription || stateTitle,
          url: stateUrl,
        });
      } else {
        const opened = openCenteredPopup(href);
        if (!opened) {
          try {
            await navigator.clipboard.writeText(stateUrl);
            showFeedback('success', 'Lien copie. Vous pouvez le partager manuellement.');
          } catch (e) {
            window.open(href, '_blank', 'noopener,noreferrer');
            showFeedback('warning', 'Popup bloquee. Ouverture dans un nouvel onglet.');
          }
        }
      }
      button.classList.add('is-clicked');
      showFeedback('success', 'Partage pret.');
    } catch (error) {
      showFeedback('error', 'Partage annule.');
    } finally {
      window.setTimeout(() => {
        button.classList.remove('is-clicked');
        button.classList.remove('is-sharing');
        delete button.dataset.sharing;
      }, 220);
    }
  };

  shareButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      handleShareClick(button, event);
    });
    button.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        button.click();
      }
    });
  });

  backLink?.addEventListener('click', (event) => {
    event.preventDefault();
    const referrer = String(document.referrer || '');
    const sameHostReferrer = referrer !== '' && (() => {
      try {
        return new URL(referrer).host === window.location.host;
      } catch (e) {
        return false;
      }
    })();

    if (window.history.length > 1 && sameHostReferrer) {
      window.history.back();
      return;
    }
    window.location.href = 'actualite.php';
  });

  refreshShareLinks();
})();
