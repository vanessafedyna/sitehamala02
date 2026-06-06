<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CouponModel.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';

$page_title = 'Admin - Codes promo';
$page_css = 'pages/admin-products.css';
$page_js = '';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$active = isset($_GET['active']) ? trim((string) $_GET['active']) : '';
$flash = admin_flash_get('coupons');
$errors = array();
$items = array();

try {
  $pdo = db();
  $model = new CouponModel($pdo);
  if (!$model->exists()) {
    $errors[] = "Table `coupons` manquante. Exécutez `database/patch_coupons.sql`.";
  } else {
    $filters = array('q' => $q);
    if ($active === '1' || $active === '0') {
      $filters['is_active'] = $active;
    }
    $items = $model->list($filters);
  }
} catch (Throwable $e) {
  $errors[] = 'Impossible de charger les coupons (base de données).';
}

function coupon_state_label(array $c): string
{
  $isActive = (int) ($c['is_active'] ?? 0);
  $uses = (int) ($c['uses_count'] ?? 0);
  $max = array_key_exists('max_uses', $c) && $c['max_uses'] !== null ? (int) $c['max_uses'] : null;
  $now = time();
  $starts = isset($c['starts_at']) && $c['starts_at'] ? strtotime((string) $c['starts_at']) : null;
  $ends = isset($c['ends_at']) && $c['ends_at'] ? strtotime((string) $c['ends_at']) : null;
  if (!$isActive) return 'Inactif';
  if ($starts !== null && $now < $starts) return 'À venir';
  if ($ends !== null && $now > $ends) return 'Expiré';
  if ($max !== null && $max > 0 && $uses >= $max) return 'Épuisé';
  return 'Actif';
}

function coupon_state_pill_class(array $c): string
{
  $label = coupon_state_label($c);
  if ($label === 'Actif') return 'admin-status-pill admin-status-pill--success';
  if ($label === 'À venir') return 'admin-status-pill admin-status-pill--info';
  if ($label === 'Expiré') return 'admin-status-pill admin-status-pill--warning';
  return 'admin-status-pill admin-status-pill--neutral';
}

require_once __DIR__ . '/_layout_header.php';
?>

