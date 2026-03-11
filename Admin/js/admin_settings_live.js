(function adminSettingsLive() {
  const siteForm = document.getElementById('settingsSiteForm');
  const securityForm = document.getElementById('settingsSecurityForm');
  const profileForm = document.getElementById('settingsProfileForm');
  if (!siteForm || !securityForm || !profileForm) return;

  const fields = {
    siteName: document.getElementById('settingSiteName'),
    supportEmail: document.getElementById('settingSupportEmail'),
    siteUrl: document.getElementById('settingSiteUrl'),
    siteDescription: document.getElementById('settingSiteDescription'),
    timezone: document.getElementById('settingTimezone'),
    currentPassword: document.getElementById('settingCurrentPassword'),
    newPassword: document.getElementById('settingNewPassword'),
    confirmPassword: document.getElementById('settingConfirmPassword'),
    fullName: document.getElementById('settingFullName'),
    adminEmail: document.getElementById('settingAdminEmail'),
    phone: document.getElementById('settingPhone'),
    bio: document.getElementById('settingBio'),
    avatar: document.getElementById('settingAvatar'),
    avatarPreview: document.getElementById('settingsAvatarPreview'),
    avatarFallback: document.getElementById('settingsAvatarFallback'),
  };

  const notify = (type, message) => {
    if (typeof window.showToast === 'function') {
      window.showToast(type, message, { key: `admin_settings_${type}` });
      return;
    }
    console[type === 'error' ? 'error' : 'log'](message);
  };

  const setLoading = (button, isLoading) => {
    if (!button) return;
    button.disabled = !!isLoading;
    button.classList.toggle('is-loading', !!isLoading);
  };

  const eyeIconSvg = () => `
    <svg class="admin-password-toggle__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path class="admin-password-toggle__eye" d="M12 5C6.5 5 2 9.6 1 12c1 2.4 5.5 7 11 7s10-4.6 11-7c-1-2.4-5.5-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"></path>
      <circle class="admin-password-toggle__pupil" cx="12" cy="12" r="2.3"></circle>
      <path class="admin-password-toggle__slash" d="M3 4.5 20.5 22"></path>
    </svg>
  `;

  const setupPasswordToggles = () => {
    const inputs = securityForm.querySelectorAll('input[type="password"]');
    inputs.forEach((input) => {
      input.classList.add('admin-input--with-toggle');
      let wrap = input.closest('.admin-password-input-wrap');
      if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'admin-password-input-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
      }
      if (wrap.querySelector('.admin-password-toggle')) return;

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'admin-password-toggle';
      button.setAttribute('aria-label', 'Afficher le mot de passe');
      button.setAttribute('aria-pressed', 'false');
      button.innerHTML = eyeIconSvg();
      button.addEventListener('click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.classList.toggle('is-visible', isPassword);
        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
        button.setAttribute('aria-label', isPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        input.focus();
      });
      wrap.appendChild(button);
    });
  };

  const toJson = async (response) => {
    let payload = {};
    try {
      payload = await response.json();
    } catch (e) {
      payload = {};
    }
    if (!response.ok) {
      throw new Error(payload?.error || 'Operation impossible');
    }
    return payload;
  };

  const postJson = async (payload) => {
    const response = await fetch('../api/admin_settings.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-Token': window.getAdminCsrfToken?.() || '',
      },
      body: JSON.stringify(payload),
    });
    return toJson(response);
  };

  const postFormData = async (formData) => {
    const response = await fetch('../api/admin_settings.php', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-Token': window.getAdminCsrfToken?.() || '',
      },
      body: formData,
    });
    return toJson(response);
  };

  const renderAvatar = (avatarUrl, fullName = '') => {
    if (!fields.avatarPreview || !fields.avatarFallback) return;
    fields.avatarPreview.querySelectorAll('img').forEach((node) => node.remove());
    if (avatarUrl) {
      const img = document.createElement('img');
      img.src = avatarUrl;
      img.alt = fullName || 'Avatar admin';
      img.loading = 'lazy';
      fields.avatarPreview.appendChild(img);
      fields.avatarFallback.style.display = 'none';
      return;
    }
    fields.avatarFallback.style.display = '';
    const initial = (fullName || 'AD').trim().charAt(0).toUpperCase() || 'A';
    fields.avatarFallback.textContent = initial;
  };

  const updateTopbarAvatar = (avatarUrl, fullName = '') => {
    const topbarAvatar = document.querySelector('.admin-user__avatar');
    if (!topbarAvatar) return;
    const safeName = (fullName || 'Admin').trim() || 'Admin';
    if (avatarUrl) {
      topbarAvatar.textContent = '';
      const img = document.createElement('img');
      img.src = avatarUrl;
      img.alt = safeName;
      img.loading = 'lazy';
      topbarAvatar.appendChild(img);
      return;
    }
    topbarAvatar.textContent = (safeName.charAt(0) || 'A').toUpperCase();
  };

  const hydrate = (payload) => {
    const site = payload?.site_settings || {};
    const profile = payload?.profile || {};

    fields.siteName.value = site.site_name || '';
    fields.supportEmail.value = site.support_email || '';
    fields.siteUrl.value = site.site_url || '';
    fields.siteDescription.value = site.site_description || '';
    if (fields.timezone) fields.timezone.value = site.timezone || 'Africa/Porto-Novo';

    fields.fullName.value = profile.full_name || '';
    fields.adminEmail.value = profile.email || '';
    fields.phone.value = profile.phone || '';
    fields.bio.value = profile.bio || '';
    renderAvatar(profile.avatar_url || '', profile.full_name || '');
    updateTopbarAvatar(profile.avatar_url || '', profile.full_name || '');
  };

  const load = async () => {
    try {
      const response = await fetch('../api/admin_settings.php', { headers: { Accept: 'application/json' } });
      const payload = await toJson(response);
      hydrate(payload?.data || {});
    } catch (error) {
      notify('error', error.message || 'Erreur chargement parametres');
    }
  };

  siteForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitBtn = document.getElementById('settingsSiteSubmit');
    setLoading(submitBtn, true);
    try {
      await postJson({
        action: 'save_site_settings',
        site_name: fields.siteName.value.trim(),
        support_email: fields.supportEmail.value.trim(),
        site_url: fields.siteUrl.value.trim(),
        site_description: fields.siteDescription.value.trim(),
        timezone: fields.timezone ? fields.timezone.value : 'Africa/Porto-Novo',
      });
      notify('success', 'Parametres globaux enregistres.');
    } catch (error) {
      notify('error', error.message || 'Enregistrement impossible');
    } finally {
      setLoading(submitBtn, false);
    }
  });

  securityForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitBtn = document.getElementById('settingsSecuritySubmit');
    setLoading(submitBtn, true);
    try {
      await postJson({
        action: 'change_password',
        current_password: fields.currentPassword.value,
        new_password: fields.newPassword.value,
        confirm_password: fields.confirmPassword.value,
      });
      fields.currentPassword.value = '';
      fields.newPassword.value = '';
      fields.confirmPassword.value = '';
      notify('success', 'Mot de passe mis a jour. Sessions invalidees.');
    } catch (error) {
      notify('error', error.message || 'Mise a jour impossible');
    } finally {
      setLoading(submitBtn, false);
    }
  });

  profileForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitBtn = document.getElementById('settingsProfileSubmit');
    setLoading(submitBtn, true);
    try {
      const formData = new FormData();
      formData.set('action', 'save_profile');
      formData.set('full_name', fields.fullName.value.trim());
      formData.set('email', fields.adminEmail.value.trim());
      formData.set('phone', fields.phone.value.trim());
      formData.set('bio', fields.bio.value.trim());
      if (fields.avatar.files && fields.avatar.files[0]) {
        formData.set('avatar', fields.avatar.files[0]);
      }

      const payload = await postFormData(formData);
      const profile = payload?.data?.profile || {};
      renderAvatar(profile.avatar_url || '', profile.full_name || fields.fullName.value);
      updateTopbarAvatar(profile.avatar_url || '', profile.full_name || fields.fullName.value);
      fields.avatar.value = '';
      notify('success', 'Profil admin enregistre.');
    } catch (error) {
      notify('error', error.message || 'Mise a jour profil impossible');
    } finally {
      setLoading(submitBtn, false);
    }
  });

  fields.avatar?.addEventListener('change', () => {
    const file = fields.avatar.files?.[0];
    if (!file) return;
    const objectUrl = URL.createObjectURL(file);
    renderAvatar(objectUrl, fields.fullName.value || 'Admin');
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 10_000);
  });

  setupPasswordToggles();
  load();
})();
