<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';

$page_title = 'Admin - Journal d’activité';
$page_css = 'pages/admin-products.css';
$page_js = '';

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 30;

$flash = admin_flash_get('audit');
$db_error = '';
$rows = array();
$total = 0;
$lastPage = 1;

function audit_has_table(PDO $pdo): bool
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, 'admin_audit_logs') !== array();
  }
  try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_audit_logs'");
    return (bool) ($stmt->fetchColumn() ?: false);
  } catch (Throwable $e) {
    return false;
  }
}

function audit_decode_metadata($raw): array
{
  if (!is_string($raw) || trim($raw) === '') {
    return array();
  }
  try {
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
  } catch (Throwable $e) {
    return array();
  }
}

function audit_actor_role_label(string $role): string
{
  $map = array(
    'owner' => 'Propriétaire',
    'partner' => 'Gestionnaire terrain',
  );
  return $map[$role] ?? $role;
}

function audit_status_label(string $status): string
{
  $status = strtolower(trim($status));
  $map = array(
    'nouveau' => 'Nouveau',
    'pending' => 'En attente',
    'confirme' => 'Confirmée',
    'confirmed' => 'Confirmée',
    'en_preparation' => 'En préparation',
    'processing' => 'En préparation',
    'en_livraison' => 'En livraison',
    'shipped' => 'En livraison',
    'livre' => 'Livrée',
    'livree' => 'Livrée',
    'published' => 'Publié',
    'draft' => 'Brouillon',
    'annulee' => 'Annulée',
    'cancelled' => 'Annulée',
  );
  return $map[$status] ?? $status;
}

function audit_action_label(string $action): string
{
  $map = array(
    'order_status_changed' => 'Statut commande modifié',
    'order_note_added' => 'Note commande ajoutée',
    'order_bulk_status_changed' => 'Statuts commandes modifiés en lot',
    'inventory_adjusted' => 'Stock ajusté',
    'product_created' => 'Produit créé',
    'product_updated' => 'Produit modifié',
    'product_status_changed' => 'Statut produit modifié',
  );
  return $map[$action] ?? $action;
}

function audit_action_pill_class(string $action): string
{
  $action = strtolower(trim($action));
  if ($action === 'inventory_adjusted') return 'admin-status-pill admin-status-pill--warning';
  if ($action === 'product_created' || $action === 'product_updated' || $action === 'product_status_changed') return 'admin-status-pill admin-status-pill--success';
  if ($action === 'order_status_changed' || $action === 'order_bulk_status_changed' || $action === 'order_note_added') return 'admin-status-pill admin-status-pill--info';
  return 'admin-status-pill admin-status-pill--neutral';
}

function audit_entity_badge_label(string $entityType, string $action, array $metadata): string
{
  $entityType = strtolower(trim($entityType));
  $action = strtolower(trim($action));

  if ($entityType === 'order') return 'Commande';
  if ($entityType === 'product') {
    if ($action === 'inventory_adjusted' || array_key_exists('old_stock', $metadata) || array_key_exists('new_stock', $metadata)) {
      return 'Stock';
    }
    return 'Produit';
  }
  if ($action === 'inventory_adjusted') return 'Stock';

  return $entityType !== '' ? ucfirst($entityType) : 'Audit';
}

function audit_entity_pill_class(string $entityType, string $action, array $metadata): string
{
  $label = audit_entity_badge_label($entityType, $action, $metadata);
  if ($label === 'Commande') return 'admin-status-pill admin-status-pill--info';
  if ($label === 'Stock') return 'admin-status-pill admin-status-pill--warning';
  if ($label === 'Produit') return 'admin-status-pill admin-status-pill--success';
  return 'admin-status-pill admin-status-pill--neutral';
}

function audit_details_lines(array $metadata): array
{
  $lines = array();

  $actorRole = trim((string) ($metadata['actor_role'] ?? ''));
  if ($actorRole !== '') {
    $lines[] = 'Rôle: ' . audit_actor_role_label($actorRole);
  }

  $oldStatus = trim((string) ($metadata['old_status'] ?? ''));
  $newStatus = trim((string) ($metadata['new_status'] ?? ''));
  if ($oldStatus !== '' || $newStatus !== '') {
    $parts = array();
    if ($oldStatus !== '') $parts[] = audit_status_label($oldStatus);
    if ($newStatus !== '') $parts[] = audit_status_label($newStatus);
    $lines[] = 'Statut: ' . implode(' -> ', $parts);
  }

  $hasOldStock = array_key_exists('old_stock', $metadata) && $metadata['old_stock'] !== null && $metadata['old_stock'] !== '';
  $hasNewStock = array_key_exists('new_stock', $metadata) && $metadata['new_stock'] !== null && $metadata['new_stock'] !== '';
  if ($hasOldStock || $hasNewStock) {
    $parts = array();
    if ($hasOldStock) $parts[] = (string) $metadata['old_stock'];
    if ($hasNewStock) $parts[] = (string) $metadata['new_stock'];
    $lines[] = 'Stock: ' . implode(' -> ', $parts);
  }

  $bulkCount = isset($metadata['bulk_count']) ? (int) $metadata['bulk_count'] : 0;
  if ($bulkCount > 0) {
    $lines[] = 'Nombre d’éléments: ' . (string) $bulkCount;
  }

  return $lines;
}

