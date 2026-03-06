(function adminLoginUI() {
  const form = document.getElementById('adminLoginForm');
  if (!form) return;

  const card = document.querySelector('.admin-login-card') || document.querySelector('.admin-auth-card');
  const email = document.getElementById('adminLoginEmail');
  const password = document.getElementById('adminLoginPassword');
  const emailError = document.getElementById('adminLoginEmailError');
  const passwordError = document.getElementById('adminLoginPasswordError');
  const capsLockHint = document.getElementById('adminLoginCapsLockHint');
  const submitBtn = document.getElementById('adminLoginSubmitBtn');
  const passwordToggle = document.getElementById('adminLoginPasswordToggle');

  const setFieldError = (input, errorNode, message) => {
    const hasError = Boolean(message);
    input?.setAttribute('aria-invalid', hasError ? 'true' : 'false');
    input?.classList.toggle('is-invalid', hasError);
    if (errorNode) errorNode.textContent = message || '';
  };

  const validateEmail = () => {
    const value = String(email?.value || '').trim();
    if (!value) {
      setFieldError(email, emailError, 'Veuillez renseigner votre email.');
      return false;
    }
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    setFieldError(email, emailError, ok ? '' : 'Format email invalide.');
    return ok;
  };

  const validatePassword = () => {
    const value = String(password?.value || '');
    if (!value) {
      setFieldError(password, passwordError, 'Veuillez renseigner votre mot de passe.');
      return false;
    }
    const ok = value.length >= 8;
    setFieldError(password, passwordError, ok ? '' : '8 caracteres minimum.');
    return ok;
  };

  const onCapsLock = (event) => {
    if (!capsLockHint || !event?.getModifierState) return;
    capsLockHint.textContent = event.getModifierState('CapsLock') ? 'Verr. Maj activee.' : '';
  };

  email?.addEventListener('input', validateEmail);
  password?.addEventListener('input', validatePassword);
  password?.addEventListener('keyup', onCapsLock);
  password?.addEventListener('keydown', onCapsLock);

  passwordToggle?.addEventListener('click', () => {
    if (!password) return;
    const isPassword = password.type === 'password';
    password.type = isPassword ? 'text' : 'password';
    passwordToggle.textContent = isPassword ? 'Masquer' : 'Voir';
    passwordToggle.setAttribute('aria-label', isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
  });

  form.addEventListener('submit', (event) => {
    const ok = validateEmail() && validatePassword();
    if (!ok) {
      event.preventDefault();
      if (card) {
        card.classList.remove('has-error');
        requestAnimationFrame(() => card.classList.add('has-error'));
      }
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.classList.add('is-loading');
    }
  });
})();
