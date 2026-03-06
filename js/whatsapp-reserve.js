(function whatsappReserveModule() {
  const SALES_WHATSAPP_PHONE = '2290144920824';
  const RESERVE_MESSAGE = 'Freshy Industries je souhaite reserver une commande';

  function sanitizePhone(rawPhone) {
    return String(rawPhone || '').replace(/\D+/g, '');
  }

  function sanitizeMessage(rawMessage) {
    // Keep a conservative printable set before URL encoding.
    return String(rawMessage || '')
      .replace(/[^\w\sÀ-ÿ,.'!?-]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function validatePhone(phoneDigits) {
    // E.164-compatible length guard, numeric only.
    return /^\d{8,15}$/.test(phoneDigits);
  }

  function generateWhatsAppLink(phone, message) {
    const phoneDigits = sanitizePhone(phone);
    if (!validatePhone(phoneDigits)) {
      throw new Error('Invalid WhatsApp phone format');
    }

    const safeMessage = sanitizeMessage(message);
    const encodedMessage = encodeURIComponent(safeMessage);
    return `https://wa.me/${phoneDigits}?text=${encodedMessage}`;
  }

  function openSecureTab(url) {
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  }

  function initReserveButton() {
    const reserveBtn = document.getElementById('reserveOrderBtn');
    if (!reserveBtn) return;

    const lockedPhone = sanitizePhone(SALES_WHATSAPP_PHONE);
    if (!validatePhone(lockedPhone)) {
      console.error('[whatsapp] Invalid configured phone');
      return;
    }

    reserveBtn.addEventListener('click', (event) => {
      event.preventDefault();
      try {
        const whatsappUrl = generateWhatsAppLink(lockedPhone, RESERVE_MESSAGE);
        openSecureTab(whatsappUrl);
      } catch (error) {
        console.error('[whatsapp] Unable to open WhatsApp link', error);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReserveButton, { once: true });
  } else {
    initReserveButton();
  }
})();

