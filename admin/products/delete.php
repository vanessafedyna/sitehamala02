<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/ProductModel.php';
require_once __DIR__ . '/../../app/services/ProductImageService.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

$page_title = 'Admin - Supprimer un produit';
$page_css = 'pages/admin-products.css';
$page_js = '';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$errors = array();

try {
  $model = new ProductModel(db());
  $product = $model->find($id);
} catch (Throwable $e) {
  $product = null;
  $errors[] = 'Impossible de charger le produit.';
}

if (!$product) {
  http_response_code(404);
}

if ($product && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    try {
      $ok = $model->delete($id);
      if ($ok) {
        $toDelete = array(
          (string) ($product['image1'] ?? ''),
          (string) ($product['image2'] ?? ''),
          (string) ($product['image3'] ?? ''),
          (string) ($product['image_path'] ?? ''),
          (string) ($product['image_main'] ?? ''),
          (string) ($product['image'] ?? ''),
        );

        $uniq = array();
        foreach ($toDelete as $rel) {
          $rel = trim((string) $rel);
          if ($rel === '') continue;
          $uniq[$rel] = true;
        }
        foreach (array_keys($uniq) as $rel) {
          ProductImageService::deleteIfLocal($rel);
        }

        admin_flash_set('products', 'success', 'Produit supprimé.');
       
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        AdminAuditService::log(db(), $adminId, 'owner_deleted_product', 'product', (int) $id);
        redirect('admin/products/index.php');
      }
      $errors[] = 'Suppression impossible.';
    } catch (Throwable $e) {
      error_log('[admin/products/delete] ' . $e->getMessage());
      $errors[] = 'Erreur lors de la suppression.';
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
          <h1 class="admin-page-header__title">Supprimer un produit</h1>
          <p class="admin-page-header__subtitle">
            Cette action retire définitivement le produit du catalogue admin.
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

        <?php
          $name = (string) ($product['name'] ?? '');
          $sku = (string) ($product['sku'] ?? '');
          $img = (string) ($product['image1'] ?? ($product['image_path'] ?? ($product['image_main'] ?? ($product['image'] ?? ''))));
          $imgUrl = $img !== '' ? ProductImageService::toUrl($img) : base_url('assets/images/placeholders/product-placeholder.svg');
        ?>

        <div class="admin-action-layout">
          <div class="admin-panel admin-panel--padded admin-action-panel" aria-label="Confirmation suppression produit">
            <div class="admin-action-summary">
              <div class="admin-action-summary__content">
                <p class="admin-action-summary__label">Produit concerné</p>
                <h2 class="admin-action-summary__title"><?php echo e($name); ?></h2>
                <?php if ($sku !== ''): ?>
                  <div class="admin-help">SKU : <?php echo e($sku); ?></div>
                <?php endif; ?>
              </div>
              <img class="admin-thumb" src="<?php echo e($imgUrl); ?>" alt="">
            </div>

            <p class="admin-action-warning admin-action-warning--danger" role="alert">
              Cette suppression est définitive. Le produit sera retiré du catalogue et ses images locales associées seront nettoyées.
            </p>

            <form method="post" action="" class="admin-action-form">
              <?php echo csrf_field(); ?>
              <div class="admin-action-form__actions">
                <button class="admin-btn admin-btn--danger" type="submit">Oui, supprimer</button>
                <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/products/edit.php?id=' . $id)); ?>">Annuler</a>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>