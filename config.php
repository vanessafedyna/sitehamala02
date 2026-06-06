<?php
// config.php - Configuration centrale du site SORA Collection

// Charger .env si present (sans dependance externe)
$env_loader = __DIR__ . '/app/config/env.php';
if (is_file($env_loader)) {
  require_once $env_loader;
  if (function_exists('env_load')) {
    env_load();
  }
}

// ============================================
// 0. CHEMIN DE BASE (auto-detection)
// ============================================
// Priorite:
// 1) APP_BASE_URL (si definie)
// 2) Auto-detection depuis DOCUMENT_ROOT
// 3) Detection depuis SCRIPT_NAME
// 4) Fallback local neutre ("/")

$doc_root_fs = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$project_root_fs = realpath(__DIR__);

$base_url = '/';
$env_base = getenv('APP_BASE_URL');
if ($env_base) {
  $env_base = (string) $env_base;
  if ($env_base !== '') {
    if ($env_base[0] !== '/') {
      $env_base = '/' . $env_base;
    }
    $base_url = rtrim($env_base, '/') . '/';
  }
}
if ($doc_root_fs && $project_root_fs) {
  $doc_root_fs_norm = rtrim(str_replace('\\', '/', $doc_root_fs), '/');
  $project_root_fs_norm = rtrim(str_replace('\\', '/', $project_root_fs), '/');

  if (strpos($project_root_fs_norm, $doc_root_fs_norm) === 0) {
    $relative = substr($project_root_fs_norm, strlen($doc_root_fs_norm));
    $relative = '/' . ltrim($relative, '/');
    if (!$env_base) {
      $base_url = rtrim($relative, '/') . '/';
    }
  }
}
// if (!$env_base && $base_url === '/') {
//   $script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
//   if ($script_name !== '') {
//     $script_dir = str_replace('\\', '/', dirname($script_name));
//     if ($script_dir !== '' && $script_dir !== '.' && $script_dir !== '/') {
//       $base_url = rtrim($script_dir, '/') . '/';
//     }
//   }
// }

// Ne pas deduire la base URL a partir du script courant,
// sinon /pages/faq.php force $base_url a /pages/
if (!$env_base && $base_url === '/') {
  $base_url = '/';
}
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
  || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$scheme = $is_https ? 'https' : 'http';

// Source de verite des URLs publiques absolues.
$app_public_url = trim((string) (getenv('APP_PUBLIC_URL') ?: ''));
if ($app_public_url !== '') {
  $app_public_url = rtrim($app_public_url, '/');
}

// Fallback local uniquement si APP_PUBLIC_URL n'est pas defini.
if ($app_public_url === '') {
  $local_host = 'localhost';
  $server_name = isset($_SERVER['SERVER_NAME']) ? trim((string) $_SERVER['SERVER_NAME']) : '';
  if (in_array($server_name, array('localhost', '127.0.0.1', '::1'), true)) {
    $local_host = $server_name;
  }

  $local_port = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 0;
  $port_suffix = '';
  if ($local_port > 0) {
    if (($scheme === 'http' && $local_port !== 80) || ($scheme === 'https' && $local_port !== 443)) {
      $port_suffix = ':' . $local_port;
    }
  }

  $app_public_url = $scheme . '://' . $local_host . $port_suffix . rtrim($base_url, '/');
}

$site_url = rtrim($app_public_url, '/') . '/';

// ============================================
// 1. CONFIGURATION GENERALE DU SITE
// ============================================

defined('SITE_NAME') || define('SITE_NAME', 'SORA Collection');
defined('APP_PUBLIC_URL') || define('APP_PUBLIC_URL', rtrim($app_public_url, '/'));
defined('SITE_URL') || define('SITE_URL', $site_url);

// ============================================
// 2. CHEMINS (pour les navigateurs)
// ============================================

