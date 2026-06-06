<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../includes/Settings.php';
require_once __DIR__ . '/../includes/Logger.php';

function order_success_set_flash_error(string $message): void
{
  if (function_exists('set_flash')) {
    try {
      set_flash('error', $message);
      return;
    } catch (Throwable $e) {
      // fallback
    }
  }
  if (function_exists('flash')) {
    try {
      flash('error', $message);
      return;
    } catch (Throwable $e) {
      // fallback
    }
  }
  if (function_exists('add_flash')) {
    try {
      add_flash('error', $message);
      return;
    } catch (Throwable $e) {
      // fallback
    }
  }

  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
    $_SESSION['flash'] = array();
  }
  $_SESSION['flash'][] = array(
    'type' => 'error',
    'message' => $message,
  );
}

$orderNumber = trim((string) ($_GET['order_number'] ?? ''));
$token = trim((string) ($_GET['t'] ?? ''));
if ($orderNumber === '') {
  order_success_set_flash_error('Numéro de commande manquant.');
  redirect('pages/suivi.php');
  exit;
}

$sessionToken = (string) ($_SESSION['order_success_token'] ?? '');
$sessionOrderNumber = (string) ($_SESSION['order_success_order_number'] ?? '');
$sessionExpiresAt = (int) ($_SESSION['order_success_expires_at'] ?? 0);
$isValidAccess =
  $sessionToken !== ''
  && $token !== ''
  && $sessionOrderNumber !== ''
  && hash_equals($sessionOrderNumber, $orderNumber)
  && $sessionExpiresAt > 0
  && time() <= $sessionExpiresAt
  && hash_equals($sessionToken, $token);

if (!$isValidAccess) {
  if ($sessionExpiresAt > 0 && time() > $sessionExpiresAt) {
    unset(
      $_SESSION['order_success_token'],
      $_SESSION['order_success_order_number'],
      $_SESSION['order_success_expires_at']
    );
  }
  order_success_set_flash_error('Session expiree. Veuillez suivre votre commande.');
  $to = 'pages/suivi.php';
  if ($orderNumber !== '') {
    $to .= '?order_number=' . urlencode($orderNumber);
  }
  redirect($to);
  exit;
}

$page_title = 'Commande confirmee';
$page_css = 'pages/cms.css';
$page_js = '';

$order = null;
$items = array();
$err = '';

try {
  $pdo = db();
  $model = new OrderModel($pdo);
  $row = $model->getByOrderNumber($orderNumber);
  if (!$row) {
    $err = 'Commande introuvable. Vérifiez le numéro puis réessayez.';
  } else {
    $order = $row;
    // Charger items (si possible)
    try {
      $items = $model->items((int) ($row['id'] ?? 0));
    } catch (Throwable $e) {
      $items = array();
    }
  }
} catch (Throwable $e) {
  if (class_exists('Logger')) {
    Logger::error('order_success_failed', array(
      'order_number' => $orderNumber,
      'error' => (string) $e->getMessage(),
    ));
  } else {
    error_log('[order_success_failed] ' . $orderNumber . ' ' . $e->getMessage());
  }
  $err = 'Erreur serveur, réessayez plus tard.';
}

// WhatsApp link (no API)
$waNumber = function_exists('public_contact_whatsapp_number') ? public_contact_whatsapp_number() : '22392828271';
$waUrl = '';

if ($order && $waNumber !== '') {
  $num = (string) ($order['order_number'] ?? '');
  $total = (int) ($order['total_amount'] ?? 0);
  $name = (string) ($order['customer_name'] ?? '');
  $phone = (string) ($order['customer_phone'] ?? '');

  $msg = "Bonjour, je viens de passer la commande #{$num}. Total: {$total} FCFA. Nom: {$name}. Telephone: {$phone}. Merci.";
  $waUrl = 'https://wa.me/' . $waNumber . '?text=' . urlencode($msg);
}

