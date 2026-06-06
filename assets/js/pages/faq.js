// faq.js — FAQ V1 (front-end only)

const $ = selector => document.querySelector(selector);
const $$ = selector => Array.from(document.querySelectorAll(selector));

const searchInput = $('#searchInput');
const clearBtn = $('#clearBtn');
const resultCount = $('#resultCount');
const noResults = $('#noResults');

const tabs = $$('.tab[data-tab]');
const sections = $$('[data-section]');

const ACCORDION_SINGLE_OPEN = true;

function escapeHtml(text) {
  return String(text)
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#039;');
}

function escapeRegExp(text) {
  return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function setActiveTab(key) {
  tabs.forEach(btn => {
  const isActive = btn.dataset.tab === key;
  btn.classList.toggle('is-active', isActive);
  btn.setAttribute('aria-current', isActive ? 'true' : 'false');
  });

  sections.forEach(sec => {
  const show = sec.dataset.section === key;
  sec.hidden = !show;
  });

  // UX: garder au moins 1 question ouverte par catégorie (si rien n'est ouvert)
  const visibleSection = sections.find(s => s.dataset.section === key);
  if (visibleSection) ensureFirstQuestionOpen(visibleSection);
}

function closeAllAccordions(root = document) {
  root.querySelectorAll('.accordion-trigger').forEach(t => t.setAttribute('aria-expanded', 'false'));
  root.querySelectorAll('.accordion-panel').forEach(p => {
  p.hidden = true;
  p.setAttribute('aria-hidden', 'true');
  });
}

function animateOpen(panel) {
  try {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    panel.animate(
      [{ opacity: 0, transform: 'translateY(-4px)' }, { opacity: 1, transform: 'translateY(0)' }],
      { duration: 180, easing: 'ease-out' }
    );
  } catch (e) {
    // no-op
  }
}

function openTrigger(trigger, opts = {}) {
  const { scroll = false, focus = false } = opts;
  if (!trigger) return false;

  const panelId = trigger.getAttribute('aria-controls');
  const panel = panelId ? document.getElementById(panelId) : null;
  if (!panel) return false;

  const isOpen = trigger.getAttribute('aria-expanded') === 'true';
  if (isOpen) {
    if (scroll) trigger.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (focus) trigger.focus();
    return true;
  }

  if (ACCORDION_SINGLE_OPEN) {
    const scope = trigger.closest('.accordion') || document;
    scope.querySelectorAll('.accordion-trigger').forEach(t => t.setAttribute('aria-expanded', 'false'));
    scope.querySelectorAll('.accordion-panel').forEach(p => {
      p.hidden = true;
      p.setAttribute('aria-hidden', 'true');
    });
  }

  trigger.setAttribute('aria-expanded', 'true');
  panel.hidden = false;
  panel.setAttribute('aria-hidden', 'false');
  animateOpen(panel);

  if (scroll) trigger.scrollIntoView({ behavior: 'smooth', block: 'center' });
  if (focus) trigger.focus();
  return true;
}

function ensureFirstQuestionOpen(sectionEl) {
  if (!sectionEl) return;
  const hasOpen = !!sectionEl.querySelector('.accordion-trigger[aria-expanded="true"]');
  if (hasOpen) return;
  const first = sectionEl.querySelector('.accordion-trigger');
  if (first) openTrigger(first, { scroll: false, focus: false });
}

function openFirstQuestionInEachCategory() {
  sections.forEach(sec => ensureFirstQuestionOpen(sec));
}

function jumpToFaqHash(hashRaw) {
  const hash = String(hashRaw || '').trim();
  if (!hash || !hash.startsWith('#')) return false;

  const target = document.getElementById(hash.slice(1));
  if (!target) return false;

  // Si recherche active: on la vide pour permettre les tabs + éviter les sections cachées
  if (searchInput && String(searchInput.value || '').trim() !== '') {
    searchInput.value = '';
    clearBtn.hidden = true;
    filterFaq('');
  }

  const sectionEl = target.closest('[data-section]');
  const key = sectionEl?.dataset?.section;
  if (key) setActiveTab(key);

  // Ouvrir l'accordéon associé (si on cible un item)
  const item = target.classList.contains('accordion-item') ? target : target.closest('.accordion-item');
  const trigger = item ? item.querySelector('.accordion-trigger') : null;
  if (trigger) openTrigger(trigger, { scroll: true, focus: true });
  else target.scrollIntoView({ behavior: 'smooth', block: 'start' });

  return true;
}

function highlightText(text, term) {
  if (!term) return escapeHtml(text);
  const re = new RegExp(`(${escapeRegExp(term)})`, 'ig');
  return escapeHtml(text).replace(re, '<span class="hl">$1</span>');
}

function filterFaq(termRaw) {
  const term = String(termRaw || '').trim();
  const hasTerm = term.length > 0;

  clearBtn.hidden = !hasTerm;

  let matches = 0;

  // On parcourt toutes les questions/réponses (toutes sections)
  sections.forEach(section => {
  let sectionHasMatch = false;
  const items = Array.from(section.querySelectorAll('.accordion-item[data-q]'));

  items.forEach(item => {
      const trigger = item.querySelector('.accordion-trigger');
      const panel = item.querySelector('.accordion-panel[data-a]');
      const p = panel ? panel.querySelector('p') : null;

      const originalQ = trigger?.dataset?.origText || trigger?.textContent || '';
      const originalA = p?.dataset?.origText || p?.textContent || '';

      // mémoriser une seule fois
      if (trigger && !trigger.dataset.origText) trigger.dataset.origText = originalQ.trim();
      if (p && !p.dataset.origText) p.dataset.origText = originalA.trim();

      const qText = (trigger?.dataset?.origText || '').trim();
      const aText = (p?.dataset?.origText || '').trim();

      const hay = (qText + ' ' + aText).toLowerCase();
      const ok = !hasTerm || hay.includes(term.toLowerCase());

      item.hidden = !ok;
      if (ok) {
    matches += 1;
    sectionHasMatch = true;
      }

      // Highlight (simple)
      if (trigger) {
    const icon = trigger.querySelector('.accordion-icon')?.outerHTML || '<span class="accordion-icon" aria-hidden="true">+</span>';
    const qOnly = qText.replace(/\s+\+$/, '').trim();
    trigger.innerHTML = `${highlightText(qOnly, term)} ${icon}`;
      }
      if (p) {
    p.innerHTML = highlightText(aText, term);
      }
  });

  // Si recherche active: on affiche uniquement les sections avec résultats
  if (hasTerm) {
      section.hidden = !sectionHasMatch;
  }
  });

  // Tabs: si recherche active, on désactive visuellement l'état "actif"
  if (hasTerm) {
  tabs.forEach(btn => btn.classList.remove('is-active'));
  } else {
  // Revenir sur le tab actif (ou défaut)
  const active = tabs.find(t => t.classList.contains('is-active')) || tabs[0];
  setActiveTab(active?.dataset?.tab || 'commande');
  }

  // Compteur + état vide
  if (!hasTerm) {
  resultCount.textContent = '—';
  noResults.hidden = true;
  return;
  }

  resultCount.textContent = `${matches} résultat${matches > 1 ? 's' : ''}`;
  noResults.hidden = matches !== 0;

  // Pour éviter un état incohérent avec des panels ouverts qui n'existent plus
  closeAllAccordions(document);
}

function initTabs() {
  tabs.forEach(btn => {
  btn.addEventListener('click', () => {
      if (String(searchInput?.value || '').trim() !== '') return; // tabs ignorés si recherche active
      setActiveTab(btn.dataset.tab);
  });
  });

  // default
  setActiveTab('commande');
}

function initAccordion() {
  document.addEventListener('click', e => {
  const trigger = e.target.closest('.accordion-trigger');
  if (!trigger) return;

  const panelId = trigger.getAttribute('aria-controls');
  const panel = panelId ? document.getElementById(panelId) : null;
  if (!panel) return;

  const isOpen = trigger.getAttribute('aria-expanded') === 'true';

  if (isOpen) {
      trigger.setAttribute('aria-expanded', 'false');
      panel.hidden = true;
      panel.setAttribute('aria-hidden', 'true');
      return;
  }

  openTrigger(trigger, { scroll: false, focus: false });
  });
}

function initSearch() {
  if (!searchInput) return;

  const run = () => filterFaq(searchInput.value);

  searchInput.addEventListener('input', run);
  clearBtn?.addEventListener('click', () => {
  searchInput.value = '';
  searchInput.focus();
  filterFaq('');
  });

  // initial
  filterFaq('');
}

initTabs();
initAccordion();
initSearch();

// UX: ouvrir la première question de chaque catégorie au chargement
openFirstQuestionInEachCategory();

// Deep-link / ancres depuis "Questions les plus fréquentes"
document.addEventListener('click', e => {
  const a = e.target.closest('a[href^="#"]');
  if (!a) return;
  const href = a.getAttribute('href') || '';
  if (!href.startsWith('#faq-')) return;
  e.preventDefault();
  const ok = jumpToFaqHash(href);
  if (ok) history.replaceState(null, '', href);
});

if (location.hash) jumpToFaqHash(location.hash);
window.addEventListener('hashchange', () => jumpToFaqHash(location.hash));
