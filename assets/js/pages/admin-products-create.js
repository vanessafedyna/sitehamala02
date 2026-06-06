document.addEventListener('DOMContentLoaded', function () {
  var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-product-create-reveal'));
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

  document.querySelectorAll('[data-variants]').forEach(function (block) {
    var list = block.querySelector('[data-variants-list]');
    var addButton = block.querySelector('[data-variants-add]');
    var template = block.querySelector('template');
    if (!list || !addButton || !template) return;

    var syncIndexes = function () {
      Array.prototype.forEach.call(list.querySelectorAll('[data-variant-row]'), function (row, index) {
        Array.prototype.forEach.call(row.querySelectorAll('[data-variant-field]'), function (input) {
          var field = input.getAttribute('data-variant-field');
          if (!field) return;
          input.name = 'variants[' + index + '][' + field + ']';
        });
      });
    };

    var bindRow = function (row) {
      var removeButton = row.querySelector('[data-variant-remove]');
      if (!removeButton) return;
      removeButton.addEventListener('click', function () {
        var rows = list.querySelectorAll('[data-variant-row]');
        if (rows.length <= 1) {
          Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
            input.value = '';
          });
          return;
        }
        row.remove();
        syncIndexes();
      });
    };

    Array.prototype.forEach.call(list.querySelectorAll('[data-variant-row]'), bindRow);

    addButton.addEventListener('click', function () {
      var wrapper = document.createElement('div');
      wrapper.innerHTML = template.innerHTML;
      var row = wrapper.firstElementChild;
      if (!row) return;
      list.appendChild(row);
      bindRow(row);
      syncIndexes();
    });

    syncIndexes();
  });
});
