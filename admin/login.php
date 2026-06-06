<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

auth_start();

// Si une session existe deja mais sans role staff, on la purge pour eviter un blocage.
$existing = current_admin_user();
if ($existing) {
  $existingRole = strtolower(trim((string) ($existing['role'] ?? '')));
  if (in_array($existingRole, array('admin', 'partner'), true)) {
    redirect('admin/index.php');
  }
  logout_admin_user();
}

$error = '';
$email = '';
/* Affiche un message dédié après la désactivation d'un compte. */
if (($_GET['disabled'] ?? '') === '1') {
  $error = 'Compte desactive. Contactez le proprietaire.';
}

/* Protection anti-bruteforce */
function login_attempts_is_blocked(PDO $pdo, string $email, string $ip): bool
{
  $email = strtolower(trim($email));
  $ip = trim($ip);
  if ($email === '' || $ip === '') return false;

  try {
    $stmt = $pdo->prepare('SELECT blocked_until FROM login_attempts WHERE email = :email AND ip = :ip LIMIT 1');
    $stmt->execute(array('email' => $email, 'ip' => $ip));
    $blockedUntil = (string) ($stmt->fetchColumn() ?: '');
    if ($blockedUntil === '') return false;

    $bu = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $blockedUntil);
    if (!$bu) return false;

    return $bu > new DateTimeImmutable('now');
  } catch (Throwable $e) {
    return false;
  }
}

/* Suivi des tentatives */
function login_attempts_register_failure(PDO $pdo, string $email, string $ip, int $maxFails = 5, int $blockMinutes = 10): void
{
  $email = strtolower(trim($email));
  $ip = trim($ip);
  if ($email === '' || $ip === '') return;

  try {
    $blockMinutes = max(1, (int) $blockMinutes);
    $maxFails = max(1, (int) $maxFails);
    $blockedUntil = (new DateTimeImmutable('now +' . $blockMinutes . ' minutes'))->format('Y-m-d H:i:s');

    $sql = 'INSERT INTO login_attempts (email, ip, fail_count, first_failed_at, last_failed_at, blocked_until)
            VALUES (:email, :ip, 1, NOW(), NOW(), NULL)
            ON DUPLICATE KEY UPDATE
              fail_count = fail_count + 1,
              last_failed_at = NOW(),
              blocked_until = CASE
                WHEN (fail_count + 1) >= :maxFails THEN :blockedUntil
                ELSE blocked_until
              END';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(
      'email' => $email,
      'ip' => $ip,
      'maxFails' => $maxFails,
      'blockedUntil' => $blockedUntil,
    ));
  } catch (Throwable $e) {
    return;
  }
}

/* Réinitialisation après succès */
function login_attempts_clear(PDO $pdo, string $email, string $ip): void
{
  $email = strtolower(trim($email));
  $ip = trim($ip);
  if ($email === '' || $ip === '') return;

  try {
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = :email AND ip = :ip');
    $stmt->execute(array('email' => $email, 'ip' => $ip));
  } catch (Throwable $e) {
    return;
  }
}

