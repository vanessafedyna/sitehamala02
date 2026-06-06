// suivi.js — Suivi de commande (DB-driven via public/api/order_track.php)

(() => {
  const $ = selector => document.querySelector(selector);
  const $$ = selector => Array.from(document.querySelectorAll(selector));

  const baseUrl = document.body?.dataset?.baseUrl || '/';

  const form = $('#trackForm');
  const orderInput = $('#orderNumber');
  const phoneInput = $('#phone');
  const orderErrorEl = $('#orderError');
  const phoneErrorEl = $('#phoneError');
  const messageEl = $('#message');

  const resultsEl = $('#results');
  const outOrderNumber = $('#outOrderNumber');
  const outStatusBadge = $('#outStatusBadge');
  const outUpdatedAt = $('#outUpdatedAt');
  const outTotal = $('#outTotal');
  const cancelAlert = $('#cancelAlert');
  const stepsRoot = $('#steps');
  const timelineRoot = $('#timeline');
  const itemsList = $('#itemsList');

  const FLOW = ['nouveau', 'confirme', 'en_preparation', 'en_livraison', 'livre'];
  const STATUS_LABEL = {
    nouveau: 'Nouvelle',
    confirme: 'Confirmee',
    en_preparation: 'Preparee',
    en_livraison: 'En livraison',
    livre: 'Livree',
    annulee: 'Annulee'
  };

  function normalizeStatus(value) {
    const v = String(value || '').trim().toLowerCase();
    if (v === 'nouvelle') return 'nouveau';
    if (v === 'confirmee') return 'confirme';
    if (v === 'preparee') return 'en_preparation';
    if (v === 'livree') return 'livre';
    return v;
  }

  function setMessage(type, text) {
    if (!messageEl) return;
    messageEl.textContent = text || '';
    messageEl.classList.remove('is-error', 'is-success');
    if (type === 'error') messageEl.classList.add('is-error');
    if (type === 'success') messageEl.classList.add('is-success');
  }

  function setFieldError(inputEl, errorEl, text) {
    if (!inputEl || !errorEl) return;
    const has = Boolean(text);
    errorEl.textContent = text || '';
    inputEl.classList.toggle('is-invalid', has);
    inputEl.setAttribute('aria-invalid', has ? 'true' : 'false');
  }

  function clearResults() {
    if (resultsEl) resultsEl.hidden = true;
    if (outOrderNumber) outOrderNumber.textContent = '—';
    if (outStatusBadge) {
      outStatusBadge.textContent = '—';
      outStatusBadge.dataset.status = '';
    }
    if (outUpdatedAt) outUpdatedAt.textContent = '—';
    if (outTotal) outTotal.textContent = '—';
    if (timelineRoot) timelineRoot.innerHTML = '';
    if (itemsList) itemsList.innerHTML = '';
    if (cancelAlert) cancelAlert.hidden = true;
    setStepsState(null, []);
  }

  function normalizeOrderNumber(value) {
    return String(value || '').trim().toUpperCase();
  }

  function isValidOrderNumber(value) {
    const v = normalizeOrderNumber(value);
    if (v.length < 6 || v.length > 80) return false;
    return /^[A-Z0-9-]+$/.test(v);
  }

  function normalizePhoneDigits(raw) {
    let v = String(raw || '').trim();
    v = v.replace(/\D+/g, '');
    if (v.startsWith('00') && v.length > 2) v = v.slice(2);
    return v;
  }

  function isValidPhone(raw) {
    const d = normalizePhoneDigits(raw);
    return /^\d+$/.test(d) && d.length >= 8 && d.length <= 15;
  }

  function statusLabel(status) {
    const key = normalizeStatus(status);
    return STATUS_LABEL[key] || key || '—';
  }

  function statusClientMessage(status) {
    const key = normalizeStatus(status);
    const map = {
      nouveau: 'Commande créée.',
      confirme: 'Commande confirmée.',
      en_preparation: 'Commande en préparation.',
      en_livraison: 'Commande en livraison.',
      livre: 'Commande livrée.',
      annulee: 'Commande annulée.'
    };
    return map[key] || 'Mise à jour de la commande.';
  }

  function cleanClientTimelineNote(rawNote) {
    let note = String(rawNote || '').trim();
    if (!note) return '';

    // Retire les sources internes entre parentheses: (admin), (system), etc.
    note = note.replace(/\s*\((?:admin|system|cron|worker|api)\)\s*/gi, ' ');

    // Retire aussi les mentions internes hors parentheses.
    note = note.replace(/\b(?:admin|system|cron|worker|api)\b/gi, '');

    // Nettoyage ponctuation/espaces.
    note = note.replace(/\s{2,}/g, ' ').trim();
    note = note.replace(/^[\-:;,.]+/, '').trim();
    note = note.replace(/\s+([:;,.!?])/g, '$1').trim();

    return note;
  }

  function formatFcfa(value) {
    const n = Number(value || 0);
    const safe = Number.isFinite(n) ? n : 0;
    return `${new Intl.NumberFormat('fr-FR').format(Math.round(safe))} FCFA`;
  }

  function formatDateTime(dt) {
    const raw = String(dt || '').trim();
    if (!raw) return '—';
    const parsed = new Date(raw.replace(' ', 'T'));
    if (!Number.isFinite(parsed.getTime())) return raw;
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  function setStepsState(status, history) {
    if (!stepsRoot) return;
    const stepEls = $$('#steps .step');
    const key = normalizeStatus(status);

    const resetStep = el => {
      el.classList.remove('is-done', 'is-complete', 'is-active', 'is-current', 'is-upcoming');
    };

    if (key === 'annulee') {
      let maxIdx = -1;
      const rows = Array.isArray(history) ? history : [];
      rows.forEach(r => {
        const st = normalizeStatus(r?.new_status ?? r?.status ?? '');
        const idx = FLOW.indexOf(st);
        if (idx > maxIdx) maxIdx = idx;
      });

      if (maxIdx < 0) maxIdx = 0;
      if (maxIdx > 3) maxIdx = 3; // ne jamais marquer "livree"

      stepEls.forEach(el => resetStep(el));
      stepEls.forEach((el, idx) => {
        if (idx <= maxIdx) el.classList.add('is-done', 'is-complete');
        else el.classList.add('is-upcoming');
      });
      return;
    }

    const activeIndex = FLOW.indexOf(key);
    stepEls.forEach((el, idx) => {
      resetStep(el);
      if (activeIndex === -1) {
        el.classList.add('is-upcoming');
        return;
      }
      if (idx < activeIndex) el.classList.add('is-done', 'is-complete');
      else if (idx === activeIndex) el.classList.add('is-active', 'is-current');
      else el.classList.add('is-upcoming');
    });
  }

  function renderTimeline(status, history) {
    if (!timelineRoot) return;
    timelineRoot.innerHTML = '';

    const statusKey = normalizeStatus(status);
    const activeIndex = FLOW.indexOf(statusKey);

    const events = Array.isArray(history) ? history : [];

    if (!events.length) {
      const item = document.createElement('div');
      item.className = 't-item is-active is-current';

      const dot = document.createElement('div');
      dot.className = 't-dot';
      dot.setAttribute('aria-hidden', 'true');

      const time = document.createElement('div');
      time.className = 't-time';
      time.textContent = '—';

      const title = document.createElement('div');
      title.className = 't-title';
      title.textContent = statusLabel(statusKey);

      const desc = document.createElement('p');
      desc.className = 't-desc';
      desc.textContent = 'Statut actuel.';

      item.appendChild(dot);
      item.appendChild(time);
      item.appendChild(title);
      item.appendChild(desc);

      timelineRoot.appendChild(item);
      return;
    }

    events.forEach((ev, i) => {
      const item = document.createElement('div');
      item.className = 't-item';

      const evStatus = normalizeStatus(ev?.new_status ?? ev?.status ?? '');
      const idx = FLOW.indexOf(evStatus);

      let state = 'upcoming';
      if (statusKey === 'annulee') {
        state = evStatus === 'annulee' ? 'active' : 'done';
      } else if (idx !== -1 && activeIndex !== -1) {
        state = idx < activeIndex ? 'done' : idx === activeIndex ? 'active' : 'upcoming';
      } else {
        state = i === events.length - 1 ? 'active' : 'done';
      }

      item.classList.add(state === 'done' ? 'is-done' : state === 'active' ? 'is-active' : 'is-upcoming');
      if (state === 'active') item.classList.add('is-current');

      const dot = document.createElement('div');
      dot.className = 't-dot';
      dot.setAttribute('aria-hidden', 'true');

      const time = document.createElement('div');
      time.className = 't-time';
      time.textContent = formatDateTime(ev?.changed_at ?? ev?.created_at ?? '');

      const title = document.createElement('div');
      title.className = 't-title';
      title.textContent = statusLabel(evStatus);

      const desc = document.createElement('p');
      desc.className = 't-desc';
      const clientNote = cleanClientTimelineNote(ev?.note);
      const genericUpdate = /^mise\s*a\s*jour\.?$/i.test(clientNote);
      desc.textContent = (!clientNote || genericUpdate) ? statusClientMessage(evStatus) : clientNote;

      item.appendChild(dot);
      item.appendChild(time);
      item.appendChild(title);
      item.appendChild(desc);

      timelineRoot.appendChild(item);
    });
  }

  function renderItems(items) {
    if (!itemsList) return;
    itemsList.innerHTML = '';

    const rows = Array.isArray(items) ? items : [];
    rows.forEach(it => {
      const row = document.createElement('div');
      row.className = 'item track-item';

      const top = document.createElement('div');
      top.className = 'track-item__top';

      const name = document.createElement('div');
      name.className = 'item-name track-item__name';
      name.textContent = String(it?.product_name_snapshot || it?.name || 'Produit');

      const qty = Number(it?.qty || 0);
      const unit = Number(it?.unit_price_snapshot || it?.unit_price || 0);

      const total = document.createElement('div');
      total.className = 'item-price track-item__total';
      total.textContent = formatFcfa(it?.line_total || 0);

      const bottom = document.createElement('div');
      bottom.className = 'track-item__bottom';

      const meta = document.createElement('div');
      meta.className = 'item-meta track-item__meta';
      meta.textContent = `Qte : ${Number.isFinite(qty) ? qty : 0} • Prix : ${formatFcfa(unit)}`;

      top.appendChild(name);
      top.appendChild(total);
      bottom.appendChild(meta);

      row.appendChild(top);
      row.appendChild(bottom);
      itemsList.appendChild(row);
    });
  }

  function renderOrder(order, items, history) {
    if (!resultsEl) return;

    resultsEl.hidden = false;

    const number = String(order?.order_number || '');
    const statusKey = normalizeStatus(order?.status || '');
    const statusText = statusLabel(statusKey);

    outOrderNumber.textContent = number || '—';
    outStatusBadge.textContent = statusText || '—';
    outStatusBadge.dataset.status = statusKey || '';

    const updatedAt = order?.updated_at || order?.created_at || '';
    outUpdatedAt.textContent = formatDateTime(updatedAt);
    outTotal.textContent = formatFcfa(order?.total_amount || 0);

    cancelAlert.hidden = statusKey !== 'annulee';
    setStepsState(statusKey, history);
    renderItems(items);
    renderTimeline(statusKey, history);
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

  function getCsrfToken() {
    const inputToken = form?.querySelector('input[name="_csrf"]');
    const inputValue = inputToken ? String(inputToken.value || '').trim() : '';
    if (inputValue) return inputValue;

    const metaToken = document.querySelector('meta[name="csrf-token"]');
    const metaValue = metaToken ? String(metaToken.getAttribute('content') || '').trim() : '';
    return metaValue || '';
  }

  async function lookupOrder(orderNumber, phone) {
    const endpoint = form?.dataset?.endpoint || `${baseUrl}public/api/order_track.php`;
    const csrfToken = getCsrfToken();
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken;
    }

    const res = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify({
        order_number: orderNumber,
        phone: phone
      })
    });

    const json = await res.json().catch(() => null);
    if (!json || typeof json !== 'object') return { ok: false, message: 'Erreur serveur.' };
    return json;
  }

  if (form) {
    clearResults();

    form.addEventListener('submit', async e => {
      e.preventDefault();

      const orderNumber = normalizeOrderNumber(orderInput?.value);
      const phone = String(phoneInput?.value || '').trim();

      setFieldError(orderInput, orderErrorEl, '');
      setFieldError(phoneInput, phoneErrorEl, '');
      setMessage('', '');

      if (!orderNumber) {
        clearResults();
        setFieldError(orderInput, orderErrorEl, 'Veuillez saisir votre numero de commande.');
        orderInput?.focus();
        return;
      }

      if (!isValidOrderNumber(orderNumber)) {
        clearResults();
        setFieldError(orderInput, orderErrorEl, 'Numero incorrect (ex: ML-2026-ABC123).');
        orderInput?.focus();
        return;
      }

      if (!phone) {
        clearResults();
        setFieldError(phoneInput, phoneErrorEl, 'Veuillez saisir votre telephone.');
        phoneInput?.focus();
        return;
      }

      if (!isValidPhone(phone)) {
        clearResults();
        setFieldError(phoneInput, phoneErrorEl, 'Telephone invalide (8 a 15 chiffres, formats locaux ou internationaux acceptes).');
        phoneInput?.focus();
        return;
      }

      setMessage('success', 'Recherche en cours...');

      try {
        const data = await lookupOrder(orderNumber, phone);
        if (!data.ok) {
          clearResults();
          setMessage('error', data.message || 'Commande introuvable.');
          return;
        }

        setMessage('success', 'Commande trouvee.');
        renderOrder(data.order || {}, data.items || [], data.history || []);
      } catch (err) {
        clearResults();
        setMessage('error', 'Impossible de faire le suivi pour le moment. Veuillez reessayer.');
      }
    });

    [orderInput, phoneInput].forEach(el => {
      el?.addEventListener?.('input', () => {
        setFieldError(orderInput, orderErrorEl, '');
        setFieldError(phoneInput, phoneErrorEl, '');
        setMessage('', '');
      });
    });
  }

  initAccordion();
})();


