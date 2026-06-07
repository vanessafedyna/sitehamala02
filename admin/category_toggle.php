<?php
declare(strict_types=1);

/* Toggle category visibility without deleting its content */

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  http_response_code(405);
  redirect('admin/categories.php');
}

if (!csrf_verify($_POST['_csrf'] ?? null)) {
  admin_flash_set('categories', 'error', 'Session expirée. Veuillez réessayer.');
  redirect('admin/categories.php');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nextActive = isset($_POST['is_active']) ? (int) $_POST['is_active'] : -1;
$returnTo = trim((string) ($_POST['return_to'] ?? 'admin/categories.php'));

if ($id <= 0 || ($nextActive !== 0 && $nextActive !== 1)) {
  admin_flash_set('categories', 'error', 'Action invalide.');
  redirect('admin/categories.php');
}

// Anti open-redirect: chemin relatif vers /admin uniquement
if ($returnTo === '' || strpos($returnTo, 'admin/') !== 0) {
  $returnTo = 'admin/categories.php';
}

try {
  $pdo = db();
  $model = new CategoryModel($pdo);
  $row = $model->findById($id);
  if (!$row) {
    admin_flash_set('categories', 'error', 'Catégorie introuvable.');
    redirect($returnTo);
  }

  $model->setActive($id, $nextActive === 1);

  $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
  $action = ($nextActive === 1) ? 'category_enabled' : 'category_disabled';
  AdminAuditService::log($pdo, $adminId, $action, 'category', (int) $id);

  admin_flash_set('categories', 'success', ($nextActive === 1) ? 'Catégorie activée.' : 'Catégorie désactivée.');
  redirect($returnTo);
} catch (Throwable $e) {
  admin_flash_set('categories', 'error', 'Impossible de mettre à jour la catégorie.');
  redirect($returnTo);
}
