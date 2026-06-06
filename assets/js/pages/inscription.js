// inscription.js — Inscription client (téléphone principal, email optionnel)
(() => {
  const baseUrl = document.body?.dataset?.baseUrl || '/';

  const form = document.getElementById('registerForm');
  if (!form) return;

  const nameEl = document.getElementById('name');
  const lastNameEl = document.getElementById('last_name');
  const phoneEl = document.getElementById('phone');
  const emailEl = document.getElementById('email');
  const passEl = document.getElementById('password');
  const pass2El = document.getElementById('password2');
  const showPassword = document.getElementById('showPassword');

  const notice = document.getElementById('registerNotice');
  const btn = document.getElementById('registerBtn');

  const nameError = document.getElementById('nameError');
  const lastNameError = document.getElementById('lastNameError');
  const phoneError = document.getElementById('phoneError');
  const emailError = document.getElementById('emailError');
  const passError = document.getElementById('passwordError');
  const pass2Error = document.getElementById('password2Error');

  const setNotice = (type, text) => {
    notice.classList.remove('is-error', 'is-success', 'is-hidden');
    if (!text) {
      notice.textContent = '';
      notice.classList.add('is-hidden');
      return;
    }
    notice.textContent = text;
    notice.classList.add(type === 'success' ? 'is-success' : 'is-error');
  };

  const setFieldError = (inputEl, errorEl, message) => {
    inputEl.classList.add('is-invalid');
    inputEl.setAttribute('aria-invalid', 'true');
    if (errorEl) errorEl.textContent = message;
  };

  const clearFieldError = (inputEl, errorEl) => {
    inputEl.classList.remove('is-invalid');
    inputEl.removeAttribute('aria-invalid');
    if (errorEl) errorEl.textContent = '';
  };

  const isValidEmail = value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());

  function normalizePhoneDigits(raw) {
    let v = String(raw || '').trim();
    v = v.replace(/[^\d]/g, '');
    if (v.startsWith('00') && v.length > 2) v = v.slice(2);
    return v;
  }

  function isValidPhone(raw) {
    const d = normalizePhoneDigits(raw);
    return /^\d+$/.test(d) && d.length >= 8 && d.length <= 15;
  }

  const validate = () => {
    setNotice('error', '');
    clearFieldError(nameEl, nameError);
    clearFieldError(lastNameEl, lastNameError);
    clearFieldError(phoneEl, phoneError);
    clearFieldError(emailEl, emailError);
    clearFieldError(passEl, passError);
    clearFieldError(pass2El, pass2Error);

    const invalid = [];

    const nameVal = String(nameEl.value || '').trim();
    const lastNameVal = String(lastNameEl.value || '').trim();
    const phoneVal = String(phoneEl.value || '').trim();
    const emailVal = String(emailEl.value || '').trim();
    const passVal = String(passEl.value || '');
    const pass2Val = String(pass2El.value || '');

    if (!nameVal || nameVal.length < 2) {
      setFieldError(nameEl, nameError, 'Veuillez saisir votre prenom.');
      invalid.push(nameEl);
    }

    if (!lastNameVal || lastNameVal.length < 2) {
      setFieldError(lastNameEl, lastNameError, 'Veuillez saisir votre nom de famille.');
      invalid.push(lastNameEl);
    }

    if (!phoneVal || !isValidPhone(phoneVal)) {
      setFieldError(phoneEl, phoneError, 'Téléphone invalide (8 à 15 chiffres, + ou 00 autorisé).');
      invalid.push(phoneEl);
    }

    if (emailVal && !isValidEmail(emailVal)) {
      setFieldError(emailEl, emailError, 'Email invalide.');
      invalid.push(emailEl);
    }

    if (!passVal || passVal.length < 10) {
      setFieldError(passEl, passError, 'Le mot de passe doit contenir au moins 10 caractères.');
      invalid.push(passEl);
    }

    if (pass2Val !== passVal) {
      setFieldError(pass2El, pass2Error, 'Les mots de passe ne correspondent pas.');
      invalid.push(pass2El);
    }

    return { ok: invalid.length === 0, firstInvalid: invalid[0] || null };
  };

  showPassword?.addEventListener('change', () => {
    const t = showPassword.checked ? 'text' : 'password';
    passEl.type = t;
    pass2El.type = t;
  });

  async function registerRequest(payload) {
    const res = await fetch(`${baseUrl}public/api/register.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const json = await res.json().catch(() => null);
    return { res, json };
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const res = validate();
    if (!res.ok) {
      setNotice('error', 'Veuillez corriger les champs signalés.');
      res.firstInvalid?.focus();
      return;
    }

    const payload = {
      name: String(nameEl.value || '').trim(),
      last_name: String(lastNameEl.value || '').trim(),
      phone: String(phoneEl.value || '').trim(),
      email: String(emailEl.value || '').trim().toLowerCase(),
      password: String(passEl.value || '')
    };

    setNotice('success', 'Création du compte...');
    if (btn) btn.disabled = true;

    try {
      const { json } = await registerRequest(payload);
      if (!json || !json.ok) {
        setNotice('error', (json && json.message) ? json.message : 'Impossible de créer le compte.');
        if (btn) btn.disabled = false;
        return;
      }

      setNotice('success', 'Compte créé. Redirection...');
      const redirectUrl = String(json.redirect || `${baseUrl}pages/mes-commandes.php`);
      window.location.href = redirectUrl;
    } catch (err) {
      setNotice('error', 'Erreur réseau. Veuillez réessayer.');
      if (btn) btn.disabled = false;
    }
  });
})();
