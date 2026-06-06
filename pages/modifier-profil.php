<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';
require_user_login();

/**
 * Même logique de normalisation que le flux login/register.
 */
function profile_normalize_phone_digits(string $raw): string
{
  $v = trim($raw);
  $v = preg_replace('/\D+/', '', $v);
  $v = (string) $v;
  if (strpos($v, '00') === 0 && strlen($v) > 2) {
    $v = substr($v, 2);
  }
  return $v;
}

function profile_normalize_phone_storage(string $raw): string
{
  $raw = trim($raw);
  $digits = profile_normalize_phone_digits($raw);
  if ($digits === '') return '';

  $hasPlus = str_starts_with($raw, '+');
  $has00 = str_starts_with($raw, '00');
  if ($hasPlus || $has00) return '+' . $digits;
  if (strlen($digits) >= 8 && strlen($digits) <= 10) return '+223' . $digits;
  return '+' . $digits;
}

function profile_is_valid_email(string $email): bool
{
  $email = trim($email);
  if ($email === '' || strlen($email) > 190) return false;
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

$authUser = current_user();
$userId = (int) ($authUser['id'] ?? 0);
if ($userId <= 0) {
  redirect('pages/connexion.php');
}

$notice = '';
$noticeType = 'error';
$errors = array();

$pdo = db();
$userCols = db_table_columns($pdo, 'users');
$editable = array();
foreach (array('name', 'last_name', 'email', 'phone') as $col) {
  if (in_array($col, $userCols, true)) $editable[] = $col;
}

$fields = $editable ?: array('name', 'last_name', 'email', 'phone');
$sql = 'SELECT ' . implode(', ', $fields) . ' FROM users WHERE id = :id LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute(array('id' => $userId));
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();

$form = array(
  'name' => trim((string) ($dbUser['name'] ?? ($authUser['name'] ?? ''))),
  'last_name' => trim((string) ($dbUser['last_name'] ?? ($authUser['last_name'] ?? ''))),
  'email' => trim((string) ($dbUser['email'] ?? ($authUser['email'] ?? ''))),
  'phone' => trim((string) ($dbUser['phone'] ?? '')),
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expiree. Veuillez reessayer.';
  } else {
    if (in_array('name', $editable, true)) $form['name'] = trim((string) ($_POST['name'] ?? ''));
    if (in_array('last_name', $editable, true)) $form['last_name'] = trim((string) ($_POST['last_name'] ?? ''));
    if (in_array('email', $editable, true)) $form['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (in_array('phone', $editable, true)) $form['phone'] = trim((string) ($_POST['phone'] ?? ''));

    if (in_array('name', $editable, true)) {
      if ($form['name'] === '' || strlen($form['name']) < 2 || strlen($form['name']) > 190) {
        $errors[] = 'Nom invalide.';
      }
    }
    if (in_array('last_name', $editable, true)) {
      if ($form['last_name'] === '' || strlen($form['last_name']) < 2 || strlen($form['last_name']) > 100) {
        $errors[] = 'Nom de famille invalide.';
      }
    }
    if (in_array('email', $editable, true) && $form['email'] !== '' && !profile_is_valid_email($form['email'])) {
      $errors[] = 'Email invalide.';
    }

    $phoneStorage = '';
    if (in_array('phone', $editable, true) && $form['phone'] !== '') {
      $phoneStorage = profile_normalize_phone_storage($form['phone']);
      $digits = profile_normalize_phone_digits($form['phone']);
      if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
        $errors[] = 'Telephone invalide.';
      }
      $form['phone'] = $phoneStorage;
    } elseif (in_array('phone', $editable, true)) {
      $form['phone'] = '';
    }

    if (!$errors && in_array('email', $editable, true) && $form['email'] !== '') {
      $q = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
      $q->execute(array('email' => $form['email'], 'id' => $userId));
      if ($q->fetch(PDO::FETCH_ASSOC)) $errors[] = 'Cet email est deja utilise.';
    }

    if (!$errors && in_array('phone', $editable, true) && $form['phone'] !== '') {
      $q = $pdo->prepare('SELECT id FROM users WHERE phone = :phone AND id <> :id LIMIT 1');
      $q->execute(array('phone' => $form['phone'], 'id' => $userId));
      if ($q->fetch(PDO::FETCH_ASSOC)) $errors[] = 'Ce numero de telephone est deja utilise.';
    }

    if (!$errors) {
      $sets = array();
      $params = array('id' => $userId);

      if (in_array('name', $editable, true)) {
        $sets[] = 'name = :name';
        $params['name'] = $form['name'];
      }
      if (in_array('last_name', $editable, true)) {
        $sets[] = 'last_name = :last_name';
        $params['last_name'] = $form['last_name'];
      }
      if (in_array('email', $editable, true)) {
        $sets[] = 'email = :email';
        $params['email'] = ($form['email'] === '' ? null : $form['email']);
      }
      if (in_array('phone', $editable, true)) {
        $sets[] = 'phone = :phone';
        $params['phone'] = ($form['phone'] === '' ? null : $form['phone']);
      }

      if ($sets) {
        $upd = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1');
        $upd->execute($params);
      }

      $sessionUser = current_user() ?: array();
      login_user(array(
        'id' => $userId,
        'email' => (string) ($form['email'] ?? (string) ($sessionUser['email'] ?? '')),
        'role' => (string) ($sessionUser['role'] ?? ''),
        'name' => (string) ($form['name'] ?? (string) ($sessionUser['name'] ?? '')),
        'last_name' => (string) ($form['last_name'] ?? (string) ($sessionUser['last_name'] ?? '')),
        'is_active' => (int) ($sessionUser['is_active'] ?? 1),
      ));

      $notice = 'Profil mis a jour avec succes.';
      $noticeType = 'success';
    }
  }
}

