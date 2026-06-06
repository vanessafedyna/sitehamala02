<?php
declare(strict_types=1);

/* Gestion des partenaires */

require_once __DIR__ . '/../_auth.php';
requireAdminCapability('users.manage');
require_once __DIR__ . '/../_flash.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/services/AdminAuditService.php';

$page_title = 'Admin - Partenaires';
$page_css = 'pages/admin-products.css';
$page_js = '';

$flash = admin_flash_get('partners');
$errors = array();
$created_password = '';

function users_table_columns(PDO $pdo): array
{
  try {
    if (function_exists('db_table_columns')) {
      return db_table_columns($pdo, 'users');
    }
  } catch (Throwable $e) {
    return array();
  }
  return array();
}

function users_password_column(array $cols): ?string
{
  if (in_array('password_hash', $cols, true)) return 'password_hash';
  if (in_array('password', $cols, true)) return 'password';
  return null;
}

function generate_temp_password(int $len = 12): string
{
  $len = max(10, min(32, (int) $len));
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $out = '';
  for ($i = 0; $i < $len; $i += 1) {
    $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
  }
  // ajouter un symbole pour complexité (sans caractères ambigus)
  $out .= '!';
  return $out;
}

try {
  $pdo = db();
  $cols = users_table_columns($pdo);
  $passwordCol = users_password_column($cols);
  if (!$passwordCol) {
    throw new RuntimeException('users.password_hash/password not found');
  }
  $hasIsActive = in_array('is_active', $cols, true); /* Détecte si le schéma permet d'activer ou désactiver un partenaire. */
  $hasPhone = in_array('phone', $cols, true);

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
      $errors[] = 'Session expirée. Veuillez réessayer.';
    } else {
      $action = (string) ($_POST['action'] ?? '');

      if ($action === 'create_partner') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $name = trim((string) ($_POST['name'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        if ($pass === '') {
          $pass = generate_temp_password(12);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $errors[] = 'Email invalide.';
        }
        if ($pass !== '' && !password_meets_policy($pass)) {
          $errors[] = password_policy_message();
        }
        if (function_exists('mb_strlen') && mb_strlen($name) > 190) {
          $errors[] = 'Nom trop long.';
        } elseif (strlen($name) > 190) {
          $errors[] = 'Nom trop long.';
        }

        if (!$errors) {
          $hash = password_hash($pass, PASSWORD_DEFAULT);

          $fields = array('email', $passwordCol, 'role');
          $placeholders = array(':email', ':hash', ':role');
          $params = array('email' => $email, 'hash' => $hash, 'role' => 'partner');

          if (in_array('name', $cols, true)) {
            $fields[] = 'name';
            $placeholders[] = ':name';
            $params['name'] = ($name === '' ? null : $name);
          }
          /* Ajoute l'état d'activation seulement si la colonne est disponible. */
          if ($hasIsActive) {
            $fields[] = 'is_active';
            $placeholders[] = ':is_active';
            $params['is_active'] = 1;
          }

          $sql = 'INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
          $stmt = $pdo->prepare($sql);
          $stmt->execute($params);

          $partnerId = (int) $pdo->lastInsertId();
          $created_password = $pass;

          $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
          AdminAuditService::log($pdo, $adminId, 'owner_created_partner', 'user', $partnerId);

          admin_flash_set('partners', 'success', 'Partenaire créé. Mot de passe temporaire: ' . $created_password);
          redirect('admin/partners/index.php');
        }
      } elseif ($action === 'reset_password') {
        $partnerId = filter_input(INPUT_POST, 'partner_id', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if (!$partnerId) {
          $errors[] = 'ID invalide.';
        } else {
          $newPass = generate_temp_password(12);
          $hash = password_hash($newPass, PASSWORD_DEFAULT);

          $stmt = $pdo->prepare("UPDATE users SET {$passwordCol} = :hash WHERE id = :id AND role = 'partner' LIMIT 1");
          $stmt->execute(array('hash' => $hash, 'id' => (int) $partnerId));

          if ($stmt->rowCount() < 1) {
            $errors[] = 'Partenaire introuvable (ou rôle invalide).';
          } else {
            $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
            AdminAuditService::log($pdo, $adminId, 'owner_reset_partner_password', 'user', (int) $partnerId);

            admin_flash_set('partners', 'success', 'Mot de passe réinitialisé: ' . $newPass);
            redirect('admin/partners/index.php');
          }
        }
      /* Ignore l'action si le schéma ne gère pas l'état d'activation. */
      } elseif ($action === 'toggle_active') {
        if (!$hasIsActive) {
          $errors[] = "Colonne `users.is_active` manquante. Exécutez: database/patch_ops_settings.sql";
        } else {
          $partnerId = filter_input(INPUT_POST, 'partner_id', FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
          $next = isset($_POST['next_active']) ? (int) $_POST['next_active'] : -1;
          if (!$partnerId || ($next !== 0 && $next !== 1)) {
            $errors[] = 'Action invalide.';
          } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = :a WHERE id = :id AND role = 'partner' LIMIT 1");
            $stmt->execute(array('a' => $next, 'id' => (int) $partnerId));
            if ($stmt->rowCount() < 1) {
              $errors[] = 'Partenaire introuvable.';
            } else {
              $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
              AdminAuditService::log($pdo, $adminId, $next === 1 ? 'partner_enabled' : 'partner_disabled', 'user', (int) $partnerId);
              admin_flash_set('partners', 'success', $next === 1 ? 'Le partenariat a été réactivé.' : 'Le partenariat a été désactivé.');
              redirect('admin/partners/index.php');
            }
          }
        }
      } else {
        $errors[] = 'Action invalide.';
      }
    }
  }

  // Liste des partenaires
  $select = "SELECT id, email, name, created_at";
  if ($hasIsActive) $select .= ", is_active";
  if ($hasPhone) $select .= ", phone";
  $stmtList = $pdo->prepare($select . " FROM users WHERE role = 'partner' ORDER BY id DESC LIMIT 200");
  $stmtList->execute();
  $partners = $stmtList->fetchAll(PDO::FETCH_ASSOC) ?: array();
} catch (Throwable $e) {
  $partners = array();
  $errors[] = 'Impossible de charger les partenaires (base de données).';
}

require_once __DIR__ . '/../_layout_header.php';
?>

<style>
  .admin-partners-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-partners-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-partners-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-partners-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-partners-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-partners-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-partners-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-partners-meta__chip {
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
  .admin-partners-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-partners-form {
    display: grid;
    gap: 16px;
  }
  .admin-partners-form__grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
  .admin-partners-field {
    min-width: 0;
  }
  .admin-partners-help {
    margin: 0;
    color: var(--admin-text-muted);
    line-height: 1.55;
  }
  .admin-partners-form__actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  .admin-partners-table-panel {
    overflow: hidden;
  }
  .admin-partners-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-partners-table-shell .admin-table {
    min-width: 980px;
  }
  .admin-partners-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-partners-table-shell td,
  .admin-partners-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: middle;
  }
  .admin-partners-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-partners-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-partners-identity {
    display: grid;
    gap: 4px;
  }
  .admin-partners-identity strong {
    color: var(--admin-ink);
  }
  .admin-partners-identity__meta,
  .admin-partners-id,
  .admin-partners-date {
    color: var(--admin-text-muted);
    font-size: 0.84rem;
  }
  .admin-partners-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    min-width: 250px;
  }
  .admin-partners-actions form {
    margin: 0;
  }
  .admin-partners-actions .admin-btn {
    white-space: nowrap;
  }
  .admin-partners-empty {
    padding: 22px;
  }
  .admin-partners-mobile-list {
    display: grid;
    gap: 14px;
  }
  .admin-partners-mobile-card {
    display: grid;
    gap: 14px;
  }
  .admin-partners-mobile-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }
  .admin-partners-mobile-card__grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-partners-mobile-card__label {
    display: block;
    margin-bottom: 4px;
    color: var(--admin-text-muted);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .admin-partners-mobile-card__value {
    color: var(--admin-ink);
    font-weight: 700;
    overflow-wrap: anywhere;
  }
  .admin-partners-mobile-card__value--muted {
    color: var(--admin-text);
    font-weight: 600;
  }
  .admin-partners-mobile-card__actions {
    display: grid;
    gap: 8px;
  }
  .admin-partners-mobile-card__actions form {
    margin: 0;
  }
  .admin-partners-mobile-card__actions .admin-btn {
    width: 100%;
  }
  .admin-partners-page .admin-btn--primary,
  .admin-partners-page .admin-btn--secondary,
  .admin-partners-page .admin-btn--ghost {
    background-image: none;
  }
  .admin-partners-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 1024px) {
    .admin-partners-form__grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 820px) {
    .admin-partners-page .admin-page-header {
      padding: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-partners-meta__chip {
      width: 100%;
      justify-content: space-between;
    }
  }
  @media (max-width: 430px) {
    .admin-partners-mobile-card__header {
      flex-direction: column;
    }
    .admin-partners-mobile-card__grid {
      grid-template-columns: minmax(0, 1fr);
    }
    .admin-partners-form__actions .admin-btn,
    .admin-page-header__actions .admin-btn {
      flex: 1 1 100%;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-partners-reveal'));
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
      <div class="admin-partners-page">
        <div class="admin-page-header admin-partners-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Accès partenaires</p>
            <h1 class="admin-page-header__title">Partenaires</h1>
            <p class="admin-page-header__subtitle">Gérez les comptes partenaires et leur accès avec une présentation plus claire, plus homogène et plus premium dans l’admin.</p>
            <div class="admin-partners-meta" aria-label="Indicateurs partenaires">
              <span class="admin-partners-meta__chip"><strong><?php echo e((string) count($partners)); ?></strong> compte(s) listé(s)</span>
              <span class="admin-partners-meta__chip"><strong><?php echo e((string) count($errors)); ?></strong> alerte(s) active(s)</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
            </a>
          </div>
        </div>

        <div class="admin-panel admin-panel--padded admin-partners-reveal" aria-label="Informations partenaires">
          <p class="admin-partners-help">Créez et réinitialisez des comptes partenaires avec accès limité aux ajouts produits.</p>
          <p class="admin-partners-help">Un partenaire désactivé ne peut plus accéder à son espace.</p>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-partners-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-partners-reveal is-visible" role="alert">
            <strong>Merci de corriger :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="admin-panel admin-panel--padded admin-partners-reveal" aria-label="Créer un partenaire">
          <form method="post" action="" class="admin-partners-form" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_partner">

            <div class="admin-partners-form__grid">
              <div class="admin-partners-field">
                <label class="admin-field-label" for="email">Email *</label>
                <input id="email" name="email" type="email" class="admin-field" required autocomplete="username">
              </div>

              <div class="admin-partners-field">
                <label class="admin-field-label" for="name">Nom (optionnel)</label>
                <input id="name" name="name" type="text" class="admin-field" maxlength="190" autocomplete="name">
              </div>

              <div class="admin-partners-field">
                <label class="admin-field-label" for="password">Mot de passe (optionnel)</label>
                <input id="password" name="password" type="text" class="admin-field" autocomplete="new-password" placeholder="Si vide : mot de passe temporaire généré">
              </div>
            </div>

            <div class="admin-partners-form__actions">
              <button class="btn admin-btn admin-btn--primary" type="submit">Créer partenaire</button>
            </div>
          </form>
        </div>

        <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-partners-table-panel admin-partners-table-shell admin-partners-reveal is-visible" aria-label="Liste partenaires">
          <?php if (!$partners): ?>
            <div class="admin-empty-panel admin-partners-empty">
              <p class="admin-empty-panel__title">Aucun partenaire.</p>
              <p class="admin-empty-panel__text">Les comptes partenaires apparaîtront ici dès qu’ils seront créés.</p>
            </div>
          <?php else: ?>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Partenaire</th>
                  <th>Statut</th>
                  <th>Créé</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($partners as $p): ?>
                  <?php
                    $pid = (int) ($p['id'] ?? 0);
                    $email = (string) ($p['email'] ?? '');
                    $name = (string) ($p['name'] ?? '');
                    $created = (string) ($p['created_at'] ?? '');
                    $active = (int) ($p['is_active'] ?? 1); /* Valeur par défaut active pour les anciens schémas. */
                  ?>
                  <tr>
                    <td><span class="admin-partners-id">#<?php echo e((string) $pid); ?></span></td>
                    <td>
                      <div class="admin-partners-identity">
                        <strong><?php echo e($name !== '' ? $name : $email); ?></strong>
                        <span class="admin-partners-identity__meta"><?php echo e($email); ?></span>
                      </div>
                    </td>
                    <td>
                      <span class="admin-status-pill <?php echo $active ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                        <?php echo $active ? 'Actif' : 'Inactif'; ?>
                      </span>
                    </td>
                    <td><span class="admin-partners-date"><?php echo e($created); ?></span></td>
                    <td>
                      <div class="admin-partners-actions">
                        <?php if ($hasIsActive): /* Affiche les actions d'activation uniquement si le champ existe. */ ?>
                          <?php if ($active): ?>
                            <form method="post" action="" onsubmit="return confirm('Réinitialiser le mot de passe ?');">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="reset_password">
                              <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                              <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit">Reset mot de passe</button>
                            </form>
                            <form method="post" action="" onsubmit="return confirm('Désactiver le partenariat ?');">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="toggle_active">
                              <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                              <input type="hidden" name="next_active" value="0">
                              <button class="btn admin-btn admin-btn--ghost admin-btn--sm" type="submit">Désactiver le partenariat</button>
                            </form>
                          <?php else: ?>
                            <form method="post" action="" onsubmit="return confirm('Réactiver le partenariat ?');">
                              <?php echo csrf_field(); ?>
                              <input type="hidden" name="action" value="toggle_active">
                              <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                              <input type="hidden" name="next_active" value="1">
                              <button class="btn admin-btn admin-btn--primary admin-btn--sm" type="submit">Réactiver le partenariat</button>
                            </form>
                          <?php endif; ?>
                        <?php else: ?>
                          <form method="post" action="" onsubmit="return confirm('Réinitialiser le mot de passe ?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                            <button class="btn admin-btn admin-btn--secondary admin-btn--sm" type="submit">Reset mot de passe</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="admin-mobile-only admin-partners-reveal is-visible" aria-label="Liste partenaires mobile">
          <div class="admin-mobile-cards admin-partners-mobile-list">
            <?php if (!$partners): ?>
              <div class="admin-empty-panel admin-partners-empty">
                <p class="admin-empty-panel__title">Aucun partenaire.</p>
                <p class="admin-empty-panel__text">Les comptes partenaires apparaîtront ici dès qu’ils seront créés.</p>
              </div>
            <?php endif; ?>

            <?php foreach ($partners as $p): ?>
              <?php
                $pid = (int) ($p['id'] ?? 0);
                $email = (string) ($p['email'] ?? '');
                $name = (string) ($p['name'] ?? '');
                $phone = (string) ($p['phone'] ?? '');
                $created = (string) ($p['created_at'] ?? '');
                $active = (int) ($p['is_active'] ?? 1);
              ?>
              <article class="admin-mobile-card admin-partners-mobile-card">
                <div class="admin-partners-mobile-card__header">
                  <div>
                    <h2 class="admin-mobile-card__title"><?php echo e($name !== '' ? $name : $email); ?></h2>
                    <div class="admin-mobile-card__meta">Partenaire #<?php echo e((string) $pid); ?></div>
                  </div>
                  <span class="admin-status-pill <?php echo $active ? 'admin-status-pill--success' : 'admin-status-pill--neutral'; ?>">
                    <?php echo $active ? 'Actif' : 'Inactif'; ?>
                  </span>
                </div>

                <div class="admin-partners-mobile-card__grid">
                  <div>
                    <span class="admin-partners-mobile-card__label">Email</span>
                    <div class="admin-partners-mobile-card__value"><?php echo e($email !== '' ? $email : '—'); ?></div>
                  </div>
                  <?php if ($phone !== ''): ?>
                    <div>
                      <span class="admin-partners-mobile-card__label">Téléphone</span>
                      <div class="admin-partners-mobile-card__value"><?php echo e($phone); ?></div>
                    </div>
                  <?php endif; ?>
                  <div>
                    <span class="admin-partners-mobile-card__label">Créé</span>
                    <div class="admin-partners-mobile-card__value admin-partners-mobile-card__value--muted"><?php echo e($created); ?></div>
                  </div>
                </div>

                <div class="admin-partners-mobile-card__actions">
                  <?php if ($hasIsActive): ?>
                    <?php if ($active): ?>
                      <form method="post" action="" onsubmit="return confirm('Réinitialiser le mot de passe ?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                        <button class="btn admin-btn admin-btn--secondary" type="submit">Reset mot de passe</button>
                      </form>
                      <form method="post" action="" onsubmit="return confirm('Désactiver le partenariat ?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                        <input type="hidden" name="next_active" value="0">
                        <button class="btn admin-btn admin-btn--ghost" type="submit">Désactiver</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="" onsubmit="return confirm('Réactiver le partenariat ?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                        <input type="hidden" name="next_active" value="1">
                        <button class="btn admin-btn admin-btn--primary" type="submit">Réactiver</button>
                      </form>
                    <?php endif; ?>
                  <?php else: ?>
                    <form method="post" action="" onsubmit="return confirm('Réinitialiser le mot de passe ?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="reset_password">
                      <input type="hidden" name="partner_id" value="<?php echo (int) $pid; ?>">
                      <button class="btn admin-btn admin-btn--secondary" type="submit">Reset mot de passe</button>
                    </form>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../_layout_footer.php'; ?>
