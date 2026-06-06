<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$page_title = 'Admin - Zones de livraison';
$page_css = 'pages/admin-products.css';
$page_js = '';

$flash = admin_flash_get('shipping_zones');
$errors = array();
$pdo = db();

function shipping_table_exists(PDO $pdo): bool
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, 'shipping_zones') !== array();
  }
  try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'shipping_zones'");
    return (bool) ($stmt && $stmt->fetchColumn());
  } catch (Throwable $e) {
    return false;
  }
}

if (!shipping_table_exists($pdo)) {
  $errors[] = "Table `shipping_zones` manquante. Exécutez `database/patch_ops_settings.sql`.";
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$errors) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    $action = (string) ($_POST['action'] ?? '');
    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

    try {
      if ($action === 'create') {
        $city = trim((string) ($_POST['city'] ?? ''));
        $zone = trim((string) ($_POST['zone'] ?? ''));
        $fee = trim((string) ($_POST['fee'] ?? '0'));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($city === '') throw new RuntimeException('Ville obligatoire.');
        if ($zone === '') $zone = null;
        if (!preg_match('/^\d+(\.\d+)?$/', $fee)) throw new RuntimeException('Frais invalides.');

        $stmt = $pdo->prepare(
          'INSERT INTO shipping_zones (city, zone, fee, is_active, sort_order) VALUES (:city, :zone, :fee, :a, :s)'
        );
        $stmt->execute(array(
          'city' => $city,
          'zone' => $zone,
          'fee' => (string) $fee,
          'a' => $active,
          's' => $sort,
        ));

        AdminAuditService::log($pdo, $adminId, 'owner_created_shipping_zone', 'shipping_zone', (int) $pdo->lastInsertId());
        admin_flash_set('shipping_zones', 'success', 'Zone ajoutée.');
        redirect('admin/shipping_zones.php');
      }

      if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $city = trim((string) ($_POST['city'] ?? ''));
        $zone = trim((string) ($_POST['zone'] ?? ''));
        $fee = trim((string) ($_POST['fee'] ?? '0'));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0) throw new RuntimeException('ID invalide.');
        if ($city === '') throw new RuntimeException('Ville obligatoire.');
        if ($zone === '') $zone = null;
        if (!preg_match('/^\d+(\.\d+)?$/', $fee)) throw new RuntimeException('Frais invalides.');

        $stmt = $pdo->prepare(
          'UPDATE shipping_zones SET city=:city, zone=:zone, fee=:fee, is_active=:a, sort_order=:s WHERE id=:id LIMIT 1'
        );
        $stmt->execute(array(
          'id' => $id,
          'city' => $city,
          'zone' => $zone,
          'fee' => (string) $fee,
          'a' => $active,
          's' => $sort,
        ));

        AdminAuditService::log($pdo, $adminId, 'owner_updated_shipping_zone', 'shipping_zone', $id);
        admin_flash_set('shipping_zones', 'success', 'Zone mise à jour.');
        redirect('admin/shipping_zones.php');
      }

      if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ID invalide.');

        $stmt = $pdo->prepare('DELETE FROM shipping_zones WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));

        AdminAuditService::log($pdo, $adminId, 'owner_deleted_shipping_zone', 'shipping_zone', $id);
        admin_flash_set('shipping_zones', 'success', 'Zone supprimée.');
        redirect('admin/shipping_zones.php');
      }

      throw new RuntimeException('Action invalide.');
    } catch (Throwable $e) {
      $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Erreur.';
    }
  }
}

$zones = array();
if (!$errors) {
  try {
    $stmt = $pdo->query('SELECT * FROM shipping_zones ORDER BY sort_order ASC, city ASC, zone ASC, id ASC');
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  } catch (Throwable $e) {
    $errors[] = 'Impossible de charger les zones.';
  }
}

require_once __DIR__ . '/_layout_header.php';
?>

