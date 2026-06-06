<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';
require_user_login();

$authUser = current_user();
$userId = (int) ($authUser['id'] ?? 0);
if ($userId <= 0) {
  redirect('pages/connexion.php');
}

$notice = '';
$noticeType = 'error';
$errors = array();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expiree. Veuillez reessayer.';
  } else {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
      $errors[] = 'Tous les champs sont obligatoires.';
    }

    if ($newPassword !== $confirmPassword) {
      $errors[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
    }

    if (!password_meets_policy($newPassword)) {
      $errors[] = password_policy_message();
    }

    if (!$errors) {
      try {
        $pdo = db();
        $userCols = db_table_columns($pdo, 'users');
        $passCol = in_array('password_hash', $userCols, true) ? 'password_hash'
          : (in_array('password', $userCols, true) ? 'password' : '');

        if ($passCol === '') {
          $errors[] = 'Configuration compte invalide.';
        } else {
          $stmt = $pdo->prepare('SELECT id, ' . $passCol . ' AS password_value FROM users WHERE id = :id LIMIT 1');
          $stmt->execute(array('id' => $userId));
          $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

          if (!$row) {
            $errors[] = 'Compte introuvable.';
          } else {
            $hash = (string) ($row['password_value'] ?? '');
            if ($hash === '' || !password_verify($currentPassword, $hash)) {
              $errors[] = 'Mot de passe actuel incorrect.';
            }
          }

          if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE users SET ' . $passCol . ' = :hash WHERE id = :id LIMIT 1');
            $upd->execute(array('hash' => $newHash, 'id' => $userId));

            session_regenerate_id(true);
            $notice = 'Mot de passe modifie avec succes.';
            $noticeType = 'success';
          }
        }
      } catch (Throwable $e) {
        $errors[] = 'Impossible de modifier le mot de passe pour le moment.';
      }
    }
  }
}

if ($errors) {
  $notice = implode(' ', $errors);
  $noticeType = 'error';
}

$page_title = 'Changer mon mot de passe';
$page_css = 'pages/mon-compte.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="section account-page account-form-page">
    <div class="container">
      <header class="account-page__head">
        <a class="account-back-link" href="<?php echo e(base_url('pages/mon-compte.php')); ?>">&larr; Retour a mon compte</a>
        <h1>Changer mon mot de passe</h1>
        <p class="section-subtitle">Renforcez la securite de votre compte avec un nouveau mot de passe.</p>
      </header>

      <section class="card account-card account-form-card profile-form-card" aria-labelledby="changePasswordTitle">
        <div class="account-card__head">
          <h2 id="changePasswordTitle">Mot de passe</h2>
        </div>

        <?php if ($notice !== ''): ?>
          <div class="notice <?php echo $noticeType === 'success' ? 'is-success' : 'is-error'; ?>" role="status" aria-live="polite">
            <?php echo e($notice); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="account-form" novalidate>
          <?php echo csrf_field(); ?>

          <div class="account-form__row">
            <label for="current_password">Mot de passe actuel</label>
            <input id="current_password" name="current_password" type="password" class="form-input" autocomplete="current-password" required>
          </div>

          <div class="account-form__row">
            <label for="new_password">Nouveau mot de passe</label>
            <input id="new_password" name="new_password" type="password" class="form-input" autocomplete="new-password" required minlength="<?php echo (int) password_min_length(); ?>">
          </div>

          <div class="account-form__row">
            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
            <input id="confirm_password" name="confirm_password" type="password" class="form-input" autocomplete="new-password" required minlength="<?php echo (int) password_min_length(); ?>">
          </div>

          <p class="account-note"><?php echo e(password_policy_message()); ?></p>

          <div class="account-form__actions">
            <button type="submit" class="btn btn-primary">Mettre a jour</button>
            <a href="<?php echo e(base_url('pages/mon-compte.php')); ?>" class="btn btn-secondary">Annuler</a>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
