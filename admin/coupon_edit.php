<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CouponModel.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$page_title = 'Admin - Éditer code promo';
$page_css = 'pages/admin-products.css';
$page_js = '';

$pdo = db();
$couponModel = new CouponModel($pdo);
$categoryModel = new CategoryModel($pdo);
$coupon = $couponModel->findById($id);
if (!$coupon) {
  http_response_code(404);
}

$categories = $categoryModel->exists() ? $categoryModel->list(array('is_active' => 1)) : array();
$selectedCats = $coupon ? $couponModel->getCategories($id) : array();

function dt_local_value(?string $dt): string
{
  $dt = trim((string) ($dt ?? ''));
  if ($dt === '') return '';
  // mysql "YYYY-mm-dd HH:ii:ss" => input "YYYY-mm-ddTHH:ii"
  $dt = str_replace(' ', 'T', $dt);
  return substr($dt, 0, 16);
}

$errors = array();
$values = array(
  'code' => (string) ($coupon['code'] ?? ''),
  'type' => (string) ($coupon['type'] ?? 'percent'),
  'value' => (string) ($coupon['value'] ?? '0'),
  'starts_at' => dt_local_value($coupon['starts_at'] ?? null),
  'ends_at' => dt_local_value($coupon['ends_at'] ?? null),
  'min_subtotal' => (string) ($coupon['min_subtotal'] ?? ''),
  'max_uses' => $coupon['max_uses'] === null ? '' : (string) ((int) $coupon['max_uses']),
  'is_active' => ((int) ($coupon['is_active'] ?? 1)) ? '1' : '0',
  'categories' => array_map('strval', $selectedCats),
);

if ($coupon && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    $values['code'] = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $values['type'] = (string) ($_POST['type'] ?? 'percent');
    $values['value'] = trim((string) ($_POST['value'] ?? '0'));
    $values['starts_at'] = trim((string) ($_POST['starts_at'] ?? ''));
    $values['ends_at'] = trim((string) ($_POST['ends_at'] ?? ''));
    $values['min_subtotal'] = trim((string) ($_POST['min_subtotal'] ?? ''));
    $values['max_uses'] = trim((string) ($_POST['max_uses'] ?? ''));
    $values['is_active'] = isset($_POST['is_active']) ? '1' : '0';
    $values['categories'] = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_map('strval', $_POST['category_ids']) : array();

    if ($values['code'] === '') $errors[] = 'Le code est obligatoire.';
    if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $values['code'])) $errors[] = 'Code invalide.';
    if (!in_array($values['type'], array('percent', 'fixed'), true)) $errors[] = 'Type invalide.';
    if (!is_numeric($values['value'])) $errors[] = 'Valeur invalide.';
    if (is_numeric($values['value']) && $values['type'] === 'percent' && ((float) $values['value'] <= 0 || (float) $values['value'] > 100)) $errors[] = 'Valeur invalide.';
    if (is_numeric($values['value']) && $values['type'] === 'fixed' && (float) $values['value'] <= 0) $errors[] = 'Valeur invalide.';
    if ($values['max_uses'] !== '' && !preg_match('/^[1-9][0-9]*$/', $values['max_uses'])) $errors[] = 'Valeur invalide.';
    if ($values['min_subtotal'] !== '' && (!is_numeric($values['min_subtotal']) || (float) $values['min_subtotal'] < 0)) $errors[] = 'Valeur invalide.';

    if (!$errors) {
      try {
        // input datetime-local => mysql datetime
        $starts = $values['starts_at'] !== '' ? str_replace('T', ' ', $values['starts_at']) . ':00' : '';
        $ends = $values['ends_at'] !== '' ? str_replace('T', ' ', $values['ends_at']) . ':00' : '';

        $couponModel->update($id, array(
          'code' => $values['code'],
          'type' => $values['type'],
          'value' => $values['value'],
          'starts_at' => $starts,
          'ends_at' => $ends,
          'min_subtotal' => $values['min_subtotal'],
          'max_uses' => $values['max_uses'],
          'is_active' => (int) $values['is_active'],
        ));
        $couponModel->setCategories($id, array_map('intval', (array) $values['categories']));

        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        AdminAuditService::log($pdo, $adminId, 'owner_updated_coupon', 'coupon', (int) $id);

        admin_flash_set('coupons', 'success', 'Coupon mis à jour.');
        redirect('admin/coupons.php');
      } catch (Throwable $e) {
        $errors[] = 'Erreur lors de la mise à jour.';
      }
    }
  }
}

