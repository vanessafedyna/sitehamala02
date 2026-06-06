<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('register', 10, 60);

$body = json_read_body();
$name = trim((string) ($body['name'] ?? ''));
$lastName = trim((string) ($body['last_name'] ?? ''));
$phoneRaw = trim((string) ($body['phone'] ?? ''));
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

function register_normalize_name_part(string $value): string
{
  return preg_replace('/\s+/u', ' ', trim($value)) ?: '';
}

function register_contains_name_part(string $haystack, string $needle): bool
{
  $haystack = register_normalize_name_part($haystack);
  $needle = register_normalize_name_part($needle);
  if ($haystack === '' || $needle === '') {
    return false;
  }

  if (function_exists('mb_strtolower')) {
    $haystack = mb_strtolower($haystack, 'UTF-8');
    $needle = mb_strtolower($needle, 'UTF-8');
  } else {
    $haystack = strtolower($haystack);
    $needle = strtolower($needle);
  }

  return strpos($haystack, $needle) !== false;
}

function register_display_name(string $name, string $lastName): string
{
  $name = register_normalize_name_part($name);
  $lastName = register_normalize_name_part($lastName);

  if ($name === '') {
    return $lastName;
  }
  if ($lastName === '') {
    return $name;
  }
  if (register_contains_name_part($name, $lastName)) {
    return $name;
  }

  return trim($name . ' ' . $lastName);
}

$errors = array();
if ($name === '' || strlen($name) < 2 || strlen($name) > 120) $errors[] = 'Nom invalide.';
if ($lastName === '' || strlen($lastName) < 2 || strlen($lastName) > 100) $errors[] = 'Nom de famille invalide.';
if ($lastName !== '' && !preg_match('/^[\p{L}\p{M}\'\ -]+$/u', $lastName)) $errors[] = 'Nom de famille invalide.';

$phoneDigits = api_normalize_phone_digits($phoneRaw);
if ($phoneDigits === '' || strlen($phoneDigits) < 8 || strlen($phoneDigits) > 15) $errors[] = 'Téléphone invalide.';

if ($email !== '' && !api_is_valid_email($email)) $errors[] = 'Email invalide.';
if (!password_meets_policy($password)) $errors[] = password_policy_message();

if ($errors) {
  json_response(422, array('ok' => false, 'message' => 'Champs invalides.', 'errors' => $errors));
}

