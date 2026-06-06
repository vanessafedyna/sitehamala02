// main.js — comportement global (menu, dropdown, effets légers)

(() => {
  document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initDropdowns();
    initDesktopCatalogueSubmenus();
    initMobileCatalogueAccordion();
    initMobileSubdropdowns();
    initAccountUi();
    updateCartCount();
    highlightActiveLinks();
    initMagicBentoProductCards();
  });

  function getFocusable(root) {
    if (!root) return [];
    const selector = [
      'a[href]',
      'button:not([disabled])',
      'input:not([disabled])',
      'select:not([disabled])',
      'textarea:not([disabled])',
      '[tabindex]:not([tabindex="-1"])'
    ].join(',');
    return Array.from(root.querySelectorAll(selector)).filter(el => {
      const isHidden = el.hasAttribute('hidden') || el.getAttribute('aria-hidden') === 'true';
      return !isHidden;
    });
  }

  // ===== Mobile menu =====
  function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (!mobileMenuBtn || !mobileMenu) return;

    let lastFocus = null;

    const setMobileMenuOpen = isOpen => {
      mobileMenu.classList.toggle('active', isOpen);
      mobileMenuBtn.setAttribute('aria-expanded', String(isOpen));
      document.body.classList.toggle('is-menu-open', isOpen);

      if (isOpen) {
        mobileMenu.removeAttribute('hidden');
        lastFocus = document.activeElement;
        const first = getFocusable(mobileMenu)[0];
        first?.focus();
        document.addEventListener('keydown', onTrapKeydown, true);
      } else {
        mobileMenu.setAttribute('hidden', '');
        document.removeEventListener('keydown', onTrapKeydown, true);
        lastFocus?.focus?.();
        lastFocus = null;
      }
    };

    const onTrapKeydown = e => {
      if (!mobileMenu.classList.contains('active')) return;

      if (e.key === 'Escape') {
        e.preventDefault();
        setMobileMenuOpen(false);
        mobileMenuBtn.focus();
        return;
      }

      if (e.key !== 'Tab') return;

      const focusable = [mobileMenuBtn, ...getFocusable(mobileMenu)];
      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const active = document.activeElement;

      if (e.shiftKey && active === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
      }
    };

    mobileMenuBtn.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.contains('active');
      setMobileMenuOpen(!isOpen);
    });

    document.addEventListener('click', event => {
      if (!mobileMenu.classList.contains('active')) return;
      if (!mobileMenu.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
        setMobileMenuOpen(false);
      }
    });
  }

  // ===== Desktop dropdowns =====
  function initDropdowns() {
    const dropdowns = Array.from(document.querySelectorAll('header .dropdown[data-dropdown]'));
    if (!dropdowns.length) return;

    // Le dropdown compte est géré séparément (patch nav-account) pour éviter les conflits.
    const managedDropdowns = dropdowns.filter(d => !d.classList.contains('nav-account'));
    if (!managedDropdowns.length) return;

    const canHover = () =>
      Boolean(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);

    const closeAllDropdowns = exceptDropdown => {
      managedDropdowns.forEach(dropdown => {
        if (exceptDropdown && dropdown === exceptDropdown) return;
        dropdown.classList.remove('is-open');
        dropdown.querySelector('.dropdown-toggle')?.setAttribute('aria-expanded', 'false');
      });
    };

    managedDropdowns.forEach(dropdown => {
      const toggle = dropdown.querySelector('.dropdown-toggle');
      const menu = dropdown.querySelector('.dropdown-menu');
      if (!toggle || !menu) return;

      // Temporisation du survol pour éviter les ouvertures accidentelles.
      let hoverTimer = null;

      const focusFirstItem = () => {
        const firstItem = getFocusable(menu)[0];
        firstItem?.focus?.();
      };

      const openDropdown = ({ focus = true } = {}) => {
        closeAllDropdowns(dropdown);
        dropdown.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        if (focus) window.setTimeout(focusFirstItem, 0);
      };

      const closeDropdown = ({ restoreFocus = false } = {}) => {
        dropdown.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) toggle.focus();
      };

      dropdown.addEventListener('mouseenter', () => {
        if (!canHover()) return;
        if (hoverTimer) window.clearTimeout(hoverTimer);
        hoverTimer = window.setTimeout(() => {
          openDropdown({ focus: false });
        }, 160);
      });

      dropdown.addEventListener('mouseleave', () => {
        if (!canHover()) return;
        if (hoverTimer) window.clearTimeout(hoverTimer);
        hoverTimer = null;
        closeDropdown();
      });

      toggle.addEventListener('click', () => {
        if (hoverTimer) window.clearTimeout(hoverTimer);
        hoverTimer = null;
        // Certains menus (ex: Catalogue) doivent naviguer au clic.
        if (dropdown?.dataset?.dropdownClick === 'navigate') {
          return;
        }
        const isOpen = dropdown.classList.contains('is-open');
        if (isOpen) closeDropdown({ restoreFocus: true });
        else openDropdown({ focus: true });
      });

      toggle.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          openDropdown({ focus: true });
          return;
        }

        // Si le menu navigue au clic, conserver le comportement clavier natif.
        if (dropdown?.dataset?.dropdownClick !== 'navigate' && (e.key === 'Enter' || e.key === ' ')) {
          e.preventDefault();
          const isOpen = dropdown.classList.contains('is-open');
          if (isOpen) closeDropdown({ restoreFocus: true });
          else openDropdown({ focus: true });
          return;
        }

        if (e.key === 'Escape') {
          e.preventDefault();
          closeDropdown({ restoreFocus: true });
        }
      });

      menu.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
          e.preventDefault();
          closeDropdown({ restoreFocus: true });
        }
      });
    });

    document.addEventListener('click', event => {
      const clickedInsideAnyDropdown = managedDropdowns.some(dropdown => dropdown.contains(event.target));
      if (!clickedInsideAnyDropdown) closeAllDropdowns();
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeAllDropdowns();
    });
  }

  // ===== Account UI (session-based) =====
  function initAccountUi() {
    const baseUrl = document.body?.dataset?.baseUrl || '/';

    // Capture phase: certains menus arrêtent la propagation des clics (stopPropagation).
    document.addEventListener('click', async e => {
      const target = e.target?.closest?.('[data-action="logout"]');
      if (!target) return;
      e.preventDefault();

      try {
        await fetch(`${baseUrl}public/api/logout.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin'
        });
      } catch (err) {
        // ignore network errors; fallback to reload
      }

      window.location.href = `${baseUrl}index.php`;
    }, true);
  }

  // ===== Mobile catalogue accordion =====
  function initMobileCatalogueAccordion() {
    const toggles = Array.from(document.querySelectorAll('.mobile-dropdown-toggle'));
    if (!toggles.length) return;

    toggles.forEach(toggle => {
      const menuId = toggle.getAttribute('aria-controls');
      const menu =
        (menuId && document.getElementById(menuId)) ? document.getElementById(menuId) : toggle.nextElementSibling;
      if (!menu) return;

      toggle.addEventListener('click', () => {
        const isOpen = menu.classList.contains('active');

        toggles.forEach(otherToggle => {
          if (otherToggle === toggle) return;
          const otherMenuId = otherToggle.getAttribute('aria-controls');
          const otherMenu =
            (otherMenuId && document.getElementById(otherMenuId))
              ? document.getElementById(otherMenuId)
              : otherToggle.nextElementSibling;
          otherMenu?.classList.remove('active');
          otherToggle.setAttribute('aria-expanded', 'false');
        });

        menu.classList.toggle('active', !isOpen);
        toggle.setAttribute('aria-expanded', String(!isOpen));
      });
    });
  }

  // ===== Mobile sous-accordéon (Femme / Homme) =====
  function initDesktopCatalogueSubmenus() {
    const toggles = Array.from(document.querySelectorAll('.dropdown-menu .submenu-parent'));
    if (!toggles.length) return;

    toggles.forEach(toggle => {
      const item = toggle.closest('.has-submenu');
      const menuId = toggle.getAttribute('aria-controls');
      const menu = (menuId && document.getElementById(menuId))
        ? document.getElementById(menuId)
        : item?.querySelector('.submenu');
      if (!item || !menu) return;

      const syncState = open => {
        item.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      };

      syncState(item.classList.contains('is-open'));

      toggle.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();

        const isOpen = item.classList.contains('is-open');
        const scope = item.parentElement;
        if (scope) {
          scope.querySelectorAll('.has-submenu.is-open').forEach(other => {
            if (other === item) return;
            other.classList.remove('is-open');
            other.querySelector('.submenu-parent')?.setAttribute('aria-expanded', 'false');
          });
        }

        syncState(!isOpen);
      });
    });
  }

  function initMobileSubdropdowns() {
    const toggles = Array.from(document.querySelectorAll('.mobile-subdropdown-toggle'));
    if (!toggles.length) return;

    toggles.forEach(toggle => {
      const menuId = toggle.getAttribute('aria-controls');
      const menu = (menuId && document.getElementById(menuId))
        ? document.getElementById(menuId)
        : toggle.nextElementSibling;
      if (!menu) return;

      // Sync aria-expanded with server-rendered .active class
      if (menu.classList.contains('active')) {
        toggle.setAttribute('aria-expanded', 'true');
      }

      toggle.addEventListener('click', () => {
        const isOpen = menu.classList.contains('active');
        menu.classList.toggle('active', !isOpen);
        toggle.setAttribute('aria-expanded', String(!isOpen));
      });
    });
  }

  // ===== Active state (fallback JS) =====
  function highlightActiveLinks() {
    const currentPage = window.location.pathname.split('/').pop();
    if (!currentPage) return;

    const links = document.querySelectorAll('.nav-menu a, .nav-actions a');
    links.forEach(link => {
      const href = link.getAttribute('href') || '';
      if (!href) return;
      if (href.split('/').pop() === currentPage) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
  }

  // ===== Cart badge (server-rendered) =====
  function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    if (!cartCount) return;
    const fromAttr = parseInt(cartCount.getAttribute('data-count') || '', 10);
    if (Number.isFinite(fromAttr)) {
      cartCount.textContent = String(fromAttr);
      if (fromAttr > 0) cartCount.hidden = false;
      else cartCount.hidden = true;
    }
  }

  // ===== MagicBento (version légère) =====
  function initMagicBentoProductCards() {
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    const grids = Array.from(document.querySelectorAll('.products-grid'));
    if (!grids.length) return;

    const particleCount = 10;
    const spotlightRadius = 360;
    const proximity = spotlightRadius * 0.5;
    const fadeDistance = spotlightRadius * 0.9;

    function setCardGlow(card, mouseX, mouseY, intensity) {
      const rect = card.getBoundingClientRect();
      const relativeX = ((mouseX - rect.left) / rect.width) * 100;
      const relativeY = ((mouseY - rect.top) / rect.height) * 100;

      card.style.setProperty('--mb-glow-x', `${relativeX}%`);
      card.style.setProperty('--mb-glow-y', `${relativeY}%`);
      card.style.setProperty('--mb-glow-intensity', String(intensity));
      card.style.setProperty('--mb-glow-radius', `${spotlightRadius}px`);
    }

    function resetGrid(grid) {
      grid.querySelectorAll('.product-card').forEach(card => {
        card.style.setProperty('--mb-glow-intensity', '0');
      });
    }

    grids.forEach(grid => {
      const cards = Array.from(grid.querySelectorAll('.product-card'));
      if (!cards.length) return;

      cards.forEach(card => {
        function ensureParticles() {
          let container = card.querySelector('.mb-particles');
          if (!container) {
            container = document.createElement('div');
            container.className = 'mb-particles';
            container.setAttribute('aria-hidden', 'true');
            card.appendChild(container);
          }
          return container;
        }

        function spawnParticles() {
          const container = ensureParticles();
          container.innerHTML = '';

          for (let i = 0; i < particleCount; i += 1) {
            const particle = document.createElement('span');
            particle.className = 'mb-particle';

            const left = Math.random() * 100;
            const top = Math.random() * 100;
            const size = 3 + Math.random() * 4;
            const dx = (Math.random() - 0.5) * 80;
            const dy = (Math.random() - 0.5) * 80;
            const drift = 2.6 + Math.random() * 2.2;
            const twinkle = 1.2 + Math.random() * 1.6;
            const delay = Math.random() * 0.6;

            particle.style.left = `${left}%`;
            particle.style.top = `${top}%`;
            particle.style.setProperty('--mb-size', `${size}px`);
            particle.style.setProperty('--mb-dx', `${dx}px`);
            particle.style.setProperty('--mb-dy', `${dy}px`);
            particle.style.setProperty('--mb-drift', `${drift}s`);
            particle.style.setProperty('--mb-twinkle', `${twinkle}s`);
            particle.style.setProperty('--mb-delay', `${delay}s`);

            container.appendChild(particle);
          }
        }

        function clearParticles() {
          card.querySelector('.mb-particles')?.replaceChildren();
        }

        let touchClearTimeout = null;

        card.addEventListener('mouseenter', spawnParticles);
        card.addEventListener('mouseleave', () => {
          if (touchClearTimeout) window.clearTimeout(touchClearTimeout);
          clearParticles();
        });

        card.addEventListener('pointerdown', e => {
          if (e.pointerType !== 'touch') return;
          spawnParticles();
          setCardGlow(card, e.clientX, e.clientY, 1);
          if (touchClearTimeout) window.clearTimeout(touchClearTimeout);
          touchClearTimeout = window.setTimeout(() => {
            clearParticles();
            card.style.setProperty('--mb-glow-intensity', '0');
          }, 900);
        });
      });

      let rafId = null;
      let lastEvent = null;
      let lastPointerType = 'mouse';

      function update() {
        rafId = null;
        if (!lastEvent) return;
        if (lastPointerType === 'touch') return;

        const mouseX = lastEvent.clientX;
        const mouseY = lastEvent.clientY;

        cards.forEach(card => {
          const rect = card.getBoundingClientRect();
          const centerX = rect.left + rect.width / 2;
          const centerY = rect.top + rect.height / 2;
          const distance =
            Math.hypot(mouseX - centerX, mouseY - centerY) - Math.max(rect.width, rect.height) / 2;
          const effectiveDistance = Math.max(0, distance);

          let glowIntensity = 0;
          if (effectiveDistance <= proximity) glowIntensity = 1;
          else if (effectiveDistance <= fadeDistance) {
            glowIntensity = (fadeDistance - effectiveDistance) / (fadeDistance - proximity);
          }

          setCardGlow(card, mouseX, mouseY, glowIntensity);
        });
      }

      grid.addEventListener('mousemove', e => {
        lastEvent = e;
        lastPointerType = 'mouse';
        if (rafId) return;
        rafId = window.requestAnimationFrame(update);
      });

      grid.addEventListener('pointermove', e => {
        lastEvent = e;
        lastPointerType = e.pointerType || 'mouse';
        if (rafId) return;
        rafId = window.requestAnimationFrame(update);
      });

      grid.addEventListener('mouseleave', () => {
        lastEvent = null;
        resetGrid(grid);
      });
    });
  }
})();

(() => {
  const elements = Array.from(document.querySelectorAll('.reveal'));
  if (!elements.length) return;

  const prefersReducedMotion =
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const makeVisible = (el) => el.classList.add('is-visible');
  const init = (el) => el.classList.add('reveal--init');

  if (prefersReducedMotion) {
    elements.forEach(makeVisible);
    return;
  }

  const inView = (el) => {
    const rect = el.getBoundingClientRect();
    return rect.top < window.innerHeight * 0.92 && rect.bottom > 0;
  };

  // Init without hiding on load for content already visible.
  elements.forEach(el => {
    if (inView(el)) {
      init(el);
      makeVisible(el);
    } else {
      init(el);
    }
  });

  if (!('IntersectionObserver' in window)) {
    elements.forEach(makeVisible);
    return;
  }

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        makeVisible(entry.target);
        obs.unobserve(entry.target);
      });
    },
    { threshold: 0.12, rootMargin: '0px 0px -10% 0px' }
  );

  elements.forEach(el => {
    if (!el.classList.contains('is-visible')) observer.observe(el);
  });
})();

(function () {
  const toggleBtn = document.getElementById('reviewsToggleBtn');
  const formWrap = document.getElementById('reviewsFormWrap');
  const form = document.getElementById('reviewsForm');
  const notice = document.getElementById('reviewsNotice');

  if (!toggleBtn || !formWrap || !form || !notice) return;

  const submitBtn = document.getElementById('reviewsSubmitBtn');

  const getCsrfToken = () => {
    const inputToken = form.querySelector('input[name="_csrf"]');
    const inputValue = inputToken ? String(inputToken.value || '').trim() : '';
    if (inputValue) return inputValue;

    const metaToken = document.querySelector('meta[name="csrf-token"]');
    const metaValue = metaToken ? String(metaToken.getAttribute('content') || '').trim() : '';
    return metaValue || '';
  };

  const setNotice = (message, type) => {
    notice.textContent = message || '';
    notice.hidden = !message;
    notice.classList.toggle('is-success', type === 'success');
    notice.classList.toggle('is-error', type === 'error');
  };

  const openForm = () => {
    formWrap.hidden = false;
    toggleBtn.setAttribute('aria-expanded', 'true');
    setNotice('', 'success');
    window.setTimeout(() => {
      formWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
      document.getElementById('reviewName')?.focus();
    }, 0);
  };

  const closeForm = () => {
    formWrap.hidden = true;
    toggleBtn.setAttribute('aria-expanded', 'false');
  };

  const toggleForm = () => {
    if (formWrap.hidden) openForm();
    else closeForm();
  };

  toggleBtn.addEventListener('click', e => {
    e.preventDefault();
    toggleForm();
  });

  document.addEventListener('click', e => {
    const target = e.target instanceof Element ? e.target : null;
    if (!target) return;

    const openBtn = target.closest('[data-action="reviews-open"]');
    if (openBtn) {
      e.preventDefault();
      openForm();
      return;
    }

    const closeBtn = target.closest('[data-action="reviews-close"]');
    if (closeBtn) {
      e.preventDefault();
      closeForm();
    }
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    setNotice('', 'success');

    const endpoint = form.getAttribute('data-endpoint') || '';
    if (!endpoint) {
      setNotice('Erreur de configuration (endpoint manquant).', 'error');
      return;
    }

    const name = String(form.elements.namedItem('name')?.value || '').trim();
    const city = String(form.elements.namedItem('city')?.value || '').trim();
    const rating = Number(form.elements.namedItem('rating')?.value || 0);
    const message = String(form.elements.namedItem('message')?.value || '').trim();

    if (!name || !city || !Number.isFinite(rating) || rating < 1 || rating > 5 || message.length < 10) {
      setNotice('Veuillez compléter tous les champs (message: 10 caractères minimum).', 'error');
      return;
    }

    const payload = { name, city, rating, message };

    try {
      if (submitBtn) submitBtn.disabled = true;
      const csrfToken = getCsrfToken();
      const headers = { 'Content-Type': 'application/json' };
      if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
      }

      const res = await fetch(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(payload),
      });

      let data = null;
      try {
        data = await res.json();
      } catch {
        data = null;
      }

      if (!res.ok || !data || data.ok !== true) {
        const msg =
          data && typeof data.message === 'string' && data.message
            ? data.message
            : 'Impossible d’envoyer votre avis.';
        setNotice(msg, 'error');
        return;
      }

      form.reset();
      closeForm();
      setNotice('Merci ! Votre avis sera publié après validation.', 'success');
    } catch {
      setNotice('Impossible d’envoyer votre avis. Réessayez plus tard.', 'error');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });
})();

// Account dropdown (navbar) — toggle propre + clic extérieur
(function () {
  const wrapper = document.querySelector('.nav-account');
  if (!wrapper) return;

  const btn = wrapper.querySelector('.nav-account__btn');
  const menu = wrapper.querySelector('.nav-account__menu');
  if (!btn || !menu) return;

  // Portal pour éviter les bugs de stacking/backdrop-filter du header sticky.
  const menuHome = menu.parentNode;
  const menuPlaceholder = document.createElement('span');
  menuPlaceholder.style.display = 'none';
  menuHome?.insertBefore(menuPlaceholder, menu);

  const setExpanded = expanded => btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');

  const focusFirstItem = () => {
    const first = menu.querySelector(
      'a:not([hidden]):not([tabindex="-1"]), button:not([hidden]):not([tabindex="-1"])'
    );
    first?.focus();
  };

  const portalMenuToBody = () => {
    if (menu.parentNode === document.body) return;
    document.body.appendChild(menu);
  };

  const restoreMenuToHeader = () => {
    if (!menuPlaceholder.parentNode) return;
    menuPlaceholder.parentNode.insertBefore(menu, menuPlaceholder);
  };

  const positionMenu = () => {
    const rect = btn.getBoundingClientRect();
    const gap = 10;
    const right = Math.max(12, window.innerWidth - rect.right);
    const top = Math.max(8, rect.bottom + gap);

    // Utiliser un positionnement fixed pour éviter les soucis de stacking/backdrop-filter
    // avec le header sticky (menu "derrière" la nav sur certains navigateurs).
    menu.style.position = 'fixed';
    menu.style.top = `${top}px`;
    menu.style.right = `${right}px`;
    menu.style.left = 'auto';
    menu.style.zIndex = '10000';
  };

  const resetMenuPosition = () => {
    menu.style.position = '';
    menu.style.top = '';
    menu.style.right = '';
    menu.style.left = '';
    menu.style.zIndex = '';
  };

  const openMenu = () => {
    portalMenuToBody();
    positionMenu();
    menu.classList.add('is-open');
    setExpanded(true);
    window.setTimeout(focusFirstItem, 0);
  };

  const closeMenu = ({ restoreFocus = false } = {}) => {
    menu.classList.remove('is-open');
    setExpanded(false);
    resetMenuPosition();
    restoreMenuToHeader();
    if (restoreFocus) btn.focus();
  };

  const isOpen = () => menu.classList.contains('is-open');

  btn.addEventListener('click', e => {
    e.preventDefault();
    e.stopPropagation();
    if (isOpen()) closeMenu({ restoreFocus: true });
    else openMenu();
  });

  menu.addEventListener('click', e => e.stopPropagation());

  document.addEventListener('click', e => {
    if (!wrapper.contains(e.target)) closeMenu();
  });

  // Fermer le menu si on scroll/resize (évite qu'il reste ouvert et masque la page).
  window.addEventListener('scroll', () => {
    if (isOpen()) closeMenu();
  }, { passive: true });

  window.addEventListener('resize', () => {
    if (isOpen()) closeMenu();
  });

  window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && isOpen()) {
      e.preventDefault();
      closeMenu({ restoreFocus: true });
    }
  });
})();

(() => {
  const seen = new WeakSet();

  document.addEventListener('error', (event) => {
    const target = event.target;
    if (!target || target.tagName !== 'IMG') return;
    if (seen.has(target)) return;

    const fallback = target.getAttribute('data-fallback-src');
    if (!fallback) return;

    if (target.getAttribute('src') === fallback) return;

    seen.add(target);
    target.setAttribute('src', fallback);
  }, true);
})();

(() => {
  const buttons = Array.from(document.querySelectorAll('.js-featured-save'));
  if (!buttons.length) return;

  const findForm = (btn) => {
    const formId = btn.getAttribute('data-form-id') || btn.getAttribute('form') || '';
    if (formId) {
      const form = document.getElementById(formId);
      if (form) return form;
    }
    const tr = btn.closest('tr');
    if (!tr) return null;
    return tr.querySelector('form.js-featured-form');
  };

  const findMsg = (form) => {
    const formId = form && form.getAttribute('id');
    if (!formId) return null;
    return document.querySelector(`.js-featured-msg[data-form-id="${CSS.escape(formId)}"]`);
  };

  const setMsg = (form, { ok, text }) => {
    const el = findMsg(form);
    if (!el) return;
    el.classList.remove('is-ok', 'is-err');
    el.classList.add(ok ? 'is-ok' : 'is-err');
    el.textContent = text;
  };

  const updateRankDisabled = (form) => {
    const checkbox = form.querySelector('input[name="is_featured"]');
    const rank = document.querySelector(`input[name="featured_rank"][form="${CSS.escape(form.id)}"]`);
    if (!rank) return;
    const checked = checkbox && checkbox.checked;
    rank.disabled = !checked;
    if (!checked) rank.value = '';
  };

  document.querySelectorAll('form.js-featured-form').forEach(form => {
    const checkbox = form.querySelector('input[name="is_featured"]');
    if (checkbox) {
      checkbox.addEventListener('change', () => updateRankDisabled(form));
      updateRankDisabled(form);
    }
  });

  buttons.forEach(btn => {
    btn.addEventListener('click', async () => {
      const form = findForm(btn);
      if (!form) return;

      const endpoint = form.getAttribute('data-endpoint');
      if (!endpoint) {
        setMsg(form, { ok: false, text: 'Endpoint manquant.' });
        return;
      }

      updateRankDisabled(form);

      btn.disabled = true;
      setMsg(form, { ok: true, text: 'Sauvegarde...' });

      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json' },
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.ok) {
          const msg = data && data.message ? data.message : 'Erreur.';
          setMsg(form, { ok: false, text: msg });
          return;
        }

        setMsg(form, { ok: true, text: 'Sauvegardé.' });
      } catch (e) {
        setMsg(form, { ok: false, text: 'Erreur réseau.' });
      } finally {
        btn.disabled = false;
      }
    });
  });
})();
