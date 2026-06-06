<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireAdminCapability('settings.manage');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../includes/Settings.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$page_title = 'Admin - Paramètres';
$page_css = 'pages/admin-products.css';
$page_js = '';

$flash = admin_flash_get('settings');
$errors = array();

function settings_table_exists(PDO $pdo): bool
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, 'settings') !== array();
  }
  try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    return (bool) ($stmt && $stmt->fetchColumn());
  } catch (Throwable $e) {
    return false;
  }
}

$keys = array(
  'shop_name' => 'SORA Collection',
  'shop_email' => '',
  'notify_admin_email' => '',
  'owner_order_notify_enabled' => '0',
  'owner_order_notify_email' => '',
  'shop_whatsapp_number' => '+22392828271',
  'free_shipping_threshold' => '0',
  'tax_rate_percent' => '0',
  'maintenance_mode' => '0',
  'maintenance_message' => 'Maintenance en cours. Merci de revenir plus tard.',
);

$values = array();

try {
  $pdo = db();
  if (!settings_table_exists($pdo)) {
    $errors[] = "Table `settings` manquante. Exécutez `database/patch_ops_settings.sql`.";
  } else {
    foreach ($keys as $k => $def) {
      $values[$k] = (string) setting($k, $def);
    }
  }
} catch (Throwable $e) {
  $errors[] = 'Impossible de charger les paramètres (base de données).';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$errors) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expiree. Veuillez reessayer.';
  } else {
    $shopName = trim((string) ($_POST['shop_name'] ?? ''));
    $shopEmail = trim((string) ($_POST['shop_email'] ?? ''));
    $notifyEmail = trim((string) ($_POST['notify_admin_email'] ?? ''));
    $ownerNotifyEnabled = isset($_POST['owner_order_notify_enabled']) ? '1' : '0';
    $ownerNotifyEmail = trim((string) ($_POST['owner_order_notify_email'] ?? ''));
    $shopWhatsappNumber = trim((string) ($_POST['shop_whatsapp_number'] ?? ''));
    $freeThr = trim((string) ($_POST['free_shipping_threshold'] ?? '0'));
    $taxRate = trim((string) ($_POST['tax_rate_percent'] ?? '0'));

    if ($shopName === '') $errors[] = 'Nom boutique obligatoire.';
    if (function_exists('mb_strlen') ? mb_strlen($shopName) > 120 : strlen($shopName) > 120) $errors[] = 'Nom boutique trop long.';

    if ($shopEmail !== '' && !filter_var($shopEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email boutique invalide.';
    if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email notification admin invalide.';
    if ($ownerNotifyEnabled === '1' && ($ownerNotifyEmail === '' || !filter_var($ownerNotifyEmail, FILTER_VALIDATE_EMAIL))) {
      $errors[] = 'Email de notification propriétaire requis et valide.';
    }

    $shopWhatsappDigits = (string) preg_replace('/\D+/', '', $shopWhatsappNumber);
    if ($shopWhatsappDigits === '' || !preg_match('/^\d{8,15}$/', $shopWhatsappDigits)) {
      $errors[] = 'Numero WhatsApp boutique invalide.';
    }

    if ($freeThr !== '' && !preg_match('/^\d+(\.\d+)?$/', $freeThr)) $errors[] = 'Seuil livraison gratuite invalide.';
    if ($taxRate !== '' && !preg_match('/^\d+(\.\d+)?$/', $taxRate)) $errors[] = 'Taux taxe invalide.';

    if (!$errors) {
      try {
        set_setting('shop_name', $shopName);
        set_setting('shop_email', $shopEmail);
        set_setting('notify_admin_email', $notifyEmail);
        set_setting('owner_order_notify_enabled', $ownerNotifyEnabled);
        set_setting('owner_order_notify_email', $ownerNotifyEmail);
        set_setting('shop_whatsapp_number', $shopWhatsappDigits);
        set_setting('free_shipping_threshold', $freeThr);
        set_setting('tax_rate_percent', $taxRate);

        $pdo = db();
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        AdminAuditService::log($pdo, $adminId, 'owner_updated_settings');

        admin_flash_set('settings', 'success', 'Paramètres enregistrés.');
        redirect('admin/settings.php');
      } catch (Throwable $e) {
        $errors[] = "L'enregistrement des paramètres a échoué. Vérifiez les champs requis puis réessayez.";
      }
    }
  }
}

require_once __DIR__ . '/_layout_header.php';
?>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-settings-reveal'));
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
      <div class="admin-settings-page">
        <div class="admin-page-header admin-settings-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Configuration boutique</p>
            <h1 class="admin-page-header__title">Paramètres</h1>
            <p class="admin-page-header__subtitle">Centralisez les réglages essentiels de la boutique dans une interface plus claire, plus sobre et plus cohérente avec le reste de l’admin.</p>
            <div class="admin-settings-meta" aria-label="Indicateurs paramètres">
              <span class="admin-settings-meta__chip"><strong>3</strong> sections principales</span>
              <span class="admin-settings-meta__chip"><strong><?php echo e((string) count($errors)); ?></strong> alerte(s) active(s)</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-settings-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-settings-reveal is-visible" role="alert">
            <strong>Le formulaire contient des informations à corriger. Vérifiez les champs obligatoires et les valeurs saisies.</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if (!$errors): ?>
          <form method="post" class="admin-settings-form" novalidate>
            <?php echo csrf_field(); ?>

            <div class="admin-panel admin-panel--padded admin-settings-section admin-settings-reveal" aria-label="Paramètres boutique">
              <div class="admin-settings-section__head">
                <h2 class="admin-settings-section__title">Boutique</h2>
                <p class="admin-settings-section__text">Réglages principaux visibles dans la boutique et utilisés comme base de présentation.</p>
              </div>

              <div class="admin-settings-grid">
                <div class="admin-settings-field">
                  <label class="admin-field-label" for="shop_name">Nom de la boutique *</label>
                  <input id="shop_name" name="shop_name" class="admin-field" required value="<?php echo e($values['shop_name'] ?? ''); ?>">
                </div>

                <div class="admin-settings-field">
                  <label class="admin-field-label" for="shop_email">Email de la boutique</label>
                  <input id="shop_email" name="shop_email" type="email" class="admin-field" value="<?php echo e($values['shop_email'] ?? ''); ?>">
                </div>

                <div class="admin-settings-field">
                  <label class="admin-field-label" for="shop_whatsapp_number">Numero WhatsApp boutique</label>
                  <input id="shop_whatsapp_number" name="shop_whatsapp_number" class="admin-field" value="<?php echo e($values['shop_whatsapp_number'] ?? ''); ?>" inputmode="tel" placeholder="22392828271">
                  <p class="admin-help">Utilise pour les liens publics <code>wa.me</code>. Aucun envoi API automatique.</p>
                </div>
              </div>
            </div>

            <div class="admin-panel admin-panel--padded admin-settings-section admin-settings-reveal" aria-label="Paramètres notifications">
              <div class="admin-settings-section__head">
                <h2 class="admin-settings-section__title">Notifications</h2>
                <p class="admin-settings-section__text">Canaux utilisés pour les alertes de commande et les notifications destinées au propriétaire.</p>
              </div>

              <div class="admin-settings-grid">
                <div class="admin-settings-field admin-settings-field--full">
                  <label class="admin-field-label" for="notify_admin_email">Email de notification des commandes</label>
                  <input id="notify_admin_email" name="notify_admin_email" type="email" class="admin-field" value="<?php echo e($values['notify_admin_email'] ?? ''); ?>">
                </div>

                <div class="admin-settings-field admin-settings-field--full">
                  <label class="admin-settings-toggle">
                    <input class="admin-field" type="checkbox" name="owner_order_notify_enabled" value="1" <?php echo ((string) ($values['owner_order_notify_enabled'] ?? '0')) !== '0' ? 'checked' : ''; ?>>
                    <span class="admin-settings-toggle__body">
                      <span class="admin-settings-toggle__title">Activer la notification propriétaire</span>
                      <span class="admin-settings-toggle__text">Conserve exactement le même comportement fonctionnel, avec une présentation plus lisible.</span>
                    </span>
                  </label>
                </div>

                <div class="admin-settings-field admin-settings-field--full">
                  <label class="admin-field-label" for="owner_order_notify_email">Email du propriétaire</label>
                  <input id="owner_order_notify_email" name="owner_order_notify_email" type="email" class="admin-field" value="<?php echo e($values['owner_order_notify_email'] ?? ''); ?>">
                </div>
              </div>
            </div>

            <div class="admin-settings-safe-note admin-settings-reveal">
              Les réglages techniques sensibles sont gérés hors de cette interface.
            </div>

            <div class="admin-panel admin-panel--padded admin-settings-section admin-settings-reveal" aria-label="Paramètres livraison et fiscalité">
              <div class="admin-settings-section__head">
                <h2 class="admin-settings-section__title">Livraison et fiscalité</h2>
                <p class="admin-settings-section__text">Réglages appliqués aux commandes, avec une densité et des alignements plus homogènes sur desktop comme sur mobile.</p>
              </div>

              <div class="admin-settings-grid">
                <div class="admin-settings-field">
                  <label class="admin-field-label" for="free_shipping_threshold">Seuil de livraison gratuite (FCFA)</label>
                  <input id="free_shipping_threshold" name="free_shipping_threshold" class="admin-field" value="<?php echo e($values['free_shipping_threshold'] ?? '0'); ?>">
                </div>

                <div class="admin-settings-field">
                  <label class="admin-field-label" for="tax_rate_percent">Taxe %</label>
                  <input id="tax_rate_percent" name="tax_rate_percent" class="admin-field" value="<?php echo e($values['tax_rate_percent'] ?? '0'); ?>">
                </div>
              </div>
            </div>

            <div class="admin-settings-actions admin-settings-reveal">
              <button class="btn admin-btn admin-btn--primary" type="submit">Enregistrer</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
