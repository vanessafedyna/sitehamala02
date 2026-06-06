<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/services/CategoryImageService.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$page_title = 'Admin - Supprimer catégorie';
$page_css = 'pages/admin-products.css';
$page_js = '';

$pdo = db();
$model = new CategoryModel($pdo);
$category = $model->findById($id);
if (!$category) {
  http_response_code(404);
}

$errors = array();
/* Linked products change this action from hard delete to deactivation */
$linkedProducts = 0;
if ($category) {
  $linkedProducts = $model->countLinkedProducts($id);
}

if ($category && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    try {
      $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

      /* si liée à des produits => soft delete recommandé */
      if ($linkedProducts > 0) {
        $model->setActive($id, false);
        AdminAuditService::log($pdo, $adminId, 'category_disabled', 'category', (int) $id);
        admin_flash_set('categories', 'success', 'Catégorie désactivée (liée à ' . (int) $linkedProducts . ' produit(s)).');
        redirect('admin/categories.php');
      }

      $pdo->beginTransaction();
      try {
        // Sécurité: supprimer les relations pivot si la table existe (ne supprime jamais les produits)
        try {
          $pdo->prepare('DELETE FROM product_categories WHERE category_id = :id')->execute(array('id' => (int) $id));
        } catch (Throwable $e) {
          // table pivot absente => ignorer
        }

        $banner = (string) ($category['banner_image'] ?? '');
        $og = (string) ($category['og_image'] ?? '');

        $model->delete($id);
        $pdo->commit();

        if ($banner !== '') CategoryImageService::deleteIfLocal($banner);
        if ($og !== '') CategoryImageService::deleteIfLocal($og);

        AdminAuditService::log($pdo, $adminId, 'category_deleted', 'category', (int) $id);

        admin_flash_set('categories', 'success', 'Catégorie supprimée.');
        redirect('admin/categories.php');
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
      }
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
          <p class="admin-page-header__eyebrow">Catalogue</p>
          <h1 class="admin-page-header__title">Supprimer une catégorie</h1>
          <p class="admin-page-header__subtitle">
            Vérifiez l'impact avant de confirmer cette action sur la collection.
          </p>
        </div>
        <div class="admin-page-header__actions">
          <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/categories.php')); ?>">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
          </a>
        </div>
      </div>

      <?php if (!$category): ?>
        <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
          <strong>Catégorie introuvable.</strong>
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
                <p class="admin-action-summary__label">Catégorie concernée</p>
                <h2 class="admin-action-summary__title"><?php echo e((string) $category['name']); ?></h2>
              </div>
              <span class="admin-status-pill <?php echo $linkedProducts > 0 ? 'admin-status-pill--warning' : 'admin-status-pill--danger'; ?>">
                <?php echo $linkedProducts > 0 ? 'Liée à des produits' : 'Suppression'; ?>
              </span>
            </div>

            <?php if ($linkedProducts > 0): ?>
              <p class="admin-action-warning">
                Cette catégorie est liée à <strong><?php echo (int) $linkedProducts; ?></strong> produit(s).
                La confirmation désactivera la catégorie afin de préserver les relations existantes.
              </p>
            <?php else: ?>
              <p class="admin-action-warning admin-action-warning--danger">
                Cette suppression est définitive. Les liens produit-catégorie associés seront également retirés.
              </p>
            <?php endif; ?>

            <form method="post" class="admin-action-form">
              <?php echo csrf_field(); ?>
              <div class="admin-action-form__actions">
                <button class="admin-btn <?php echo $linkedProducts > 0 ? 'admin-btn--primary' : 'admin-btn--danger'; ?>" type="submit">
                  <?php echo $linkedProducts > 0 ? 'Confirmer la désactivation' : 'Confirmer la suppression'; ?>
                </button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