include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>Commande confirmee</h1>
      <p class="subtitle">Merci. Votre commande a bien ete enregistree.</p>
    </div>
  </section>

  <section class="cms-body">
    <div class="container">
      <?php if ($err !== ''): ?>
        <div class="feature-card" role="alert">
          <p><strong><?php echo e($err); ?></strong></p>
        </div>
        <p>
          <a class="btn btn-outline" href="<?php echo e(base_url('pages/suivi.php')); ?>">Suivre une commande</a>
          <a class="btn btn-secondary" href="<?php echo e($base_url); ?>pages/catalogue.php">Retour au catalogue</a>
        </p>
      <?php else: ?>
        <?php
          $num = (string) ($order['order_number'] ?? '');
          $st = (string) ($order['status'] ?? 'nouvelle');
          $total = (int) ($order['total_amount'] ?? 0);
          $subtotal = (int) ($order['subtotal_amount'] ?? 0);
          $discount = (int) ($order['discount_amount'] ?? 0);
          $ship = (int) ($order['shipping_fee_amount'] ?? 0);
          $tax = (int) ($order['tax_amount'] ?? 0);
          $name = (string) ($order['customer_name'] ?? '');
          $phone = (string) ($order['customer_phone'] ?? '');
          $city = (string) ($order['city'] ?? '');
          $district = trim((string) ($order['district'] ?? ''));
          $deliveryLabel = $city;
          if ($district !== '') {
            $deliveryLabel .= ' - ' . $district;
          }
        ?>

        <div class="card cms-content order-success-hero" aria-label="Confirmation commande">
          <h2 class="order-success-hero__title">Merci pour votre commande</h2>
          <p class="order-success-hero__subtitle">Votre commande a bien ete enregistree.</p>
          <p class="order-success-hero__hint">Conservez votre numero de commande pour le suivi.</p>

          <div class="order-success-number" aria-label="Numero de commande">
            <p class="order-success-number__label">Numero de commande</p>
            <p class="order-success-number__value">#<?php echo e($num); ?></p>
          </div>

          <p class="order-success-hero__reassure">Nous vous contacterons si necessaire pour confirmer la livraison.</p>
        </div>

        <div class="card cms-content" aria-label="Resume commande">
          <p><strong>Numero :</strong> <?php echo e($num); ?></p>
          <p><strong>Statut :</strong> <?php echo e($st); ?></p>
          <p><strong>Client :</strong> <?php echo e($name); ?> - <?php echo e($phone); ?></p>
          <p><strong>Livraison :</strong> <?php echo e($deliveryLabel !== '' ? $deliveryLabel : '-'); ?></p>

          <hr style="border:none;border-top:1px solid var(--lightest-gray);margin:14px 0;">

          <?php if ($subtotal > 0): ?>
            <p><strong>Sous-total :</strong> <?php echo e(number_format($subtotal, 0, ',', ' ')); ?> FCFA</p>
          <?php endif; ?>
          <?php if ($discount > 0): ?>
            <p><strong>Reduction :</strong> -<?php echo e(number_format($discount, 0, ',', ' ')); ?> FCFA</p>
          <?php endif; ?>
          <?php if ($ship > 0): ?>
            <p><strong>Livraison :</strong> <?php echo e(number_format($ship, 0, ',', ' ')); ?> FCFA</p>
          <?php endif; ?>
          <?php if ($tax > 0): ?>
            <p><strong>Taxe :</strong> <?php echo e(number_format($tax, 0, ',', ' ')); ?> FCFA</p>
          <?php endif; ?>
          <p style="font-size:1.1rem;"><strong>Total :</strong> <?php echo e(number_format($total, 0, ',', ' ')); ?> FCFA</p>

          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px;">
            <a class="btn btn-outline" href="<?php echo e(base_url('pages/suivi.php?order_number=' . urlencode($num))); ?>">
              Suivre la commande
            </a>
            <?php if ($waUrl !== ''): ?>
              <a class="btn btn-primary" href="<?php echo e($waUrl); ?>" target="_blank" rel="noopener">
                Recevoir la confirmation sur WhatsApp
              </a>
            <?php endif; ?>
          </div>

          <p class="admin-help" style="margin-top:10px;">
            Conseil (Mali) : gardez votre telephone disponible, un partenaire peut vous contacter.
          </p>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

