(function () {
  "use strict";

  function ensureContainer() {
    var existing = document.querySelector(".toast-container");
    if (existing) return existing;
    var el = document.createElement("div");
    el.className = "toast-container";
    el.setAttribute("aria-live", "polite");
    el.setAttribute("aria-atomic", "true");
    document.body.appendChild(el);
    return el;
  }

  function showToast(type, message) {
    if (!message) return;
    var container = ensureContainer();

    var toast = document.createElement("div");
    toast.className = "toast toast--" + (type || "info");

    var title = document.createElement("p");
    title.className = "toast__title";
    title.textContent = type === "error" ? "Erreur" : type === "success" ? "Succès" : "Info";

    var msg = document.createElement("p");
    msg.className = "toast__msg";
    msg.textContent = message;

    toast.appendChild(title);
    toast.appendChild(msg);
    container.appendChild(toast);

    window.setTimeout(function () {
      toast.remove();
    }, 3800);
  }

  // Convertir les flash HTML existants en toasts (fallback reste en place si JS off)
  document.addEventListener("DOMContentLoaded", function () {
    if (!document.body.classList.contains("page-admin")) return;

    var alerts = document.querySelectorAll(".admin-alert");
    alerts.forEach(function (a) {
      var text = (a.textContent || "").trim();
      if (!text) return;

      var type = "info";
      if (a.classList.contains("admin-alert--success")) type = "success";
      if (a.classList.contains("admin-alert--error")) type = "error";

      showToast(type, text);
      // Laisser un fallback visuel minimal : on masque l'alert si toast affiché
      a.style.display = "none";
    });
  });
})();

