<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
auth_start();

// Legacy tracking disabled: use pages/suivi.php
$orderNumber = trim((string) ($_GET['order_number'] ?? ''));
$target = 'pages/suivi.php';
if ($orderNumber !== '') {
  $target .= '?order_number=' . urlencode($orderNumber);
}

redirect($target);
exit;
