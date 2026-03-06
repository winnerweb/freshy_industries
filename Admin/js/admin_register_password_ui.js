(function adminRegisterPasswordUI() {
  const form = document.getElementById('adminRegisterPasswordForm');
  if (!form) return;

  const passwordInput = document.getElementById('adminRegisterPassword');
  const confirmInput = document.getElementById('adminRegisterPasswordConfirm');
  const submitBtn = document.getElementById('adminRegisterPasswordSubmit');
  const serverAlert = document.getElementById('adminRegisterPasswordError');
  const pwErr = document.getElementById('adminRegisterPasswordFieldError');
  const cfErr = document.getElementById('adminRegisterPasswordConfirmFieldError');
  const dots = document.querySelectorAll('.admin-password-line .dot');

  const setFieldState = (input, errorEl, message) => {
    if (!input || !errorEl) return;
    const hasError = Boolean(message);
    input.classList.toggle('is-invalid', hasError);
    input.setAttribute('aria-invalid', hasError ? 'true' : 'false');
    errorEl.textContent = hasError ? message : '';
  };

  const validate = () => {
    const password = String(passwordInput?.value || '');
    const confirm = String(confirmInput?.value || '');
    let ok = true;

    if (password.length < 8) {
      setFieldState(passwordInput, pwErr, 'Minimum 8 caracteres.');
      ok = false;
    } else {
      setFieldState(passwordInput, pwErr, '');
    }

    if (confirm.length < 8) {
      setFieldState(confirmInput, cfErr, 'Minimum 8 caracteres.');
      ok = false;
    } else if (password !== confirm) {
      setFieldState(confirmInput, cfErr, 'Les mots de passe ne correspondent pas.');
      ok = false;
    } else {
      setFieldState(confirmInput, cfErr, '');
    }
    return ok;
  };

  const updateDots = () => {
    if (dots.length < 3) return;
    // Etapes 1 et 2 sont considerees atteintes sur cette page.
    dots[0].classList.add('is-active');
    dots[1].classList.add('is-active');
    // Etape finale active dynamiquement si les mots de passe sont valides.
    dots[2].classList.toggle('is-active', validate());
  };

  form.querySelectorAll('[data-password-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-password-toggle');
      const input = targetId ? document.getElementById(targetId) : null;
      if (!input) return;
      const nextType = input.type === 'password' ? 'text' : 'password';
      input.type = nextType;
      btn.classList.toggle('is-visible', nextType === 'text');
      input.focus();
    });
  });

  passwordInput?.addEventListener('input', updateDots);
  confirmInput?.addEventListener('input', updateDots);
  passwordInput?.addEventListener('blur', updateDots);
  confirmInput?.addEventListener('blur', updateDots);

  form.addEventListener('submit', (event) => {
    if (serverAlert) {
      serverAlert.classList.remove('is-visible');
      serverAlert.textContent = '';
    }
    if (!validate()) {
      event.preventDefault();
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.classList.add('is-loading');
    }
  });

  updateDots();
})();
