<?php
declare(strict_types=1);

/* Validation de publication */

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

$page_title = 'Admin - Publier un produit';
$page_css = 'pages/admin-products.css';
$page_js = '';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = array();

try {
  $pdo = db();
  $model = new ProductModel($pdo);
  $product = $model->find($id);

  // Vérifier colonne status
  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
  $supports_status = in_array('status', $fields, true);
  if (!$supports_status) {
    $errors[] = 'Votre base ne supporte pas le workflow pending/published. Exécutez: database/patch_products_workflow.sql';
  }
} catch (Throwable $e) {
  $product = null;
  $supports_status = false;
  $errors[] = 'Impossible de charger le produit.';
}

if (!$product) {
  http_response_code(404);
}

if ($product && $supports_status && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    try {
      $oldStatus = strtolower(trim((string) ($product['status'] ?? '')));
      $ok = $model->update((int) $id, array('status' => 'published'));
      if ($ok) {
        admin_flash_set('products', 'success', 'Produit publié.');

        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        AdminAuditService::log($pdo, $adminId, 'product_status_changed', 'product', (int) $id, array(
          'actor_role' => admin_current_role(),
          'old_status' => $oldStatus,
          'new_status' => 'published',
        ));

        redirect('admin/products/index.php');
      }
      $errors[] = 'Publication impossible.';
    } catch (Throwable $e) {
      error_log('[admin/products/publish] ' . $e->getMessage());
      $errors[] = 'Erreur lors de la publication.';
    }
  }
}

require_once __DIR__ . '/../_layout_header.php';
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-page-header">
        <div class="admin-page-header__content">
          <p class="admin-page-header__eyebrow">Catalogue</p>
          <h1 class="admin-page-header__title">Publier un produit</h1>
          <p class="admin-page-header__subtitle">
            Confirmez la mise en ligne du produit avant de le rendre visible dans le catalogue.
          </p>
        </div>
        <div class="admin-page-header__actions">
          <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
          </a>
        </div>
      </div>

      <?php if (!$product): ?>
        <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
          <strong>Produit introuvable.</strong>
        </div>
      <?php else: ?>
        <?php
          $name = (string) ($product['name'] ?? '');
          $sku = (string) ($product['sku'] ?? '');
          $st = strtolower(trim((string) ($product['status'] ?? '')));
        ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
            <strong>Erreur :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($supports_status && $st === 'published'): ?>
          <div class="admin-action-layout">
            <div class="admin-panel admin-panel--padded admin-action-panel" role="status">
              <div class="admin-action-summary">
                <div class="admin-action-summary__content">
                  <p class="admin-action-summary__label">Produit concerné</p>
                  <h2 class="admin-action-summary__title"><?php echo e($name); ?></h2>
                  <?php if ($sku !== ''): ?>
                    <div class="admin-help">SKU : <?php echo e($sku); ?></div>
                  <?php endif; ?>
                </div>
                <span class="admin-status-pill admin-status-pill--success">Publié</span>
              </div>

              <p class="admin-action-warning">
                Ce produit est déjà publié. Aucune confirmation supplémentaire n'est nécessaire.
              </p>
            </div>
          </div>
        <?php elseif ($supports_status): ?>
          <div class="admin-action-layout">
            <div class="admin-panel admin-panel--padded admin-action-panel" aria-label="Confirmation publication">
              <div class="admin-action-summary">
                <div class="admin-action-summary__content">
                  <p class="admin-action-summary__label">Produit concerné</p>
                  <h2 class="admin-action-summary__title"><?php echo e($name); ?></h2>
                  <?php if ($sku !== ''): ?>
                    <div class="admin-help">SKU : <?php echo e($sku); ?></div>
                  <?php endif; ?>
                </div>
                <span class="admin-status-pill admin-status-pill--warning">En attente</span>
              </div>

              <p class="admin-action-warning" role="status">
                Après confirmation, le produit sera visible sur le site dans le catalogue.
              </p>

              <form method="post" action="" class="admin-action-form">
                <?php echo csrf_field(); ?>
                <div class="admin-action-form__actions">
                  <button class="admin-btn admin-btn--primary" type="submit">Publier</button>
                  <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/index.php')); ?>">Annuler</a>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>