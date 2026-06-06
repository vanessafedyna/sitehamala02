// assets/js/panier.js (V1)

document.addEventListener('DOMContentLoaded', function () {
  // Auto update quantité (dégradé: marche aussi sans JS via bouton)
  const forms = Array.from(document.querySelectorAll('[data-qty-form]'));
  const timers = new WeakMap();

  forms.forEach(form => {
    const input = form.querySelector('.qty-input');
    if (!input) return;

    const submitForm = () => {
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    };

    const parseBound = (value, fallback) => {
      const n = parseInt(value, 10);
      return Number.isFinite(n) ? n : fallback;
    };

    const min = parseBound(input.getAttribute('min'), 1);
    const maxAttr = input.getAttribute('max');
    const max = maxAttr !== null ? parseBound(maxAttr, Number.POSITIVE_INFINITY) : Number.POSITIVE_INFINITY;

    const setQty = nextValue => {
      if (!Number.isFinite(nextValue)) return;
      const bounded = Math.max(min, Math.min(max, nextValue));
      input.value = String(bounded);
      input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    input.addEventListener('input', () => {
      const prev = timers.get(form);
      if (prev) clearTimeout(prev);

      const t = setTimeout(() => {
        const value = parseInt(input.value, 10);
        if (!Number.isFinite(value) || value < 1) return;
        submitForm();
      }, 650);

      timers.set(form, t);
    });

    const minusBtn = form.querySelector('[data-qty-minus]');
    const plusBtn = form.querySelector('[data-qty-plus]');

    if (minusBtn) {
      minusBtn.addEventListener('click', () => {
        const value = parseBound(input.value, min);
        setQty(value - 1);
      });
    }

    if (plusBtn) {
      plusBtn.addEventListener('click', () => {
        const value = parseBound(input.value, min);
        setQty(value + 1);
      });
    }
  });

  // Accordéon FAQ (accessible)
  const accordion = document.querySelector('[data-accordion]');
  if (accordion) {
    accordion.addEventListener('click', e => {
      const trigger = e.target.closest('.accordion-trigger');
      if (!trigger) return;

      const panelId = trigger.getAttribute('aria-controls');
      const panel = panelId ? document.getElementById(panelId) : null;
      const isOpen = trigger.getAttribute('aria-expanded') === 'true';

      trigger.setAttribute('aria-expanded', String(!isOpen));
      if (panel) panel.hidden = isOpen;
    });
  }
});