function users_table_columns(PDO $pdo): array
{
  try {
    if (function_exists('db_table_columns')) {
      return db_table_columns($pdo, 'users');
    }
    return array();
  } catch (Throwable $e) {
    return array();
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $error = 'Session expiree. Veuillez reessayer.';
  } else {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Veuillez saisir un email valide.';
    } elseif ($password === '') {
      $error = 'Veuillez saisir votre mot de passe.';
    } else {
      try {
        $pdo = db();
       
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (login_attempts_is_blocked($pdo, $email, $ip)) {
          AdminAuditService::log($pdo, null, 'login_blocked');
          $error = 'Trop de tentatives. Reessayez dans quelques minutes.';
          throw new RuntimeException('blocked');
        }

        $cols = users_table_columns($pdo);
        $has_role = in_array('role', $cols, true);
        $has_name = in_array('name', $cols, true);
        /* Compatibilité avec les schémas qui exposent l'état d'activation du compte. */
        $has_is_active = in_array('is_active', $cols, true);

        $password_col = null;
        if (in_array('password_hash', $cols, true)) {
          $password_col = 'password_hash';
        } elseif (in_array('password', $cols, true)) {
          $password_col = 'password';
        }

        if (!$password_col) {
          $error = 'Configuration utilisateurs invalide.';
          throw new RuntimeException('users.password_hash/password not found');
        }

        $select = array('id', 'email', $password_col);
        if ($has_role) $select[] = 'role';
        if ($has_name) $select[] = 'name';
        /* Charge l'état d'activation uniquement si la colonne existe. */
        if ($has_is_active) $select[] = 'is_active';

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM users WHERE email = :email LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array('email' => $email));
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $allowed_roles = array('admin', 'partner');
        $role = $user && $has_role ? (string) ($user['role'] ?? '') : '';
        $role_norm = strtolower(trim($role));
        /* En l'absence de colonne dédiée, le compte est considéré actif. */
        $isActive = $user && $has_is_active ? (int) ($user['is_active'] ?? 1) : 1;

        // Si la colonne role n'existe pas (ancien schema), on autorise uniquement l'email admin par defaut.
        if (!$has_role && $user) {
          $role_norm = ($email === 'admin@malishop.com') ? 'admin' : '';
        }

        $hash = $user ? trim((string) ($user[$password_col] ?? '')) : '';

        $ok = $user
          && in_array($role_norm, $allowed_roles, true)
          && $isActive === 1
          && $hash !== ''
          && password_verify($password, $hash);

        if ($ok) {
          login_admin_user(array(
            'id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'role' => $role_norm,
            'name' => $user && $has_name ? (string) ($user['name'] ?? '') : '',
            /* Conserve l'état du compte en session pour les contrôles d'accès admin. */
            'is_active' => $isActive,
          ));

         
          $_SESSION['admin_id'] = (int) ($user['id'] ?? 0);
          $_SESSION['admin_email'] = (string) ($user['email'] ?? '');
          $_SESSION['admin_role'] = ($role_norm === 'admin') ? 'owner' : 'partner';

         
          login_attempts_clear($pdo, $email, $ip);
          AdminAuditService::log($pdo, (int) ($user['id'] ?? 0), 'login_success');

          session_write_close();
          redirect('admin/index.php');
        }

       
        login_attempts_register_failure($pdo, $email, $ip, 5, 10);
        AdminAuditService::log($pdo, $user ? (int) ($user['id'] ?? 0) : null, 'login_failed');

        $error = ($user && $has_is_active && $isActive !== 1) ? 'Compte desactive.' : 'Identifiants incorrects.';
      } catch (Throwable $e) {
        error_log('[admin/login] ' . $e->getMessage());
        if ($error === '') {
          $error = 'Erreur de connexion.';
        }
      }
    }
  }
}

$page_title = 'Connexion admin';
$page_css = 'pages/admin-login.css';
$page_js = '';
$body_class = 'page-admin-login';
$main_css_version = (int) (@filemtime(__DIR__ . '/../assets/css/main.css') ?: time());
$page_css_files = is_array($page_css) ? $page_css : array_filter(array($page_css));
$page_js_files = is_array($page_js) ? $page_js : array_filter(array($page_js));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($page_title); ?></title>
  <link rel="icon" type="image/svg+xml" href="<?php echo e(base_url('assets/images/branding/logo-sitehamala.svg')); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?php echo e(base_url('assets/css/main.css?v=' . $main_css_version)); ?>">
  <?php foreach ($page_css_files as $css_file): ?>
    <?php $page_css_v = @filemtime(__DIR__ . '/../assets/css/' . $css_file) ?: $main_css_version; ?>
    <link rel="stylesheet" href="<?php echo e(base_url('assets/css/' . $css_file . '?v=' . (int) $page_css_v)); ?>">
  <?php endforeach; ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">

