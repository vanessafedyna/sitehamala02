// contact.js — Contact + newsletter (DB-driven via public/api/*)
(() => {
  const baseUrl = document.body?.dataset?.baseUrl || '/';

  const form = document.getElementById('contactForm');
  if (!form) return;

  const fullName = document.getElementById('fullName');
  const phone = document.getElementById('phone');
  const email = document.getElementById('email');
  const subject = document.getElementById('subject');
  const message = document.getElementById('message');
  const notice = document.getElementById('formNotice');
  const submitBtn = document.getElementById('submitBtn');

  const errors = {
    fullName: document.getElementById('fullNameError'),
    phone: document.getElementById('phoneError'),
    email: document.getElementById('emailError'),
    message: document.getElementById('messageError')
  };

  const newsletterForm = document.getElementById('newsletterForm');
  const newsletterEmail = document.getElementById('newsletterEmail');
  const newsletterNotice = document.getElementById('newsletterNotice');

  const setNotice = (el, type, text) => {
    if (!el) return;
    el.classList.remove('is-error', 'is-success', 'is-hidden');
    if (!text) {
      el.textContent = '';
      el.classList.add('is-hidden');
      return;
    }
    el.textContent = text;
    el.classList.add(type === 'success' ? 'is-success' : 'is-error');
  };

  const clearFieldError = (inputEl, errorEl) => {
    inputEl.classList.remove('is-invalid');
    inputEl.removeAttribute('aria-invalid');
    if (errorEl) errorEl.textContent = '';
  };

  const setFieldError = (inputEl, errorEl, messageText) => {
    inputEl.classList.add('is-invalid');
    inputEl.setAttribute('aria-invalid', 'true');
    if (errorEl) errorEl.textContent = messageText;
  };

  const normalizePhoneDigits = value => String(value || '').replace(/[^\d]/g, '');

  const isValidPhone = value => {
    const raw = String(value || '').trim();
    if (!raw) return false;
    let digits = normalizePhoneDigits(raw);
    if (digits.startsWith('223') && digits.length > 8) digits = digits.slice(3);
    return digits.length >= 8 && digits.length <= 10;
  };

  const isValidEmail = value => {
    const v = String(value || '').trim();
    if (!v) return false;
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  };

  const validate = () => {
    const invalid = [];

    setNotice(notice, 'error', '');
    clearFieldError(fullName, errors.fullName);
    clearFieldError(phone, errors.phone);
    clearFieldError(email, errors.email);
    clearFieldError(message, errors.message);

    const nameVal = String(fullName.value || '').trim();
    const phoneVal = String(phone.value || '').trim();
    const emailVal = String(email.value || '').trim();
    const messageVal = String(message.value || '').trim();

    if (!nameVal) {
      setFieldError(fullName, errors.fullName, 'Veuillez renseigner votre nom complet.');
      invalid.push(fullName);
    }

    if (!messageVal) {
      setFieldError(message, errors.message, 'Veuillez écrire votre message.');
      invalid.push(message);
    }

    const hasPhone = !!phoneVal;
    const hasEmail = !!emailVal;

    if (!hasPhone && !hasEmail) {
      setFieldError(phone, errors.phone, 'Veuillez saisir un téléphone ou un email.');
      setFieldError(email, errors.email, 'Veuillez saisir un téléphone ou un email.');
      invalid.push(phone);
    } else {
      if (hasPhone && !isValidPhone(phoneVal)) {
        setFieldError(phone, errors.phone, 'Téléphone invalide (min. 8 chiffres, +223 autorisé).');
        invalid.push(phone);
      }
      if (hasEmail && !isValidEmail(emailVal)) {
        setFieldError(email, errors.email, 'Email invalide (ex: nom@email.com).');
        invalid.push(email);
      }
    }

    return { ok: invalid.length === 0, firstInvalid: invalid[0] || null };
  };

  const isFormProbablyValid = () => {
    const nameVal = String(fullName.value || '').trim();
    const phoneVal = String(phone.value || '').trim();
    const emailVal = String(email.value || '').trim();
    const messageVal = String(message.value || '').trim();

    if (!nameVal || !messageVal) return false;
    if (!phoneVal && !emailVal) return false;
    if (phoneVal && !isValidPhone(phoneVal)) return false;
    if (emailVal && !isValidEmail(emailVal)) return false;
    return true;
  };

  const updateSubmitState = () => {
    if (!submitBtn) return;
    const ok = isFormProbablyValid();
    submitBtn.disabled = !ok;
    submitBtn.setAttribute('aria-disabled', ok ? 'false' : 'true');
  };

  [fullName, phone, email, message].forEach(el => {
    el.addEventListener('input', () => updateSubmitState());
    el.addEventListener('blur', () => {
      validate();
      updateSubmitState();
    });
  });

  setNotice(notice, 'error', '');
  updateSubmitState();

  async function submitContact(payload) {
    const res = await fetch(`${baseUrl}public/api/contact_submit.php`, {
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
    updateSubmitState();

    if (!res.ok) {
      setNotice(notice, 'error', 'Veuillez corriger les champs signalés.');
      res.firstInvalid?.focus();
      return;
    }

    const payload = {
      name: String(fullName.value || '').trim(),
      phone: String(phone.value || '').trim(),
      email: String(email.value || '').trim().toLowerCase(),
      subject: String(subject?.value || '').trim(),
      message: String(message.value || '').trim()
    };

    setNotice(notice, 'success', 'Envoi en cours...');
    if (submitBtn) submitBtn.disabled = true;

    try {
      const { json } = await submitContact(payload);
      if (!json || !json.ok) {
        setNotice(notice, 'error', (json && json.message) ? json.message : "Impossible d'envoyer le message.");
        if (submitBtn) submitBtn.disabled = false;
        updateSubmitState();
        return;
      }

      setNotice(notice, 'success', json.message || 'Message envoyé.');
      form.reset();
      updateSubmitState();
    } catch (err) {
      setNotice(notice, 'error', "Impossible d'envoyer le message pour le moment.");
      if (submitBtn) submitBtn.disabled = false;
      updateSubmitState();
    }
  });

  if (newsletterForm && newsletterEmail && newsletterNotice) {
    setNotice(newsletterNotice, 'error', '');

    async function subscribeNewsletter(emailValue) {
      const res = await fetch(`${baseUrl}public/api/newsletter_subscribe.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ email: emailValue })
      });
      const json = await res.json().catch(() => null);
      return { res, json };
    }

    newsletterForm.addEventListener('submit', async e => {
      e.preventDefault();
      const v = String(newsletterEmail.value || '').trim().toLowerCase();
      if (!v || !isValidEmail(v)) {
        setNotice(newsletterNotice, 'error', 'Veuillez saisir un email valide pour vous inscrire.');
        newsletterEmail.focus();
        return;
      }

      setNotice(newsletterNotice, 'success', 'Inscription en cours...');
      try {
        const { json } = await subscribeNewsletter(v);
        if (!json || !json.ok) {
          setNotice(newsletterNotice, 'error', (json && json.message) ? json.message : "Impossible de s'inscrire.");
          return;
        }
        setNotice(newsletterNotice, 'success', json.message || 'Inscription ok.');
        newsletterForm.reset();
      } catch (err) {
        setNotice(newsletterNotice, 'error', "Impossible de s'inscrire pour le moment.");
      }
    });
  }
})();

