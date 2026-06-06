<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';
require_once dirname(__DIR__, 2) . '/includes/Logger.php';

/**
 * Connexion PDO réutilisable (V1).
 * - utf8mb4
 * - erreurs en exceptions
 * - fetch associatif par défaut
 * - vraies requêtes préparées (pas d’emulation)
 * - aucune info sensible à l’écran (log fichier uniquement)
 */
function db(): PDO
{
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  if (!class_exists(PDO::class)) {
    if (class_exists('Logger')) {
      Logger::error('db_extension_missing');
    } else {
      log_error('[DB] Extension PDO non disponible.');
    }
    throw new RuntimeException('Erreur de connexion');
  }

  // Valeurs locales par defaut (surchargeables via variables d'environnement).
  // Compat legacy conservee temporairement: MALISHOP_DB_* (a retirer apres migration env complete).
 $host = (string) ($_ENV['DB_HOST'] ?? env('DB_HOST', env('MALISHOP_DB_HOST', '127.0.0.1')));
$name = (string) ($_ENV['DB_NAME'] ?? env('DB_NAME', env('MALISHOP_DB_NAME', 'malishop')));
$user = (string) ($_ENV['DB_USER'] ?? env('DB_USER', env('MALISHOP_DB_USER', 'root')));
$pass = (string) ($_ENV['DB_PASS'] ?? env('DB_PASS', env('MALISHOP_DB_PASS', '')));
$appEnv = strtolower(trim((string) ($_ENV['APP_ENV'] ?? env('APP_ENV', 'dev'))));
  $charset = 'utf8mb4';

  if ($appEnv === 'prod' && strtolower(trim($user)) === 'root') {
    if (class_exists('Logger')) {
      Logger::warn('db_root_user_in_production', array(
        'host' => $host,
        'db' => $name,
      ));
    } else {
      log_error('[DB] APP_ENV=prod avec DB_USER=root detecte pour la base "' . $name . '" sur ' . $host);
    }
  }

  $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

  $options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    // Eviter de bloquer trop longtemps si MySQL est arrêté en local.
    PDO::ATTR_TIMEOUT => 2,
  );

  try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
  } catch (Throwable $e) {
    if (class_exists('Logger')) {
      Logger::error('db_connection_failed', array(
        'host' => $host,
        'db' => $name,
        'error' => $e->getMessage(),
      ));
    } else {
      log_error('[DB] Connexion échouée: ' . $e->getMessage());
    }
    throw new RuntimeException('Erreur de connexion');
  }
}

