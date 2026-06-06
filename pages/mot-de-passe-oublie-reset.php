<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/services/PasswordResetOtpService.php';
auth_start();

if (current_user()) {
  redirect('pages/mes-commandes.php');
}

if (!PasswordResetOtpService::hasVerifiedContext()) {
  $_SESSION['password_reset_notice'] = 'Session de reinitialisation expiree. Recommencez.';
  redirect('pages/mot-de-passe-oublie.php');
}

$notice = '';
$noticeType = 'error';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $notice = 'Session expiree. Veuillez reessayer.';
    $noticeType = 'error';
  } else {
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');
    $result = PasswordResetOtpService::resetPassword($password, $password2);

    if (!empty($result['ok'])) {
      $_SESSION['password_reset_notice'] = (string) ($result['message'] ?? 'Mot de passe reinitialise avec succes.');
      redirect('pages/connexion.php');
    }

    $notice = (string) ($result['message'] ?? 'Impossible de reinitialiser le mot de passe.');
    $noticeType = 'error';
  }
}

$page_title = 'Nouveau mot de passe';
$page_css = 'pages/connexion.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main class="auth-page" id="main">
  <header class="page-head">
    <div class="container">
      <h1>Nouveau mot de passe</h1>
      <p class="subtitle">Definissez un nouveau mot de passe pour votre compte.</p>
    </div>
  </header>

  <section class="section" aria-label="Nouveau mot de passe">
    <div class="container">
      <div class="login-card">
        <div class="login-title">Reinitialisation</div>

        <form method="post" novalidate>
          <?php echo csrf_field(); ?>

          <div class="field">
            <label class="sr-only" for="password">Nouveau mot de passe</label>
            <input
              id="password"
              name="password"
              class="input"
              type="password"
              autocomplete="new-password"
              required
              minlength="10"
              placeholder=" "
            />
            <span class="label" aria-hidden="true">Nouveau mot de passe</span>
          </div>

          <div class="field">
            <label class="sr-only" for="password2">Confirmation</label>
            <input
              id="password2"
              name="password2"
              class="input"
              type="password"
              autocomplete="new-password"
              required
              minlength="10"
              placeholder=" "
            />
            <span class="label" aria-hidden="true">Confirmer le mot de passe</span>
          </div>

          <div class="hint"><?php echo e(password_policy_message()); ?></div>

          <div class="notice <?php echo $notice === '' ? 'is-hidden' : ''; ?> <?php echo $noticeType === 'success' ? 'is-success' : 'is-error'; ?>" role="status" aria-live="polite">
            <?php echo e($notice); ?>
          </div>

          <button class="button" type="submit">Valider le nouveau mot de passe</button>
        </form>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

