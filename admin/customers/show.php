<?php
declare(strict_types=1);

/* Profil client admin */

require_once __DIR__ . '/../_auth.php';
requireRole('owner');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/models/CustomerModel.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$page_title = 'Admin - Client';
$page_css = 'pages/admin-products.css';
$page_js = '';

$flash = admin_flash_get('customer_show');
$errors = array();
$customer = null;
$orders = array();

function customer_history_total_expr(array $fields): string
{
  if (in_array('total_amount', $fields, true)) return 'COALESCE(total_amount, 0)';
  if (in_array('grand_total', $fields, true)) return 'COALESCE(grand_total, 0)';
  if (in_array('total_fcfa', $fields, true)) return 'COALESCE(total_fcfa, 0)';
  return '0';
}

function customer_history_phone_digits(string $raw): string
{
  $digits = preg_replace('/\D+/', '', trim($raw));
  return is_string($digits) ? $digits : '';
}

function customer_history_phone_expr(string $column): string
{
  return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE($column, '')), ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', ''), '/', '')";
}

/**
 * @param array<int, array<string,mixed>> $rows
 * @param array<int, bool> $seen
 * @return array<int, array<string,mixed>>
 */
function customer_history_merge_rows(array $rows, array &$seen, array $extraRows, int $limit = 50): array
{
  foreach ($extraRows as $row) {
    $orderId = (int) ($row['id'] ?? 0);
    if ($orderId <= 0 || isset($seen[$orderId])) {
      continue;
    }
    $rows[] = $row;
    $seen[$orderId] = true;
    if (count($rows) >= $limit) {
      break;
    }
  }

  return $rows;
}

function customer_history_orders(PDO $pdo, int $customerId, string $phoneRaw, string $emailRaw): array
{
  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'orders') : array();
  if ($fields === array()) return array();

  $totalExpr = customer_history_total_expr($fields);
  $selectSql = 'SELECT id, order_number, status, ' . $totalExpr . ' AS total_amount, created_at FROM orders';
  $rows = array();
  $seen = array();
  $hasCustomerProfileId = in_array('customer_profile_id', $fields, true);

  if ($hasCustomerProfileId && $customerId > 0) {
    $stmt = $pdo->prepare(
      $selectSql . '
       WHERE customer_profile_id = :cid
       ORDER BY created_at DESC
       LIMIT 50'
    );
    $stmt->execute(array('cid' => $customerId));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    foreach ($rows as $row) {
      $orderId = (int) ($row['id'] ?? 0);
      if ($orderId > 0) {
        $seen[$orderId] = true;
      }
    }
  }

  $phoneDigits = customer_history_phone_digits($phoneRaw);
  if (strlen($phoneDigits) >= 8 && count($rows) < 50) {
    $phoneColumn = in_array('customer_phone', $fields, true)
      ? 'customer_phone'
      : (in_array('phone', $fields, true) ? 'phone' : '');
    if ($phoneColumn !== '') {
      $where = customer_history_phone_expr($phoneColumn) . ' = :phone_digits';
      if ($hasCustomerProfileId) {
        $where .= ' AND (customer_profile_id IS NULL OR customer_profile_id = 0';
        if ($customerId > 0) {
          $where .= ' OR customer_profile_id = :cid';
        }
        $where .= ')';
      }
      $stmt = $pdo->prepare(
        $selectSql . '
         WHERE ' . $where . '
         ORDER BY created_at DESC
         LIMIT 50'
      );
      $params = array('phone_digits' => $phoneDigits);
      if ($hasCustomerProfileId && $customerId > 0) {
        $params['cid'] = $customerId;
      }
      $stmt->execute($params);
      $rows = customer_history_merge_rows($rows, $seen, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array());
    }
  }

  $email = strtolower(trim($emailRaw));
  if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && count($rows) < 50) {
    $emailColumn = in_array('customer_email', $fields, true)
      ? 'customer_email'
      : (in_array('email', $fields, true) ? 'email' : '');
    if ($emailColumn !== '') {
      $where = "LOWER(TRIM(COALESCE($emailColumn, ''))) = :email";
      if ($hasCustomerProfileId) {
        $where .= ' AND (customer_profile_id IS NULL OR customer_profile_id = 0';
        if ($customerId > 0) {
          $where .= ' OR customer_profile_id = :cid';
        }
        $where .= ')';
      }
      $stmt = $pdo->prepare(
        $selectSql . '
         WHERE ' . $where . '
         ORDER BY created_at DESC
         LIMIT 50'
      );
      $params = array('email' => $email);
      if ($hasCustomerProfileId && $customerId > 0) {
        $params['cid'] = $customerId;
      }
      $stmt->execute($params);
      $rows = customer_history_merge_rows($rows, $seen, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array());
    }
  }

  return $rows;
}

