<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';

/**
 * Retourne la valeur d'un setting (string) ou $default si absent.
 */
function setting(string $key, $default = null)
{
  static $cache = array();
  $key = trim($key);
  if ($key === '') return $default;

  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }

  try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key_name = :k LIMIT 1');
    $stmt->execute(array('k' => $key));
    $val = $stmt->fetchColumn();
    if ($val === false) {
      $cache[$key] = $default;
      return $default;
    }
    $cache[$key] = (string) $val;
    return $cache[$key];
  } catch (Throwable $e) {
    $cache[$key] = $default;
    return $default;
  }
}

function set_setting(string $key, string $value): void
{
  $key = trim($key);
  if ($key === '') {
    throw new RuntimeException('Clé setting invalide.');
  }
  $value = (string) $value;

  $pdo = db();
  $stmt = $pdo->prepare(
    'INSERT INTO settings (key_name, value, updated_at)
     VALUES (:k, :v, NOW())
     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
  );
  $stmt->execute(array('k' => $key, 'v' => $value));
}

/**
 * Retourne true si au moins une variable d'environnement de la liste est definie et non vide.
 *
 * @param string[] $envKeys
 */
function env_has_any(array $envKeys): bool
{
  foreach ($envKeys as $envKey) {
    $envKey = trim((string) $envKey);
    if ($envKey === '') {
      continue;
    }
    $value = getenv($envKey);
    if ($value !== false && trim((string) $value) !== '') {
      return true;
    }
  }
  return false;
}

/**
 * Lit une valeur uniquement depuis l'environnement (pas de fallback DB).
 *
 * @param string[] $envKeys
 */
function env_value(array $envKeys, $default = '')
{
  foreach ($envKeys as $envKey) {
    $envKey = trim((string) $envKey);
    if ($envKey === '') {
      continue;
    }
    $value = getenv($envKey);
    if ($value !== false && (string) $value !== '') {
      return (string) $value;
    }
  }
  return $default;
}
