<?php
declare(strict_types=1);

// Script temporaire : crée (ou réinitialise) un compte partenaire local.
// À SUPPRIMER après usage.

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$isCli = (PHP_SAPI === 'cli');
$isLocal = ($ip === '127.0.0.1' || $ip === '::1');

if (!$isCli && !$isLocal) {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';

$email = 'partner@malishop.com';
$pass = 'Partner123!';
$role = 'partner';

$reset = false;
if ($isCli) {
  $args = $_SERVER['argv'] ?? [];
  $reset = in_array('--reset', $args, true);
} else {
  $reset = (($_GET['reset'] ?? '') === '1');
}

function users_table_columns(PDO $pdo): array
{
  $rows = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $fields = [];
  foreach ($rows as $row) {
    if (!empty($row['Field'])) {
      $fields[] = (string) $row['Field'];
    }
  }
  return $fields;
}

try {
  $pdo = db();
  if (!password_meets_policy($pass)) {
    throw new RuntimeException(password_policy_message());
  }
  $cols = users_table_columns($pdo);

  $passwordCol = null;
  if (in_array('password_hash', $cols, true)) {
    $passwordCol = 'password_hash';
  } elseif (in_array('password', $cols, true)) {
    $passwordCol = 'password';
  }
  if (!$passwordCol) {
    throw new RuntimeException('users.password_hash/password not found');
  }

  $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
  $stmt->execute(['email' => $email]);
  $existingId = (int) ($stmt->fetchColumn() ?: 0);

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  if ($existingId > 0) {
    if (!$reset) {
      echo "Partner existe\n";
      exit;
    }

    $sets = ["{$passwordCol} = :hash"];
    $params = ['hash' => $hash, 'id' => $existingId];

    if (in_array('role', $cols, true)) {
      $sets[] = 'role = :role';
      $params['role'] = $role;
    }

    if (in_array('name', $cols, true)) {
      $sets[] = 'name = :name';
      $params['name'] = 'Partenaire';
    }

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    echo "Partner réinitialisé\n";
    echo "Email: {$email}\n";
    echo "Pass: {$pass}\n";
    exit;
  }

  $fields = ['email', $passwordCol];
  $placeholders = [':email', ':hash'];
  $params = ['email' => $email, 'hash' => $hash];

  if (in_array('role', $cols, true)) {
    $fields[] = 'role';
    $placeholders[] = ':role';
    $params['role'] = $role;
  }

  if (in_array('name', $cols, true)) {
    $fields[] = 'name';
    $placeholders[] = ':name';
    $params['name'] = 'Partenaire';
  }

  $sql = 'INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
  $ins = $pdo->prepare($sql);
  $ins->execute($params);

  echo "Partner créé\n";
  echo "Email: {$email}\n";
  echo "Pass: {$pass}\n";
} catch (Throwable $e) {
  log_error('[scripts/create-partner] ' . $e->getMessage());
  echo "Erreur\n";
}

