(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    if (!document.body.classList.contains("page-admin")) return;

    var input = document.getElementById("adminModuleSearch");
    if (!input) return;

    var items = Array.prototype.slice.call(document.querySelectorAll(".admin-nav__item"));
    var noRes = document.querySelector(".admin-nav__noResults");

    function update() {
      var q = (input.value || "").trim().toLowerCase();
      var visible = 0;
      items.forEach(function (li) {
        var label = (li.getAttribute("data-label") || "").toLowerCase();
        var show = q === "" || label.indexOf(q) !== -1;
        li.hidden = !show;
        if (show) visible += 1;
      });

      if (noRes) {
        noRes.hidden = visible !== 0;
      }
    }

    input.addEventListener("input", update);
    update();
  });
})();

