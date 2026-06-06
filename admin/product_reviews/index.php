<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';

$page_title = 'Admin - Avis produits';
$page_css = 'pages/admin-products.css';
$page_js = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    admin_flash_set('product_reviews', 'error', 'Session expirée. Veuillez réessayer.');
    redirect('admin/product_reviews/index.php');
  }

  $action = (string) ($_POST['action'] ?? '');
  $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, array(
    'options' => array('min_range' => 1),
  ));

  if (!$id || !in_array($action, array('approve', 'unapprove', 'delete'), true)) {
    admin_flash_set('product_reviews', 'error', 'Action invalide.');
    redirect('admin/product_reviews/index.php');
  }

  try {
    $pdo = db();

    if ($action === 'approve') {
      $stmt = $pdo->prepare('UPDATE product_reviews SET is_approved = 1 WHERE id = :id');
      $stmt->execute(array(':id' => (int) $id));
      admin_flash_set('product_reviews', 'success', 'Avis produit approuvé.');
    } elseif ($action === 'unapprove') {
      $stmt = $pdo->prepare('UPDATE product_reviews SET is_approved = 0 WHERE id = :id');
      $stmt->execute(array(':id' => (int) $id));
      admin_flash_set('product_reviews', 'success', 'Avis produit remis en attente.');
    } elseif ($action === 'delete') {
      $stmt = $pdo->prepare('DELETE FROM product_reviews WHERE id = :id');
      $stmt->execute(array(':id' => (int) $id));
      admin_flash_set('product_reviews', 'success', 'Avis produit supprimé.');
    }
  } catch (Throwable $e) {
    error_log('[admin/product_reviews] ' . $e->getMessage());
    admin_flash_set('product_reviews', 'error', 'Erreur serveur.');
  }

  redirect('admin/product_reviews/index.php');
}

$flash = admin_flash_get('product_reviews');
$db_error = '';
$pending = array();
$approved = array();

try {
  $pdo = db();

  $stmtPending = $pdo->prepare(
    'SELECT pr.id, pr.product_id, pr.customer_name, pr.customer_city, pr.rating, pr.comment, pr.created_at,
            COALESCE(p.name, CONCAT("#", pr.product_id)) AS product_name
     FROM product_reviews pr
     LEFT JOIN products p ON p.id = pr.product_id
     WHERE pr.is_approved = 0
     ORDER BY pr.created_at DESC
     LIMIT 100'
  );
  $stmtPending->execute();
  $pending = $stmtPending->fetchAll(PDO::FETCH_ASSOC) ?: array();

  $stmtApproved = $pdo->prepare(
    'SELECT pr.id, pr.product_id, pr.customer_name, pr.customer_city, pr.rating, pr.comment, pr.created_at,
            COALESCE(p.name, CONCAT("#", pr.product_id)) AS product_name
     FROM product_reviews pr
     LEFT JOIN products p ON p.id = pr.product_id
     WHERE pr.is_approved = 1
     ORDER BY pr.created_at DESC
     LIMIT 50'
  );
  $stmtApproved->execute();
  $approved = $stmtApproved->fetchAll(PDO::FETCH_ASSOC) ?: array();
} catch (Throwable $e) {
  $db_error = 'Impossible de charger les avis produit (base de données).';
}

function product_reviews_excerpt(string $text, int $max = 140): string
{
  $t = trim($text);
  if ($t === '') return '';
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    if (mb_strlen($t) <= $max) return $t;
    return rtrim(mb_substr($t, 0, $max)) . '…';
  }
  if (strlen($t) <= $max) return $t;
  return rtrim(substr($t, 0, $max)) . '…';
}

require_once __DIR__ . '/../_layout_header.php';
?>

