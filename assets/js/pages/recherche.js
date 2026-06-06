// Recherche produits (DB-driven) : ce fichier ne fait plus de filtrage local.
// Il garde uniquement l'UX FAQ (accordéon) et de petites améliorations.

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('searchInput');
  if (searchInput && !searchInput.value) {
    // Améliore l'UX sans casser le submit GET.
    searchInput.focus?.();
  }

  document.addEventListener('click', e => {
    const faqQ = e.target.closest?.('.faq-question');
    if (!faqQ) return;

    const answerId = faqQ.getAttribute('aria-controls');
    const answer = answerId ? document.getElementById(answerId) : null;
    const toggle = faqQ.querySelector('.faq-toggle');
    const isExpanded = faqQ.getAttribute('aria-expanded') === 'true';

    // Fermer les autres
    document.querySelectorAll('.faq-question[aria-expanded=\"true\"]').forEach(other => {
      if (other === faqQ) return;
      const otherId = other.getAttribute('aria-controls');
      const otherAnswer = otherId ? document.getElementById(otherId) : null;
      const otherToggle = other.querySelector('.faq-toggle');
      other.setAttribute('aria-expanded', 'false');
      if (otherAnswer) {
        otherAnswer.classList.remove('active');
        otherAnswer.setAttribute('aria-hidden', 'true');
      }
      if (otherToggle) otherToggle.classList.remove('active');
    });

    const next = !isExpanded;
    faqQ.setAttribute('aria-expanded', String(next));
    if (answer) {
      answer.classList.toggle('active', next);
      answer.setAttribute('aria-hidden', String(!next));
    }
    if (toggle) toggle.classList.toggle('active', next);
  });
});

