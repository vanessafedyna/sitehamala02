<?php
declare(strict_types=1);

/* Met à jour la mise en avant d'un produit depuis l'administration. */

require_once __DIR__ . '/../../../admin/_auth.php';
require_once __DIR__ . '/../_common.php';

requireRole('owner');

header('Content-Type: application/json; charset=utf-8');

function json_ok(): void
{
  echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
  exit;
}

function json_error(int $code, string $message): void
{
  http_response_code($code);
  echo json_encode(array('ok' => false, 'message' => $message), JSON_UNESCAPED_UNICODE);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_error(400, 'Methode invalide.');
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = array();

if (strpos($contentType, 'application/json') !== false) {
  $raw = (string) file_get_contents('php://input');
  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    json_error(400, 'JSON invalide.');
  }
  $payload = $decoded;
} else {
  $payload = $_POST;
}

$csrfToken = isset($payload['_csrf']) ? (string) $payload['_csrf'] : (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
api_require_csrf($csrfToken);

$productId = (int) ($payload['product_id'] ?? 0);
if ($productId <= 0) {
  json_error(400, 'product_id invalide.');
}

$isFeatured = 0;
if (isset($payload['is_featured'])) {
  $isFeatured = ((int) $payload['is_featured']) ? 1 : 0;
}

$rankRaw = $payload['featured_rank'] ?? null;
$rank = null;
if ($rankRaw !== null) {
  $rankStr = trim((string) $rankRaw);
  if ($rankStr !== '') {
    $rankInt = (int) preg_replace('/[^0-9-]/', '', $rankStr);
    if ($rankInt < 1) {
      json_error(400, 'featured_rank doit etre >= 1.');
    }
    $rank = $rankInt;
  }
}

// Regle metier: si pas vedette => rank NULL
if ($isFeatured === 0) {
  $rank = null;
}

try {
  $pdo = db();

  // Verifier que les colonnes existent
  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
  $supports = in_array('is_featured', $fields, true) && in_array('featured_rank', $fields, true);
  if (!$supports) {
    json_error(500, 'Colonnes manquantes. Executez le patch SQL: database/patch_products_featured.sql');
  }

  $stmt = $pdo->prepare('UPDATE products SET is_featured = :f, featured_rank = :r WHERE id = :id LIMIT 1');
  $stmt->bindValue(':f', $isFeatured, PDO::PARAM_INT);
  if ($rank === null) {
    $stmt->bindValue(':r', null, PDO::PARAM_NULL);
  } else {
    $stmt->bindValue(':r', $rank, PDO::PARAM_INT);
  }
  $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
  $stmt->execute();

  json_ok();
} catch (Throwable $e) {
  error_log('[api/admin/products_featured_update] ' . $e->getMessage());
  json_error(500, 'Erreur serveur.');
}