try {
  $pdo = db();
  $model = new CustomerModel($pdo);
  if (!$model->exists()) {
    throw new RuntimeException('missing_table');
  }

  $customer = $model->findById($id);
  if (!$customer) {
    http_response_code(404);
  }

  if ($customer && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
      $errors[] = 'Session expirée. Veuillez réessayer.';
    } else {
      $action = (string) ($_POST['action'] ?? '');
      if ($action === 'toggle_blacklist') {
        $next = ((int) ($customer['is_blacklisted'] ?? 0)) ? false : true;
        $ok = $model->setBlacklisted($id, $next);
        if ($ok) {
          $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
          AdminAuditService::log($pdo, $adminId, 'owner_toggled_customer_blacklist', 'customer', (int) $id);

          admin_flash_set('customer_show', 'success', $next ? 'Client blacklisté.' : 'Client retiré de la blacklist.');
          redirect('admin/customers/show.php?id=' . $id);
        }
        $errors[] = 'Impossible de mettre à jour la blacklist.';
      }
    }
  }

  if ($customer) {
    $orders = customer_history_orders(
      $pdo,
      (int) $id,
      (string) ($customer['phone'] ?? ''),
      (string) ($customer['email'] ?? '')
    );
  }
} catch (Throwable $e) {
  if ($e instanceof RuntimeException && $e->getMessage() === 'missing_table') {
    $errors[] = 'Table customers manquante. Executez: database/patch_customers.sql';
  } else {
    $errors[] = 'Impossible de charger le client.';
  }
}

function fcfa(int $amount): string
{
  return number_format($amount, 0, ',', ' ') . ' FCFA';
}

function customer_order_status_norm(string $status): string
{
  $status = strtolower(trim($status));
  $map = array(
    'nouvelle' => 'nouveau',
    'confirmee' => 'confirme',
    'preparee' => 'en_preparation',
    'livree' => 'livre',
    'pending' => 'nouveau',
    'confirmed' => 'confirme',
    'processing' => 'en_preparation',
    'prepared' => 'en_preparation',
    'shipped' => 'en_livraison',
    'delivering' => 'en_livraison',
    'delivered' => 'livre',
    'cancelled' => 'annulee',
  );

  return $map[$status] ?? $status;
}

function customer_order_status_label(string $status): string
{
  $status = customer_order_status_norm($status);
  $map = array(
    'nouveau' => 'Nouveau',
    'confirme' => 'Confirmée',
    'en_preparation' => 'En préparation',
    'en_livraison' => 'En livraison',
    'livre' => 'Livrée',
    'annulee' => 'Annulée',
  );

  return $map[$status] ?? $status;
}

function customer_order_status_class(string $status): string
{
  $status = customer_order_status_norm($status);
  if ($status === 'livre') return 'admin-status-pill admin-status-pill--success';
  if ($status === 'annulee') return 'admin-status-pill admin-status-pill--danger';
  return 'admin-status-pill';
}

