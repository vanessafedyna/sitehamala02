<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('newsletter_subscribe', 40, 60);

$body = json_read_body();
$email = strtolower(trim((string) ($body['email'] ?? '')));

if (!api_is_valid_email($email)) {
  json_response(422, array('ok' => false, 'message' => 'Email invalide.'));
}

try {
  $pdo = api_pdo();
  $stmt = $pdo->prepare('INSERT INTO newsletter_subscribers (email) VALUES (:email)');
  try {
    $stmt->execute(array('email' => $email));
    json_response(201, array('ok' => true, 'message' => 'Inscription ok.'));
  } catch (PDOException $e) {
    $info = $e->errorInfo;
    $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
    $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
    if ($sqlState === '23000' || $driverCode === '1062') {
      json_response(200, array('ok' => true, 'message' => 'Deja inscrit.'));
    }
    throw $e;
  }
} catch (Throwable $e) {
  error_log('[api/newsletter_subscribe] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de sinscrire.'));
}

