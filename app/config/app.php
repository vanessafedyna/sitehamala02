<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
env_load();

// Configuration applicative minimale (V1).
// APP_DEBUG est pilote par l'environnement pour ne pas forcer la prod ni casser le local.
$appDebugRaw = strtolower(trim((string) (getenv('APP_DEBUG') ?: 'false')));
$appDebug = in_array($appDebugRaw, array('1', 'true', 'yes', 'on'), true);

defined('APP_DEBUG') || define('APP_DEBUG', $appDebug);

// Debug autorise uniquement depuis localhost (si APP_DEBUG=true).
defined('APP_DEBUG_ALLOWED_IPS') || define('APP_DEBUG_ALLOWED_IPS', array('127.0.0.1', '::1'));
