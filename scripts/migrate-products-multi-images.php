<?php
declare(strict_types=1);

// Migration: ajoute image1/image2/image3 à products (si manquants) + backfill image1=image_path.
// CLI: `php scripts/migrate-products-multi-images.php`
// Web (localhost uniquement): `scripts/migrate-products-multi-images.php`

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$isCli = (PHP_SAPI === 'cli');
$isLocal = ($ip === '127.0.0.1' || $ip === '::1' || $ip === '');

if (!$isCli && !$isLocal) {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';

function out(string $line): void
{
  if (PHP_SAPI !== 'cli') {
    echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br>\n";
    return;
  }
  echo $line . PHP_EOL;
}

/**
 * @return string[]
 */
function table_columns(PDO $pdo, string $table): array
{
  $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC) ?: array();
  $fields = array();
  foreach ($rows as $row) {
    if (!empty($row['Field'])) {
      $fields[] = (string) $row['Field'];
    }
  }
  return $fields;
}

try {
  $pdo = db();
  $cols = table_columns($pdo, 'products');

  if (!$cols) {
    out('Erreur: table products introuvable.');
    exit(1);
  }

  $need = array();
  foreach (array('image1', 'image2', 'image3') as $c) {
    if (!in_array($c, $cols, true)) {
      $need[] = $c;
    }
  }

  if (!$need) {
    out('OK: products contient déjà image1/image2/image3.');
    exit(0);
  }

  if (!in_array('image_path', $cols, true)) {
    out('Erreur: colonne products.image_path manquante (attendue dans ce projet).');
    exit(1);
  }

  $clauses = array();

  // Ajouter dans l'ordre, avec AFTER cohérent.
  $hasImage1 = in_array('image1', $cols, true) || in_array('image1', $need, true);
  if (in_array('image1', $need, true)) {
    $clauses[] = 'ADD COLUMN image1 VARCHAR(255) NULL AFTER image_path';
  }
  if (in_array('image2', $need, true)) {
    $after = $hasImage1 ? 'image1' : 'image_path';
    $clauses[] = 'ADD COLUMN image2 VARCHAR(255) NULL AFTER ' . $after;
  }
  if (in_array('image3', $need, true)) {
    $after = in_array('image2', $cols, true) || in_array('image2', $need, true) ? 'image2' : ($hasImage1 ? 'image1' : 'image_path');
    $clauses[] = 'ADD COLUMN image3 VARCHAR(255) NULL AFTER ' . $after;
  }

  $sql = 'ALTER TABLE products ' . implode(', ', $clauses);
  out('Exécution: ' . $sql);
  $pdo->exec($sql);

  // Backfill image1 à partir de image_path
  out('Backfill: image1 = image_path (si image1 vide)');
  $pdo->exec("UPDATE products SET image1 = image_path WHERE (image1 IS NULL OR image1 = '') AND image_path IS NOT NULL AND image_path <> ''");

  out('OK: migration terminée.');
} catch (Throwable $e) {
  out('Erreur: ' . $e->getMessage());
  exit(1);
}