<style>
  .page-admin-login {
    background:
      radial-gradient(circle at top left, rgba(31, 122, 79, 0.08), transparent 30%),
      linear-gradient(180deg, #f5faf7 0%, #fbfcfb 100%);
  }
  .page-admin-login #main {
    min-height: 100vh;
  }
  .admin-login-page {
    display: grid;
    align-items: center;
    min-height: calc(100vh - 120px);
    padding: 24px 0 36px;
  }
  .admin-login-shell {
    width: min(100%, 440px);
    margin: 0 auto;
  }
  .admin-login-card,
  .admin-login-alert {
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 18px 42px rgba(18, 52, 36, 0.06);
  }
  .admin-login-card {
    display: grid;
    gap: 18px;
    min-width: 0;
    padding: 30px;
  }
  .admin-login-card__head {
    display: grid;
    gap: 10px;
    justify-items: center;
    text-align: center;
  }
  .admin-login-card__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: fit-content;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid rgba(31, 122, 79, 0.12);
    border-radius: 999px;
    background: rgba(248, 251, 249, 0.92);
    color: #1f7a4f;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .admin-login-card__title {
    margin: 0;
    color: #153222;
    font-size: clamp(1.55rem, 2.4vw, 1.85rem);
    line-height: 1.12;
  }
  .admin-login-card__text {
    margin: 0;
    color: rgba(21, 50, 34, 0.68);
    line-height: 1.55;
  }
  .admin-login-alert {
    padding: 14px 16px;
    border-left: 4px solid #b63b20;
    color: #153222;
  }
  .admin-login-alert strong {
    color: #7d2718;
  }
  .auth-card {
    display: block;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
  }
  .auth-form {
    display: grid;
    gap: 18px;
  }
  .auth-field {
    display: grid;
    gap: 8px;
  }
  .auth-label {
    color: #153222;
    font-size: 0.92rem;
    font-weight: 700;
  }
  .auth-input {
    width: 100%;
    min-width: 0;
    min-height: 48px;
    padding: 12px 14px;
    border: 1px solid rgba(31, 122, 79, 0.12);
    border-radius: 14px;
    background: #fbfcfb;
    color: #153222;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
    transition: border-color 160ms ease, box-shadow 160ms ease, background-color 160ms ease;
  }
  .auth-input:focus-visible {
    outline: 0;
    border-color: rgba(31, 122, 79, 0.24);
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.14);
    background: #ffffff;
  }
  .auth-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding-top: 8px;
  }
  .auth-actions .btn {
    min-height: 46px;
    border-radius: 14px;
    background-image: none;
  }
  .auth-actions .btn.btn-primary {
    background: #1f7a4f;
    border-color: #1f7a4f;
    color: #ffffff;
  }
  .auth-actions .btn.btn-primary:hover,
  .auth-actions .btn.btn-primary:focus-visible {
    background: #17613f;
    border-color: #17613f;
    color: #ffffff;
  }
  .auth-actions .btn.btn-outline {
    background: rgba(248, 251, 249, 0.96);
    border-color: rgba(31, 122, 79, 0.14);
    color: #1f7a4f;
  }
  .auth-actions .btn.btn-outline:hover,
  .auth-actions .btn.btn-outline:focus-visible {
    background: rgba(31, 122, 79, 0.08);
    border-color: rgba(31, 122, 79, 0.22);
    color: #17613f;
  }
  .auth-actions .btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.14);
  }
  @media (max-width: 1024px) {
    .admin-login-card {
      width: 100%;
    }
  }
  @media (max-width: 820px) {
    .admin-login-page {
      padding-top: 16px;
    }
    .admin-login-card {
      padding: 22px;
    }
  }
  @media (max-width: 430px) {
    .admin-login-page {
      min-height: auto;
      padding: 10px 0 24px;
    }
    .admin-login-card,
    .admin-login-alert {
      border-radius: 20px;
    }
    .admin-login-card {
      padding: 18px;
    }
    .auth-actions {
      display: grid;
      grid-template-columns: 1fr;
    }
    .auth-actions .btn {
      width: 100%;
      justify-content: center;
    }
  }
</style>
</head>
<body class="page-admin-login">

<main id="main">
  <section>
    <div class="container">
      <div class="admin-login-page">
        <div class="admin-login-shell">
          <div class="admin-login-card" aria-label="Formulaire de connexion admin">
            <div class="admin-login-card__head">
              <div class="admin-login-card__eyebrow">
                <i class="fas fa-user-shield" aria-hidden="true"></i>
                Back-office admin
              </div>
              <h1 class="admin-login-card__title">Connexion admin</h1>
              <p class="admin-login-card__text">Utilisez vos identifiants admin pour acceder au back-office.</p>
            </div>

            <?php if ($error): ?>
              <div class="admin-login-alert" role="alert">
                <strong><?php echo e($error); ?></strong>
              </div>
            <?php endif; ?>

            <div class="auth-card">
              <form method="post" action="" class="auth-form" novalidate>
                <?php echo csrf_field(); ?>

                <div class="auth-field">
                  <label class="auth-label" for="email">Email</label>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    class="auth-input"
                    autocomplete="username"
                    required
                    value="<?php echo e($email); ?>"
                  >
                </div>

                <div class="auth-field">
                  <label class="auth-label" for="password">Mot de passe</label>
                  <input
                    id="password"
                    name="password"
                    type="password"
                    class="auth-input"
                    autocomplete="current-password"
                    required
                  >
                </div>

                <div class="auth-actions">
                  <button class="btn btn-primary" type="submit">Se connecter</button>
                  <a class="btn btn-outline" href="<?php echo e(base_url('index.php')); ?>">Annuler</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php $main_js_v = @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>
<script src="<?php echo e(base_url('assets/js/main.js?v=' . (int) $main_js_v)); ?>"></script>
<?php foreach ($page_js_files as $js_file): ?>
  <?php $page_js_v = @filemtime(__DIR__ . '/../assets/js/' . $js_file) ?: $main_js_v; ?>
  <script src="<?php echo e(base_url('assets/js/' . $js_file . '?v=' . (int) $page_js_v)); ?>"></script>
<?php endforeach; ?>
</body>
</html>
