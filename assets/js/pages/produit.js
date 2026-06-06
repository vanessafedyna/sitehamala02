// assets/js/pages/produit.js

document.addEventListener('DOMContentLoaded', function () {
  const galleryRoot = document.querySelector('.product-gallery');
  const mainBox = document.querySelector('.product-main');
  let mainImg = document.getElementById('productMainImage');
  const thumbs = Array.from(document.querySelectorAll('.thumb[data-src]'));

  const ensureMainImage = () => {
    if (mainImg || !mainBox) return mainImg;

    const placeholder = document.getElementById('productMainPlaceholder');
    if (placeholder) placeholder.remove();

    mainImg = document.createElement('img');
    mainImg.id = 'productMainImage';
    mainImg.alt = 'Image produit';
    mainBox.appendChild(mainImg);
    return mainImg;
  };

  const setMainImage = (src, btn) => {
    const targetImg = ensureMainImage();
    if (!targetImg || !src) return;

    const nextSrc = String(src).trim();
    if (!nextSrc) return;

    if (targetImg.getAttribute('src') === nextSrc) {
      setActiveThumb(btn);
      return;
    }

    const preload = new Image();
    if (mainBox) mainBox.classList.add('is-updating');

    preload.onload = () => {
      targetImg.src = nextSrc;
      const label = (btn && btn.getAttribute('aria-label')) || '';
      if (label) targetImg.alt = label.replace(/^Voir\s+/i, '');
      if (mainBox) mainBox.classList.remove('is-updating');
      setActiveThumb(btn);
    };

    preload.onerror = () => {
      if (mainBox) mainBox.classList.remove('is-updating');
    };

    preload.src = nextSrc;
  };

  const setActiveThumb = btn => {
    thumbs.forEach(b => {
      b.classList.remove('is-active');
      b.setAttribute('aria-current', 'false');
    });
    btn.classList.add('is-active');
    btn.setAttribute('aria-current', 'true');
  };

  thumbs.forEach(btn => {
    btn.addEventListener('click', function () {
      const src = (this.dataset.src || '').trim();
      if (!src) return;
      setMainImage(src, this);
    });

    btn.addEventListener('keydown', function (e) {
      const key = e.key;
      if (key !== 'ArrowRight' && key !== 'ArrowLeft') return;
      e.preventDefault();

      const currentIndex = thumbs.indexOf(this);
      if (currentIndex < 0) return;

      const delta = key === 'ArrowRight' ? 1 : -1;
      const nextIndex = (currentIndex + delta + thumbs.length) % thumbs.length;
      const nextBtn = thumbs[nextIndex];
      if (!nextBtn) return;

      nextBtn.focus();
      const nextSrc = (nextBtn.dataset.src || '').trim();
      if (nextSrc) setMainImage(nextSrc, nextBtn);
    });
  });

  // FAQ accordion (accessible)
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

  if (galleryRoot && thumbs.length > 0) {
    const first = thumbs.find(b => b.classList.contains('is-active')) || thumbs[0];
    if (first) setActiveThumb(first);
  }

  const variantForm = document.querySelector('[data-variant-form]');
  if (variantForm) {
    const variantInput = variantForm.querySelector('[data-variant-input]');
    const variantButtons = Array.from(variantForm.querySelectorAll('[data-variant-option]'));
    const variantColor = variantForm.querySelector('[data-variant-color-meta]');
    const variantColorValue = variantColor ? variantColor.querySelector('span') : null;
    const variantError = variantForm.querySelector('[data-variant-error]');
    const submitButton = variantForm.querySelector('button[type="submit"]');
    const missingVariantMessage = (variantForm.getAttribute('data-variant-message') || '').trim() || 'Veuillez choisir une taille.';

    const setVariantError = message => {
      if (!variantError) return;
      const text = String(message || '').trim();
      variantError.textContent = text || missingVariantMessage;
      variantError.classList.toggle('is-hidden', text === '');
    };

    const updateVariantMeta = button => {
      if (!button) {
        if (variantColor && variantColorValue) {
          variantColor.classList.add('is-hidden');
          variantColorValue.textContent = '';
        }
        return;
      }

      const color = String(button.dataset.variantColor || '').trim();

      if (variantColor && variantColorValue) {
        variantColorValue.textContent = color;
        variantColor.classList.toggle('is-hidden', color === '');
      }
    };

    const syncSubmitState = () => {
      if (!submitButton) return;
      const hasVariant = !!(variantInput && String(variantInput.value || '').trim());
      submitButton.disabled = !hasVariant;
      submitButton.setAttribute('aria-disabled', hasVariant ? 'false' : 'true');
    };

    const setSelectedVariant = button => {
      variantButtons.forEach(item => {
        const isSelected = item === button;
        item.classList.toggle('is-selected', isSelected);
        item.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
      });

      if (variantInput) {
        variantInput.value = button ? String(button.dataset.variantId || '').trim() : '';
      }

      updateVariantMeta(button);
      syncSubmitState();
      if (button) {
        setVariantError('');
      }
    };

    variantButtons.forEach(button => {
      button.addEventListener('click', () => {
        setSelectedVariant(button);
      });
    });

    variantForm.addEventListener('submit', event => {
      const hasVariant = !!(variantInput && String(variantInput.value || '').trim());
      if (hasVariant) return;

      event.preventDefault();
      setVariantError(missingVariantMessage);
      const firstButton = variantButtons[0] || null;
      if (firstButton) firstButton.focus();
    });

    setSelectedVariant(null);
    syncSubmitState();
  }

  // Product review form (AJAX)
  const reviewForm = document.getElementById('productReviewForm');
  const reviewNotice = document.getElementById('productReviewNotice');
  if (reviewForm) {
    reviewForm.addEventListener('submit', async e => {
      e.preventDefault();

      if (reviewNotice) {
        reviewNotice.textContent = '';
        reviewNotice.classList.remove('is-success', 'is-error');
      }

      const endpoint = reviewForm.getAttribute('data-endpoint') || '';
      if (!endpoint) return;

      const formData = new FormData(reviewForm);
      const name = String(formData.get('customer_name') || '').trim();
      const rating = Number(formData.get('rating') || 0);
      const comment = String(formData.get('comment') || '').trim();
      const productId = Number(formData.get('product_id') || 0);

      if (!productId || !name || !comment || !Number.isFinite(rating) || rating < 1 || rating > 5) {
        if (reviewNotice) {
          reviewNotice.textContent = 'Veuillez remplir correctement tous les champs obligatoires.';
          reviewNotice.classList.add('is-error');
        }
        return;
      }

      const submitBtn = reviewForm.querySelector('button[type="submit"]');

      try {
        if (submitBtn) submitBtn.disabled = true;

        const res = await fetch(endpoint, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });

        let data = null;
        try {
          data = await res.json();
        } catch {
          data = null;
        }

        if (!res.ok || !data || data.success !== true) {
          const msg = data && typeof data.message === 'string' ? data.message : 'Impossible d envoyer votre avis.';
          if (reviewNotice) {
            reviewNotice.textContent = msg;
            reviewNotice.classList.add('is-error');
          }
          return;
        }

        reviewForm.reset();
        if (reviewNotice) {
          reviewNotice.textContent = 'Merci pour votre avis.';
          reviewNotice.classList.add('is-success');
        }
      } catch {
        if (reviewNotice) {
          reviewNotice.textContent = 'Impossible d envoyer votre avis pour le moment.';
          reviewNotice.classList.add('is-error');
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }
});
