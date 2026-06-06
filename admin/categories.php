<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$page_title = 'Admin - Catégories';
$page_css = 'pages/admin-products.css';
$page_js = '';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$flash = admin_flash_get('categories');
$errors = array();
$items = array();

/* Paginate long category lists without changing the existing filters */
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 20;
$totalAll = 0;
$totalFiltered = 0;
$lastPage = 1;

try {
  $model = new CategoryModel(db());
  if (!$model->exists()) {
    $errors[] = "Table `categories` manquante. Exécutez `database/patch_categories.sql`.";
  } else {
    $totalAll = $model->count(array());
    $totalFiltered = $model->count(array('q' => $q));
    $lastPage = max(1, (int) ceil($totalFiltered / $perPage));
    if ($page > $lastPage) $page = $lastPage;

    $items = $model->list(array(
      'q' => $q,
      'limit' => $perPage,
      'offset' => ($page - 1) * $perPage,
    ));
  }
} catch (Throwable $e) {
  $errors[] = 'Impossible de charger les catégories (base de données).';
}

require_once __DIR__ . '/_layout_header.php';
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-page-header">
        <div class="admin-page-header__content">
          <p class="admin-page-header__eyebrow">Catalogue</p>
          <h1 class="admin-page-header__title">Catégories / Collections</h1>
          <p class="admin-page-header__subtitle">Gérez les collections visibles sur le site.</p>
        </div>
        <div class="admin-page-header__actions">
          <a class="admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/category_add.php')); ?>">
            <i class="fas fa-plus" aria-hidden="true"></i> Ajouter
          </a>
          <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
            <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour dashboard
          </a>
        </div>
      </div>

      <?php if ($flash): ?>
        <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded" role="status" aria-live="polite">
          <strong><?php echo e($flash['message']); ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="admin-alert admin-alert--error admin-panel admin-panel--padded" role="alert">
          <strong>Erreur :</strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php else: ?>
        <div class="admin-filterbar" aria-label="Recherche catégories">
          <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search">
            <label class="sr-only" for="q">Recherche</label>
            <input class="admin-field" id="q" name="q" type="text" value="<?php echo e($q); ?>" placeholder="Rechercher par nom ou slug">
            <button class="admin-btn admin-btn--primary" type="submit">
              <i class="fas fa-search" aria-hidden="true"></i> Rechercher
            </button>
            <?php if ($q !== ''): ?>
              <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/categories.php')); ?>">Réinitialiser</a>
            <?php endif; ?>
          </form>
          <div class="admin-help admin-filterbar__group">
            Total : <?php echo e((string) $totalAll); ?> • Affichées : <?php echo e((string) $totalFiltered); ?>
          </div>
        </div>

        <div class="admin-panel admin-panel--padded admin-table-shell" aria-label="Liste catégories">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Nom</th>
                <th>Slug</th>
                <th>Ordre</th>
                <th>Actif</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$items): ?>
                <tr>
                  <td colspan="5">
                    <div class="admin-empty-panel">
                      <p class="admin-empty-panel__title">Aucune catégorie.</p>
                      <p class="admin-empty-panel__text">Ajoutez une première catégorie ou ajustez votre recherche pour afficher des résultats.</p>
                      <div class="admin-empty-panel__actions">
                        <a class="admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/category_add.php')); ?>">
                          <i class="fas fa-plus" aria-hidden="true"></i> Ajouter une catégorie
                        </a>
                        <?php if ($q !== ''): ?>
                          <a class="admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/categories.php')); ?>">Réinitialiser la recherche</a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
              <?php foreach ($items as $c): ?>
                <?php
                  $id = (int) ($c['id'] ?? 0);
                  $name = (string) ($c['name'] ?? '');
                  $slug = (string) ($c['slug'] ?? '');
                  $sort = (int) ($c['sort_order'] ?? 0);
                  $active = (int) ($c['is_active'] ?? 1);
                  $isOwner = (string) ($_SESSION['admin_role'] ?? '') === 'owner';
                  $returnTo = 'admin/categories.php' . ($q !== '' ? ('?q=' . urlencode($q) . '&page=' . (int) $page) : ('?page=' . (int) $page));
                ?>
                <tr>
                  <td><strong><?php echo e($name); ?></strong></td>
                  <td><?php echo e($slug); ?></td>
                  <td><?php echo e((string) $sort); ?></td>
                  <td>
                    <?php if ($active): ?>
                      <span class="admin-status-pill admin-status-pill--success">Oui</span>
                    <?php else: ?>
                      <span class="admin-status-pill admin-status-pill--neutral">Non</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="admin-actions">
                      <a class="admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/category_edit.php?id=' . $id)); ?>">Modifier</a>

                      <?php if ($isOwner): ?>
                        <form method="post" action="<?php echo e(base_url('admin/category_toggle.php')); ?>" style="margin:0;">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                          <input type="hidden" name="is_active" value="<?php echo $active ? '0' : '1'; ?>">
                          <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
                          <button class="admin-btn admin-btn--primary admin-btn--sm" type="submit">
                            <?php echo $active ? 'Désactiver' : 'Activer'; ?>
                          </button>
                        </form>
                        <a class="admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/category_delete.php?id=' . $id)); ?>">Supprimer</a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <?php if ($totalFiltered > $perPage): ?>
            <div class="admin-pagination" aria-label="Pagination">
              <?php
                $buildUrl = function (int $p) use ($q) {
                  $params = array('page' => $p);
                  if ($q !== '') $params['q'] = $q;
                  return base_url('admin/categories.php?' . http_build_query($params));
                };
              ?>

              <span class="admin-help">Page <?php echo (int) $page; ?> / <?php echo (int) $lastPage; ?></span>

              <?php if ($page > 1): ?>
                <a class="admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e($buildUrl($page - 1)); ?>">Précédent</a>
              <?php endif; ?>
              <?php if ($page < $lastPage): ?>
                <a class="admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e($buildUrl($page + 1)); ?>">Suivant</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
