<?php
require_once __DIR__ . '/../app/helpers/seo.php';
require_once __DIR__ . '/../app/helpers/cart.php';
require_once __DIR__ . '/../app/services/ProductVariantService.php';

auth_start();
$adminUser = function_exists('current_admin_user') ? current_admin_user() : null;
$user = current_user();
$role = strtolower(trim((string) ($user['role'] ?? '')));

// Migration safety: if old sessions stored admin in client slot, clear it.
if ($user && in_array($role, array('admin', 'partner'), true)) {
  unset($_SESSION['user']);
  $user = null;
  $role = '';
}

$adminRole = strtolower(trim((string) ($adminUser['role'] ?? '')));
// Important: on ne mélange pas l'espace admin et l'espace client sur le site public.
$isStaff = false;
$isClientUser = (bool) $user;
$userLastName = trim((string) ($user['last_name'] ?? ''));

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
  $normalized = cart_normalize_map($_SESSION['cart']);
  $_SESSION['cart'] = $normalized;

  foreach ($normalized as $qty) {
    $cart_count += $qty;
  }

  // Nettoyer les produits inexistants/inactifs (evite un badge "fantome" apres suppressions DB).
  if ($cart_count > 0) {
    try {
      require_once __DIR__ . '/../app/config/database.php';
      $pdo = db();
      $variantService = new ProductVariantService($pdo);

      $lines = cart_normalize_lines($normalized);
      $productIds = array();
      foreach ($lines as $line) {
        $productId = (int) ($line['product_id'] ?? 0);
        $variantId = (int) ($line['variant_id'] ?? 0);
        if ($productId <= 0 && $variantId > 0) {
          $variant = $variantService->findById($variantId);
          if ($variant) {
            $productId = (int) ($variant['product_id'] ?? 0);
          }
        }
        if ($productId > 0) {
          $productIds[$productId] = true;
        }
      }

      $ids = array_keys($productIds);
      if (!$ids) {
        $_SESSION['cart'] = array();
        $cart_count = 0;
      } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders) AND (is_active = 1)");
        $stmt->execute($ids);
        $found = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: array();
        $foundIds = array();
        foreach ($found as $fid) {
          $foundIds[(int) $fid] = true;
        }

        $changed = false;
        foreach ($lines as $line) {
          $productId = (int) ($line['product_id'] ?? 0);
          $variantId = (int) ($line['variant_id'] ?? 0);
          if ($productId <= 0 && $variantId > 0) {
            $variant = $variantService->findById($variantId);
            if ($variant) {
              $productId = (int) ($variant['product_id'] ?? 0);
            }
          }
          if ($productId <= 0 || empty($foundIds[$productId])) {
            unset($_SESSION['cart'][(string) ($line['key'] ?? '')]);
            $changed = true;
          }
        }

        if ($changed) {
          $cart_count = 0;
          foreach ($_SESSION['cart'] as $qty) {
            $cart_count += (int) $qty;
          }
        }
      }
    } catch (Throwable $e) {
      // Pas bloquant: si DB indisponible on garde le count session.
    }
  }
}

$page_title = isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME;
$page_css = isset($page_css) ? $page_css : '';
$page_js = isset($page_js) ? $page_js : '';

// Prepare SEO/meta values with safe fallbacks.
$seo_meta = seo_prepare_meta(array(
  'site_name' => SITE_NAME,
  'page_title' => $page_title,
  'page_seo_title' => $page_seo_title ?? '',
  'page_meta_description' => $page_meta_description ?? '',
  'page_og_title' => $page_og_title ?? '',
  'page_og_description' => $page_og_description ?? '',
  'page_og_image' => $page_og_image ?? '',
));

$seo_title_final = $seo_meta['seo_title_final'];
$meta_desc_final = $seo_meta['meta_desc_final'];
$og_title = $seo_meta['og_title'];
$og_description = $seo_meta['og_description'];
$og_image = $seo_meta['og_image'];

// Keep existing variables used by includes/header.php.
$og_title_final = $og_title;
$og_desc_final = $og_description;
$og_image_final = $og_image;

