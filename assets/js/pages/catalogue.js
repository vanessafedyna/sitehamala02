// Catalogue (V1) — interactions front-end
// Note: si la page est en mode "server" (DB/pagination côté PHP), on n'intercepte pas la recherche/pagination.

(() => {
  function isServerMode() {
    const modeEl = document.querySelector('[data-catalogue-mode]');
    return (modeEl?.getAttribute('data-catalogue-mode') || '') === 'server';
  }

  function initCataloguePage() {
    const serverMode = isServerMode();
    const urlParams = new URLSearchParams(window.location.search);
    const categoryFromUrl = urlParams.get('categorie');

    initCategorySelect(categoryFromUrl);
    if (!serverMode) initSearchBar();
    initAddToCart();
    initFaqAccordion();
    if (!serverMode) initPagination();

    if (!serverMode && categoryFromUrl) filterProductsByCategory(categoryFromUrl);

    if (!serverMode) {
      const initialPage = getActivePageNumber();
      if (Number.isFinite(initialPage)) updatePaginationInfo(initialPage);
    }
  }

  function initCategorySelect(categoryFromUrl) {
    const select = document.getElementById('categorySelect');
    if (!select) return;

    if (categoryFromUrl) select.value = categoryFromUrl;

    select.addEventListener('change', () => {
      const selected = String(select.value || '').trim();
      /* préserver la collection (?cat=slug) si présente */
      const params = new URLSearchParams(window.location.search);
      params.delete('page');
      if (selected) params.set('categorie', selected);
      else params.delete('categorie');

      const qs = params.toString();
      window.location.href = qs ? `catalogue.php?${qs}` : 'catalogue.php';
    });
  }

  function initSearchBar() {
    const form = document.querySelector('.search-bar');
    if (!form) return;

    form.addEventListener('submit', e => {
      e.preventDefault();
      const input = form.querySelector('.search-input');
      const term = String(input?.value || '').trim().toLowerCase();
      if (!term) return;

      searchProducts(term);
      if (input) input.value = '';
    });
  }

  function initAddToCart() {
    const buttons = Array.from(document.querySelectorAll('.add-to-cart'));
    if (!buttons.length) return;

    buttons.forEach(button => {
      // En mode "server", les boutons peuvent être dans un <form> (POST réel).
      // Dans ce cas on ne simule rien côté JS.
      if (button.closest('form')) return;

      button.addEventListener('click', () => {
        bumpCartCount();

        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
        button.disabled = true;

        window.setTimeout(() => {
          button.innerHTML = originalHTML;
          button.disabled = false;
        }, 2000);
      });
    });
  }

  function initFaqAccordion() {
    const questions = Array.from(document.querySelectorAll('.faq-question'));
    if (!questions.length) return;

    questions.forEach(question => {
      question.addEventListener('click', () => {
        const answerId = question.getAttribute('aria-controls');
        const answer = answerId ? document.getElementById(answerId) : question.nextElementSibling;
        const toggle = question.querySelector('.faq-toggle');
        const isExpanded = question.getAttribute('aria-expanded') === 'true';

        questions.forEach(other => {
          if (other === question) return;
          const otherAnswerId = other.getAttribute('aria-controls');
          const otherAnswer = otherAnswerId ? document.getElementById(otherAnswerId) : other.nextElementSibling;
          const otherToggle = other.querySelector('.faq-toggle');

          other.setAttribute('aria-expanded', 'false');
          if (otherAnswer) {
            otherAnswer.classList.remove('active');
            otherAnswer.setAttribute('aria-hidden', 'true');
          }
          otherToggle?.classList.remove('active');
        });

        const nextExpanded = !isExpanded;
        question.setAttribute('aria-expanded', String(nextExpanded));
        if (answer) {
          answer.classList.toggle('active', nextExpanded);
          answer.setAttribute('aria-hidden', String(!nextExpanded));
        }
        toggle?.classList.toggle('active', nextExpanded);
      });
    });
  }

  function initPagination() {
    const pageButtons = Array.from(
      document.querySelectorAll('.pagination-btn:not(.prev-btn):not(.next-btn)')
    );
    if (!pageButtons.length) return;

    pageButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        if (btn.classList.contains('active')) return;
        const n = parseInt(btn.textContent || '', 10);
        if (!Number.isFinite(n)) return;
        simulatePageLoad(n);
      });
    });

    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');

    prevBtn?.addEventListener('click', () => {
      const current = getActivePageNumber();
      if (Number.isFinite(current) && current > 1) simulatePageLoad(current - 1);
    });

    nextBtn?.addEventListener('click', () => {
      const current = getActivePageNumber();
      if (Number.isFinite(current)) simulatePageLoad(current + 1);
    });
  }

  function getActivePageNumber() {
    const activeBtn = document.querySelector('.pagination-btn.active');
    const n = parseInt(activeBtn?.textContent || '', 10);
    return Number.isFinite(n) ? n : NaN;
  }

  function searchProducts(term) {
    const products = Array.from(document.querySelectorAll('.product-card'));
    let found = false;

    products.forEach(product => {
      const name = product.querySelector('h3')?.textContent?.toLowerCase() || '';
      const sku = product.querySelector('.product-sku')?.textContent?.toLowerCase() || '';
      const desc = product.querySelector('.product-description')?.textContent?.toLowerCase() || '';

      const ok = name.includes(term) || sku.includes(term) || desc.includes(term);
      product.style.display = ok ? 'block' : 'none';
      if (ok) found = true;
    });

    const noProductsMessage = document.getElementById('noProductsMessage');
    if (noProductsMessage) noProductsMessage.style.display = found ? 'none' : 'block';
  }

  function filterProductsByCategory(category) {
    const products = Array.from(document.querySelectorAll('.product-card'));
    let visibleCount = 0;

    products.forEach(product => {
      const productCategory = (product.getAttribute('data-category') || '').toLowerCase();
      const shouldShow = !category || productCategory === String(category).toLowerCase();
      product.style.display = shouldShow ? 'block' : 'none';
      if (shouldShow) visibleCount += 1;
    });

    const noProductsMessage = document.getElementById('noProductsMessage');
    if (noProductsMessage) noProductsMessage.style.display = visibleCount > 0 ? 'none' : 'block';
  }

  function simulatePageLoad(pageNumber) {
    const pageButtons = Array.from(
      document.querySelectorAll('.pagination-btn:not(.prev-btn):not(.next-btn)')
    );
    const pages = pageButtons
      .map(btn => parseInt(btn.textContent || '', 10))
      .filter(n => Number.isFinite(n));
    const maxPage = pages.length ? Math.max(...pages) : 1;
    const nextPage = Math.min(Math.max(1, pageNumber), maxPage);

    pageButtons.forEach(btn => btn.classList.remove('active'));
    const targetBtn = pageButtons.find(btn => parseInt(btn.textContent || '', 10) === nextPage);
    if (targetBtn) targetBtn.classList.add('active');

    updatePaginationInfo(nextPage);

    const grid =
      document.getElementById('productsGrid') ||
      document.getElementById('productGrid') ||
      document.querySelector('.products-grid') ||
      document.querySelector('.product-grid');

    grid?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function updatePaginationInfo(pageNumber) {
    const infoEl = document.getElementById('paginationInfo');
    if (!infoEl) return;

    const perPage = parseInt(infoEl.getAttribute('data-per-page') || '', 10) || 6;
    const total = parseInt(infoEl.getAttribute('data-total') || '', 10) || 0;

    const start = total > 0 ? (pageNumber - 1) * perPage + 1 : 0;
    const end = total > 0 ? Math.min(pageNumber * perPage, total) : 0;
    infoEl.textContent = `Affichage de ${start}–${end} sur ${total} produits`;
  }

  function bumpCartCount() {
    const cartCount = document.querySelector('.cart-count');
    if (!cartCount) return;
    const currentCount = parseInt(cartCount.textContent || '', 10) || 0;
    const next = Math.max(0, currentCount + 1);
    cartCount.textContent = String(next);
    cartCount.setAttribute('data-count', String(next));
    if (next > 0) cartCount.hidden = false;
  }

  document.addEventListener('DOMContentLoaded', initCataloguePage);
})();
