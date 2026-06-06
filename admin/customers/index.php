<?php
declare(strict_types=1);

/* Liste clients admin */

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/CustomerModel.php';

$page_title = 'Admin - Clients';
$page_css = 'pages/admin-products.css';
$page_js = '';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$blacklisted = isset($_GET['blacklisted']) ? trim((string) $_GET['blacklisted']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 25;

$flash = admin_flash_get('customers');
$db_error = '';
$rows = array();
$total = 0;
$lastPage = 1;
$appEnv = strtolower(trim((string) env('APP_ENV', 'dev')));
$showDebugError = ($appEnv !== 'prod');

try {
  $pdo = db();
  $model = new CustomerModel($pdo);
  if (!$model->exists()) {
    throw new RuntimeException('missing_table');
  }

  $filters = array(
    'q' => $q,
    'blacklisted' => $blacklisted,
  );

  $total = $model->count($filters);
  $lastPage = max(1, (int) ceil($total / $perPage));
  $page = min($page, $lastPage);
  $offset = ($page - 1) * $perPage;

  $rows = $model->list(array_merge($filters, array('limit' => $perPage, 'offset' => $offset)));
} catch (Throwable $e) {
  if ($e instanceof RuntimeException && $e->getMessage() === 'missing_table') {
    $db_error = 'Table customers manquante. Exécutez: database/patch_customers.sql';
  } else {
    $db_error = 'Impossible de charger les clients (base de données).';
    if ($showDebugError) {
      $db_error .= ' Détail: ' . $e->getMessage();
    }
  }
  $rows = array();
  $total = 0;
  $lastPage = 1;
  $page = 1;
}

require_once __DIR__ . '/../_layout_header.php';
?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-customers-reveal'));
    if (!revealNodes.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || window.innerWidth <= 768) {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
        node.style.transitionDelay = '0ms';
      });
      return;
    }

    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.14 });

    revealNodes.forEach(function (node, index) {
      node.style.transitionDelay = Math.min(index * 45, 220) + 'ms';
      revealObserver.observe(node);
    });
  });
</script>