<style>
  .admin-product-reviews-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-product-reviews-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-product-reviews-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-product-reviews-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-product-reviews-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-product-reviews-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-product-reviews-meta__chip {
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
  .admin-product-reviews-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-product-reviews-toolbar {
    display: grid;
    gap: 14px;
  }
  .admin-product-reviews-toolbar .admin-help {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-product-reviews-section {
    display: grid;
    gap: 14px;
  }
  .admin-product-reviews-section__heading {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
  }
  .admin-product-reviews-section__heading h2 {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1.04rem;
  }
  .admin-product-reviews-table-panel {
    overflow: hidden;
  }
  .admin-product-reviews-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-product-reviews-table-shell .admin-table {
    min-width: 1120px;
  }
  .admin-product-reviews-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-product-reviews-table-shell td,
  .admin-product-reviews-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: top;
  }
  .admin-product-reviews-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-product-reviews-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-product-reviews-product,
  .admin-product-reviews-identity {
    display: grid;
    gap: 4px;
    min-width: 0;
  }
  .admin-product-reviews-product strong,
  .admin-product-reviews-identity strong,
  .admin-product-reviews-message {
    overflow-wrap: anywhere;
  }
  .admin-product-reviews-rating {
    color: var(--admin-ink);
    font-weight: 700;
  }
  .admin-product-reviews-status {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }
  .admin-product-reviews-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
  }
  .admin-product-reviews-actions form {
    margin: 0;
  }
  .admin-product-reviews-actions .admin-btn {
    white-space: nowrap;
  }
  .admin-product-reviews-empty {
    padding: 10px 4px;
  }
  .admin-product-reviews-mobile-cards {
    display: grid;
    gap: 14px;
  }
  .admin-product-reviews-mobile-card {
    display: grid;
    gap: 14px;
    border: 1px solid var(--admin-border);
    border-radius: 18px;
    background: var(--admin-surface);
    box-shadow: var(--admin-shadow-sm);
    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
  }
  .admin-product-reviews-mobile-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px rgba(18, 52, 36, 0.08);
    border-color: rgba(31, 122, 79, 0.16);
  }
  .admin-product-reviews-mobile-card__header {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
    align-items: flex-start;
  }
  .admin-product-reviews-mobile-card__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1rem;
    line-height: 1.35;
  }
  .admin-product-reviews-mobile-card__meta {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-product-reviews-mobile-card__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .admin-product-reviews-mobile-card__field {
    min-width: 0;
    padding: 12px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 14px;
    background: #fbfcfb;
  }
  .admin-product-reviews-mobile-card__label {
    display: block;
    margin-bottom: 6px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-product-reviews-mobile-card__value {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-product-reviews-mobile-card__value--muted {
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-product-reviews-mobile-card__message {
    color: var(--admin-text);
    line-height: 1.6;
  }
  .admin-product-reviews-mobile-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }
  .admin-product-reviews-mobile-card__actions form {
    flex: 1 1 100%;
    margin: 0;
  }
  .admin-product-reviews-mobile-card__actions .admin-btn {
    width: 100%;
  }
  .admin-product-reviews-page .admin-btn--primary,
  .admin-product-reviews-page .admin-btn--secondary {
    background-image: none;
  }
  .admin-product-reviews-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 820px) {
    .admin-product-reviews-page .admin-page-header {
      padding: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-product-reviews-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-product-reviews-meta {
      gap: 8px;
    }
    .admin-product-reviews-mobile-card__header {
      flex-direction: column;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-product-reviews-reveal'));
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
      <div class="admin-product-reviews-page">
        <div class="admin-page-header admin-product-reviews-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Avis produits</p>
            <h1 class="admin-page-header__title">Modération des avis produits</h1>
            <p class="admin-page-header__subtitle">Supervisez les avis liés aux fiches produits depuis une vue plus claire, plus dense et plus homogène.</p>
            <div class="admin-product-reviews-meta" aria-label="Indicateurs avis produits">
              <span class="admin-product-reviews-meta__chip"><strong><?php echo e((string) count($pending)); ?></strong> en attente</span>
              <span class="admin-product-reviews-meta__chip"><strong><?php echo e((string) count($approved)); ?></strong> publiés</span>
              <span class="admin-product-reviews-meta__chip"><strong><?php echo e((string) (count($pending) + count($approved))); ?></strong> affichés</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-product-reviews-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($db_error): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-product-reviews-reveal is-visible" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php else: ?>
          <div class="admin-panel admin-panel--padded admin-product-reviews-toolbar admin-product-reviews-reveal" aria-label="Statut avis produit">
            <div class="admin-help">
              <span>En attente : <?php echo e((string) count($pending)); ?></span>
              <span>Publiés : <?php echo e((string) count($approved)); ?></span>
            </div>
          </div>

          <div class="admin-product-reviews-section admin-product-reviews-reveal is-visible">
            <div class="admin-product-reviews-section__heading">
              <h2>En attente de validation</h2>
              <span class="admin-status-pill admin-status-pill--warning"><?php echo e((string) count($pending)); ?> en attente</span>
            </div>

            <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-product-reviews-table-panel admin-product-reviews-table-shell" aria-label="Avis produit en attente">
              <?php if (!$pending): ?>
                <div class="admin-empty-panel admin-product-reviews-empty">
                  <p class="admin-empty-panel__title">Aucun avis en attente.</p>
                  <p class="admin-empty-panel__text">Les nouveaux avis produits à modérer apparaîtront ici.</p>
                </div>
              <?php else: ?>
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Produit</th>
                      <th>Auteur</th>
                      <th>Ville</th>
                      <th>Note</th>
                      <th>Statut</th>
                      <th>Commentaire</th>
                      <th>Date</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pending as $r): ?>
                      <?php
                        $id = (int) ($r['id'] ?? 0);
                        $rating = (int) ($r['rating'] ?? 0);
                        if ($rating < 1) $rating = 1;
                        if ($rating > 5) $rating = 5;
                      ?>
                      <tr>
                        <td><span class="admin-help">#<?php echo e((string) $id); ?></span></td>
                        <td>
                          <div class="admin-product-reviews-product">
                            <strong><?php echo e((string) ($r['product_name'] ?? '')); ?></strong>
                            <div class="admin-help">Produit #<?php echo e((string) ($r['product_id'] ?? '')); ?></div>
                          </div>
                        </td>
                        <td>
                          <div class="admin-product-reviews-identity">
                            <strong><?php echo e((string) ($r['customer_name'] ?? '')); ?></strong>
                          </div>
                        </td>
                        <td><?php echo e((string) ($r['customer_city'] ?? '')) !== '' ? e((string) ($r['customer_city'] ?? '')) : '—'; ?></td>
                        <td><span class="admin-product-reviews-rating"><?php echo e((string) $rating); ?>/5</span></td>
                        <td>
                          <div class="admin-product-reviews-status">
                            <span class="admin-status-pill admin-status-pill--warning">En attente</span>
                          </div>
                        </td>
                        <td class="admin-product-reviews-message" title="<?php echo e((string) ($r['comment'] ?? '')); ?>"><?php echo e(product_reviews_excerpt((string) ($r['comment'] ?? ''))); ?></td>
                        <td><span class="admin-help"><?php echo e((string) ($r['created_at'] ?? '')); ?></span></td>
                        <td style="text-align:right;">
                          <div class="admin-product-reviews-actions">
                            <form method="post" action="">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="approve">
                              <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                              <button class="btn admin-btn admin-btn--primary admin-btn--sm" type="submit">Approuver</button>
                            </form>

                            <form method="post" action="" onsubmit="return confirm('Supprimer cet avis ?');">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit">Supprimer</button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

            <div class="admin-mobile-only" aria-label="Avis produit en attente mobile">
              <div class="admin-product-reviews-mobile-cards">
                <?php if (!$pending): ?>
                  <div class="admin-mobile-card admin-product-reviews-mobile-card">
                    <div class="admin-empty-panel admin-product-reviews-empty">
                      <p class="admin-empty-panel__title">Aucun avis en attente.</p>
                      <p class="admin-empty-panel__text">Les nouveaux avis produits à modérer apparaîtront ici.</p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php foreach ($pending as $r): ?>
                  <?php
                    $id = (int) ($r['id'] ?? 0);
                    $rating = (int) ($r['rating'] ?? 0);
                    if ($rating < 1) $rating = 1;
                    if ($rating > 5) $rating = 5;
                  ?>
                  <article class="admin-mobile-card admin-product-reviews-mobile-card">
                    <div class="admin-product-reviews-mobile-card__header">
                      <div>
                        <h2 class="admin-product-reviews-mobile-card__title"><?php echo e((string) ($r['product_name'] ?? '')); ?></h2>
                        <div class="admin-product-reviews-mobile-card__meta">Produit #<?php echo e((string) ($r['product_id'] ?? '')); ?> · Avis #<?php echo e((string) $id); ?></div>
                      </div>
                      <span class="admin-status-pill admin-status-pill--warning">En attente</span>
                    </div>

                    <div class="admin-product-reviews-mobile-card__grid">
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Auteur</span>
                        <div class="admin-product-reviews-mobile-card__value"><?php echo e((string) ($r['customer_name'] ?? '')); ?></div>
                      </div>
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Ville</span>
                        <div class="admin-product-reviews-mobile-card__value admin-product-reviews-mobile-card__value--muted"><?php echo e((string) ($r['customer_city'] ?? '')) !== '' ? e((string) ($r['customer_city'] ?? '')) : '—'; ?></div>
                      </div>
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Note</span>
                        <div class="admin-product-reviews-mobile-card__value"><?php echo e((string) $rating); ?>/5</div>
                      </div>
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Date</span>
                        <div class="admin-product-reviews-mobile-card__value admin-product-reviews-mobile-card__value--muted"><?php echo e((string) ($r['created_at'] ?? '')); ?></div>
                      </div>
                    </div>

                    <div class="admin-product-reviews-mobile-card__message"><?php echo e(product_reviews_excerpt((string) ($r['comment'] ?? ''), 220)); ?></div>

                    <div class="admin-product-reviews-mobile-card__actions">
                      <form method="post" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                        <button class="btn admin-btn admin-btn--primary" type="submit">Approuver</button>
                      </form>

                      <form method="post" action="" onsubmit="return confirm('Supprimer cet avis ?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                        <button class="btn admin-btn admin-btn--secondary" type="submit">Supprimer</button>
                      </form>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="admin-product-reviews-section admin-product-reviews-reveal is-visible">
            <div class="admin-product-reviews-section__heading">
              <h2>Derniers avis publiés</h2>
              <span class="admin-status-pill admin-status-pill--success"><?php echo e((string) count($approved)); ?> publiés</span>
            </div>

            <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-product-reviews-table-panel admin-product-reviews-table-shell" aria-label="Avis produit publiés">
              <?php if (!$approved): ?>
                <div class="admin-empty-panel admin-product-reviews-empty">
                  <p class="admin-empty-panel__title">Aucun avis publié.</p>
                  <p class="admin-empty-panel__text">Les avis produits approuvés apparaîtront ici après validation.</p>
                </div>
              <?php else: ?>
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Produit</th>
                      <th>Auteur</th>
                      <th>Ville</th>
                      <th>Note</th>
                      <th>Statut</th>
                      <th>Commentaire</th>
                      <th>Date</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($approved as $r): ?>
                      <?php
                        $id = (int) ($r['id'] ?? 0);
                        $rating = (int) ($r['rating'] ?? 0);
                        if ($rating < 1) $rating = 1;
                        if ($rating > 5) $rating = 5;
                      ?>
                      <tr>
                        <td><span class="admin-help">#<?php echo e((string) $id); ?></span></td>
                        <td>
                          <div class="admin-product-reviews-product">
                            <strong><?php echo e((string) ($r['product_name'] ?? '')); ?></strong>
                            <div class="admin-help">Produit #<?php echo e((string) ($r['product_id'] ?? '')); ?></div>
                          </div>
                        </td>
                        <td>
                          <div class="admin-product-reviews-identity">
                            <strong><?php echo e((string) ($r['customer_name'] ?? '')); ?></strong>
                          </div>
                        </td>
                        <td><?php echo e((string) ($r['customer_city'] ?? '')) !== '' ? e((string) ($r['customer_city'] ?? '')) : '—'; ?></td>
                        <td><span class="admin-product-reviews-rating"><?php echo e((string) $rating); ?>/5</span></td>
                        <td>
                          <div class="admin-product-reviews-status">
                            <span class="admin-status-pill admin-status-pill--success">Publié</span>
                          </div>
                        </td>
                        <td class="admin-product-reviews-message" title="<?php echo e((string) ($r['comment'] ?? '')); ?>"><?php echo e(product_reviews_excerpt((string) ($r['comment'] ?? ''))); ?></td>
                        <td><span class="admin-help"><?php echo e((string) ($r['created_at'] ?? '')); ?></span></td>
                        <td style="text-align:right;">
                          <div class="admin-product-reviews-actions">
                            <form method="post" action="">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="unapprove">
                              <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit">Mettre en attente</button>
                            </form>

                            <form method="post" action="" onsubmit="return confirm('Supprimer cet avis ?');">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit">Supprimer</button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>

            <div class="admin-mobile-only" aria-label="Avis produit publiés mobile">
              <div class="admin-product-reviews-mobile-cards">
                <?php if (!$approved): ?>
                  <div class="admin-mobile-card admin-product-reviews-mobile-card">
                    <div class="admin-empty-panel admin-product-reviews-empty">
                      <p class="admin-empty-panel__title">Aucun avis publié.</p>
                      <p class="admin-empty-panel__text">Les avis produits approuvés apparaîtront ici après validation.</p>
                    </div>
                  </div>
                <?php endif; ?>

                <?php foreach ($approved as $r): ?>
                  <?php
                    $id = (int) ($r['id'] ?? 0);
                    $rating = (int) ($r['rating'] ?? 0);
                    if ($rating < 1) $rating = 1;
                    if ($rating > 5) $rating = 5;
                  ?>
                  <article class="admin-mobile-card admin-product-reviews-mobile-card">
                    <div class="admin-product-reviews-mobile-card__header">
                      <div>
                        <h2 class="admin-product-reviews-mobile-card__title"><?php echo e((string) ($r['product_name'] ?? '')); ?></h2>
                        <div class="admin-product-reviews-mobile-card__meta">Produit #<?php echo e((string) ($r['product_id'] ?? '')); ?> · Avis #<?php echo e((string) $id); ?></div>
                      </div>
                      <span class="admin-status-pill admin-status-pill--success">Publié</span>
                    </div>

                    <div class="admin-product-reviews-mobile-card__grid">
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Auteur</span>
                        <div class="admin-product-reviews-mobile-card__value"><?php echo e((string) ($r['customer_name'] ?? '')); ?></div>
                      </div>
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Ville</span>
                        <div class="admin-product-reviews-mobile-card__value admin-product-reviews-mobile-card__value--muted"><?php echo e((string) ($r['customer_city'] ?? '')) !== '' ? e((string) ($r['customer_city'] ?? '')) : '—'; ?></div>
                      </div>
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Note</span>
                        <div class="admin-product-reviews-mobile-card__value"><?php echo e((string) $rating); ?>/5</div>
                      </div>
                      <div class="admin-product-reviews-mobile-card__field">
                        <span class="admin-product-reviews-mobile-card__label">Date</span>
                        <div class="admin-product-reviews-mobile-card__value admin-product-reviews-mobile-card__value--muted"><?php echo e((string) ($r['created_at'] ?? '')); ?></div>
                      </div>
                    </div>

                    <div class="admin-product-reviews-mobile-card__message"><?php echo e(product_reviews_excerpt((string) ($r['comment'] ?? ''), 220)); ?></div>

                    <div class="admin-product-reviews-mobile-card__actions">
                      <form method="post" action="">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="unapprove">
                        <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                        <button class="btn admin-btn admin-btn--secondary" type="submit">Mettre en attente</button>
                      </form>

                      <form method="post" action="" onsubmit="return confirm('Supprimer cet avis ?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo e((string) $id); ?>">
                        <button class="btn admin-btn admin-btn--secondary" type="submit">Supprimer</button>
                      </form>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