require_once __DIR__ . '/_layout_header.php';
?>

<style>
  .admin-coupon-form-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-coupon-form-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-coupon-form-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-coupon-form-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-coupon-form-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-coupon-form-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-coupon-form-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-coupon-form-meta__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-coupon-form-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-coupon-form {
    display: grid;
    gap: 16px;
  }
  .admin-coupon-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
    gap: 16px;
    align-items: start;
  }
  .admin-coupon-form-stack {
    display: grid;
    gap: 16px;
    min-width: 0;
  }
  .admin-coupon-form-section {
    display: grid;
    gap: 18px;
    min-width: 0;
  }
  .admin-coupon-form-section__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
  }
  .admin-coupon-form-kicker {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(31, 122, 79, 0.08);
    color: #28513d;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .admin-coupon-form-section__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1.05rem;
    font-weight: 700;
  }
  .admin-coupon-form-section__text {
    margin: 6px 0 0;
    color: var(--admin-text-muted);
    line-height: 1.55;
  }
  .admin-coupon-form-fields {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-coupon-form-side-fields,
  .admin-coupon-form-category-list {
    display: grid;
    gap: 12px;
  }
  .admin-coupon-form-category-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-coupon-form-field {
    display: grid;
    gap: 8px;
    min-width: 0;
  }
  .admin-coupon-form-field--full {
    grid-column: 1 / -1;
  }
  .admin-coupon-form-field .admin-field,
  .admin-coupon-form-field .admin-select {
    width: 100%;
    min-width: 0;
    background-image: none;
  }
  .admin-coupon-form-field .admin-field:focus,
  .admin-coupon-form-field .admin-select:focus,
  .admin-coupon-form-field .admin-field:focus-visible,
  .admin-coupon-form-field .admin-select:focus-visible {
    outline: 0;
    border-color: rgba(31, 122, 79, 0.45);
    box-shadow: 0 0 0 4px rgba(31, 122, 79, 0.12);
  }
  .admin-coupon-form-toggle {
    display: inline-flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
    padding: 14px 16px;
    border: 1px solid var(--admin-border);
    border-radius: 16px;
    background: var(--admin-surface-soft);
    color: var(--admin-text);
    line-height: 1.45;
  }
  .admin-coupon-form-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 1px;
    flex: 0 0 auto;
    accent-color: var(--admin-accent);
  }
  .admin-coupon-form-toggle__body {
    display: grid;
    gap: 4px;
    min-width: 0;
  }
  .admin-coupon-form-toggle__title {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-coupon-form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
  }
  .admin-coupon-form-page .admin-btn--primary,
  .admin-coupon-form-page .admin-btn--secondary {
    background-image: none;
  }
  .admin-coupon-form-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 980px) {
    .admin-coupon-form-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 768px) {
    .admin-coupon-form-page .admin-page-header,
    .admin-coupon-form-page .admin-panel--padded {
      padding: 16px;
    }
    .admin-coupon-form-fields,
    .admin-coupon-form-category-list {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-coupon-form-meta__chip,
    .admin-coupon-form-page .admin-page-header__actions,
    .admin-coupon-form-actions,
    .admin-coupon-form-actions .admin-btn,
    .admin-coupon-form-page .admin-page-header__actions .admin-btn {
      width: 100%;
    }
    .admin-coupon-form-actions .admin-btn,
    .admin-coupon-form-page .admin-page-header__actions .admin-btn {
      justify-content: center;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-coupon-form-reveal'));
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
      <div class="admin-coupon-form-page">
        <div class="admin-page-header admin-coupon-form-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Promotions commerciales</p>
            <h1 class="admin-page-header__title">Éditer un code promo</h1>
            <p class="admin-page-header__subtitle"><?php echo $coupon ? e($values['code'] !== '' ? $values['code'] : ('Coupon #' . $id)) : 'Coupon introuvable.'; ?></p>
            <?php if ($coupon): ?>
              <div class="admin-coupon-form-meta" aria-label="Contexte code promo">
                <span class="admin-coupon-form-meta__chip"><strong>Type</strong> <?php echo e($values['type'] === 'percent' ? 'Pourcentage' : 'Montant fixe'); ?></span>
                <span class="admin-coupon-form-meta__chip"><strong>Valeur</strong> <?php echo e($values['value']); ?></span>
                <span class="<?php echo $values['is_active'] === '1' ? 'admin-status-pill admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                  <?php echo $values['is_active'] === '1' ? 'Actif' : 'Inactif'; ?>
                </span>
              </div>
            <?php endif; ?>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/coupons.php')); ?>">
              <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
            </a>
          </div>
        </div>

        <?php if (!$coupon): ?>
          <div class="admin-panel admin-panel--padded admin-empty-panel admin-coupon-form-reveal is-visible" role="alert">
            <p class="admin-empty-panel__title">Coupon introuvable.</p>
            <p class="admin-empty-panel__text">Revenez à la liste des codes promo pour sélectionner un coupon existant.</p>
          </div>
        <?php else: ?>
          <?php if ($errors): ?>
            <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-coupon-form-reveal is-visible" role="alert">
              <strong>Merci de corriger :</strong>
              <ul>
                <?php foreach ($errors as $err): ?>
                  <li><?php echo e($err); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" class="admin-coupon-form" novalidate>
            <?php echo csrf_field(); ?>

            <div class="admin-coupon-form-grid">
              <div class="admin-coupon-form-stack">
                <section class="admin-panel admin-panel--padded admin-coupon-form-section admin-coupon-form-reveal" aria-labelledby="couponCodeTitle">
                  <div class="admin-coupon-form-section__head">
                    <div>
                      <span class="admin-coupon-form-kicker">Code</span>
                      <h2 id="couponCodeTitle" class="admin-coupon-form-section__title">Identification</h2>
                      <p class="admin-coupon-form-section__text">Code saisi par les clients au moment de la commande.</p>
                    </div>
                  </div>

                  <div class="admin-coupon-form-fields">
                    <div class="admin-coupon-form-field admin-coupon-form-field--full">
                      <label class="admin-field-label" for="code">Code *</label>
                      <input id="code" name="code" class="admin-field" required value="<?php echo e($values['code']); ?>">
                    </div>
                  </div>
                </section>

                <section class="admin-panel admin-panel--padded admin-coupon-form-section admin-coupon-form-reveal" aria-labelledby="couponDiscountTitle">
                  <div class="admin-coupon-form-section__head">
                    <div>
                      <span class="admin-coupon-form-kicker">Réduction</span>
                      <h2 id="couponDiscountTitle" class="admin-coupon-form-section__title">Type et valeur</h2>
                      <p class="admin-coupon-form-section__text">Conserve le même mode de calcul, avec des champs mieux alignés.</p>
                    </div>
                  </div>

                  <div class="admin-coupon-form-fields">
                    <div class="admin-coupon-form-field">
                      <label class="admin-field-label" for="type">Type *</label>
                      <select id="type" name="type" class="admin-field admin-select">
                        <option value="percent" <?php echo $values['type'] === 'percent' ? 'selected' : ''; ?>>Pourcentage</option>
                        <option value="fixed" <?php echo $values['type'] === 'fixed' ? 'selected' : ''; ?>>Montant fixe</option>
                      </select>
                    </div>

                    <div class="admin-coupon-form-field">
                      <label class="admin-field-label" for="value">Valeur *</label>
                      <input id="value" name="value" class="admin-field" value="<?php echo e($values['value']); ?>">
                    </div>

                    <div class="admin-coupon-form-field admin-coupon-form-field--full">
                      <label class="admin-field-label" for="min_subtotal">Sous-total minimum (FCFA)</label>
                      <input id="min_subtotal" name="min_subtotal" class="admin-field" value="<?php echo e($values['min_subtotal']); ?>">
                    </div>
                  </div>
                </section>

                <section class="admin-panel admin-panel--padded admin-coupon-form-section admin-coupon-form-reveal" aria-labelledby="couponValidityTitle">
                  <div class="admin-coupon-form-section__head">
                    <div>
                      <span class="admin-coupon-form-kicker">Validité</span>
                      <h2 id="couponValidityTitle" class="admin-coupon-form-section__title">Dates et usages</h2>
                      <p class="admin-coupon-form-section__text">Période d'activation et limite d'utilisation du coupon.</p>
                    </div>
                  </div>

                  <div class="admin-coupon-form-fields">
                    <div class="admin-coupon-form-field">
                      <label class="admin-field-label" for="starts_at">Début</label>
                      <input id="starts_at" name="starts_at" type="datetime-local" class="admin-field" value="<?php echo e($values['starts_at']); ?>">
                    </div>

                    <div class="admin-coupon-form-field">
                      <label class="admin-field-label" for="ends_at">Fin</label>
                      <input id="ends_at" name="ends_at" type="datetime-local" class="admin-field" value="<?php echo e($values['ends_at']); ?>">
                    </div>

                    <div class="admin-coupon-form-field admin-coupon-form-field--full">
                      <label class="admin-field-label" for="max_uses">Max usages</label>
                      <input id="max_uses" name="max_uses" type="number" class="admin-field" value="<?php echo e($values['max_uses']); ?>" min="1" step="1">
                    </div>
                  </div>
                </section>
              </div>

              <aside class="admin-coupon-form-stack" aria-label="Statut et catégories">
                <section class="admin-panel admin-panel--padded admin-coupon-form-section admin-coupon-form-reveal" aria-labelledby="couponStatusTitle">
                  <div class="admin-coupon-form-section__head">
                    <div>
                      <span class="admin-coupon-form-kicker">Statut</span>
                      <h2 id="couponStatusTitle" class="admin-coupon-form-section__title">Publication</h2>
                      <p class="admin-coupon-form-section__text">Contrôle l'activation du code promo.</p>
                    </div>
                  </div>

                  <label class="admin-coupon-form-toggle">
                    <input type="checkbox" name="is_active" value="1" <?php echo $values['is_active'] === '1' ? 'checked' : ''; ?>>
                    <span class="admin-coupon-form-toggle__body">
                      <span class="admin-coupon-form-toggle__title">Actif</span>
                      <span class="admin-help">Conserve le même champ de statut.</span>
                    </span>
                  </label>
                </section>

                <section class="admin-panel admin-panel--padded admin-coupon-form-section admin-coupon-form-reveal" aria-labelledby="couponCategoriesTitle">
                  <div class="admin-coupon-form-section__head">
                    <div>
                      <span class="admin-coupon-form-kicker">Portée</span>
                      <h2 id="couponCategoriesTitle" class="admin-coupon-form-section__title">Catégories (optionnel)</h2>
                      <p class="admin-coupon-form-section__text">Si vide: coupon applicable à tout le panier. Sinon: seulement aux produits des catégories sélectionnées.</p>
                    </div>
                  </div>

                  <?php if (!$categories): ?>
                    <div class="admin-empty-panel">
                      <p class="admin-empty-panel__title">Aucune catégorie active.</p>
                    </div>
                  <?php else: ?>
                    <div class="admin-coupon-form-category-list">
                      <?php foreach ($categories as $cat): ?>
                        <?php $cid = (int) ($cat['id'] ?? 0); ?>
                        <label class="admin-coupon-form-toggle">
                          <input
                            type="checkbox"
                            name="category_ids[]"
                            value="<?php echo (int) $cid; ?>"
                            <?php echo in_array((string) $cid, (array) $values['categories'], true) ? 'checked' : ''; ?>
                          >
                          <span class="admin-coupon-form-toggle__body">
                            <span class="admin-coupon-form-toggle__title"><?php echo e((string) ($cat['name'] ?? '')); ?></span>
                          </span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </section>

                <div class="admin-coupon-form-actions admin-coupon-form-reveal is-visible">
                  <button class="btn admin-btn admin-btn--primary" type="submit">Enregistrer</button>
                </div>
              </aside>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