<style>
  .admin-coupons-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-coupons-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-coupons-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-coupons-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-coupons-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-coupons-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-coupons-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-coupons-meta__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
  }
  .admin-coupons-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-coupons-toolbar {
    display: grid;
    gap: 14px;
  }
  .admin-coupons-toolbar .admin-filterbar__group {
    align-items: stretch;
  }
  .admin-coupons-toolbar .admin-filterbar__group--grow {
    flex: 1 1 560px;
  }
  .admin-coupons-toolbar .admin-field,
  .admin-coupons-toolbar .admin-select {
    min-width: min(220px, 100%);
  }
  .admin-coupons-toolbar .admin-help {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-coupons-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-coupons-toolbar__actions .admin-btn {
    white-space: nowrap;
  }
  .admin-coupons-table-panel {
    overflow: hidden;
  }
  .admin-coupons-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-coupons-table-shell .admin-table {
    min-width: 900px;
  }
  .admin-coupons-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-coupons-table-shell td,
  .admin-coupons-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: middle;
  }
  .admin-coupons-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-coupons-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-coupons-code {
    display: grid;
    gap: 4px;
  }
  .admin-coupons-code strong {
    color: var(--admin-ink);
    font-size: 0.96rem;
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
  }
  .admin-coupons-code__meta,
  .admin-coupons-uses {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
  }
  .admin-coupons-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-start;
  }
  .admin-coupons-empty {
    padding: 22px;
  }
  .admin-coupons-mobile-list {
    display: grid;
    gap: 14px;
  }
  .admin-coupons-mobile-card {
    display: grid;
    gap: 14px;
  }
  .admin-coupons-mobile-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }
  .admin-coupons-mobile-card__grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-coupons-mobile-card__label {
    display: block;
    margin-bottom: 4px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-coupons-mobile-card__value {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-coupons-mobile-card__value--muted {
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-coupons-mobile-card__actions {
    display: grid;
    gap: 8px;
  }
  .admin-coupons-mobile-card__actions .admin-btn {
    width: 100%;
  }
  .admin-coupons-page .admin-btn--primary,
  .admin-coupons-page .admin-btn--secondary,
  .admin-coupons-page .admin-btn--ghost {
    background-image: none;
  }
  .admin-coupons-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 1024px) {
    .admin-coupons-toolbar .admin-field,
    .admin-coupons-toolbar .admin-select {
      min-width: min(180px, 100%);
    }
  }
  @media (max-width: 820px) {
    .admin-coupons-page .admin-page-header {
      padding: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-coupons-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-coupons-meta {
      gap: 8px;
    }
    .admin-coupons-meta__chip {
      width: 100%;
      justify-content: space-between;
    }
    .admin-coupons-toolbar__actions {
      width: 100%;
    }
    .admin-coupons-toolbar__actions .admin-btn,
    .admin-page-header__actions .admin-btn {
      flex: 1 1 100%;
      justify-content: center;
    }
    .admin-coupons-mobile-card__header {
      flex-direction: column;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-coupons-reveal'));
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

<main id="main">
  <section>
    <div class="container">
      <div class="admin-coupons-page">
        <div class="admin-page-header admin-coupons-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Promotions commerciales</p>
            <h1 class="admin-page-header__title">Codes promo</h1>
            <p class="admin-page-header__subtitle">Gérez les promotions, dates et restrictions depuis une vue admin plus nette, plus dense et plus premium.</p>
            <div class="admin-coupons-meta" aria-label="Indicateurs coupons">
              <span class="admin-coupons-meta__chip"><strong><?php echo e((string) count($items)); ?></strong> coupon(s)</span>
              <span class="admin-coupons-meta__chip"><strong><?php echo e((string) count($errors)); ?></strong> alerte(s) active(s)</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/coupon_add.php')); ?>">
              <i class="fas fa-plus" aria-hidden="true"></i> Ajouter
            </a>
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour dashboard
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-coupons-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-coupons-reveal is-visible" role="alert">
            <strong>Erreur :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else: ?>
          <div class="admin-panel admin-panel--padded admin-coupons-toolbar admin-coupons-reveal" aria-label="Recherche coupons">
            <div class="admin-filterbar">
              <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search">
                <label class="sr-only" for="active">Statut</label>
                <select id="active" name="active" class="admin-select">
                  <option value="">Tous</option>
                  <option value="1" <?php echo $active === '1' ? 'selected' : ''; ?>>Actifs</option>
                  <option value="0" <?php echo $active === '0' ? 'selected' : ''; ?>>Inactifs</option>
                </select>

                <label class="sr-only" for="q">Code</label>
                <input class="admin-field" id="q" name="q" type="text" value="<?php echo e($q); ?>" placeholder="Rechercher un code">

                <div class="admin-coupons-toolbar__actions">
                  <button class="btn admin-btn admin-btn--primary" type="submit">
                    <i class="fas fa-search" aria-hidden="true"></i> Filtrer
                  </button>
                  <?php if ($q !== '' || $active !== ''): ?>
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/coupons.php')); ?>">Réinitialiser</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
            <div class="admin-help"><?php echo e((string) count($items)); ?> coupon(s)</div>
          </div>

          <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-coupons-table-panel admin-coupons-table-shell admin-coupons-reveal is-visible" aria-label="Liste coupons">
            <?php if (!$items): ?>
              <div class="admin-empty-panel admin-coupons-empty">
                <p class="admin-empty-panel__title">Aucun coupon.</p>
                <p class="admin-empty-panel__text">Les codes promo apparaîtront ici dès qu’ils seront créés.</p>
              </div>
            <?php else: ?>
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Valeur</th>
                    <th>État</th>
                    <th>Usages</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $c): ?>
                    <?php
                      $id = (int) ($c['id'] ?? 0);
                      $code = (string) ($c['code'] ?? '');
                      $type = (string) ($c['type'] ?? '');
                      $value = (string) ($c['value'] ?? '0');
                      $uses = (int) ($c['uses_count'] ?? 0);
                      $max = $c['max_uses'] === null ? null : (int) $c['max_uses'];
                    ?>
                    <tr>
                      <td>
                        <div class="admin-coupons-code">
                          <strong><?php echo e($code); ?></strong>
                          <span class="admin-coupons-code__meta">Code promotionnel</span>
                        </div>
                      </td>
                      <td><?php echo e($type === 'percent' ? '%' : 'Fixe'); ?></td>
                      <td><?php echo e($value); ?></td>
                      <td><span class="<?php echo e(coupon_state_pill_class($c)); ?>"><?php echo e(coupon_state_label($c)); ?></span></td>
                      <td><span class="admin-coupons-uses"><?php echo e((string) $uses); ?><?php echo $max ? (' / ' . (string) $max) : ''; ?></span></td>
                      <td>
                        <div class="admin-coupons-actions">
                          <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/coupon_edit.php?id=' . $id)); ?>">Éditer</a>
                          <a class="btn admin-btn admin-btn--ghost admin-btn--sm" href="<?php echo e(base_url('admin/coupon_delete.php?id=' . $id)); ?>">Supprimer</a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

          <div class="admin-mobile-only admin-coupons-reveal is-visible" aria-label="Liste coupons mobile">
            <div class="admin-mobile-cards admin-coupons-mobile-list">
              <?php if (!$items): ?>
                <div class="admin-empty-panel admin-coupons-empty">
                  <p class="admin-empty-panel__title">Aucun coupon.</p>
                  <p class="admin-empty-panel__text">Les codes promo apparaîtront ici dès qu’ils seront créés.</p>
                </div>
              <?php endif; ?>

              <?php foreach ($items as $c): ?>
                <?php
                  $id = (int) ($c['id'] ?? 0);
                  $code = (string) ($c['code'] ?? '');
                  $type = (string) ($c['type'] ?? '');
                  $value = (string) ($c['value'] ?? '0');
                  $uses = (int) ($c['uses_count'] ?? 0);
                  $max = $c['max_uses'] === null ? null : (int) $c['max_uses'];
                ?>
                <article class="admin-mobile-card admin-coupons-mobile-card">
                  <div class="admin-coupons-mobile-card__header">
                    <div>
                      <h2 class="admin-mobile-card__title"><?php echo e($code); ?></h2>
                      <div class="admin-mobile-card__meta">Code promotionnel</div>
                    </div>
                    <span class="<?php echo e(coupon_state_pill_class($c)); ?>"><?php echo e(coupon_state_label($c)); ?></span>
                  </div>

                  <div class="admin-coupons-mobile-card__grid">
                    <div>
                      <span class="admin-coupons-mobile-card__label">Type</span>
                      <div class="admin-coupons-mobile-card__value"><?php echo e($type === 'percent' ? '%' : 'Fixe'); ?></div>
                    </div>
                    <div>
                      <span class="admin-coupons-mobile-card__label">Valeur</span>
                      <div class="admin-coupons-mobile-card__value"><?php echo e($value); ?></div>
                    </div>
                    <div>
                      <span class="admin-coupons-mobile-card__label">Usages</span>
                      <div class="admin-coupons-mobile-card__value admin-coupons-mobile-card__value--muted"><?php echo e((string) $uses); ?><?php echo $max ? (' / ' . (string) $max) : ''; ?></div>
                    </div>
                  </div>

                  <div class="admin-coupons-mobile-card__actions">
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/coupon_edit.php?id=' . $id)); ?>">Éditer</a>
                    <a class="btn admin-btn admin-btn--ghost" href="<?php echo e(base_url('admin/coupon_delete.php?id=' . $id)); ?>">Supprimer</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