<style>
  .admin-shipping-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-shipping-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-shipping-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-shipping-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-shipping-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-shipping-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-shipping-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-shipping-meta__chip {
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
  .admin-shipping-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-shipping-form {
    display: grid;
    gap: 16px;
  }
  .admin-shipping-form__grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-shipping-form__grid--compact {
    grid-template-columns: 1.2fr 0.8fr;
  }
  .admin-shipping-toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border: 1px solid var(--admin-border);
    border-radius: 16px;
    background: var(--admin-surface-soft);
    color: var(--admin-text);
    font-weight: 700;
  }
  .admin-shipping-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
    accent-color: var(--admin-accent);
  }
  .admin-shipping-form__actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  .admin-shipping-table-panel {
    overflow: hidden;
  }
  .admin-shipping-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-shipping-table-shell .admin-table {
    min-width: 1260px;
  }
  .admin-shipping-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-shipping-table-shell td,
  .admin-shipping-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: middle;
  }
  .admin-shipping-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-shipping-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-shipping-location {
    display: grid;
    gap: 4px;
  }
  .admin-shipping-location strong {
    color: var(--admin-ink);
  }
  .admin-shipping-location__meta,
  .admin-shipping-fee,
  .admin-shipping-order {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
  }
  .admin-shipping-inline-form {
    display: grid;
    gap: 12px;
  }
  .admin-shipping-inline-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: 1.2fr 1.2fr 0.9fr 0.7fr auto;
    align-items: end;
  }
  .admin-shipping-inline-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 46px;
    padding: 0 4px;
    color: var(--admin-text);
    font-weight: 600;
    white-space: nowrap;
  }
  .admin-shipping-inline-toggle input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--admin-accent);
  }
  .admin-shipping-inline-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
  }
  .admin-shipping-inline-actions form,
  .admin-shipping-inline-form form {
    margin: 0;
  }
  .admin-shipping-empty {
    padding: 22px;
  }
  .admin-shipping-mobile-list {
    display: grid;
    gap: 14px;
  }
  .admin-shipping-mobile-card {
    display: grid;
    gap: 14px;
  }
  .admin-shipping-mobile-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }
  .admin-shipping-mobile-card__grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-shipping-mobile-card__label {
    display: block;
    margin-bottom: 4px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-shipping-mobile-card__value {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-shipping-mobile-card__value--muted {
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-shipping-mobile-edit {
    display: grid;
    gap: 12px;
  }
  .admin-shipping-mobile-edit .admin-field {
    min-width: 0;
  }
  .admin-shipping-mobile-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-shipping-mobile-toggle input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin: 0;
    accent-color: var(--admin-accent);
  }
  .admin-shipping-mobile-actions {
    display: grid;
    gap: 8px;
  }
  .admin-shipping-mobile-actions form {
    margin: 0;
  }
  .admin-shipping-mobile-actions .admin-btn {
    width: 100%;
  }
  .admin-shipping-page .admin-btn--primary,
  .admin-shipping-page .admin-btn--secondary,
  .admin-shipping-page .admin-btn--ghost {
    background-image: none;
  }
  .admin-shipping-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 1024px) {
    .admin-shipping-form__grid,
    .admin-shipping-form__grid--compact,
    .admin-shipping-inline-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 820px) {
    .admin-shipping-page .admin-page-header {
      padding: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-shipping-meta__chip {
      width: 100%;
      justify-content: space-between;
    }
  }
  @media (max-width: 430px) {
    .admin-shipping-mobile-card__header {
      flex-direction: column;
    }
    .admin-shipping-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
    .admin-shipping-form__actions .admin-btn,
    .admin-page-header__actions .admin-btn {
      flex: 1 1 100%;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-shipping-reveal'));
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
      <div class="admin-shipping-page">
        <div class="admin-page-header admin-shipping-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Logistique locale</p>
            <h1 class="admin-page-header__title">Zones de livraison</h1>
            <p class="admin-page-header__subtitle">Organisez les frais par ville et par zone depuis une interface plus claire, plus dense et plus cohérente avec le reste de l’admin.</p>
            <div class="admin-shipping-meta" aria-label="Indicateurs livraison">
              <span class="admin-shipping-meta__chip"><strong><?php echo e((string) count($zones)); ?></strong> zone(s) listée(s)</span>
              <span class="admin-shipping-meta__chip"><strong><?php echo e((string) count($errors)); ?></strong> alerte(s) active(s)</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour dashboard
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-shipping-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-shipping-reveal is-visible" role="alert">
            <strong>Erreur :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!$errors): ?>
          <div class="admin-panel admin-panel--padded admin-shipping-reveal" aria-label="Ajouter une zone">
            <form method="post" class="admin-shipping-form" novalidate>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="create">

              <div class="admin-shipping-form__grid">
                <div>
                  <label class="admin-field-label" for="city">Ville *</label>
                  <input id="city" name="city" class="admin-field" placeholder="Bamako" required>
                </div>
                <div>
                  <label class="admin-field-label" for="zone">Zone (optionnel)</label>
                  <input id="zone" name="zone" class="admin-field" placeholder="ACI 2000">
                </div>
              </div>

              <div class="admin-shipping-form__grid admin-shipping-form__grid--compact">
                <div>
                  <label class="admin-field-label" for="fee">Frais (FCFA) *</label>
                  <input id="fee" name="fee" class="admin-field" value="0" required>
                </div>
                <div>
                  <label class="admin-field-label" for="sort_order">Ordre</label>
                  <input id="sort_order" name="sort_order" type="number" class="admin-field" value="0">
                </div>
              </div>

              <label class="admin-shipping-toggle">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
              </label>

              <div class="admin-shipping-form__actions">
                <button class="btn admin-btn admin-btn--primary" type="submit">Ajouter</button>
              </div>
            </form>
          </div>

          <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-shipping-table-panel admin-shipping-table-shell admin-shipping-reveal is-visible" aria-label="Liste zones">
            <?php if (!$zones): ?>
              <div class="admin-empty-panel admin-shipping-empty">
                <p class="admin-empty-panel__title">Aucune zone.</p>
                <p class="admin-empty-panel__text">Les zones de livraison apparaîtront ici dès qu’elles seront ajoutées.</p>
              </div>
            <?php else: ?>
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Localisation</th>
                    <th>Frais</th>
                    <th>Statut</th>
                    <th>Ordre</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($zones as $z): ?>
                    <?php
                      $id = (int) ($z['id'] ?? 0);
                      $city = (string) ($z['city'] ?? '');
                      $zone = (string) ($z['zone'] ?? '');
                      $fee = (string) ($z['fee'] ?? '0');
                      $active = (int) ($z['is_active'] ?? 1);
                      $sort = (int) ($z['sort_order'] ?? 0);
                    ?>
                    <tr>
                      <td>
                        <div class="admin-shipping-location">
                          <strong><?php echo e($city); ?></strong>
                          <span class="admin-shipping-location__meta"><?php echo e($zone !== '' ? $zone : '—'); ?></span>
                        </div>
                      </td>
                      <td><span class="admin-shipping-fee"><?php echo e($fee); ?> FCFA</span></td>
                      <td>
                        <span class="admin-status-pill <?php echo $active ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                          <?php echo $active ? 'Active' : 'Inactive'; ?>
                        </span>
                      </td>
                      <td><span class="admin-shipping-order"><?php echo e((string) $sort); ?></span></td>
                      <td>
                        <div class="admin-shipping-inline-form">
                          <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                            <div class="admin-shipping-inline-grid">
                              <input type="text" name="city" class="admin-field" value="<?php echo e($city); ?>" aria-label="Ville">
                              <input type="text" name="zone" class="admin-field" value="<?php echo e($zone); ?>" aria-label="Zone">
                              <input type="text" name="fee" class="admin-field" value="<?php echo e($fee); ?>" aria-label="Frais">
                              <input type="number" name="sort_order" class="admin-field" value="<?php echo (int) $sort; ?>" aria-label="Ordre">
                              <label class="admin-shipping-inline-toggle">
                                <input type="checkbox" name="is_active" value="1" <?php echo $active ? 'checked' : ''; ?>>
                                Actif
                              </label>
                            </div>
                            <div class="admin-shipping-inline-actions">
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit">Enregistrer</button>
                            </div>
                          </form>
                          <form method="post" onsubmit="return confirm('Supprimer cette zone ?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                            <button class="btn admin-btn admin-btn--ghost admin-btn--sm" type="submit">Supprimer</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

          <div class="admin-mobile-only admin-shipping-reveal is-visible" aria-label="Liste zones mobile">
            <div class="admin-mobile-cards admin-shipping-mobile-list">
              <?php if (!$zones): ?>
                <div class="admin-empty-panel admin-shipping-empty">
                  <p class="admin-empty-panel__title">Aucune zone.</p>
                  <p class="admin-empty-panel__text">Les zones de livraison apparaîtront ici dès qu’elles seront ajoutées.</p>
                </div>
              <?php endif; ?>

              <?php foreach ($zones as $z): ?>
                <?php
                  $id = (int) ($z['id'] ?? 0);
                  $city = (string) ($z['city'] ?? '');
                  $zone = (string) ($z['zone'] ?? '');
                  $fee = (string) ($z['fee'] ?? '0');
                  $active = (int) ($z['is_active'] ?? 1);
                  $sort = (int) ($z['sort_order'] ?? 0);
                ?>
                <article class="admin-mobile-card admin-shipping-mobile-card">
                  <div class="admin-shipping-mobile-card__header">
                    <div>
                      <h2 class="admin-mobile-card__title"><?php echo e($city); ?></h2>
                      <div class="admin-mobile-card__meta"><?php echo e($zone !== '' ? $zone : '—'); ?></div>
                    </div>
                    <span class="admin-status-pill <?php echo $active ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                      <?php echo $active ? 'Active' : 'Inactive'; ?>
                    </span>
                  </div>

                  <div class="admin-shipping-mobile-card__grid">
                    <div>
                      <span class="admin-shipping-mobile-card__label">Frais</span>
                      <div class="admin-shipping-mobile-card__value"><?php echo e($fee); ?> FCFA</div>
                    </div>
                    <div>
                      <span class="admin-shipping-mobile-card__label">Ordre</span>
                      <div class="admin-shipping-mobile-card__value admin-shipping-mobile-card__value--muted"><?php echo e((string) $sort); ?></div>
                    </div>
                  </div>

                  <form method="post" class="admin-shipping-mobile-edit">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                    <input type="text" name="city" class="admin-field" value="<?php echo e($city); ?>" aria-label="Ville">
                    <input type="text" name="zone" class="admin-field" value="<?php echo e($zone); ?>" aria-label="Zone">
                    <input type="text" name="fee" class="admin-field" value="<?php echo e($fee); ?>" aria-label="Frais">
                    <input type="number" name="sort_order" class="admin-field" value="<?php echo (int) $sort; ?>" aria-label="Ordre">
                    <label class="admin-shipping-mobile-toggle">
                      <input type="checkbox" name="is_active" value="1" <?php echo $active ? 'checked' : ''; ?>>
                      Actif
                    </label>
                    <div class="admin-shipping-mobile-actions">
                      <button class="btn admin-btn admin-btn--secondary" type="submit">Enregistrer</button>
                    </div>
                  </form>

                  <div class="admin-shipping-mobile-actions">
                    <form method="post" onsubmit="return confirm('Supprimer cette zone ?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
                      <button class="btn admin-btn admin-btn--ghost" type="submit">Supprimer</button>
                    </form>
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