defined('BASE_URL') || define('BASE_URL', SITE_URL);
defined('ASSETS_URL') || define('ASSETS_URL', BASE_URL . 'assets/');
defined('CSS_URL') || define('CSS_URL', ASSETS_URL . 'css/');
defined('JS_URL') || define('JS_URL', ASSETS_URL . 'js/');
defined('IMAGES_URL') || define('IMAGES_URL', ASSETS_URL . 'images/');

// ============================================
// 3. CONFIGURATION E-COMMERCE
// ============================================

defined('CURRENCY') || define('CURRENCY', 'FCFA');
defined('CURRENCY_SYMBOL') || define('CURRENCY_SYMBOL', 'FCFA');

defined('TAX_RATE') || define('TAX_RATE', 0.00);
defined('SHIPPING_COST') || define('SHIPPING_COST', 1500);

// ============================================
// 4. PARAMETRES DU SITE
// ============================================

defined('PRODUCTS_PER_PAGE') || define('PRODUCTS_PER_PAGE', 12);
defined('ORDERS_PER_PAGE') || define('ORDERS_PER_PAGE', 20);

$available_cities = array(
  'Bamako',
  'Ségou',
  'Sikasso',
  'Mopti',
  'Kayes',
  'Koulikoro',
  'Gao',
  'Kidal',
  'Tombouctou',
);

// ============================================
// 5. SECURITE
// ============================================

defined('OTP_LENGTH') || define('OTP_LENGTH', 6);
defined('OTP_EXPIRE_MINUTES') || define('OTP_EXPIRE_MINUTES', 10);

// ============================================
// 6. BASE DE DONNEES
// ============================================
// Valeurs locales par defaut (surchargees via variables d'environnement).
// Compat legacy conservee temporairement: MALISHOP_DB_* (a retirer apres migration env complete).
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: (getenv('MALISHOP_DB_HOST') ?: '127.0.0.1'));
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: (getenv('MALISHOP_DB_NAME') ?: 'malishop'));
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: (getenv('MALISHOP_DB_USER') ?: 'root'));
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: (getenv('MALISHOP_DB_PASS') ?: ''));
require_once __DIR__ . '/app/config/database.php';

if (!function_exists('get_pdo')) {
  function get_pdo(): PDO {
    return db();
  }
}

// ============================================
// 7. FONCTIONS UTILES
// ============================================

function format_price($price) {
  return number_format($price, 0, ',', ' ') . ' FCFA';
}

function generate_sku($prefix = 'ML') {
  return $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

function is_active_page($page_name) {
  $current_page = basename($_SERVER['PHP_SELF']);
  return ($current_page == $page_name) ? 'active' : '';
}

// ============================================
// 8. VARIABLES GLOBALES UTILES
// ============================================

// $base_url est consomme par includes/header.php et includes/footer.php
// Exemples: "/", "/sitehamala/"

$css_variables = array(
  'primary-black' => '#000000',
  'dark-gray' => '#333333',
  'medium-gray' => '#666666',
  'light-gray' => '#999999',
  'lightest-gray' => '#eeeeee',
  'pure-white' => '#ffffff',
);

$welcome_message = 'Bienvenue sur ' . SITE_NAME . ' - Votre boutique en ligne au Mali';

// ============================================
// 9. CONFIGURATION D'ENVIRONNEMENT
// ============================================

defined('DEBUG_MODE') || define('DEBUG_MODE', (bool) (defined('APP_DEBUG') ? APP_DEBUG : false));

if (DEBUG_MODE) {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(0);
  ini_set('display_errors', 0);
}

// ============================================
// 10. INFORMATIONS DE CONTACT
// ============================================

$contact_info = array(
  'email' => 'contact@soracollectionmali.com',
  'phone' => '92828271',
  'address' => 'Torokorobougou, en face du terrain de la Commune V',
  'hours' => 'Lun-Ven: 8h-18h, Sam: 9h-13h',
);

// ============================================================
// Compat minimal: alias getPDO()
// ============================================================
if (!function_exists('getPDO')) {
  function getPDO(): PDO
  {
    return db();
  }
}

?>