try {
  $pdo = db();
  if (!audit_has_table($pdo)) {
    throw new RuntimeException('missing_table');
  }

  $auditCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'admin_audit_logs') : array();
  $hasMetadata = in_array('metadata', $auditCols, true);

  $where = array();
  $params = array();

  if ($action !== '') {
    $where[] = 'l.action = :action';
    $params['action'] = $action;
  }

  if ($q !== '') {
    $where[] = '(l.action LIKE :q1 OR l.entity_type LIKE :q2 OR CAST(l.entity_id AS CHAR) LIKE :q3 OR l.ip LIKE :q4 OR u.email LIKE :q5)';
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
    $params['q5'] = $like;
  }

  $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

  $countSql = 'SELECT COUNT(*) FROM admin_audit_logs l LEFT JOIN users u ON u.id = l.admin_id' . $sqlWhere;
  $stmtCnt = $pdo->prepare($countSql);
  $stmtCnt->execute($params);
  $total = (int) ($stmtCnt->fetchColumn() ?: 0);

  $lastPage = max(1, (int) ceil($total / $perPage));
  $page = min($page, $lastPage);
  $offset = ($page - 1) * $perPage;

  $listSql = 'SELECT l.id, l.admin_id, u.email AS admin_email, l.action, l.entity_type, l.entity_id, l.ip, l.user_agent, l.created_at'
              . ($hasMetadata ? ', l.metadata' : '')
              . ' FROM admin_audit_logs l
                  LEFT JOIN users u ON u.id = l.admin_id'
              . $sqlWhere
              . ' ORDER BY l.id DESC LIMIT :limit OFFSET :offset';

  $stmt = $pdo->prepare($listSql);
  foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
} catch (Throwable $e) {
  if ($e instanceof RuntimeException && $e->getMessage() === 'missing_table') {
    $db_error = 'Table audit manquante. Exécutez: database/patch_admin_security.sql';
  } else {
    $db_error = 'Impossible de charger le journal d’activité (base de données).';
  }
  $rows = array();
  $total = 0;
  $lastPage = 1;
  $page = 1;
}

$hasActiveFilters = ($action !== '' || $q !== '');
$filtersCount = (int) ($action !== '') + (int) ($q !== '');
$visibleCount = count($rows);

require_once __DIR__ . '/../_layout_header.php';
?>

