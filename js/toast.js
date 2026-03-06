(function toastModule() {
  if (window.showToast) return;

  const TYPE_DEFAULT_DELAY = {
    success: 3200,
    info: 4200,
    warning: 5200,
    error: 6200,
  };

  const ICONS = {
    success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.55 16.6 5.4 12.45l1.4-1.4 2.75 2.75 7.65-7.65 1.4 1.4Z"/></svg>',
    error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22Zm0 6 1 7h-2Zm0 10.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>',
    warning: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 1 21h22Zm-1 6h2v6h-2Zm1 10a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>',
    info: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 10h2v8h-2Zm1-7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm0 19a10 10 0 1 1 0-20 10 10 0 0 1 0 20Z"/></svg>',
  };

  const ACTIVE = new Map();
  const ORDER = [];
  const LEAVE_MS = 220;
  let root = null;

  function normalizeType(type) {
    const value = (type || 'info').toString().toLowerCase().trim();
    return ['success', 'error', 'warning', 'info'].includes(value) ? value : 'info';
  }

  function getRoot() {
    if (root && document.body.contains(root)) return root;
    root = document.getElementById('toast-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'toast-root';
      root.className = 'toast-stack';
      root.setAttribute('aria-live', 'polite');
      root.setAttribute('aria-atomic', 'false');
      document.body.appendChild(root);
    }
    return root;
  }

  function removeKey(key) {
    ACTIVE.delete(key);
    const idx = ORDER.indexOf(key);
    if (idx >= 0) ORDER.splice(idx, 1);
  }

  function destroyToast(key, item) {
    if (!item) return;
    if (item.timerId) {
      clearTimeout(item.timerId);
      item.timerId = null;
    }
    removeKey(key);
    item.element.remove();
  }

  function dismissToast(key) {
    const item = ACTIVE.get(key);
    if (!item || item.closing) return;
    item.closing = true;
    item.element.classList.add('is-leaving');
    window.setTimeout(() => destroyToast(key, item), LEAVE_MS);
  }

  function setProgress(item, delay) {
    if (!item.progressEl) return;
    item.progressEl.style.transition = 'none';
    item.progressEl.style.width = '100%';
    requestAnimationFrame(() => {
      item.progressEl.style.transition = `width ${delay}ms linear`;
      item.progressEl.style.width = '0%';
    });
  }

  function clearTimer(item) {
    if (item && item.timerId) {
      clearTimeout(item.timerId);
      item.timerId = null;
    }
  }

  function scheduleDismiss(key, item, delay) {
    if (!item || item.sticky) return;
    clearTimer(item);
    item.remaining = delay;
    item.startedAt = Date.now();
    setProgress(item, delay);
    item.timerId = window.setTimeout(() => dismissToast(key), delay);
  }

  function pauseDismiss(item) {
    if (!item || item.sticky || !item.timerId) return;
    const elapsed = Date.now() - item.startedAt;
    item.remaining = Math.max(120, item.remaining - elapsed);
    clearTimer(item);
    if (item.progressEl) {
      const style = window.getComputedStyle(item.progressEl);
      item.progressEl.style.transition = 'none';
      item.progressEl.style.width = style.width;
    }
  }

  function resumeDismiss(key, item) {
    if (!item || item.sticky || item.timerId) return;
    scheduleDismiss(key, item, item.remaining || TYPE_DEFAULT_DELAY[item.type]);
  }

  function trimStack(maxToasts) {
    while (ORDER.length > maxToasts) {
      const oldestKey = ORDER[0];
      dismissToast(oldestKey);
      break;
    }
  }

  function buildToast(type, message, options, key) {
    const el = document.createElement('article');
    el.className = `fx-toast fx-toast--${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

    const iconHtml = options.icon === false
      ? ''
      : `<span class="fx-toast__icon">${typeof options.icon === 'string' ? options.icon : ICONS[type]}</span>`;

    const closeLabel = options.closeLabel || 'Fermer la notification';
    const progressHtml = options.sticky ? '' : '<span class="fx-toast__progress" aria-hidden="true"></span>';

    el.innerHTML = `
      ${iconHtml}
      <p class="fx-toast__message"></p>
      <button type="button" class="fx-toast__close" aria-label="${closeLabel}">×</button>
      ${progressHtml}
    `;

    const messageEl = el.querySelector('.fx-toast__message');
    const closeEl = el.querySelector('.fx-toast__close');
    const progressEl = el.querySelector('.fx-toast__progress');
    if (messageEl) messageEl.textContent = message;

    const item = {
      key,
      type,
      message,
      element: el,
      messageEl,
      progressEl,
      timerId: null,
      sticky: Boolean(options.sticky),
      remaining: 0,
      startedAt: 0,
      closing: false,
    };

    closeEl?.addEventListener('click', () => dismissToast(key));
    el.addEventListener('mouseenter', () => pauseDismiss(item));
    el.addEventListener('mouseleave', () => resumeDismiss(key, item));

    return item;
  }

  function updateExisting(item, type, message, options) {
    item.type = type;
    item.message = message;
    item.sticky = Boolean(options.sticky);
    item.closing = false;
    item.element.className = `fx-toast fx-toast--${type}`;
    item.element.setAttribute('role', type === 'error' ? 'alert' : 'status');
    item.element.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
    if (item.messageEl) item.messageEl.textContent = message;
  }

  function showToast(type, message, options) {
    const normalizedType = normalizeType(type);
    const text = (message || '').toString().trim();
    if (!text) return null;

    const opts = Object.assign(
      {
        delay: TYPE_DEFAULT_DELAY[normalizedType],
        sticky: false,
        maxToasts: 5,
        dedupe: true,
        key: '',
      },
      options || {}
    );

    const key = opts.key || text;
    const container = getRoot();
    const existing = opts.dedupe ? ACTIVE.get(key) : null;

    if (existing) {
      updateExisting(existing, normalizedType, text, opts);
      scheduleDismiss(key, existing, Number(opts.delay) || TYPE_DEFAULT_DELAY[normalizedType]);
      return key;
    }

    const item = buildToast(normalizedType, text, opts, key);
    container.prepend(item.element);
    ACTIVE.set(key, item);
    ORDER.push(key);
    trimStack(Math.max(1, Number(opts.maxToasts) || 5));

    scheduleDismiss(key, item, Number(opts.delay) || TYPE_DEFAULT_DELAY[normalizedType]);
    return key;
  }

  window.showToast = showToast;
  window.dismissToast = dismissToast;
})();

