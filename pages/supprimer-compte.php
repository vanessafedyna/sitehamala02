<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

$user = current_user();
if (!$user) {
  redirect('pages/connexion.php?redirect=' . urlencode('pages/supprimer-compte.php'));
}

$page_title = 'Supprimer mon compte';
$page_css = array('pages/mes-commandes.css');
$page_js = 'pages/delete-account.js';

include __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="section delete-page">
    <div class="container">
      <div class="delete-card">
        <h1 class="delete-title">Supprimer mon compte</h1>
        <p class="delete-subtitle">Zone sensible. Cette action est irréversible et coupera immédiatement l'accès à votre compte.</p>

        <div class="danger-zone" role="alert" aria-live="polite">
          <div class="danger-title">Action définitive</div>
          <p class="danger-text">Vous ne pourrez plus vous connecter avec ce compte.</p>
        </div>

        <form id="deleteForm" method="post" action="<?php echo e(base_url('public/api/account_delete.php')); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-row">
            <label for="delete_password">Mot de passe</label>
            <input id="delete_password" name="password" type="password" class="form-input" autocomplete="current-password" placeholder="Entrez votre mot de passe" required>
          </div>

          <label class="confirm-row" for="confirm_delete">
            <input id="confirm_delete" type="checkbox" name="confirm_delete" value="1" required>
            <span>Je confirme la suppression définitive</span>
          </label>

          <button id="btn_delete" class="btn-danger" type="submit" disabled>Supprimer définitivement</button>
          <p class="helper-text">Le bouton s’active après confirmation.</p>
          <div id="deleteFormNotice" class="helper-text" role="alert" aria-live="polite"></div>
        </form>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

