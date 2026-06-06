// mes-commandes.js — Liste des commandes du compte (DB-driven via public/api/my_orders.php)
(() => {
  const baseUrl = document.body?.dataset?.baseUrl || '/';

  const notice = document.getElementById('ordersNotice');
  const list = document.getElementById('ordersList');

  const setNotice = (type, text) => {
    if (!notice) return;
    notice.classList.remove('is-error', 'is-success', 'is-hidden');
    if (!text) {
      notice.textContent = '';
      notice.classList.add('is-hidden');
      return;
    }
    notice.textContent = text;
    notice.classList.add(type === 'success' ? 'is-success' : 'is-error');
  };

  const STATUS_LABEL = {
    nouvelle: 'Nouvelle',
    confirmee: 'Confirmee',
    preparee: 'Preparee',
    en_livraison: 'En livraison',
    livree: 'Livree',
    annulee: 'Annulee'
  };

  function statusLabel(status) {
    const key = String(status || '').trim().toLowerCase();
    return STATUS_LABEL[key] || key || '-';
  }

  function statusClass(status) {
    const key = String(status || '').trim().toLowerCase();
    if (key === 'nouvelle') return 'status-pill--pending';
    if (key === 'confirmee') return 'status-pill--confirmed';
    if (key === 'preparee') return 'status-pill--preparing';
    if (key === 'en_livraison') return 'status-pill--shipping';
    if (key === 'livree') return 'status-pill--delivered';
    if (key === 'annulee') return 'status-pill--cancelled';
    return 'status-pill--default';
  }

  function formatFcfa(value) {
    const n = Number(value || 0);
    const safe = Number.isFinite(n) ? n : 0;
    return `${new Intl.NumberFormat('fr-FR').format(Math.round(safe))} FCFA`;
  }

  function formatDateTime(dt) {
    const raw = String(dt || '').trim();
    if (!raw) return '-';
    const parsed = new Date(raw.replace(' ', 'T'));
    if (!Number.isFinite(parsed.getTime())) return raw;
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  async function loadOrders() {
    const res = await fetch(`${baseUrl}public/api/my_orders.php`, { method: 'GET', credentials: 'same-origin' });
    const json = await res.json().catch(() => null);
    if (!json || typeof json !== 'object') throw new Error('invalid_json');
    return json;
  }

  function renderEmptyState() {
    if (!list) return;
    list.innerHTML = '';

    const empty = document.createElement('div');
    empty.className = 'orders-empty';

    const title = document.createElement('h3');
    title.className = 'orders-empty__title';
    title.textContent = 'Vous n\'avez pas encore de commande';

    const text = document.createElement('p');
    text.className = 'orders-empty__text';
    text.textContent = 'Passez votre premiere commande pour la retrouver ici.';

    const cta = document.createElement('a');
    cta.className = 'btn btn-secondary';
    cta.href = `${baseUrl}pages/catalogue.php`;
    cta.textContent = 'Decouvrir la boutique';

    empty.appendChild(title);
    empty.appendChild(text);
    empty.appendChild(cta);
    list.appendChild(empty);
  }

  function renderOrders(orders) {
    if (!list) return;
    list.innerHTML = '';

    if (!Array.isArray(orders) || orders.length === 0) {
      renderEmptyState();
      return;
    }

    orders.forEach(o => {
      const row = document.createElement('div');
      row.className = 'order-row';

      const top = document.createElement('div');
      top.className = 'order-row__top';

      const numberWrap = document.createElement('div');
      numberWrap.className = 'order-number-wrap';

      const numberLabel = document.createElement('p');
      numberLabel.className = 'order-number-label';
      numberLabel.textContent = 'Numero de commande';

      const number = document.createElement('p');
      number.className = 'order-number';
      number.textContent = String(o?.order_number || '-').trim() || '-';

      const pill = document.createElement('span');
      pill.className = `status-pill ${statusClass(o?.status)}`;
      pill.textContent = statusLabel(o?.status);

      numberWrap.appendChild(numberLabel);
      numberWrap.appendChild(number);
      top.appendChild(numberWrap);
      top.appendChild(pill);

      const summary = document.createElement('div');
      summary.className = 'order-summary';

      const dateItem = document.createElement('div');
      dateItem.className = 'order-summary__item';
      const dateLabel = document.createElement('span');
      dateLabel.textContent = 'Date';
      const dateValue = document.createElement('strong');
      dateValue.textContent = formatDateTime(o?.created_at);
      dateItem.appendChild(dateLabel);
      dateItem.appendChild(dateValue);

      const totalItem = document.createElement('div');
      totalItem.className = 'order-summary__item';
      const totalLabel = document.createElement('span');
      totalLabel.textContent = 'Total';
      const totalValue = document.createElement('strong');
      totalValue.textContent = formatFcfa(o?.total_amount || 0);
      totalItem.appendChild(totalLabel);
      totalItem.appendChild(totalValue);

      summary.appendChild(dateItem);
      summary.appendChild(totalItem);

      const actions = document.createElement('div');
      actions.className = 'order-actions';

      const track = document.createElement('a');
      track.className = 'btn btn-primary';
      track.href = `${baseUrl}pages/suivi.php?order_number=${encodeURIComponent(String(o?.order_number || ''))}`;
      track.textContent = 'Suivre la commande';

      actions.appendChild(track);

      row.appendChild(top);
      row.appendChild(summary);
      row.appendChild(actions);

      list.appendChild(row);
    });
  }

  (async () => {
    try {
      setNotice('success', 'Chargement...');
      const data = await loadOrders();
      if (!data.ok) {
        setNotice('error', data.message || 'Impossible de charger vos commandes.');
        return;
      }
      setNotice('', '');
      renderOrders(Array.isArray(data.orders) ? data.orders : []);
    } catch (err) {
      setNotice('error', 'Impossible de charger vos commandes.');
    }
  })();
})();