require_once __DIR__ . '/../_layout_header.php';
?>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-customer-show">
        <?php if (!$customer): ?>
          <div class="admin-page-header">
            <div class="admin-page-header__content">
              <p class="admin-page-header__eyebrow">Relations clients</p>
              <h1 class="admin-page-header__title">Fiche client</h1>
              <p class="admin-page-header__subtitle">Cette fiche client n'est plus disponible ou n'a pas pu être chargée.</p>
            </div>
            <div class="admin-page-header__actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/index.php')); ?>">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux clients
              </a>
            </div>
          </div>
        <?php else: ?>
          <?php
            $name = (string) ($customer['full_name'] ?? '');
            $phone = (string) ($customer['phone'] ?? '');
            $email = (string) ($customer['email'] ?? '');
            $city = (string) ($customer['city'] ?? '');
            $district = (string) ($customer['district'] ?? '');
            $addr = (string) ($customer['address_note'] ?? '');
            $isB = (int) ($customer['is_blacklisted'] ?? 0);
            $createdAt = (string) ($customer['created_at'] ?? '');
          ?>

          <div class="admin-page-header">
            <div class="admin-page-header__content">
              <p class="admin-page-header__eyebrow">Relations clients</p>
              <h1 class="admin-page-header__title"><?php echo e($name !== '' ? $name : ('Client #' . $id)); ?></h1>
              <p class="admin-page-header__subtitle">Consultez la fiche client, ses coordonnées et son historique de commandes depuis une vue plus claire et plus homogène.</p>
              <div class="admin-page-header__meta" aria-label="Résumé client">
                <span class="admin-customer-chip">Client <strong>#<?php echo e((string) $id); ?></strong></span>
                <?php if ($phone !== ''): ?>
                  <span class="admin-customer-chip">Téléphone <strong><?php echo e($phone); ?></strong></span>
                <?php endif; ?>
                <?php if ($createdAt !== ''): ?>
                  <span class="admin-customer-chip">Créé le <strong><?php echo e($createdAt); ?></strong></span>
                <?php endif; ?>
                <span class="admin-status-pill <?php echo $isB ? 'admin-status-pill--danger' : 'admin-status-pill--success'; ?>">
                  <?php echo $isB ? 'Blacklisté' : 'Actif'; ?>
                </span>
              </div>
            </div>
            <div class="admin-page-header__actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/index.php')); ?>">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour aux clients
              </a>
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
                <i class="fas fa-gauge-high" aria-hidden="true"></i> Tableau de bord
              </a>
            </div>
          </div>
        <?php endif; ?>

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
        <?php endif; ?>

        <?php if (!$customer): ?>
          <div class="admin-panel admin-empty-panel admin-customer-empty">
            <p class="admin-empty-panel__title">Client introuvable.</p>
            <p class="admin-empty-panel__text">Retournez à la liste des clients pour continuer votre navigation dans le back-office.</p>
            <div class="admin-empty-panel__actions">
              <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/customers/index.php')); ?>">
                Retour aux clients
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="admin-customer-grid">
            <div class="admin-customer-stack">
              <div class="admin-customer-overview" aria-label="Informations client">
                <div class="feature-card admin-panel admin-customer-panel">
                  <div class="admin-customer-panel__header">
                    <div>
                      <h3 class="admin-customer-panel__title">Coordonnees</h3>
                      <p class="admin-customer-panel__subtitle">Informations de contact principales du client.</p>
                    </div>
                  </div>
                  <div class="admin-customer-kv">
                    <div class="admin-customer-kv__item">
                      <span class="admin-customer-kv__label">Nom complet</span>
                      <div class="admin-customer-kv__value"><?php echo e($name !== '' ? $name : '-'); ?></div>
                    </div>
                    <div class="admin-customer-kv__item">
                      <span class="admin-customer-kv__label">Téléphone</span>
                      <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($phone !== '' ? $phone : '-'); ?></div>
                    </div>
                    <?php if ($email !== ''): ?>
                      <div class="admin-customer-kv__item">
                        <span class="admin-customer-kv__label">Email</span>
                        <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($email); ?></div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="feature-card admin-panel admin-customer-panel">
                  <div class="admin-customer-panel__header">
                    <div>
                      <h3 class="admin-customer-panel__title">Localisation</h3>
                      <p class="admin-customer-panel__subtitle">Ville, quartier et note d'adresse.</p>
                    </div>
                  </div>
                  <div class="admin-customer-kv">
                    <div class="admin-customer-kv__item">
                      <span class="admin-customer-kv__label">Ville</span>
                      <div class="admin-customer-kv__value"><?php echo e($city !== '' ? $city : '-'); ?></div>
                    </div>
                    <?php if ($district !== ''): ?>
                      <div class="admin-customer-kv__item">
                        <span class="admin-customer-kv__label">Quartier</span>
                        <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($district); ?></div>
                      </div>
                    <?php endif; ?>
                    <?php if ($addr !== ''): ?>
                      <div class="admin-customer-kv__item">
                        <span class="admin-customer-kv__label">Note d'adresse</span>
                        <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($addr); ?></div>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="feature-card admin-panel admin-customer-panel" aria-label="Commandes du client">
                <div class="admin-customer-panel__header">
                  <div>
                    <h3 class="admin-customer-panel__title">Historique commandes</h3>
                    <p class="admin-customer-panel__subtitle">Dernieres commandes rattachees au profil client.</p>
                  </div>
                </div>
                <?php if (!$orders): ?>
                  <div class="admin-empty-panel">
                    <p class="admin-empty-panel__title">Aucune commande liee.</p>
                    <p class="admin-empty-panel__text">Le lien est disponible si la colonne <code>orders.customer_profile_id</code> existe.</p>
                  </div>
                <?php else: ?>
                  <div class="admin-table-shell admin-customer-table-shell admin-desktop-only">
                    <table class="admin-table">
                      <thead>
                        <tr>
                          <th>Commande</th>
                          <th>Statut</th>
                          <th>Total</th>
                          <th>Date</th>
                          <th style="text-align:right;"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($orders as $o): ?>
                          <?php
                            $oid = (int) ($o['id'] ?? 0);
                            $num = (string) ($o['order_number'] ?? '');
                            $st = (string) ($o['status'] ?? '');
                            $total = (int) ($o['total_amount'] ?? 0);
                            $at = (string) ($o['created_at'] ?? '');
                          ?>
                          <tr>
                            <td><strong><?php echo e($num !== '' ? $num : ('Commande #' . $oid)); ?></strong></td>
                            <td><span class="<?php echo e(customer_order_status_class($st)); ?>"><?php echo e(customer_order_status_label($st)); ?></span></td>
                            <td><?php echo e(fcfa($total)); ?></td>
                            <td><?php echo e($at); ?></td>
                            <td style="text-align:right;">
                              <a class="btn admin-btn admin-btn--secondary admin-btn--sm" href="<?php echo e(base_url('admin/orders/show.php?id=' . $oid)); ?>">Voir</a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>

                  <div class="admin-mobile-only admin-customer-mobile-cards" aria-label="Commandes du client mobile">
                    <?php foreach ($orders as $o): ?>
                      <?php
                        $oid = (int) ($o['id'] ?? 0);
                        $num = (string) ($o['order_number'] ?? '');
                        $st = (string) ($o['status'] ?? '');
                        $total = (int) ($o['total_amount'] ?? 0);
                        $at = (string) ($o['created_at'] ?? '');
                      ?>
                      <article class="admin-mobile-card admin-customer-mobile-card">
                        <div class="admin-customer-mobile-card__header">
                          <div>
                            <h4 class="admin-customer-mobile-card__title"><?php echo e($num !== '' ? $num : ('Commande #' . $oid)); ?></h4>
                            <div class="admin-customer-mobile-card__meta"><?php echo e($at); ?></div>
                          </div>
                          <span class="<?php echo e(customer_order_status_class($st)); ?>"><?php echo e(customer_order_status_label($st)); ?></span>
                        </div>
                        <div class="admin-customer-mobile-card__grid">
                          <div class="admin-customer-mobile-card__field">
                            <span class="admin-customer-mobile-card__label">Total</span>
                            <div class="admin-customer-mobile-card__value"><?php echo e(fcfa($total)); ?></div>
                          </div>
                          <div class="admin-customer-mobile-card__field">
                            <span class="admin-customer-mobile-card__label">Commande</span>
                            <div class="admin-customer-mobile-card__value admin-customer-mobile-card__value--muted">#<?php echo e((string) $oid); ?></div>
                          </div>
                        </div>
                        <div class="admin-customer-mobile-card__actions">
                          <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/orders/show.php?id=' . $oid)); ?>">Voir</a>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="admin-customer-stack">
              <div class="feature-card admin-panel admin-customer-panel" aria-label="Statut client">
                <div class="admin-customer-panel__header">
                  <div>
                    <h3 class="admin-customer-panel__title">Statut client</h3>
                    <p class="admin-customer-panel__subtitle">État de blacklist et action rapide.</p>
                  </div>
                </div>
                <div class="admin-customer-kv">
                  <div class="admin-customer-kv__item">
                    <span class="admin-customer-kv__label">Statut</span>
                    <div class="admin-customer-kv__value">
                      <span class="admin-status-pill <?php echo $isB ? 'admin-status-pill--danger' : 'admin-status-pill--success'; ?>">
                        <?php echo $isB ? 'Client blacklisté' : 'Client actif'; ?>
                      </span>
                    </div>
                  </div>
                  <?php if ($createdAt !== ''): ?>
                    <div class="admin-customer-kv__item">
                      <span class="admin-customer-kv__label">Création</span>
                      <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($createdAt); ?></div>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="admin-customer-actions" style="margin-top:14px;">
                  <form method="post" action="" onsubmit="return confirm('<?php echo $isB ? 'Retirer de la blacklist ?' : 'Mettre en blacklist ?'; ?>');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="toggle_blacklist">
                    <button class="btn <?php echo $isB ? 'btn-outline' : 'btn-secondary'; ?>" type="submit">
                      <?php echo $isB ? 'Retirer blacklist' : 'Blacklist'; ?>
                    </button>
                  </form>
                </div>
              </div>

              <div class="feature-card admin-panel admin-customer-panel" aria-label="Synthèse client">
                <div class="admin-customer-panel__header">
                  <div>
                    <h3 class="admin-customer-panel__title">Synthèse</h3>
                    <p class="admin-customer-panel__subtitle">Lecture rapide des informations disponibles.</p>
                  </div>
                </div>
                <div class="admin-customer-kv">
                  <div class="admin-customer-kv__item">
                    <span class="admin-customer-kv__label">Nombre de commandes</span>
                    <div class="admin-customer-kv__value"><?php echo e((string) count($orders)); ?></div>
                  </div>
                  <div class="admin-customer-kv__item">
                    <span class="admin-customer-kv__label">Ville</span>
                    <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($city !== '' ? $city : '-'); ?></div>
                  </div>
                  <div class="admin-customer-kv__item">
                    <span class="admin-customer-kv__label">Email</span>
                    <div class="admin-customer-kv__value admin-customer-kv__value--muted"><?php echo e($email !== '' ? $email : '-'); ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
