<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';
auth_start();

if (current_user()) {
  redirect('pages/mes-commandes.php');
}

$phone = trim((string) ($_GET['phone'] ?? ($_SESSION['password_reset_phone_prefill'] ?? '')));
$_SESSION['password_reset_phone_prefill'] = $phone;
unset($_SESSION['password_reset_notice']);

$whatsappNumber = public_contact_whatsapp_number();
$baseMessage = 'Bonjour, j\'ai oublie mon mot de passe sur Sora Collection Mali.';
$message = $baseMessage;
if ($phone !== '') {
  $message .= ' Mon numero est : ' . $phone . '.';
}
$message .= ' Pouvez-vous m\'aider ?';
$whatsappUrl = public_contact_whatsapp_url($message);

$page_title = 'Reinitialisation via WhatsApp';
$page_css = 'pages/connexion.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main class="auth-page" id="main">
  <header class="page-head">
    <div class="container">
      <h1>Mot de passe oublie</h1>
      <p class="subtitle">Contactez notre equipe sur WhatsApp pour recuperer l'acces a votre compte client.</p>
    </div>
  </header>

  <section class="section" aria-label="Recuperation de mot de passe">
    <div class="container">
      <div class="login-card">
        <div class="login-title">Reinitialisation via WhatsApp</div>

        <form method="get" novalidate>
          <div class="field">
            <label class="sr-only" for="phone">Numero de telephone</label>
            <input
              id="phone"
              name="phone"
              class="input"
              type="text"
              inputmode="tel"
              autocomplete="tel"
              placeholder=" "
              value="<?php echo e($phone); ?>"
            />
            <span class="label" aria-hidden="true">Numero de telephone</span>
          </div>

          <div class="hint">Optionnel. Exemple: +223 XX XX XX XX</div>

          <div class="notice is-success" role="status" aria-live="polite">
            Contactez-nous sur WhatsApp pour réinitialiser votre mot de passe.
          </div>

          <a
            class="button auth-whatsapp-button"
            id="passwordResetWhatsappLink"
            href="<?php echo e($whatsappUrl); ?>"
            target="_blank"
            rel="noopener"
            data-whatsapp-number="<?php echo e($whatsappNumber); ?>"
            data-message-prefix="<?php echo e($baseMessage); ?>"
          >Ouvrir WhatsApp</a>

          <div class="divider" role="separator" aria-hidden="true"></div>
          <div class="signup">
            <a class="link" href="<?php echo e(base_url('pages/connexion.php')); ?>">Retour a la connexion</a>
            <button class="link" type="submit">Actualiser le lien</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</main>

<script>
(function () {
  var phoneInput = document.getElementById('phone');
  var whatsappLink = document.getElementById('passwordResetWhatsappLink');
  if (!phoneInput || !whatsappLink) return;

  function buildMessage() {
    var phone = String(phoneInput.value || '').trim();
    var message = String(whatsappLink.dataset.messagePrefix || '');
    if (phone !== '') {
      message += ' Mon numero est : ' + phone + '.';
    }
    message += " Pouvez-vous m'aider ?";
    return message;
  }

  function updateLink() {
    var number = String(whatsappLink.dataset.whatsappNumber || '').replace(/[^0-9]/g, '');
    whatsappLink.href = 'https://wa.me/' + encodeURIComponent(number) + '?text=' + encodeURIComponent(buildMessage());
  }

  phoneInput.addEventListener('input', updateLink);
  updateLink();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
