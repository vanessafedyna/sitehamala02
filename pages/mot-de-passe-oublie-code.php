<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

if (current_user()) {
  redirect('pages/mes-commandes.php');
}

$phone = trim((string) ($_SESSION['password_reset_phone_prefill'] ?? ''));

$flash = trim((string) ($_SESSION['password_reset_notice'] ?? ''));
unset($_SESSION['password_reset_notice']);

$page_title = 'Recuperation via WhatsApp';
$page_css = 'pages/connexion.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main class="auth-page" id="main">
  <header class="page-head">
    <div class="container">
      <h1>Recuperation via WhatsApp</h1>
      <p class="subtitle">Contactez-nous sur WhatsApp pour réinitialiser votre mot de passe.</p>
    </div>
  </header>

  <section class="section" aria-label="Recuperation via WhatsApp">
    <div class="container">
      <div class="login-card">
        <div class="login-title">WhatsApp</div>

        <?php if ($flash !== ''): ?>
          <div class="notice is-success" role="status" aria-live="polite"><?php echo e($flash); ?></div>
        <?php endif; ?>

        <div class="notice is-success" role="status" aria-live="polite">
          Contactez-nous sur WhatsApp pour réinitialiser votre mot de passe.
        </div>

        <?php if ($phone !== ''): ?>
          <div class="hint">Numero saisi: <?php echo e($phone); ?></div>
        <?php endif; ?>

        <a class="button auth-whatsapp-button" href="<?php echo e(base_url('pages/mot-de-passe-oublie.php')); ?>">Contacter via WhatsApp</a>

        <div class="divider" role="separator" aria-hidden="true"></div>
        <div class="signup">
          <a class="link" href="<?php echo e(base_url('pages/connexion.php')); ?>">Retour a la connexion</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
