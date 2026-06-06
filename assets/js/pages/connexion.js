// connexion.js — Connexion client (téléphone ou email)
(() => {
  const baseUrl = document.body?.dataset?.baseUrl || '/';

  const form = document.getElementById('loginForm');
  if (!form) return;

  const identifier = document.getElementById('identifier');
  const password = document.getElementById('password');
  const showPassword = document.getElementById('showPassword');
  const notice = document.getElementById('loginNotice');
  const loginBtn = document.getElementById('loginBtn');

  const identifierError = document.getElementById('identifierError');
  const passwordError = document.getElementById('passwordError');

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
    clearFieldError(identifier, identifierError);
    clearFieldError(password, passwordError);

    const idVal = String(identifier.value || '').trim();
    const passVal = String(password.value || '');

    let firstInvalid = null;

    const okId = (idVal !== '' && (isValidEmail(idVal) || isValidPhone(idVal)));
    if (!okId) {
      setFieldError(identifier, identifierError, 'Veuillez saisir un email ou un téléphone valide.');
      firstInvalid ||= identifier;
    }

    if (!passVal) {
      setFieldError(password, passwordError, 'Mot de passe obligatoire.');
      firstInvalid ||= password;
    }

    return { ok: !firstInvalid, firstInvalid };
  };

  showPassword?.addEventListener('change', () => {
    password.type = showPassword.checked ? 'text' : 'password';
  });

  async function loginRequest(identifierVal, passVal) {
    const res = await fetch(`${baseUrl}public/api/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ identifier: identifierVal, password: passVal })
    });
    const json = await res.json().catch(() => null);
    return { res, json };
  }

  function getRedirectTarget() {
    const p = new URLSearchParams(window.location.search || '');
    const redir = String(p.get('redirect') || '').trim();
    if (!redir) return `${baseUrl}pages/mes-commandes.php`;
    if (redir.startsWith('/') || redir.startsWith('http://') || redir.startsWith('https://')) return redir;
    return `${baseUrl}${redir.replace(/^\/+/, '')}`;
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const res = validate();
    if (!res.ok) {
      setNotice('error', 'Veuillez corriger les champs signalés.');
      res.firstInvalid?.focus();
      return;
    }

    const idVal = String(identifier.value || '').trim();
    const passVal = String(password.value || '');

    setNotice('success', 'Connexion en cours...');
    if (loginBtn) loginBtn.disabled = true;

    try {
      const { json } = await loginRequest(idVal, passVal);
      if (!json || !json.ok) {
        setNotice('error', (json && json.message) ? json.message : 'Identifiants incorrects.');
        if (loginBtn) loginBtn.disabled = false;
        return;
      }

      setNotice('success', 'Connexion réussie.');
      window.location.href = getRedirectTarget();
    } catch (err) {
      setNotice('error', 'Impossible de se connecter pour le moment. Veuillez réessayer.');
      if (loginBtn) loginBtn.disabled = false;
    }
  });
})();
