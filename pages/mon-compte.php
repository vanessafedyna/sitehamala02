<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';
require_user_login();

$user = current_user();
$userId = (int) ($user['id'] ?? 0);

$profile = array(
  'name' => trim((string) ($user['name'] ?? '')),
  'last_name' => trim((string) ($user['last_name'] ?? '')),
  'email' => trim((string) ($user['email'] ?? '')),
  'phone' => '',
);

try {
  if ($userId > 0) {
    $pdo = db();
    $userCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'users') : array();
    $select = array();
    if (in_array('phone', $userCols, true)) $select[] = 'phone';
    if (in_array('name', $userCols, true)) $select[] = 'name';
    if (in_array('last_name', $userCols, true)) $select[] = 'last_name';
    if (in_array('email', $userCols, true)) $select[] = 'email';

    if ($select) {
      $sql = 'SELECT ' . implode(', ', $select) . ' FROM users WHERE id = :id LIMIT 1';
      $stmt = $pdo->prepare($sql);
      $stmt->execute(array('id' => $userId));
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();

      if (!empty($row['name'])) $profile['name'] = trim((string) $row['name']);
      if (!empty($row['last_name'])) $profile['last_name'] = trim((string) $row['last_name']);
      if (!empty($row['email'])) $profile['email'] = trim((string) $row['email']);
      if (!empty($row['phone'])) $profile['phone'] = trim((string) $row['phone']);
    }
  }
} catch (Throwable $e) {
  // Page volontairement minimaliste: ignorer les erreurs profil.
}

$displayName = trim($profile['name'] . ' ' . $profile['last_name']);
if ($displayName === '') {
  $displayName = 'Utilisateur';
}

$page_title = 'Mon compte';
$page_css = 'pages/mon-compte.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="section account-page">
    <div class="container">
      <header class="account-page__head">
        <h1>Mon compte</h1>
        <p class="section-subtitle">Gerez vos informations et accedez rapidement a vos actions principales.</p>
      </header>

      <div class="account-grid">
        <section class="card account-card" aria-labelledby="accountInfoTitle">
          <div class="account-card__head">
            <h2 id="accountInfoTitle">Informations personnelles</h2>
          </div>
          <dl class="account-kv">
            <div class="account-kv__row">
              <dt>Nom</dt>
              <dd><?php echo e($displayName !== '' ? $displayName : 'Non renseigne'); ?></dd>
            </div>
            <div class="account-kv__row">
              <dt>Telephone</dt>
              <dd><?php echo e($profile['phone'] !== '' ? $profile['phone'] : 'Non renseigne'); ?></dd>
            </div>
            <div class="account-kv__row">
              <dt>Email</dt>
              <dd><?php echo e($profile['email'] !== '' ? $profile['email'] : 'Non renseigne'); ?></dd>
            </div>
          </dl>
        </section>

        <section class="card account-card" aria-labelledby="accountActionsTitle">
          <div class="account-card__head">
            <h2 id="accountActionsTitle">Actions</h2>
          </div>
          <div class="account-actions">
            <a href="<?php echo e(base_url('pages/modifier-profil.php')); ?>" class="account-action">
              <i class="fas fa-pen-to-square" aria-hidden="true"></i>
              <span>Modifier mes informations</span>
            </a>
            <a href="<?php echo e(base_url('pages/changer-mot-de-passe.php')); ?>" class="account-action">
              <i class="fas fa-key" aria-hidden="true"></i>
              <span>Changer mon mot de passe</span>
            </a>
          </div>
          <p class="account-note">Mettez a jour votre profil et vos acces en toute securite.</p>
        </section>

        <section class="card account-card" aria-labelledby="accountQuickLinksTitle">
          <div class="account-card__head">
            <h2 id="accountQuickLinksTitle">Historique rapide</h2>
          </div>
          <div class="account-links">
            <a href="<?php echo e(base_url('pages/mes-commandes.php')); ?>" class="account-link">
              <i class="fas fa-list" aria-hidden="true"></i>
              <span>Mes commandes</span>
            </a>
            <a href="<?php echo e(base_url('pages/suivi.php')); ?>" class="account-link">
              <i class="fas fa-truck" aria-hidden="true"></i>
              <span>Suivi commande</span>
            </a>
          </div>
        </section>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
