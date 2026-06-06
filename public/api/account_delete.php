<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function account_delete_redirect_back(): void
{
  redirect('pages/mes-commandes.php');
}

function account_delete_set_flash(string $message): void
{
  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
    $_SESSION['flash'] = array();
  }
  $_SESSION['flash'][] = $message;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
  account_delete_set_flash('Action non autorisée.');
  account_delete_redirect_back();
}

$user = current_user();
if (!$user || (int) ($user['id'] ?? 0) <= 0) {
  account_delete_set_flash('Veuillez vous connecter.');
  redirect('pages/connexion.php?redirect=' . urlencode('pages/mes-commandes.php'));
}

if (!csrf_verify($_POST['_csrf'] ?? null)) {
  account_delete_set_flash('Session expirée. Veuillez réessayer.');
  account_delete_redirect_back();
}

$password = (string) ($_POST['password'] ?? '');
$confirm = (string) ($_POST['confirm_delete'] ?? '');
if (trim($password) === '') {
  account_delete_set_flash('Mot de passe obligatoire.');
  account_delete_redirect_back();
}
if ($confirm !== '1') {
  account_delete_set_flash('Veuillez confirmer la suppression.');
  account_delete_redirect_back();
}

try {
  $pdo = api_pdo();
  $uc = api_users_columns($pdo);
  $passCol = (string) ($uc['password_col'] ?? '');
  $deletedCol = (string) ($uc['deleted_at_col'] ?? '');

  if ($passCol === '' || $deletedCol === '') {
    account_delete_set_flash('Configuration compte incomplète.');
    account_delete_redirect_back();
  }

  $uid = (int) ($user['id'] ?? 0);
  $sql = 'SELECT id, ' . $passCol . ' AS password_value, ' . $deletedCol . ' AS deleted_at'
    . ' FROM users WHERE id = :id LIMIT 1';
  $stmt = $pdo->prepare($sql);
  $stmt->execute(array('id' => $uid));
  $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  if (!$row) {
    account_delete_set_flash('Compte introuvable.');
    account_delete_redirect_back();
  }

  if (!empty((string) ($row['deleted_at'] ?? ''))) {
    logout_user();
    account_delete_set_flash('Compte déjà supprimé.');
    redirect('index.php');
  }

  $hash = (string) ($row['password_value'] ?? '');
  if ($hash === '' || !password_verify($password, $hash)) {
    account_delete_set_flash('Mot de passe incorrect.');
    account_delete_redirect_back();
  }

  $pdo->beginTransaction();
  $upd = $pdo->prepare('UPDATE users SET ' . $deletedCol . ' = NOW() WHERE id = :id AND ' . $deletedCol . ' IS NULL');
  $upd->execute(array('id' => $uid));
  if ($upd->rowCount() !== 1) {
    $pdo->rollBack();
    account_delete_set_flash('Impossible de supprimer le compte.');
    account_delete_redirect_back();
  }
  $pdo->commit();

  logout_user();
  account_delete_set_flash('Votre compte a été supprimé.');
  redirect('index.php');
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log('[api/account_delete] ' . $e->getMessage());
  account_delete_set_flash('Erreur serveur. Veuillez réessayer.');
  account_delete_redirect_back();
}