<style>
  .admin-audit-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-audit-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-audit-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-audit-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-audit-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-audit-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-audit-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-audit-meta__chip {
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
  .admin-audit-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-audit-toolbar {
    display: grid;
    gap: 14px;
  }
  .admin-audit-toolbar .admin-filterbar__group {
    align-items: stretch;
  }
  .admin-audit-toolbar .admin-filterbar__group--grow {
    flex: 1 1 640px;
  }
  .admin-audit-toolbar .admin-field {
    min-width: min(240px, 100%);
  }
  .admin-audit-toolbar .admin-help {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-audit-toolbar__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }
  .admin-audit-toolbar__actions .admin-btn {
    white-space: nowrap;
  }
  .admin-audit-table-panel {
    overflow: hidden;
  }
  .admin-audit-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-audit-table-shell .admin-table {
    min-width: 1120px;
  }
  .admin-audit-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-audit-table-shell td,
  .admin-audit-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: top;
  }
  .admin-audit-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-audit-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-audit-id {
    color: var(--admin-text-muted);
    font-weight: 700;
    white-space: nowrap;
  }
  .admin-audit-date {
    color: var(--admin-text-muted);
    white-space: nowrap;
  }
  .admin-audit-admin {
    display: grid;
    gap: 4px;
    min-width: 0;
  }
  .admin-audit-admin strong,
  .admin-audit-admin span {
    overflow-wrap: anywhere;
  }
  .admin-audit-action {
    display: grid;
    gap: 8px;
    min-width: 0;
  }
  .admin-audit-action__code {
    color: var(--admin-text-muted);
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 0.78rem;
    overflow-wrap: anywhere;
  }
  .admin-audit-entity {
    display: grid;
    gap: 8px;
    min-width: 0;
  }
  .admin-audit-entity__meta {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-audit-details {
    display: grid;
    gap: 8px;
    min-width: 0;
  }
  .admin-audit-details__item {
    padding: 10px 12px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 12px;
    background: #fbfcfb;
    color: var(--admin-text);
    line-height: 1.45;
    overflow-wrap: anywhere;
  }
  .admin-audit-empty {
    padding: 24px;
  }
  .admin-audit-empty .admin-empty-panel__actions {
    margin-top: 16px;
  }
  .admin-audit-mobile-list {
    display: grid;
    gap: 14px;
  }
  .admin-audit-mobile-card {
    display: grid;
    gap: 14px;
    border: 1px solid var(--admin-border);
    border-radius: 18px;
    background: var(--admin-surface);
    box-shadow: var(--admin-shadow-sm);
    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
  }
  .admin-audit-mobile-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px rgba(18, 52, 36, 0.08);
    border-color: rgba(31, 122, 79, 0.16);
  }
  .admin-audit-mobile-card__header {
    display: flex;
    gap: 12px;
    justify-content: space-between;
    align-items: flex-start;
  }
  .admin-audit-mobile-card__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1rem;
    line-height: 1.35;
  }
  .admin-audit-mobile-card__meta {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-audit-mobile-card__badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
  }
  .admin-audit-mobile-card__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .admin-audit-mobile-card__field {
    min-width: 0;
    padding: 12px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 14px;
    background: #fbfcfb;
  }
  .admin-audit-mobile-card__label {
    display: block;
    margin-bottom: 6px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-audit-mobile-card__value {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-audit-mobile-card__value--muted {
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-audit-mobile-card__section {
    display: grid;
    gap: 10px;
  }
  .admin-audit-mobile-card__actions .admin-btn {
    width: 100%;
  }
  .admin-audit-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: center;
  }
  .admin-audit-page .admin-btn--primary,
  .admin-audit-page .admin-btn--secondary,
  .admin-audit-page .admin-btn--ghost {
    background-image: none;
  }
  .admin-audit-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 1024px) {
    .admin-audit-toolbar .admin-field {
      min-width: min(190px, 100%);
    }
  }
  @media (max-width: 820px) {
    .admin-audit-page .admin-page-header {
      padding: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-audit-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-audit-meta {
      gap: 8px;
    }
    .admin-audit-meta__chip {
      width: 100%;
      justify-content: center;
    }
    .admin-audit-mobile-card__header {
      flex-direction: column;
    }
    .admin-audit-mobile-card__badges {
      justify-content: flex-start;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = document.querySelectorAll('.admin-audit-reveal');
    if (!revealNodes.length) return;

    if ('IntersectionObserver' in window) {
      var revealObserver = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12 });

      revealNodes.forEach(function (node) {
        revealObserver.observe(node);
      });
    } else {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
      });
    }
  });
