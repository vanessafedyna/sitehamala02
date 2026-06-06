<?php
declare(strict_types=1);

$u = function_exists('current_admin_user') ? current_admin_user() : null;
$email = (string) ($u['email'] ?? '');
$role = isset($adminRole) ? (string) $adminRole : (function_exists('admin_current_role') ? admin_current_role() : '');
$roleMap = array(
  'owner' => 'Proprietaire',
  'partner' => 'Gestionnaire terrain',
);
$roleLabel = $roleMap[$role] ?? ($role !== '' ? $role : (string) ($u['role'] ?? ''));
?>

<header class="admin-topbar" aria-label="Barre admin">
  <div class="admin-topbar__left">
    <div class="admin-topbar__search" role="search" aria-label="Rechercher un module">
      <label class="sr-only" for="adminModuleSearch">Rechercher</label>
      <input id="adminModuleSearch" class="admin-topbar__searchInput" type="search" placeholder="Rechercher un module..." autocomplete="off">
    </div>

    <div class="admin-topbar__crumb">
      <span class="admin-topbar__pill">
        <i class="fas fa-user-shield" aria-hidden="true"></i>
        <span class="admin-topbar__pillText"><strong><?php echo e($email); ?></strong></span>
      </span>
      <span class="admin-topbar__pill">
        <i class="fas fa-id-badge" aria-hidden="true"></i>
        <span class="admin-topbar__pillText">Role : <strong><?php echo e($roleLabel); ?></strong></span>
      </span>
    </div>
  </div>

  <div class="admin-topbar__right">
    <a class="btn btn-outline btn-sm" href="<?php echo e(base_url('index.php')); ?>">
      <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour site
    </a>
    <a class="btn btn-outline btn-sm" href="<?php echo e(base_url('admin/logout.php')); ?>">
      <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Deconnexion
    </a>
  </div>
</header>
