document.addEventListener('DOMContentLoaded', function () {
  var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-orders-reveal'));
  var items = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item]'));
  var toggles = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-toggle]'));
  var counter = document.querySelector('[data-bulk-count]');
  var bulkForm = document.getElementById('bulkOrdersForm');
  var selectionMirror = bulkForm ? document.getElementById('bulkSelectionMirror') : null;
  var injectedFieldSelector = '[data-bulk-injected="true"]';
  var bulkButtons = bulkForm
    ? Array.prototype.slice.call(bulkForm.querySelectorAll('button[type="submit"][name="new_status"]'))
    : [];

  if (bulkForm && !selectionMirror) {
    selectionMirror = document.createElement('div');
    selectionMirror.id = 'bulkSelectionMirror';
    selectionMirror.hidden = true;
    bulkForm.appendChild(selectionMirror);
  }

  var updateRowState = function (item) {
    var row = item.closest('tr, article');
    if (!row) return;
    row.classList.toggle('is-selected', item.checked);
  };

  var getCheckedItems = function () {
    return items.filter(function (item) {
      return item.checked;
    });
  };

  var normalizeBulkUiText = function () {
    toggles.forEach(function (toggle) {
      toggle.setAttribute('aria-label', 'Selectionner tout');
      var label = toggle.closest('label');
      if (label) {
        var textNode = label.querySelector('span');
        if (textNode) {
          textNode.textContent = 'Sélectionner tout';
        }
      }
    });

    bulkButtons.forEach(function (button) {
      if (button.value === 'confirme') {
        button.textContent = 'Confirmer la sélection';
      } else if (button.value === 'en_preparation') {
        button.textContent = 'Mettre en préparation';
      } else if (button.value === 'en_livraison') {
        button.textContent = 'Marquer en livraison';
      }
    });

    Array.prototype.slice.call(document.querySelectorAll('.admin-mobile-check span')).forEach(function (node) {
      node.textContent = 'Sélectionner pour le lot';
    });
  };

  var sync = function () {
    var checked = getCheckedItems().length;
    if (counter) {
      counter.textContent = checked + ' s\u00e9lectionn\u00e9e(s)';
    }

    var hasItems = items.length > 0;
    bulkButtons.forEach(function (button) {
      button.disabled = !hasItems;
    });

    if (!toggles.length) {
      items.forEach(updateRowState);
      rebuildBulkFormFields();
      return;
    }

    var allChecked = hasItems && checked > 0 && checked === items.length;
    toggles.forEach(function (toggle) {
      toggle.disabled = !hasItems;
      toggle.checked = allChecked;
      toggle.indeterminate = checked > 0 && checked < items.length;
    });

    items.forEach(updateRowState);
    rebuildBulkFormFields();
  };

  var rebuildBulkFormFields = function () {
    if (!selectionMirror) return;

    Array.prototype.slice.call(selectionMirror.querySelectorAll(injectedFieldSelector)).forEach(function (field) {
      field.remove();
    });

    getCheckedItems().forEach(function (item) {
      var orderId = item.value;
      if (!orderId) return;

      var idField = document.createElement('input');
      idField.type = 'hidden';
      idField.name = 'order_ids[]';
      idField.value = orderId;
      idField.setAttribute('data-bulk-injected', 'true');
      selectionMirror.appendChild(idField);

      var statusSource = document.querySelector('input[name="current_statuses[' + orderId + ']"]');
      if (!statusSource) return;

      var statusField = document.createElement('input');
      statusField.type = 'hidden';
      statusField.name = 'current_statuses[' + orderId + ']';
      statusField.value = statusSource.value;
      statusField.setAttribute('data-bulk-injected', 'true');
      selectionMirror.appendChild(statusField);
    });
  };

  if (toggles.length || counter || bulkButtons.length) {
    normalizeBulkUiText();

    toggles.forEach(function (toggle) {
      toggle.addEventListener('change', function () {
        items.forEach(function (item) {
          item.checked = toggle.checked;
        });
        sync();
      });
    });

    items.forEach(function (item) {
      item.addEventListener('change', sync);
    });

    sync();
  }

  if (bulkForm) {
    bulkForm.addEventListener('submit', function (event) {
      var checkedCount = getCheckedItems().length;
      var submitter = event.submitter;

      if (checkedCount === 0) {
        event.preventDefault();
        return;
      }

      if (submitter && submitter.classList.contains('admin-btn')) {
        submitter.disabled = true;
        submitter.textContent = 'Traitement...';
      }
    });
  }

  if (revealNodes.length) {
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });
      revealNodes.forEach(function (node) {
        observer.observe(node);
      });
    } else {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
      });
    }
  }
});