<?php
  $hasActiveFilters = ($q !== '' || $blacklisted !== '');
  $blacklistedCount = 0;
  foreach ($rows as $customerMeta) {
    if (!is_array($customerMeta)) continue;
    if ((int) ($customerMeta['is_blacklisted'] ?? 0) === 1) {
      $blacklistedCount++;
    }
  }
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-customers-page">
        <div class="admin-page-header admin-customers-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Relations clients</p>
            <h1 class="admin-page-header__title">Clients</h1>
            <p class="admin-page-header__subtitle">Consultez les fiches clients, les coordonnées et les statuts de blacklist depuis une vue plus claire et plus homogène.</p>
            <div class="admin-customers-meta" aria-label="Indicateurs clients">
              <span class="admin-customers-meta__chip"><strong><?php echo e((string) $total); ?></strong> client(s)</span>
              <span class="admin-customers-meta__chip"><strong><?php echo e((string) $blacklistedCount); ?></strong> blacklisté(s)</span>
              <span class="admin-customers-meta__chip"><strong><?php echo e((string) count($rows)); ?></strong> sur cette page</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-customers-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($db_error): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-customers-reveal is-visible" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php else: ?>
          <div class="admin-panel admin-panel--padded admin-customers-toolbar admin-customers-reveal" aria-label="Recherche clients">
            <div class="admin-filterbar">
              <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search">
                <label class="sr-only" for="blacklisted">Blacklist</label>
                <select id="blacklisted" name="blacklisted" class="admin-select">
                  <option value="" <?php echo $blacklisted === '' ? 'selected' : ''; ?>>Tous</option>
                  <option value="0" <?php echo $blacklisted === '0' ? 'selected' : ''; ?>>Non blacklistés</option>
                  <option value="1" <?php echo $blacklisted === '1' ? 'selected' : ''; ?>>Blacklistés</option>
                </select>

                <label class="sr-only" for="q">Recherche</label>
                <input
                  class="admin-field"
                  id="q"
                  name="q"
                  type="text"
                  value="<?php echo e($q); ?>"
                  placeholder="Rechercher nom / tel / email"
                >

                <div class="admin-customers-toolbar__actions">
                  <button class="btn admin-btn admin-btn--primary" type="submit">
                    <i class="fas fa-search" aria-hidden="true"></i> Rechercher
                  </button>
                  <?php if ($hasActiveFilters): ?>
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/index.php')); ?>">Réinitialiser</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
            <div class="admin-help"><?php echo e((string) $total); ?> client(s)</div>
          </div>

          <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-customers-table-panel admin-customers-table-shell admin-customers-reveal is-visible" aria-label="Liste clients">
            <?php if (!$rows): ?>
              <div class="admin-empty-panel admin-customers-empty">
                <?php if ($hasActiveFilters): ?>
                  <p class="admin-empty-panel__title">Aucun client ne correspond aux filtres.</p>
                  <p class="admin-empty-panel__text">Essayez une autre recherche ou réinitialisez les filtres pour recharger la liste.</p>
                  <div class="admin-empty-panel__actions">
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/index.php')); ?>">Réinitialiser</a>
                  </div>
                <?php else: ?>
                  <p class="admin-empty-panel__title">Aucun client.</p>
                  <p class="admin-empty-panel__text">Les fiches clients apparaîtront ici dès que des données seront disponibles.</p>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Téléphone</th>
                    <th>Ville</th>
                    <th>Blacklist</th>
                    <th>Créé</th>
                    <th style="text-align:right;"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $c): ?>
                    <?php
                      $id = (int) ($c['id'] ?? 0);
                      $name = (string) ($c['full_name'] ?? '');
                      $phone = (string) ($c['phone'] ?? '');
                      $city = (string) ($c['city'] ?? '');
                      $isB = (int) ($c['is_blacklisted'] ?? 0);
                      $created = (string) ($c['created_at'] ?? '');
                    ?>
                    <tr>
                      <td><span class="admin-help">#<?php echo e((string) $id); ?></span></td>
                      <td>
                        <div class="admin-customers-identity">
                          <strong><?php echo e($name !== '' ? $name : ('Client #' . $id)); ?></strong>
                        </div>
                      </td>
                      <td><?php echo e($phone !== '' ? $phone : '—'); ?></td>
                      <td><?php echo e($city !== '' ? $city : '—'); ?></td>
                      <td>
                        <span class="admin-status-pill <?php echo $isB ? 'admin-status-pill--danger' : 'admin-status-pill--success'; ?>">
                          <?php echo $isB ? 'Blacklisté' : 'Actif'; ?>
                        </span>
                      </td>
                      <td><span class="admin-customers-date"><?php echo e($created); ?></span></td>
                      <td style="text-align:right;">
                        <div class="admin-customers-actions">
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/customers/show.php?id=' . $id)); ?>">Voir</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

          <div class="admin-mobile-only admin-customers-reveal is-visible" aria-label="Liste clients mobile">
            <?php if (!$rows): ?>
              <div class="admin-customers-mobile-cards">
                <div class="admin-mobile-card admin-customers-mobile-card">
                  <div class="admin-empty-panel admin-customers-empty">
                    <?php if ($hasActiveFilters): ?>
                      <p class="admin-empty-panel__title">Aucun client ne correspond aux filtres.</p>
                      <p class="admin-empty-panel__text">Essayez une autre recherche ou réinitialisez les filtres pour recharger la liste.</p>
                      <div class="admin-empty-panel__actions">
                        <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/index.php')); ?>">Réinitialiser</a>
                      </div>
                    <?php else: ?>
                      <p class="admin-empty-panel__title">Aucun client.</p>
                      <p class="admin-empty-panel__text">Les fiches clients apparaîtront ici dès que des données seront disponibles.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="admin-customers-mobile-cards">
                <?php foreach ($rows as $c): ?>
                  <?php
                    $id = (int) ($c['id'] ?? 0);
                    $name = (string) ($c['full_name'] ?? '');
                    $phone = (string) ($c['phone'] ?? '');
                    $city = (string) ($c['city'] ?? '');
                    $isB = (int) ($c['is_blacklisted'] ?? 0);
                    $ordersCount = isset($c['orders_count']) ? (int) $c['orders_count'] : (isset($c['order_count']) ? (int) $c['order_count'] : -1);
                  ?>
                  <article class="admin-mobile-card admin-customers-mobile-card">
                    <div class="admin-customers-mobile-card__header">
                      <div>
                        <h2 class="admin-customers-mobile-card__title"><?php echo e($name !== '' ? $name : ('Client #' . $id)); ?></h2>
                        <div class="admin-customers-mobile-card__meta">Client #<?php echo e((string) $id); ?></div>
                      </div>
                      <span class="admin-status-pill <?php echo $isB ? 'admin-status-pill--danger' : 'admin-status-pill--success'; ?>">
                        <?php echo $isB ? 'Blacklisté' : 'Actif'; ?>
                      </span>
                    </div>

                    <div class="admin-customers-mobile-card__grid">
                      <div class="admin-customers-mobile-card__field">
                        <span class="admin-customers-mobile-card__label">Téléphone</span>
                        <div class="admin-customers-mobile-card__value"><?php echo e($phone !== '' ? $phone : '—'); ?></div>
                      </div>
                      <div class="admin-customers-mobile-card__field">
                        <span class="admin-customers-mobile-card__label">Ville</span>
                        <div class="admin-customers-mobile-card__value admin-customers-mobile-card__value--muted"><?php echo e($city !== '' ? $city : '—'); ?></div>
                      </div>
                      <?php if ($ordersCount >= 0): ?>
                        <div class="admin-customers-mobile-card__field">
                          <span class="admin-customers-mobile-card__label">Commandes</span>
                          <div class="admin-customers-mobile-card__value"><?php echo e((string) $ordersCount); ?></div>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="admin-customers-mobile-card__actions">
                      <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/show.php?id=' . $id)); ?>">Voir</a>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($lastPage > 1): ?>
            <nav class="admin-pagination admin-customers-reveal is-visible" aria-label="Pagination clients">
              <?php
                $qsBase = array();
                if ($q !== '') $qsBase['q'] = $q;
                if ($blacklisted !== '') $qsBase['blacklisted'] = $blacklisted;
              ?>

              <?php if ($page > 1): ?>
                <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page - 1))); ?>
                <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/customers/index.php?' . $qs)); ?>">Précédent</a>
              <?php endif; ?>

              <span class="admin-help">Page <?php echo e((string) $page); ?> / <?php echo e((string) $lastPage); ?></span>

              <?php if ($page < $lastPage): ?>
                <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page + 1))); ?>
                <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/customers/index.php?' . $qs)); ?>">Suivant</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
