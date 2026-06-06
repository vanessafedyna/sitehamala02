/* UI only (future intégration PHP) */
(() => {
  const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) return;

  const numbers = Array.from(document.querySelectorAll('.stat-number[data-target]'));
  if (!numbers.length) return;

  const animateNumber = (el, target) => {
  const start = 0;
  const duration = 800;
  const startTime = performance.now();

  const step = now => {
      const t = Math.min(1, (now - startTime) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const value = Math.round(start + (target - start) * eased);
      el.textContent = String(value);
      if (t < 1) requestAnimationFrame(step);
  };

  requestAnimationFrame(step);
  };

  const observed = new WeakSet();

  const io = new IntersectionObserver(
  entries => {
      entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const el = entry.target;
    if (observed.has(el)) return;
    observed.add(el);

    const target = Number(el.getAttribute('data-target'));
    if (!Number.isFinite(target)) return;
    animateNumber(el, target);
      });
  },
  { threshold: 0.35 }
  );

  numbers.forEach(el => io.observe(el));
})();