try {
  $pdo = api_pdo();
  $uc = api_users_columns($pdo);
  $passCol = (string) ($uc['password_col'] ?? '');
  $phoneCol = (string) ($uc['phone_col'] ?? '');
  $lastNameCol = (string) ($uc['last_name_col'] ?? '');
  $deletedCol = (string) ($uc['deleted_at_col'] ?? '');

  if ($passCol === '') {
    json_response(500, array('ok' => false, 'message' => 'Configuration users invalide.'));
  }
  if ($phoneCol === '') {
    json_response(500, array(
      'ok' => false,
      'message' => 'La base doit avoir users.phone pour inscrire par téléphone. Appliquez le patch SQL.',
    ));
  }
  if ($lastNameCol === '') {
    json_response(500, array(
      'ok' => false,
      'message' => 'La base doit avoir users.last_name. Appliquez le patch SQL users_last_name.',
    ));
  }

  $phoneStored = api_normalize_phone_storage($phoneRaw);

  // Unicite telephone: bloque seulement les comptes actifs.
  $stmt = $pdo->prepare(
    'SELECT id'
    . ($deletedCol !== '' ? (', ' . $deletedCol . ' AS deleted_at') : '')
    . ' FROM users WHERE ' . $phoneCol . ' = :phone LIMIT 1'
  );
  $stmt->execute(array('phone' => $phoneStored));
  $phoneRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

  $reactivateUserId = 0;
  if ($phoneRow) {
    $phoneId = (int) ($phoneRow['id'] ?? 0);
    $phoneDeletedAt = (string) ($phoneRow['deleted_at'] ?? '');
    if ($phoneId > 0) {
      if ($deletedCol !== '' && $phoneDeletedAt !== '') {
        $reactivateUserId = $phoneId;
      } else {
        json_response(409, array('ok' => false, 'message' => 'Ce numéro est déjà utilisé.'));
      }
    }
  }

  // Unicite email: meme regle que le telephone.
  if ($email !== '') {
    $stmt = $pdo->prepare(
      'SELECT id'
      . ($deletedCol !== '' ? (', ' . $deletedCol . ' AS deleted_at') : '')
      . ' FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute(array('email' => $email));
    $emailRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($emailRow) {
      $emailId = (int) ($emailRow['id'] ?? 0);
      $emailDeletedAt = (string) ($emailRow['deleted_at'] ?? '');
      if ($emailId > 0) {
        if ($deletedCol !== '' && $emailDeletedAt !== '') {
          if ($reactivateUserId > 0 && $reactivateUserId !== $emailId) {
            json_response(409, array('ok' => false, 'message' => 'Ce numéro ou cet email est déjà utilisé.'));
          }
          $reactivateUserId = $emailId;
        } else {
          json_response(409, array('ok' => false, 'message' => 'Cet email est déjà utilisé.'));
        }
      }
    }
  }

  $passwordHash = password_hash($password, PASSWORD_DEFAULT);
  $userId = 0;

  if ($reactivateUserId > 0 && $deletedCol !== '') {
    // Reactivation d'un compte supprime: on preserve l'ID et les historiques lies.
    $sets = array(
      $passCol . ' = :hash',
      $phoneCol . ' = :phone',
      'email = :email',
      $lastNameCol . ' = :last_name',
      $deletedCol . ' = NULL',
    );
    $params = array(
      'hash' => $passwordHash,
      'phone' => $phoneStored,
      'email' => ($email === '' ? null : $email),
      'last_name' => $lastName,
      'id' => $reactivateUserId,
    );

    if (!empty($uc['name_col'])) {
      $sets[] = $uc['name_col'] . ' = :name';
      $params['name'] = $name;
    }

    $upd = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1');
    $upd->execute($params);
    if ($upd->rowCount() !== 1) {
      json_response(500, array('ok' => false, 'message' => 'Impossible de créer le compte.'));
    }

    $userId = $reactivateUserId;
  } else {
    $fields = array($passCol, $phoneCol);
    $placeholders = array(':hash', ':phone');
    $params = array(
      'hash' => $passwordHash,
      'phone' => $phoneStored,
    );

    // email optionnel
    $fields[] = 'email';
    $placeholders[] = ':email';
    $params['email'] = ($email === '' ? null : $email);

    if (!empty($uc['name_col'])) {
      $fields[] = $uc['name_col'];
      $placeholders[] = ':name';
      $params['name'] = $name;
    }

    $fields[] = $lastNameCol;
    $placeholders[] = ':last_name';
    $params['last_name'] = $lastName;

    $sql = 'INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $ins = $pdo->prepare($sql);
    $ins->execute($params);
    $userId = (int) $pdo->lastInsertId();
  }

  // Auto-login
  $user = array(
    'id' => $userId,
    'email' => $email,
    'role' => '',
    'name' => $name,
    'last_name' => $lastName,
  );
  login_user($user);

  // Flash one-shot pour la redirection post-inscription.
  $fullName = register_display_name($name, $lastName);
  $welcomeMessage = 'Bienvenue ' . $fullName . ' ! Votre compte a bien été créé.';
  $_SESSION['register_flash'] = array(
    'type' => 'register_success',
    'title' => 'Compte créé avec succès',
    'full_name' => $fullName,
    'message' => $welcomeMessage,
  );

  json_response(201, array(
    'ok' => true,
    'redirect' => base_url('pages/mes-commandes.php'),
    'user' => array(
      'id' => $userId,
      'email' => $email,
      'name' => $name,
      'last_name' => $lastName,
    ),
  ));
} catch (PDOException $e) {
  error_log('[api/register] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de créer le compte.'));
} catch (Throwable $e) {
  error_log('[api/register] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de créer le compte.'));
}