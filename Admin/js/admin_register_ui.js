(function adminRegisterUI() {
  const form = document.getElementById('adminRegisterForm');
  if (!form) return;

  const step1 = form.querySelector('[data-step="1"]');
  const step2 = form.querySelector('[data-step="2"]');
  const nextBtn = document.getElementById('adminRegisterNextBtn');
  const prevBtn = document.getElementById('adminRegisterPrevBtn');
  const submitBtn = document.getElementById('adminRegisterSubmitBtn');
  const dots = document.querySelectorAll('.admin-register-line .dot');

  const setStep = (step) => {
    step1?.classList.toggle('is-active', step === 1);
    step2?.classList.toggle('is-active', step === 2);
    if (dots.length > 0) {
      dots.forEach((dot, i) => dot.classList.toggle('is-active', i < step));
    }
  };

  const getField = (name) => form.querySelector(`[name="${name}"]`);
  const markInvalid = (input, invalid) => {
    if (!input) return;
    input.style.borderColor = invalid ? '#C24D98' : '';
    input.style.boxShadow = invalid ? '0 0 0 4px rgba(194,77,152,.16)' : '';
  };

  const validateStep1 = () => {
    const fullName = getField('full_name');
    const email = getField('email');
    const phone = getField('phone');
    const okName = String(fullName?.value || '').trim().length >= 2;
    const okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email?.value || '').trim());
    const okPhone = /^\+?[0-9 ]{8,20}$/.test(String(phone?.value || '').trim());
    markInvalid(fullName, !okName);
    markInvalid(email, !okEmail);
    markInvalid(phone, !okPhone);
    return okName && okEmail && okPhone;
  };

  const updateDotsForStep1 = () => {
    if (dots.length < 2) return;
    // Etape 1 toujours active sur cette page.
    dots[0].classList.add('is-active');
    // Etape 2 active dynamiquement si les infos sont valides.
    dots[1].classList.toggle('is-active', validateStep1());
  };

  const validateStep2 = () => {
    if (!step2) return true;
    const password = getField('password');
    const confirm = getField('password_confirm');
    const value = String(password?.value || '');
    const same = value === String(confirm?.value || '');
    const longEnough = value.length >= 8;
    markInvalid(password, !(longEnough && same));
    markInvalid(confirm, !(longEnough && same));
    return longEnough && same;
  };

  nextBtn?.addEventListener('click', () => {
    if (!step2) return;
    if (!validateStep1()) return;
    setStep(2);
    getField('password')?.focus();
  });

  prevBtn?.addEventListener('click', () => {
    setStep(1);
  });

  ['full_name', 'email', 'phone'].forEach((name) => {
    const field = getField(name);
    field?.addEventListener('input', updateDotsForStep1);
    field?.addEventListener('blur', updateDotsForStep1);
  });

  form.addEventListener('submit', (event) => {
    if (!validateStep1()) {
      event.preventDefault();
      setStep(1);
      return;
    }
    if (!validateStep2()) {
      event.preventDefault();
      setStep(2);
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Creation...';
    }
  });

  updateDotsForStep1();
})();
