<?php
declare(strict_types=1);


// La plupart des pages incluent déjà _auth.php; on garde require_once pour rester safe.
require_once __DIR__ . '/_auth.php';

$adminRole = admin_current_role();
$isOwner = ($adminRole === 'owner');

// Body class admin (cache la navbar + footer publics via CSS admin)
$body_class = trim((string) ($body_class ?? '') . ' page-admin admin-saas');

// Injecter CSS/JS admin sans casser les pages existantes
if (!isset($page_css) || $page_css === null) {
  $page_css = array();
}
if (!is_array($page_css)) {
  $page_css = $page_css ? array((string) $page_css) : array();
}
if (!in_array('pages/admin-saas.css', $page_css, true)) {
  $page_css[] = 'pages/admin-saas.css';
}

if (!isset($page_js) || $page_js === null) {
  $page_js = array();
}
if (!is_array($page_js)) {
  $page_js = $page_js ? array((string) $page_js) : array();
}
foreach (array('toasts.js', 'admin-saas.js') as $js) {
  if (!in_array($js, $page_js, true)) {
    $page_js[] = $js;
  }
}

require_once __DIR__ . '/AdminStats.php';

$sidebarBadges = array();
if ($isOwner) {
  try {
    $sidebarBadges = AdminStats::sidebarBadges(db());
  } catch (Throwable $e) {
    $sidebarBadges = array();
  }
}

$currentScript = strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

/**
 * @return array<int, array<string,mixed>>
 */
function admin_saas_modules(string $role): array
{
  $role = strtolower(trim($role));
  $isOwner = ($role === 'owner');

  $all = array(
    array('section' => 'Overview', 'items' => array(
      array('key' => 'overview', 'label' => 'Overview', 'href' => base_url('admin/index.php'), 'icon' => 'fa-gauge-high', 'roles' => array('owner', 'partner')),
      array('key' => 'dashboard', 'label' => 'Tableau de bord', 'href' => base_url('admin/index.php'), 'icon' => 'fa-chart-line', 'roles' => array('owner')),
    )),
    array('section' => 'Catalogue', 'items' => array(
      array('key' => 'products', 'label' => 'Produits', 'href' => base_url('admin/products/index.php'), 'icon' => 'fa-box', 'roles' => array('owner', 'partner')),
      array('key' => 'categories', 'label' => 'Catégories', 'href' => base_url('admin/categories.php'), 'icon' => 'fa-layer-group', 'roles' => array('owner')),
      array('key' => 'stock', 'label' => 'Stock', 'href' => base_url('admin/products/stock_index.php'), 'icon' => 'fa-warehouse', 'roles' => array('owner', 'partner')),
    )),
    array('section' => 'Ventes', 'items' => array(
      array('key' => 'orders', 'label' => 'Commandes', 'href' => base_url('admin/orders/index.php'), 'icon' => 'fa-receipt', 'roles' => array('owner', 'partner')),
      array('key' => 'revenue', 'label' => 'Chiffre d’affaires', 'href' => base_url('admin/revenue/index.php'), 'icon' => 'fa-chart-column', 'roles' => array('owner')),
      array('key' => 'customers', 'label' => 'Clients', 'href' => base_url('admin/customers/index.php'), 'icon' => 'fa-users', 'roles' => array('owner')),
      array('key' => 'shipping', 'label' => 'Livraison', 'href' => base_url('admin/shipping_zones.php'), 'icon' => 'fa-truck', 'roles' => array('owner')),
    )),
    array('section' => 'Marketing', 'items' => array(
      array('key' => 'coupons', 'label' => 'Codes promo', 'href' => base_url('admin/coupons.php'), 'icon' => 'fa-tags', 'roles' => array('owner')),
      array('key' => 'reviews', 'label' => 'Avis clients', 'href' => base_url('admin/reviews/index.php'), 'icon' => 'fa-star', 'roles' => array('owner')),
      array('key' => 'product_reviews', 'label' => 'Avis produits', 'href' => base_url('admin/product_reviews/index.php'), 'icon' => 'fa-comment-dots', 'roles' => array('owner')),
      array('key' => 'cms_pages', 'label' => 'Pages CMS', 'href' => base_url('admin/pages.php'), 'icon' => 'fa-file-lines', 'roles' => array('owner')),
    )),
    array('section' => 'Système', 'items' => array(
      array('key' => 'settings', 'label' => 'Paramètres', 'href' => base_url('admin/settings.php'), 'icon' => 'fa-sliders', 'roles' => array('owner')),
      array('key' => 'partners', 'label' => 'Partenaires', 'href' => base_url('admin/partners/index.php'), 'icon' => 'fa-user-gear', 'roles' => array('owner')),
      array('key' => 'audit', 'label' => 'Journal d’activité', 'href' => base_url('admin/audit/index.php'), 'icon' => 'fa-clock-rotate-left', 'roles' => array('owner')),
    )),
  );

  if ($isOwner) {
    return $all;
  }

  // partner: add-only produits (UI)
  $filtered = array();
  foreach ($all as $group) {
    $items = array();
    foreach (($group['items'] ?? array()) as $it) {
      $roles = $it['roles'] ?? array();
      if (in_array('partner', $roles, true)) {
        $items[] = $it;
      }
    }
    if ($items) {
      $group['items'] = $items;
      $filtered[] = $group;
    }
  }
  return $filtered;
}

$adminModules = admin_saas_modules($adminRole);

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">
  <aside class="admin-sidebar" aria-label="Modules admin">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
  </aside>
  <div class="admin-workspace">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <div class="admin-workspace__content">