$req_path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$req_query = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '');
$canonical_rel = $req_path;
if ($canonical_rel !== '' && isset($base_url) && $base_url !== '' && str_starts_with($canonical_rel, (string) $base_url)) {
  $canonical_rel = substr($canonical_rel, strlen((string) $base_url));
}
$canonical_rel = ltrim((string) $canonical_rel, '/');
if ($canonical_rel === 'index.php') {
  $canonical_rel = '';
}
$canonical_url = SITE_URL . $canonical_rel;

// Canonical SEO: ignorer les query params de navigation/filtre/recherche.
// On conserve uniquement des params structurants pouvant identifier une ressource.
$canonical_keep_params = array('id', 'sku', 'slug', 'cat', 'categorie', 'sous_categorie');
$canonical_query = array();
if ($req_query !== '') {
  $parsed_query = array();
  parse_str($req_query, $parsed_query);
  foreach ($canonical_keep_params as $k) {
    if (!array_key_exists($k, $parsed_query)) continue;
    $v = $parsed_query[$k];
    if (is_array($v)) continue;
    $v = trim((string) $v);
    if ($v === '') continue;
    $canonical_query[$k] = $v;
  }
}
if ($canonical_query) {
  $canonical_url .= '?' . http_build_query($canonical_query, '', '&', PHP_QUERY_RFC3986);
}

// Structured data (JSON-LD): socle global + donnees page si disponibles.
$json_ld_blocks = array();
$json_ld_blocks[] = array(
  '@context' => 'https://schema.org',
  '@type' => 'Organization',
  'name' => (string) SITE_NAME,
  'url' => rtrim((string) SITE_URL, '/'),
  'logo' => absolute_url('assets/images/branding/logo-sitehamala.svg'),
);
if (isset($page_json_ld_product) && is_array($page_json_ld_product) && !empty($page_json_ld_product)) {
  $json_ld_blocks[] = $page_json_ld_product;
}

// Determiner la categorie active
$current_category = isset($_GET['categorie']) ? (string) $_GET['categorie'] : '';
$current_sous_categorie = isset($_GET['sous_categorie']) ? (string) $_GET['sous_categorie'] : '';

// Determiner la page active (pour mise en evidence navigation)
// Utiliser SCRIPT_NAME pour détecter la page active de façon fiable.
$current_script_path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$current_page = basename($current_script_path ?: ($current_path ?: ''));
$is_admin_area = stripos($current_script_path, '/admin/') !== false;
$isStaff = $is_admin_area && $adminUser && in_array($adminRole, array('admin', 'partner'), true);
$is_catalogue_related = in_array($current_page, array('catalogue.php', 'produit.php', 'recherche.php'), true);
$is_account_related = in_array($current_page, array('connexion.php', 'inscription.php', 'mon-compte.php', 'mes-commandes.php', 'suivi.php', 'supprimer-compte.php'), true);

/* Mode maintenance: bloque le public, laisse /admin/* accessible. */
if (!$is_admin_area) {
  try {
    $maintenanceOn = (string) setting('maintenance_mode', '0');
    $maintenanceOn = trim($maintenanceOn);
    if ($maintenanceOn !== '' && $maintenanceOn !== '0') {
      $maintenance_message = (string) setting('maintenance_message', 'Maintenance en cours. Merci de revenir plus tard.');
      Logger::info('maintenance_block', array('uri' => (string) ($_SERVER['REQUEST_URI'] ?? '')));
      include __DIR__ . '/maintenance.php';
      exit;
    }
  } catch (Throwable $e) {
    // en cas d'erreur DB/settings, on ne bloque pas
  }
}

$nav_is_active = function ($page) use ($current_page, $is_admin_area) {
// Éviter de marquer "Accueil" actif sur les pages /admin/*.
  if ($is_admin_area) return false;
  return $current_page === $page;
};
$nav_active_class = function ($page) use ($nav_is_active) {
  return $nav_is_active($page) ? 'active' : '';
};
$nav_aria_current = function ($page) use ($nav_is_active) {
  return $nav_is_active($page) ? 'aria-current="page"' : '';
};
// Helper pour la classe .is-active (compatibilité avec .active).
$nav_is_active_class = function ($page) use ($nav_is_active) {
  return $nav_is_active($page) ? 'is-active' : '';
};

// Autoriser une classe body par page sans impacter les pages existantes.
$body_class_attr = '';
if (isset($body_class) && is_string($body_class) && trim($body_class) !== '') {
  $body_class_attr = ' class="' . e(trim($body_class)) . '"';
}

