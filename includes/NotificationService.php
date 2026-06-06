<?php
declare(strict_types=1);

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../lib/NotificationQueue.php';

final class NotificationService
{
  /**
   * @return string[]
   */
  private static function adminRecipients(): array
  {
    $to = array();

    $notify = trim((string) setting('notify_admin_email', ''));
    if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
      $to[] = strtolower($notify);
    }

    $shop = trim((string) setting('shop_email', ''));
    if ($shop !== '' && filter_var($shop, FILTER_VALIDATE_EMAIL)) {
      $to[] = strtolower($shop);
    }

    $ownerEnabled = trim((string) setting('owner_order_notify_enabled', '0'));
    $ownerEmail = trim((string) setting('owner_order_notify_email', ''));
    if ($ownerEnabled !== '' && $ownerEnabled !== '0' && $ownerEmail !== '' && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
      $to[] = strtolower($ownerEmail);
    }

    return array_values(array_unique($to));
  }

  private static function resolveClientEmail(array $order): string
  {
    $direct = trim((string) ($order['customer_email'] ?? ''));
    if ($direct !== '' && filter_var($direct, FILTER_VALIDATE_EMAIL)) {
      return strtolower($direct);
    }

    $customerProfileId = (int) ($order['customer_profile_id'] ?? 0);
    $customerId = (int) ($order['customer_id'] ?? 0);

    try {
      $pdo = db();

      if ($customerProfileId > 0) {
        try {
          $stmt = $pdo->prepare('SELECT email FROM customers WHERE id = :id LIMIT 1');
          $stmt->execute(array('id' => $customerProfileId));
          $email = trim((string) ($stmt->fetchColumn() ?: ''));
          if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return strtolower($email);
          }
        } catch (Throwable $e) {
          // continue
        }
      }

      if ($customerId > 0) {
        try {
          $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
          $stmt->execute(array('id' => $customerId));
          $email = trim((string) ($stmt->fetchColumn() ?: ''));
          if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return strtolower($email);
          }
        } catch (Throwable $e) {
          // continue
        }
      }
    } catch (Throwable $e) {
      // continue
    }

    return '';
  }

  private static function statusLabel(string $status): string
  {
    $s = strtolower(trim($status));
    $map = array(
      'nouveau' => 'Nouveau',
      'confirme' => 'Confirme',
      'en_preparation' => 'En preparation',
      'en_livraison' => 'En livraison',
      'livre' => 'Livre',
      // legacy
      'nouvelle' => 'Nouveau',
      'confirmee' => 'Confirme',
      'preparee' => 'En preparation',
      'livree' => 'Livre',
      'annulee' => 'Annulee',
    );
    return $map[$s] ?? ucfirst($s);
  }

  private static function queue(): ?NotificationQueue
  {
    try {
      return new NotificationQueue(db());
    } catch (Throwable $e) {
      Logger::error('notification_queue_init_failed', array('err' => $e->getMessage()));
      return null;
    }
  }

  private static function enqueue(int $orderId, string $type, string $recipient, array $payload, int $maxAttempts = 5, string $channel = 'email'): bool
  {
    $queue = self::queue();
    if (!$queue) return false;

    $ok = $queue->enqueue($orderId, $type, $recipient, $payload, $maxAttempts, $channel);
    if (!$ok) {
      Logger::warn('notification_enqueue_failed', array(
        'order_id' => $orderId,
        'type' => $type,
        'channel' => $channel,
        'recipient' => $recipient,
      ));
    }
    return $ok;
  }

  /**
   * @param array<string,mixed> $order
   * @param array<int,array<string,mixed>> $items
   */
  public static function notifyAdminNewOrder(array $order, array $items): void
  {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) return;

    $recipients = self::adminRecipients();

    $payload = array(
      'order_number' => (string) ($order['order_number'] ?? ''),
      'items_count' => count($items),
    );

    if (!$recipients) {
      Logger::warn('notify_admin_skipped_no_recipient');
    } else {
      foreach ($recipients as $recipient) {
        self::enqueue($orderId, 'admin_new_order', $recipient, $payload, 5);
      }
    }

  }

  /**
   * @param array<string,mixed> $order
   */
  public static function notifyClientOrderCreated(array $order): void
  {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) return;

    $to = self::resolveClientEmail($order);

    $payload = array(
      'order_number' => (string) ($order['order_number'] ?? ''),
      'status' => (string) ($order['status'] ?? 'nouveau'),
    );
    if ($to === '') {
      Logger::info('notify_client_created_skipped_no_email', array('order_id' => $orderId));
    } else {
      self::enqueue($orderId, 'client_order_created', $to, $payload, 5);
    }

  }

  /**
   * @param array<string,mixed> $order
   */
  public static function notifyClientShipped(array $order): void
  {
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) return;

    $to = self::resolveClientEmail($order);

    $status = (string) ($order['status'] ?? 'en_livraison');
    if (trim($status) === '') $status = 'en_livraison';

    $payload = array(
      'order_number' => (string) ($order['order_number'] ?? ''),
      'status' => $status,
    );

    if ($to === '') {
      Logger::info('notify_client_shipped_skipped_no_email', array('order_id' => $orderId));
    } else {
      self::enqueue($orderId, 'client_status_update', $to, $payload, 5);
    }

  }

  /**
   * @param array<string,mixed> $payload
   * @return array<string,string>|null
   */
  public static function buildEmailForJob(string $type, int $orderId, array $payload): ?array
  {
    $type = trim($type);
    $orderId = (int) $orderId;
    if ($type === '' || $orderId <= 0) return null;

    try {
      $model = new OrderModel(db());
      $order = $model->findById($orderId);
      if (!$order) return null;
      $items = $model->items($orderId);

      $orderNumber = (string) ($order['order_number'] ?? '');
      $customerName = (string) ($order['customer_name'] ?? '');
      $customerPhone = (string) ($order['customer_phone'] ?? '');
      $city = (string) ($order['city'] ?? '');
      $district = trim((string) ($order['district'] ?? ''));
      $deliveryLabel = $city;
      if ($district !== '') {
        $deliveryLabel .= ' - ' . $district;
      }
      $total = (int) ($order['total_amount'] ?? 0);

      if ($type === 'admin_new_order') {
        $itemsHtml = '';
        foreach ($items as $it) {
          $pn = htmlspecialchars((string) ($it['product_name_snapshot'] ?? 'Produit'), ENT_QUOTES, 'UTF-8');
          $qty = (int) ($it['qty'] ?? 0);
          $itemsHtml .= '<li>' . $pn . ' x ' . $qty . '</li>';
        }

        $adminUrl = (defined('SITE_URL') ? SITE_URL : '') . 'admin/orders/show.php?id=' . $orderId;
        return array(
          'subject' => 'Nouvelle commande ' . $orderNumber,
          'html' => '<h2>Nouvelle commande</h2>'
            . '<p><strong>Numero :</strong> ' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Client :</strong> ' . htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') . ' - ' . htmlspecialchars($customerPhone, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Livraison :</strong> ' . htmlspecialchars($deliveryLabel, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Total :</strong> ' . number_format($total, 0, ',', ' ') . ' FCFA</p>'
            . '<p><a href="' . htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8') . '">Ouvrir dans l admin</a></p>'
            . '<h3>Articles</h3><ul>' . $itemsHtml . '</ul>',
          'text' => '',
        );
      }

      if ($type === 'client_order_created') {
        return array(
          'subject' => 'Commande creee ' . $orderNumber,
          'html' => '<p>Bonjour,</p>'
            . '<p>Votre commande <strong>' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</strong> a ete creee.</p>'
            . '<p>Total : <strong>' . number_format($total, 0, ',', ' ') . ' FCFA</strong></p>'
            . '<p>Statut : <strong>' . htmlspecialchars(self::statusLabel((string) ($order['status'] ?? 'nouveau')), ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Merci.</p>',
          'text' => '',
        );
      }

      if ($type === 'client_status_update') {
        $status = trim((string) ($payload['status'] ?? ($order['status'] ?? '')));
        if ($status === '') $status = (string) ($order['status'] ?? '');

        return array(
          'subject' => 'Mise a jour commande ' . $orderNumber,
          'html' => '<p>Bonjour,</p>'
            . '<p>Le statut de votre commande <strong>' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</strong> est maintenant :</p>'
            . '<p><strong>' . htmlspecialchars(self::statusLabel($status), ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Merci.</p>',
          'text' => '',
        );
      }

      return null;
    } catch (Throwable $e) {
      Logger::warn('build_email_for_job_failed', array('type' => $type, 'order_id' => $orderId, 'err' => $e->getMessage()));
      return null;
    }
  }
}
