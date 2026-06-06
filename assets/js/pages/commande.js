// commande.js ? UI checkout V1 (validation + UX)

const $ = selector => document.querySelector(selector);
const $$ = selector => Array.from(document.querySelectorAll(selector));

const toastEl = $('#toast');
const form = $('#checkoutForm');
const submitBtn = $('#submitBtn');
const cartJsonInput = $('#cartJson');

function showToast(message) {
  if (!toastEl) return;
  toastEl.textContent = message || '';
  toastEl.hidden = !message;
  window.clearTimeout(showToast._t);
  showToast._t = window.setTimeout(() => {
    toastEl.hidden = true;
  }, 2600);
}

function setFieldError(fieldEl, errorEl, message) {
  if (!fieldEl || !errorEl) return;
  const hasError = Boolean(message);

  errorEl.textContent = message || '';
  fieldEl.classList.toggle('is-invalid', hasError);
  fieldEl.setAttribute('aria-invalid', hasError ? 'true' : 'false');
}

function normalizePhone(raw) {
  let v = String(raw || '').trim();
  v = v.replace(/[\s().-]/g, '');
  if (!v) return '';
  if (v.startsWith('00')) v = `+${v.slice(2)}`;
  if (v.startsWith('+')) {
    const digits = v.slice(1).replace(/\D+/g, '');
    if (!digits.startsWith('223')) return '';
    const local = digits.slice(3);
    return /^\d{8}$/.test(local) ? `+223${local}` : '';
  }
  const digits = v.replace(/\D+/g, '');
  if (!digits) return '';
  if (digits.startsWith('223')) {
    const local = digits.slice(3);
    return /^\d{8}$/.test(local) ? `+223${local}` : '';
  }
  return /^\d{8}$/.test(digits) ? `+223${digits}` : '';
}

function validatePhone(raw) {
  const normalized = normalizePhone(raw);
  if (!normalized) return { valid: false, message: 'Telephone invalide.' };
  return { valid: true, message: '' };
}
// Accordion (accessible)
function initAccordion() {
  const root = document.querySelector('[data-accordion]');
  if (!root) return;

  root.addEventListener('click', e => {
    const trigger = e.target.closest('.accordion-trigger');
    if (!trigger) return;

    const panelId = trigger.getAttribute('aria-controls');
    const panel = panelId ? document.getElementById(panelId) : null;
    const isOpen = trigger.getAttribute('aria-expanded') === 'true';

    trigger.setAttribute('aria-expanded', String(!isOpen));
    if (panel) {
      panel.hidden = isOpen;
      panel.setAttribute('aria-hidden', String(isOpen));
    }
  });
}

initAccordion();

if (form) {
  let cartEmpty = form?.dataset?.cartEmpty === '1';

  const fields = {
    fullName: $('#fullName'),
    phone: $('#phone'),
    city: $('#city')
  };

  const errorEls = {
    fullName: $('#fullNameError'),
    phone: $('#phoneError'),
    city: $('#cityError')
  };

  let hasSubmitted = false;
  const touched = new Set();

  function normalizeCartMap(map) {
    const out = {};
    if (!map || typeof map !== 'object') return out;

    Object.keys(map).forEach(key => {
      const id = parseInt(key, 10);
      const qty = parseInt(map[key], 10);
      if (!Number.isFinite(id) || id <= 0) return;
      if (!Number.isFinite(qty) || qty < 1) return;
      out[id] = qty;
    });

    return out;
  }

  function trySetCartJsonFromLocalStorage() {
    if (!cartJsonInput) return false;
    if (String(cartJsonInput.value || '').trim() !== '') return true;

    const keys = ['malishop_cart', 'cart', 'MALISHOP_CART', 'malishop:cart'];
    for (const key of keys) {
      const raw = window.localStorage.getItem(key);
      if (!raw) continue;
      try {
        const parsed = JSON.parse(raw);

        // Accept:
        // - { "12": 2, "99": 1 }
        // - [ { product_id: 12, qty: 2 }, ... ]
        let map = {};

        if (Array.isArray(parsed)) {
          parsed.forEach(item => {
            const id = parseInt(item?.product_id ?? item?.id ?? 0, 10);
            const qty = parseInt(item?.qty ?? item?.quantity ?? 0, 10);
            if (!Number.isFinite(id) || id <= 0) return;
            if (!Number.isFinite(qty) || qty < 1) return;
            map[id] = qty;
          });
        } else if (parsed && typeof parsed === 'object') {
          map = normalizeCartMap(parsed);
        }

        const normalized = normalizeCartMap(map);
        const ids = Object.keys(normalized);
        if (!ids.length) continue;

        cartJsonInput.value = JSON.stringify(normalized);
        return true;
      } catch (e) {
        // ignore invalid JSON
      }
    }

    return false;
  }

  function validateField(name, showErrors) {
    const el = fields[name];
    const value = el ? String(el.value || '').trim() : '';

    if (!el) return true;

    if (name === 'city') {
      const ok = value !== '';
      if (showErrors) setFieldError(el, errorEls.city, ok ? '' : 'Veuillez s?lectionner une ville.');
      return ok;
    }

    if (el.hasAttribute('required') && value === '') {
      if (showErrors) setFieldError(el, errorEls[name], 'Ce champ est requis.');
      return false;
    }

    if (name === 'phone' && value !== '') {
      const { valid, message } = validatePhone(value);
      if (showErrors) setFieldError(el, errorEls.phone, message);
      return valid;
    }

    if (showErrors) setFieldError(el, errorEls[name], '');
    return true;
  }

  function validateAll(showErrors) {
    const order = ['fullName', 'phone', 'city'];
    let firstInvalid = null;
    let ok = true;

    order.forEach(name => {
      const shouldShow = Boolean(showErrors) && (hasSubmitted || touched.has(name));
      const valid = validateField(name, shouldShow);
      if (!valid) {
        ok = false;
        if (!firstInvalid) firstInvalid = fields[name];
      }
    });

    // UX: ne pas bloquer le bouton tant que le panier n'est pas vide.
    // La soumission affiche/scroll vers la premiï¿½re erreur si invalid.
    if (submitBtn) submitBtn.disabled = cartEmpty;
    return { ok, firstInvalid };
  }

  function bindLiveValidation() {
    ['fullName', 'phone', 'city'].forEach(name => {
      const el = fields[name];
      if (!el) return;

      const eventName = el.tagName.toLowerCase() === 'select' ? 'change' : 'input';
      el.addEventListener(eventName, () => {
        touched.add(name);
        validateAll(true);
      });
      el.addEventListener('blur', () => {
        touched.add(name);
        validateAll(true);
      });
    });
  }

  bindLiveValidation();

  // Bridge optionnel: si un ancien front stocke le panier en localStorage, on l'envoie au serveur.
  if (cartEmpty) {
    const found = trySetCartJsonFromLocalStorage();
    if (found) {
      cartEmpty = false;
      showToast('Panier d?tect?, vous pouvez valider.');
    }
  }

  validateAll(false);

  form.addEventListener('submit', e => {
    if (cartEmpty) {
      // Derni?re chance (bridge)
      const found = trySetCartJsonFromLocalStorage();
      if (found) {
        cartEmpty = false;
      }
    }

    if (cartEmpty) {
      e.preventDefault();
      showToast('Votre panier est vide.');
      return;
    }

    hasSubmitted = true;
    const { ok, firstInvalid } = validateAll(true);
    if (!ok) {
      e.preventDefault();
      firstInvalid?.focus();
      showToast('Veuillez corriger les champs en rouge.');
    }
  });
}

