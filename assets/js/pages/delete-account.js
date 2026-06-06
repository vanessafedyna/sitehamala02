(function(){
  const pwd = document.querySelector('#delete_password');
  const cb = document.querySelector('#confirm_delete');
  const btn = document.querySelector('#btn_delete');
  const form = document.querySelector('#deleteForm');
  const notice = document.querySelector('#deleteFormNotice');

  if (!pwd || !cb || !btn) return;

  const update = () => {
    const ok = cb.checked && pwd.value.trim().length > 0;
    btn.disabled = !ok;
    if (notice) {
      notice.textContent = ok ? '' : 'Cochez la confirmation et saisissez votre mot de passe.';
    }
  };

  pwd.addEventListener('input', update);
  cb.addEventListener('change', update);
  update();

  if (form) {
    form.addEventListener('submit', function(e){
      if (!btn.disabled) {
        if (!window.confirm('Confirmer la suppression définitive ?')) {
          e.preventDefault();
        }
      }
    });
  }
})();

