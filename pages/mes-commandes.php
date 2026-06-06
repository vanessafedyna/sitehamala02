<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

$user = current_user();
if (!$user) {
  redirect('pages/connexion.php?redirect=' . urlencode('pages/mes-commandes.php'));
}

$page_title = 'Mes commandes';
$page_css = 'pages/mes-commandes.css';
$page_js = 'pages/mes-commandes.js';
$registerFlash = isset($_SESSION['register_flash']) && is_array($_SESSION['register_flash']) ? $_SESSION['register_flash'] : null;
unset($_SESSION['register_flash']);
$flashMessages = isset($_SESSION['flash']) && is_array($_SESSION['flash']) ? $_SESSION['flash'] : array();
unset($_SESSION['flash']);

$registerTitle = '';
$registerFullName = '';
$registerMessage = '';
$registerWelcomeLine = '';
if (is_array($registerFlash)) {
  $registerTitle = trim((string) ($registerFlash['title'] ?? ''));
  $registerFullName = trim((string) ($registerFlash['full_name'] ?? ''));
  $registerMessage = trim((string) ($registerFlash['message'] ?? ''));

  if ($registerTitle === '') {
    $registerTitle = 'Compte créé avec succès';
  }
  if ($registerFullName !== '') {
    $registerWelcomeLine = 'Bienvenue ' . $registerFullName . ' !';
  } elseif ($registerMessage !== '') {
    $registerWelcomeLine = $registerMessage;
  }
}

include __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <header class="page-head">
    <div class="container">
      <h1>Mes commandes</h1>
      <p class="subtitle">Retrouvez vos commandes et accédez au suivi.</p>
      <?php foreach ($flashMessages as $fm): ?>
        <?php
          $msg = is_array($fm) ? (string) ($fm['message'] ?? '') : (string) $fm;
          if ($msg === '') continue;
        ?>
        <div class="welcome-alert" role="status" aria-live="polite">
          <div class="welcome-icon">&#128075;</div>
          <div class="welcome-content">
            <div class="welcome-title"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="welcome-sub">Heureux de vous revoir sur SORA Collection.</div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($registerTitle !== '' && $registerWelcomeLine !== ''): ?>
        <div class="register-success-alert" role="status" aria-live="polite">
          <div class="register-success-alert__icon" aria-hidden="true">&#10003;</div>
          <div class="register-success-alert__content">
            <p class="register-success-alert__title"><?php echo htmlspecialchars($registerTitle, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="register-success-alert__welcome"><?php echo htmlspecialchars($registerWelcomeLine, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="register-success-alert__sub">Vous pouvez maintenant suivre vos commandes et commander plus rapidement.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <section class="section">
    <div class="container">
      <div class="card my-orders">
        <div class="my-orders__head">
          <div class="my-orders__intro">
            <h2 class="my-orders__title">Historique de vos commandes</h2>
            <p class="my-orders__subtitle">Accédez rapidement à vos informations de suivi et à l’historique de vos achats.</p>
          </div>
          <a class="btn btn-secondary my-orders__cta" href="<?php echo $base_url; ?>pages/catalogue.php">Retour au catalogue</a>
        </div>

        <div class="notice is-hidden" id="ordersNotice" role="status" aria-live="polite"></div>
        <p class="my-orders__reassurance">Consultez vos commandes à tout moment et suivez leur évolution en toute simplicité.</p>
        <div id="ordersList" class="orders-list" aria-label="Liste des commandes"></div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
