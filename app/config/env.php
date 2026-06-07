<?php
declare(strict_types=1);

/**
 * Charge un fichier `.env` (si présent) sans dépendance externe.
 * - Format: KEY=VALUE
 * - Lignes vides et commentaires (# ou ;) ignorés
 * - Valeurs optionnellement entre guillemets
 */
function env_load(?string $filePath = null): void
{
  static $loaded = false;
  if ($loaded) {
    return;
  }
  $loaded = true;

  $root = dirname(__DIR__, 2);
  $path = $filePath ?: ($root . DIRECTORY_SEPARATOR . '.env');
  if (!is_file($path) || !is_readable($path)) {
    return;
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if (!$lines) {
    return;
  }

  foreach ($lines as $line) {
    $line = trim((string) $line);
    if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
      continue;
    }

    $pos = strpos($line, '=');
    if ($pos === false) {
      continue;
    }

    $key = trim(substr($line, 0, $pos));
    $value = trim(substr($line, $pos + 1));

    if ($key === '') {
      continue;
    }

    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
      $value = substr($value, 1, -1);
    }

    // Respecter les variables déjà présentes (environnement XAMPP / OS).
    if (getenv($key) !== false) {
      continue;
    }

    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
  }
}