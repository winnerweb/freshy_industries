(function newsletterSubscribe() {
  const form = document.querySelector('.newsletter-form');
  if (!form) return;

  const input = form.querySelector('.newsletter-input');
  const button = form.querySelector('button[type="submit"], button');
  const honeypot = form.querySelector('input[name="website"]');
  if (!input || !button) return;

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `newsletter_${type}` });
      return;
    }
    console.log(message);
  };

  const showSuccessCard = () => {
    const existing = document.getElementById('newsletterSuccessCard');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.className = 'newsletter-success-overlay';
    overlay.id = 'newsletterSuccessCard';
    overlay.innerHTML = `
      <div class="newsletter-success-card" role="dialog" aria-modal="true" aria-label="Inscription newsletter reussie">
        <div class="newsletter-success-burst" aria-hidden="true">
          <span class="newsletter-success-check">✓</span>
        </div>
        <h3>Success!</h3>
        <p>Votre inscription newsletter est confirmee.</p>
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

  const validateEmail = (email) => {
    const normalized = String(email || '').trim().toLowerCase();
    if (!normalized || normalized.length > 255) return '';
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    return regex.test(normalized) ? normalized : '';
  };

  const setLoading = (loading) => {
    button.disabled = loading;
    button.classList.toggle('is-loading', loading);
    if (loading) {
      button.dataset.originalText = button.textContent || "S'inscrire";
      button.textContent = 'Inscription...';
    } else if (button.dataset.originalText) {
      button.textContent = button.dataset.originalText;
    }
  };

  const submit = async (event) => {
    event?.preventDefault();
    const email = validateEmail(input.value);
    if (!email) {
      notify('error', 'Veuillez entrer une adresse email valide.');
      input.focus();
      return;
    }

    setLoading(true);
    try {
      const response = await fetch('api/newsletter_subscribe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-Token': String(window.FRESHY_CSRF_TOKEN || '').trim(),
        },
        body: JSON.stringify({ email, website: String(honeypot?.value || '') }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload?.error || 'Inscription impossible.');
      }
      showSuccessCard();
      input.value = '';
    } catch (error) {
      notify('error', error.message || 'Inscription impossible.');
    } finally {
      setLoading(false);
    }
  };

  form.addEventListener('submit', submit);
})();
