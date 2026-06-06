<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('login', 20, 60);

$body = json_read_body();
$identifier = trim((string) ($body['identifier'] ?? ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if ($identifier === '' || $password === '') {
  json_response(422, array('ok' => false, 'message' => 'Champs invalides.'));
}

$isEmail = api_is_valid_email($identifier);
$phoneDigits = api_normalize_phone_digits($identifier);
$isPhone = (!$isEmail && $phoneDigits !== '' && strlen($phoneDigits) >= 8 && strlen($phoneDigits) <= 15);

if (!$isEmail && !$isPhone) {
  json_response(422, array('ok' => false, 'message' => 'Veuillez saisir un email ou un téléphone valide.'));
}

try {
  $pdo = api_pdo();
  $uc = api_users_columns($pdo);
  $passCol = (string) ($uc['password_col'] ?? '');
  $phoneCol = (string) ($uc['phone_col'] ?? '');

  if ($passCol === '') {
    json_response(500, array('ok' => false, 'message' => 'Configuration users invalide.'));
  }

  $fields = array('id', 'email', $passCol . ' AS password_value');
  if (!empty($uc['name_col'])) $fields[] = $uc['name_col'] . ' AS name';
  if (!empty($uc['last_name_col'])) $fields[] = $uc['last_name_col'] . ' AS last_name';
  if (!empty($uc['deleted_at_col'])) $fields[] = $uc['deleted_at_col'] . ' AS deleted_at';
  if (!empty($uc['role_col'])) $fields[] = $uc['role_col'] . ' AS role';

  if ($isEmail) {
    $sql = 'SELECT ' . implode(', ', $fields) . ' FROM users WHERE email = :v LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array('v' => strtolower($identifier)));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  } else {
    if ($phoneCol === '') {
      json_response(500, array(
        'ok' => false,
        'message' => 'La base doit avoir users.phone pour se connecter par téléphone. Appliquez le patch SQL.',
      ));
    }
    $phoneStored = api_normalize_phone_storage($identifier);
    $sql = 'SELECT ' . implode(', ', $fields) . ' FROM users WHERE ' . $phoneCol . ' = :v LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array('v' => $phoneStored));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  if (!$row) {
    json_response(401, array('ok' => false, 'message' => 'Identifiants incorrects.'));
  }
  if (!empty($uc['deleted_at_col']) && !empty((string) ($row['deleted_at'] ?? ''))) {
    json_response(403, array('ok' => false, 'message' => 'Compte supprimé.'));
  }

  $hash = (string) ($row['password_value'] ?? '');
  if ($hash === '' || !password_verify($password, $hash)) {
    json_response(401, array('ok' => false, 'message' => 'Identifiants incorrects.'));
  }

  $user = array(
    'id' => (int) ($row['id'] ?? 0),
    'email' => (string) ($row['email'] ?? ''),
    'role' => (string) ($row['role'] ?? ''),
    'name' => (string) ($row['name'] ?? ''),
    'last_name' => (string) ($row['last_name'] ?? ''),
  );

  login_user($user);
  $lastName = trim((string) ($user['last_name'] ?? ''));
  $msg = $lastName !== '' ? ('Bienvenue ' . $lastName) : 'Bienvenue';
  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
    $_SESSION['flash'] = array();
  }
  $_SESSION['flash'][] = $msg;

  json_response(200, array(
    'ok' => true,
    'user' => array(
      'id' => (int) $user['id'],
      'email' => (string) $user['email'],
      'role' => (string) $user['role'],
      'name' => (string) $user['name'],
      'last_name' => (string) $user['last_name'],
    ),
  ));
} catch (Throwable $e) {
  error_log('[api/login] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de se connecter.'));
}
