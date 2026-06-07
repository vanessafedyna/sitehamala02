<?php
declare(strict_types=1);


if (!isset($adminModules) || !is_array($adminModules)) {
  $adminModules = array();
}
$badges = (isset($sidebarBadges) && is_array($sidebarBadges)) ? $sidebarBadges : array();
$current = isset($currentScript) ? (string) $currentScript : strtolower((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

$isActive = function (string $href) use ($current): bool {
  $path = (string) (parse_url($href, PHP_URL_PATH) ?: '');
  if ($path === '') return false;
  $currentPath = (string) (parse_url($current, PHP_URL_PATH) ?: $current);
  return $currentPath === $path || (str_contains($currentPath, rtrim($path, '/')) && str_contains($path, '/admin/'));
};

$badgeForKey = function (string $key) use ($badges): int {
  if ($key === 'orders') return (int) ($badges['orders_todo'] ?? 0);
  if ($key === 'reviews') return (int) ($badges['reviews_pending'] ?? 0);
  if ($key === 'product_reviews') return (int) ($badges['product_reviews_pending'] ?? 0);
  if ($key === 'products') return (int) ($badges['products_pending'] ?? 0);
  if ($key === 'stock') return (int) (($badges['stock_out'] ?? 0) + ($badges['stock_low'] ?? 0));
  return 0;
};

$moduleMap = array();
foreach ($adminModules as $group) {
  foreach ((array) ($group['items'] ?? array()) as $item) {
    $key = (string) ($item['key'] ?? '');
    if ($key !== '') {
      $moduleMap[$key] = $item;
    }
  }
}

$mainKeys = array('overview', 'orders', 'revenue', 'products', 'stock', 'customers');
$mainNav = array();
foreach ($mainKeys as $key) {
  if (!isset($moduleMap[$key])) {
    continue;
  }
  $item = $moduleMap[$key];
  if ($key === 'overview' || $key === 'dashboard') {
    $item['label'] = 'Accueil';
  }
  $mainNav[] = $item;
}

if (isset($moduleMap['coupons']) || isset($moduleMap['reviews']) || isset($moduleMap['product_reviews'])) {
  $mainNav[] = array(
    'key' => 'marketing_group',
    'label' => 'Marketing',
    'icon' => 'fa-bullhorn',
    'children' => array_values(array_filter(array(
      $moduleMap['coupons'] ?? null,
      $moduleMap['reviews'] ?? null,
      $moduleMap['product_reviews'] ?? null,
    ))),
  );
}

$settingsChildren = array_values(array_filter(array(
  $moduleMap['settings'] ?? null,
  $moduleMap['cms_pages'] ?? null,
  $moduleMap['partners'] ?? null,
  $moduleMap['audit'] ?? null,
  $moduleMap['categories'] ?? null,
  $moduleMap['shipping'] ?? null,
)));

if ($settingsChildren) {
  $mainNav[] = array(
    'key' => 'settings_group',
    'label' => 'Paramètres',
    'icon' => 'fa-sliders',
    'children' => $settingsChildren,
  );
}
?>

<div class="admin-sidebar__brand">
  <a class="admin-brand" href="<?php echo e(base_url('admin/index.php')); ?>" aria-label="<?php echo e(SITE_NAME); ?>">
    <img
      src="<?php echo e(base_url('assets/images/branding/logo-sitehamala.svg')); ?>"
      class="brand-logo admin-brand__logo"
      alt="<?php echo e(SITE_NAME); ?>"
      width="3000"
      height="3000"
      decoding="async"
    >
    <span class="sr-only">Admin</span>
  </a>
</div>

<nav class="admin-nav" aria-label="Navigation admin">
  <div class="admin-nav__noResults" hidden>Aucun module</div>

  <div class="admin-nav__section admin-nav__section--primary">
    <div class="admin-nav__title">Navigation</div>
    <ul class="admin-nav__list admin-nav__list--primary">
      <?php foreach ($mainNav as $it): ?>
        <?php
          $label = (string) ($it['label'] ?? '');
          $icon = (string) ($it['icon'] ?? 'fa-circle');
          $key = (string) ($it['key'] ?? '');
          $children = (array) ($it['children'] ?? array());
          $badge = $key !== '' ? $badgeForKey($key) : 0;
          $searchParts = array($label);
        ?>
        <?php if ($children): ?>
          <?php
            $childActive = false;
            $childBadge = 0;
            foreach ($children as $child) {
              $childHref = (string) ($child['href'] ?? '#');
              $childKey = (string) ($child['key'] ?? '');
              $searchParts[] = (string) ($child['label'] ?? '');
              if ($childHref !== '#' && $isActive($childHref)) {
                $childActive = true;
              }
              if ($childKey !== '') {
                $childBadge += $badgeForKey($childKey);
              }
            }
            $labelLower = function_exists('mb_strtolower') ? mb_strtolower(implode(' ', $searchParts)) : strtolower(implode(' ', $searchParts));
          ?>
          <li class="admin-nav__item admin-nav__item--collapsible" data-label="<?php echo e($labelLower); ?>" <?php echo $childActive ? 'data-active="1"' : ''; ?>>
            <details class="admin-nav__group" <?php echo $childActive ? 'open' : ''; ?>>
              <summary class="admin-nav__summary">
                <span class="admin-nav__summaryMain">
                  <i class="fas <?php echo e($icon); ?>" aria-hidden="true"></i>
                  <span class="admin-nav__text"><?php echo e($label); ?></span>
                </span>
                <span class="admin-nav__summaryAside">
                  <?php if ($childBadge > 0): ?>
                    <span class="admin-nav__badge admin-nav__badge--soft" aria-label="compteur"><?php echo (int) $childBadge; ?></span>
                  <?php endif; ?>
                  <i class="fas fa-chevron-down admin-nav__chevron" aria-hidden="true"></i>
                </span>
              </summary>
              <ul class="admin-nav__sublist">
                <?php foreach ($children as $child): ?>
                  <?php
                    $href = (string) ($child['href'] ?? '#');
                    $childLabel = (string) ($child['label'] ?? '');
                    $childIcon = (string) ($child['icon'] ?? 'fa-circle');
                    $childKey = (string) ($child['key'] ?? '');
                    $active = $href !== '#' && $isActive($href);
                    $childItemBadge = $childKey !== '' ? $badgeForKey($childKey) : 0;
                    $childLabelLower = function_exists('mb_strtolower') ? mb_strtolower($childLabel) : strtolower($childLabel);
                  ?>
                  <li class="admin-nav__subitem" data-label="<?php echo e($childLabelLower); ?>" <?php echo $active ? 'data-active="1"' : ''; ?>>
                    <a class="admin-nav__link admin-nav__link--sub <?php echo $active ? 'is-active' : ''; ?>" href="<?php echo e($href); ?>">
                      <i class="fas <?php echo e($childIcon); ?>" aria-hidden="true"></i>
                      <span class="admin-nav__text"><?php echo e($childLabel); ?></span>
                      <?php if ($childItemBadge > 0): ?>
                        <span class="admin-nav__badge admin-nav__badge--soft" aria-label="compteur"><?php echo (int) $childItemBadge; ?></span>
                      <?php endif; ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          </li>
        <?php else: ?>
          <?php
            $href = (string) ($it['href'] ?? '#');
            $active = $href !== '#' && $isActive($href);
            $labelLower = function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label);
          ?>
          <li class="admin-nav__item" data-label="<?php echo e($labelLower); ?>" <?php echo $active ? 'data-active="1"' : ''; ?>>
            <a class="admin-nav__link <?php echo $active ? 'is-active' : ''; ?>" href="<?php echo e($href); ?>">
              <i class="fas <?php echo e($icon); ?>" aria-hidden="true"></i>
              <span class="admin-nav__text"><?php echo e($label); ?></span>
              <?php if ($badge > 0): ?>
                <span class="admin-nav__badge admin-nav__badge--soft" aria-label="compteur"><?php echo (int) $badge; ?></span>
              <?php endif; ?>
            </a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </div>
</nav>