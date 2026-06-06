<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CouponModel.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$page_title = 'Admin - Supprimer coupon';
$page_css = 'pages/admin-products.css';
$page_js = '';

$pdo = db();
$model = new CouponModel($pdo);
$coupon = $model->findById($id);
if (!$coupon) {
  http_response_code(404);
}

$errors = array();
if ($coupon && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    try {
      $stmt = $pdo->prepare('UPDATE coupons SET is_active = 0 WHERE id = :id');
      $stmt->execute(array('id' => (int) $id));

      $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
      AdminAuditService::log($pdo, $adminId, 'owner_deleted_coupon', 'coupon', (int) $id);

      admin_flash_set('coupons', 'success', 'Coupon supprimé.');
      redirect('admin/coupons.php');
    } catch (Throwable $e) {
      $errors[] = 'Suppression impossible.';
    }
  }
}

require_once __DIR__ . '/_layout_header.php';
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-page-header">
        <div class="admin-page-header__content">
          <p class="admin-page-header__eyebrow">Promotions commerciales</p>
          <h1 class="admin-page-header__title">Supprimer un coupon</h1>
          <p class="admin-page-header__subtitle">
            Confirmez la suppression du code promo uniquement si son retrait est bien souhaité.
          </p>
        </div>
        <div class="admin-page-header__actions">
          <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/coupons.php')); ?>">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
          </a>
        </div>
      </div>

      <?php if (!$coupon): ?>
        <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
          <strong>Coupon introuvable.</strong>
        </div>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
            <strong><?php echo e($errors[0]); ?></strong>
          </div>
        <?php endif; ?>

        <div class="admin-action-layout">
          <div class="admin-panel admin-panel--padded admin-action-panel" role="alert">
            <div class="admin-action-summary">
              <div class="admin-action-summary__content">
                <p class="admin-action-summary__label">Code promo concerné</p>
                <h2 class="admin-action-summary__title"><?php echo e((string) ($coupon['code'] ?? '')); ?></h2>
              </div>
              <span class="admin-status-pill admin-status-pill--danger">Suppression</span>
            </div>

            <p class="admin-action-warning admin-action-warning--danger">
              Cette suppression est définitive. Le coupon ne pourra plus être utilisé après confirmation.
            </p>

            <form method="post" class="admin-action-form">
              <?php echo csrf_field(); ?>
              <div class="admin-action-form__actions">
                <button class="admin-btn admin-btn--danger" type="submit">Confirmer la suppression</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