if ($errors) {
  $notice = implode(' ', $errors);
  $noticeType = 'error';
}

$page_title = 'Modifier mon profil';
$page_css = 'pages/mon-compte.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main id="main">
  <section class="section account-page account-form-page">
    <div class="container">
      <header class="account-page__head">
        <a class="account-back-link" href="<?php echo e(base_url('pages/mon-compte.php')); ?>">&larr; Retour a mon compte</a>
        <h1>Modifier mes informations</h1>
        <p class="section-subtitle">Mettez a jour vos informations personnelles.</p>
      </header>

      <section class="card account-card account-form-card profile-form-card" aria-labelledby="editProfileTitle">
        <div class="account-card__head">
          <h2 id="editProfileTitle">Informations personnelles</h2>
        </div>

        <?php if ($notice !== ''): ?>
          <div class="notice <?php echo $noticeType === 'success' ? 'is-success' : 'is-error'; ?>" role="status" aria-live="polite">
            <?php echo e($notice); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="account-form" novalidate>
          <?php echo csrf_field(); ?>

          <?php if (in_array('name', $editable, true)): ?>
            <div class="account-form__row">
              <label for="name">Nom</label>
              <input id="name" name="name" type="text" class="form-input" maxlength="190" required value="<?php echo e($form['name']); ?>">
            </div>
          <?php endif; ?>

          <?php if (in_array('last_name', $editable, true)): ?>
            <div class="account-form__row">
              <label for="last_name">Nom de famille</label>
              <input id="last_name" name="last_name" type="text" class="form-input" maxlength="100" required value="<?php echo e($form['last_name']); ?>">
            </div>
          <?php endif; ?>

          <?php if (in_array('email', $editable, true)): ?>
            <div class="account-form__row">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" class="form-input" maxlength="190" value="<?php echo e($form['email']); ?>">
            </div>
          <?php endif; ?>

          <?php if (in_array('phone', $editable, true)): ?>
            <div class="account-form__row">
              <label for="phone">Telephone</label>
              <input id="phone" name="phone" type="text" inputmode="tel" class="form-input" maxlength="32" value="<?php echo e($form['phone']); ?>">
            </div>
          <?php endif; ?>

          <div class="account-form__actions">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="<?php echo e(base_url('pages/mon-compte.php')); ?>" class="btn btn-secondary">Annuler</a>
          </div>
        </form>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
