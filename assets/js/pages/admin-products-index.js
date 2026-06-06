document.addEventListener('DOMContentLoaded', function () {
  var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-products-reveal'));
  if (revealNodes.length) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || window.innerWidth <= 768) {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
        node.style.transitionDelay = '0ms';
      });
    } else {
      var revealObserver = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        });
      }, { threshold: 0.14 });

      revealNodes.forEach(function (node, index) {
        node.style.transitionDelay = Math.min(index * 45, 220) + 'ms';
        revealObserver.observe(node);
      });
    }
  }

  document.querySelectorAll('[data-inline-edit-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      var wrap = button.closest('[data-inline-edit]');
      if (!wrap) return;
      wrap.classList.add('is-open');
      var input = wrap.querySelector('input');
      if (input) input.focus();
    });
  });

  document.querySelectorAll('[data-inline-edit-cancel]').forEach(function (button) {
    button.addEventListener('click', function () {
      var wrap = button.closest('[data-inline-edit]');
      if (!wrap) return;
      wrap.classList.remove('is-open');
    });
  });
});
