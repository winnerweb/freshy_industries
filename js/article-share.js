(function articleShareModule() {
  const shareRoot = document.querySelector('[data-share-root]');
  if (!shareRoot) return;
  const backLink = document.querySelector('.article-detail-page .back-link');

  const title = (document.getElementById('articleDetailTitle')?.textContent || document.title).trim();
  const articleUrl = window.location.href;
  const image = document.getElementById('articleDetailImage')?.src || '';
  const description = (document.querySelector('#articleDetailIntro p')?.textContent || '').trim().slice(0, 260);

  const setMetaTag = (selector, attrName, attrValue, content) => {
    let node = document.head.querySelector(selector);
    if (!node) {
      node = document.createElement('meta');
      node.setAttribute(attrName, attrValue);
      document.head.appendChild(node);
    }
    node.setAttribute('content', content);
  };

  setMetaTag('meta[property="og:title"]', 'property', 'og:title', title);
  setMetaTag('meta[property="og:description"]', 'property', 'og:description', description);
  setMetaTag('meta[property="og:url"]', 'property', 'og:url', articleUrl);
  if (image) setMetaTag('meta[property="og:image"]', 'property', 'og:image', image);
  setMetaTag('meta[name="twitter:title"]', 'name', 'twitter:title', title);
  setMetaTag('meta[name="twitter:description"]', 'name', 'twitter:description', description);
  if (image) setMetaTag('meta[name="twitter:image"]', 'name', 'twitter:image', image);

  const encode = encodeURIComponent;
  const buildShareUrl = (network) => {
    switch (network) {
      case 'facebook':
        return `https://www.facebook.com/sharer/sharer.php?u=${encode(articleUrl)}`;
      case 'x':
        return `https://twitter.com/intent/tweet?url=${encode(articleUrl)}&text=${encode(title)}`;
      case 'whatsapp':
        return `https://wa.me/?text=${encode(`${title} ${articleUrl}`)}`;
      case 'linkedin':
        return `https://www.linkedin.com/sharing/share-offsite/?url=${encode(articleUrl)}`;
      default:
        return articleUrl;
    }
  };

  const openPopup = (url) => {
    const width = 600;
    const height = 500;
    const left = Math.max(0, Math.round(window.screenX + (window.outerWidth - width) / 2));
    const top = Math.max(0, Math.round(window.screenY + (window.outerHeight - height) / 2));
    const popup = window.open(url, '_blank', `noopener,noreferrer,width=${width},height=${height},left=${left},top=${top}`);
    if (!popup) {
      window.open(url, '_blank', 'noopener,noreferrer');
      if (typeof window.showToast === 'function') {
        window.showToast('warning', 'Popup bloquee. Ouverture dans un nouvel onglet.');
      }
      return;
    }
    popup.focus();
  };

  shareRoot.querySelectorAll('.share-button[data-network]').forEach((button) => {
    const network = String(button.getAttribute('data-network') || '').toLowerCase();
    const url = buildShareUrl(network);
    button.setAttribute('href', url);

    button.addEventListener('click', (event) => {
      event.preventDefault();
      if (network === 'whatsapp' && /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        window.open(url, '_blank', 'noopener,noreferrer');
      } else {
        openPopup(url);
      }
      button.classList.add('is-clicked');
      window.setTimeout(() => button.classList.remove('is-clicked'), 180);
      if (typeof window.showToast === 'function') {
        window.showToast('success', 'Lien pret a etre partage.');
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
})();