</script>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-audit-page">
        <div class="admin-page-header admin-audit-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Traçabilité admin</p>
            <h1 class="admin-page-header__title">Journal d’activité</h1>
            <p class="admin-page-header__subtitle">Consultez les actions d’administration dans une vue plus claire, plus dense et cohérente avec le design premium déjà en place.</p>
            <div class="admin-audit-meta" aria-label="Indicateurs du journal d’activité">
              <span class="admin-audit-meta__chip"><strong><?php echo e((string) $total); ?></strong> entrée(s)</span>
              <span class="admin-audit-meta__chip"><strong><?php echo e((string) $visibleCount); ?></strong> sur cette page</span>
              <span class="admin-audit-meta__chip"><strong><?php echo e((string) $filtersCount); ?></strong> filtre(s) actif(s)</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-audit-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($db_error): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-audit-reveal is-visible" role="alert">
            <strong><?php echo e($db_error); ?></strong>
          </div>
        <?php else: ?>
          <div class="admin-panel admin-panel--padded admin-audit-toolbar admin-audit-reveal" aria-label="Filtres du journal d’activité">
            <div class="admin-filterbar">
              <form method="get" action="" class="admin-filterbar__group admin-filterbar__group--grow" role="search">
                <label class="sr-only" for="action">Action</label>
                <input
                  class="admin-field"
                  id="action"
                  name="action"
                  type="text"
                  value="<?php echo e($action); ?>"
                  placeholder="Action exacte, ex: order_status_changed"
                >

                <label class="sr-only" for="q">Recherche</label>
                <input
                  class="admin-field"
                  id="q"
                  name="q"
                  type="text"
                  value="<?php echo e($q); ?>"
                  placeholder="Rechercher email, IP, entité ou identifiant"
                >

                <div class="admin-audit-toolbar__actions">
                  <button class="btn admin-btn admin-btn--primary" type="submit">
                    <i class="fas fa-search" aria-hidden="true"></i> Filtrer
                  </button>
                  <?php if ($hasActiveFilters): ?>
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/audit/index.php')); ?>">Réinitialiser</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>
            <div class="admin-help"><?php echo e((string) $total); ?> entrée(s)</div>
          </div>

          <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-audit-table-panel admin-audit-table-shell admin-audit-reveal is-visible" aria-label="Journal d’activité">
            <?php if (!$rows): ?>
              <div class="admin-empty-panel admin-audit-empty">
                <?php if ($hasActiveFilters): ?>
                  <p class="admin-empty-panel__title">Aucune entrée ne correspond aux filtres.</p>
                  <p class="admin-empty-panel__text">Essayez une autre recherche ou réinitialisez les filtres pour recharger le journal.</p>
                  <div class="admin-empty-panel__actions">
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/audit/index.php')); ?>">Réinitialiser</a>
                  </div>
                <?php else: ?>
                  <p class="admin-empty-panel__title">Aucune entrée.</p>
                  <p class="admin-empty-panel__text">Les événements d’administration apparaîtront ici dès qu’ils seront enregistrés.</p>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Entité</th>
                    <th>Détails</th>
                    <th>IP</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <?php
                      $id = (int) ($r['id'] ?? 0);
                      $created = (string) ($r['created_at'] ?? '');
                      $adminEmail = (string) ($r['admin_email'] ?? '');
                      $adminId = (int) ($r['admin_id'] ?? 0);
                      $act = (string) ($r['action'] ?? '');
                      $etype = (string) ($r['entity_type'] ?? '');
                      $eid = (string) ($r['entity_id'] ?? '');
                      $metadata = audit_decode_metadata((string) ($r['metadata'] ?? ''));
                      $details = audit_details_lines($metadata);
                      $entityBadge = audit_entity_badge_label($etype, $act, $metadata);
                      $ip = (string) ($r['ip'] ?? '');
                    ?>
                    <tr>
                      <td><span class="admin-audit-id">#<?php echo e((string) $id); ?></span></td>
                      <td><span class="admin-audit-date"><?php echo e($created); ?></span></td>
                      <td>
                        <div class="admin-audit-admin">
                          <strong>
                            <?php if ($adminEmail !== ''): ?>
                              <?php echo e($adminEmail); ?>
                            <?php elseif ($adminId > 0): ?>
                              #<?php echo e((string) $adminId); ?>
                            <?php else: ?>
                              —
                            <?php endif; ?>
                          </strong>
                          <span class="admin-help"><?php echo $adminId > 0 ? ('Admin #' . e((string) $adminId)) : 'Utilisateur système'; ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-audit-action">
                          <span class="<?php echo e(audit_action_pill_class($act)); ?>"><?php echo e(audit_action_label($act)); ?></span>
                          <span class="admin-audit-action__code"><?php echo e($act); ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-audit-entity">
                          <span class="<?php echo e(audit_entity_pill_class($etype, $act, $metadata)); ?>"><?php echo e($entityBadge); ?></span>
                          <span class="admin-audit-entity__meta">
                            <?php if ($etype !== '' || $eid !== ''): ?>
                              <?php echo e($etype !== '' ? $etype : 'audit'); ?><?php echo $eid !== '' ? (' #' . e($eid)) : ''; ?>
                            <?php else: ?>
                              —
                            <?php endif; ?>
                          </span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-audit-details">
                          <?php if ($details): ?>
                            <?php foreach ($details as $detail): ?>
                              <div class="admin-audit-details__item"><?php echo e($detail); ?></div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="admin-audit-details__item">Aucun détail complémentaire.</div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td><span class="admin-audit-date"><?php echo e($ip !== '' ? $ip : '—'); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>

          <div class="admin-mobile-only admin-audit-reveal is-visible" aria-label="Journal d’activité mobile">
            <div class="admin-mobile-cards admin-audit-mobile-list">
              <?php if (!$rows): ?>
                <div class="admin-mobile-card admin-audit-mobile-card">
                  <div class="admin-empty-panel admin-audit-empty">
                    <?php if ($hasActiveFilters): ?>
                      <p class="admin-empty-panel__title">Aucune entrée ne correspond aux filtres.</p>
                      <p class="admin-empty-panel__text">Essayez une autre recherche ou réinitialisez les filtres pour recharger le journal.</p>
                      <div class="admin-empty-panel__actions">
                        <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/audit/index.php')); ?>">Réinitialiser</a>
                      </div>
                    <?php else: ?>
                      <p class="admin-empty-panel__title">Aucune entrée.</p>
                      <p class="admin-empty-panel__text">Les événements d’administration apparaîtront ici dès qu’ils seront enregistrés.</p>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php foreach ($rows as $r): ?>
                <?php
                  $id = (int) ($r['id'] ?? 0);
                  $created = (string) ($r['created_at'] ?? '');
                  $adminEmail = (string) ($r['admin_email'] ?? '');
                  $adminId = (int) ($r['admin_id'] ?? 0);
                  $act = (string) ($r['action'] ?? '');
                  $etype = (string) ($r['entity_type'] ?? '');
                  $eid = (string) ($r['entity_id'] ?? '');
                  $metadata = audit_decode_metadata((string) ($r['metadata'] ?? ''));
                  $details = audit_details_lines($metadata);
                  $entityBadge = audit_entity_badge_label($etype, $act, $metadata);
                  $ip = (string) ($r['ip'] ?? '');
                ?>
                <article class="admin-mobile-card admin-audit-mobile-card">
                  <div class="admin-audit-mobile-card__header">
                    <div>
                      <h2 class="admin-audit-mobile-card__title"><?php echo e(audit_action_label($act)); ?></h2>
                      <div class="admin-audit-mobile-card__meta"><?php echo e($created); ?></div>
                    </div>
                    <div class="admin-audit-mobile-card__badges">
                      <span class="<?php echo e(audit_entity_pill_class($etype, $act, $metadata)); ?>"><?php echo e($entityBadge); ?></span>
                      <span class="admin-help">#<?php echo e((string) $id); ?></span>
                    </div>
                  </div>

                  <div class="admin-audit-mobile-card__grid">
                    <div class="admin-audit-mobile-card__field">
                      <span class="admin-audit-mobile-card__label">Admin</span>
                      <div class="admin-audit-mobile-card__value">
                        <?php if ($adminEmail !== ''): ?>
                          <?php echo e($adminEmail); ?>
                        <?php elseif ($adminId > 0): ?>
                          #<?php echo e((string) $adminId); ?>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="admin-audit-mobile-card__field">
                      <span class="admin-audit-mobile-card__label">Entité</span>
                      <div class="admin-audit-mobile-card__value admin-audit-mobile-card__value--muted">
                        <?php if ($etype !== '' || $eid !== ''): ?>
                          <?php echo e($etype !== '' ? $etype : 'audit'); ?><?php echo $eid !== '' ? (' #' . e($eid)) : ''; ?>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>

                  <div class="admin-audit-mobile-card__section">
                    <span class="admin-audit-mobile-card__label">Détails</span>
                    <div class="admin-audit-details">
                      <?php if ($details): ?>
                        <?php foreach ($details as $detail): ?>
                          <div class="admin-audit-details__item"><?php echo e($detail); ?></div>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="admin-audit-details__item">Aucun détail complémentaire.</div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="admin-audit-mobile-card__section">
                    <span class="admin-audit-mobile-card__label">IP</span>
                    <div class="admin-audit-mobile-card__value admin-audit-mobile-card__value--muted"><?php echo e($ip !== '' ? $ip : '—'); ?></div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($lastPage > 1): ?>
            <nav class="admin-pagination admin-audit-pagination admin-audit-reveal is-visible" aria-label="Pagination du journal d’activité">
              <?php
                $qsBase = array();
                if ($action !== '') $qsBase['action'] = $action;
                if ($q !== '') $qsBase['q'] = $q;
              ?>

              <?php if ($page > 1): ?>
                <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page - 1))); ?>
                <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/audit/index.php?' . $qs)); ?>">Précédent</a>
              <?php endif; ?>

              <span class="admin-help">Page <?php echo e((string) $page); ?> / <?php echo e((string) $lastPage); ?></span>

              <?php if ($page < $lastPage): ?>
                <?php $qs = http_build_query(array_merge($qsBase, array('page' => $page + 1))); ?>
                <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/audit/index.php?' . $qs)); ?>">Suivant</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
