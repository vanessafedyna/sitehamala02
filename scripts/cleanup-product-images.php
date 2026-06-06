<?php
declare(strict_types=1);

// Nettoyage DB des chemins d'images produits pointant vers des fichiers inexistants.
// - Mode par défaut: dry-run (aucune modification)
// - Pour appliquer: CLI `php scripts/cleanup-product-images.php --apply`
// - Web (localhost uniquement): `scripts/cleanup-product-images.php?apply=1`

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$isCli = (PHP_SAPI === 'cli');
$isLocal = ($ip === '127.0.0.1' || $ip === '::1' || $ip === '');

if (!$isCli && !$isLocal) {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';

function arg_has(string $name): bool
{
  $args = $_SERVER['argv'] ?? array();
  return in_array($name, $args, true);
}

$apply = false;
if ($isCli) {
  $apply = arg_has('--apply');
} else {
  $apply = (($_GET['apply'] ?? '') === '1');
}

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

function is_external_url(string $path): bool
{
  return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
}

/**
 * Normalise un chemin image DB en chemin relatif sous uploads/products quand possible.
 * Retourne '' si vide.
 */
function normalize_image_db_value(string $path): string
{
  $path = trim($path);
  if ($path === '') return '';

  $path = str_replace('\\', '/', $path);

  // Extraire à partir de uploads/products/ si on a un chemin complet
  $pos = stripos($path, 'uploads/products/');
  if ($pos !== false) {
    $path = substr($path, $pos);
  } elseif (preg_match('/^[a-zA-Z]:\\//', $path)) {
    // Chemin Windows => basename
    $path = basename($path);
  }

  // Si c'est un simple nom de fichier, le mettre dans uploads/products/
  if (!is_external_url($path) && $path !== '' && $path[0] !== '/' && strpos($path, '/') === false) {
    $path = 'uploads/products/' . ltrim($path, '/');
  }

  return $path;
}

/**
 * True si path local (sous uploads/products/) existe sur le disque.
 */
function local_image_exists(string $relative): bool
{
  $relative = ltrim(trim($relative), '/');
  if ($relative === '') return false;
  if (is_external_url($relative)) return true;
  if (stripos($relative, 'uploads/products/') !== 0) return true; // inconnu => on ne bloque pas
  $fs = base_path($relative);
  return is_file($fs);
}

try {
  $pdo = db();

  $cols = table_columns($pdo, 'products');
  if (!$cols) {
    out('Erreur: table products introuvable.');
    exit(1);
  }

  $imageCols = array_values(array_filter(array('image1', 'image2', 'image3', 'image_path', 'image_main', 'image'), fn ($c) => in_array($c, $cols, true)));
  if (!$imageCols) {
    out('Aucune colonne image trouvée sur products.');
    exit(0);
  }

  out('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'));
  out('Colonnes images détectées: ' . implode(', ', $imageCols));

  $selectCols = array_merge(array('id', 'sku'), $imageCols);
  $stmt = $pdo->query('SELECT ' . implode(', ', $selectCols) . ' FROM products ORDER BY id ASC');

  $total = 0;
  $rowsChanged = 0;
  $valuesNullified = 0;
  $valuesNormalized = 0;
  $backfilled = 0;

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $total += 1;

    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) continue;

    $updates = array();
    $params = array('id' => $id);

    // Normaliser + nullifier références cassées
    foreach ($imageCols as $col) {
      $raw = (string) ($row[$col] ?? '');
      $rawTrim = trim($raw);
      if ($rawTrim === '') {
        continue;
      }

      if (is_external_url($rawTrim) || ($rawTrim !== '' && $rawTrim[0] === '/')) {
        // Laisser tel quel (URL / chemin absolu serveur)
        continue;
      }

      $norm = normalize_image_db_value($rawTrim);
      if ($norm !== $rawTrim) {
        $updates[$col] = $norm;
        $valuesNormalized += 1;
      }

      if (!local_image_exists($norm)) {
        $updates[$col] = null;
        $valuesNullified += 1;
      }
    }

    // Backfill compat: image1 <-> image_path si présents
    $hasImage1 = in_array('image1', $imageCols, true);
    $hasImagePath = in_array('image_path', $imageCols, true);
    if ($hasImage1 && $hasImagePath) {
      $current1 = array_key_exists('image1', $updates) ? (string) ($updates['image1'] ?? '') : (string) ($row['image1'] ?? '');
      $currentPath = array_key_exists('image_path', $updates) ? (string) ($updates['image_path'] ?? '') : (string) ($row['image_path'] ?? '');

      $current1 = trim((string) $current1);
      $currentPath = trim((string) $currentPath);

      if ($current1 === '' && $currentPath !== '') {
        $updates['image1'] = $currentPath;
        $backfilled += 1;
      } elseif ($currentPath === '' && $current1 !== '') {
        $updates['image_path'] = $current1;
        $backfilled += 1;
      }
    }

    if (!$updates) {
      continue;
    }

    $rowsChanged += 1;

    $parts = array();
    foreach ($updates as $col => $val) {
      if ($val === null) {
        $parts[] = $col . ' = NULL';
        continue;
      }
      $ph = ':' . $col;
      $parts[] = $col . ' = ' . $ph;
      $params[$col] = $val;
    }

    $sql = 'UPDATE products SET ' . implode(', ', $parts) . ' WHERE id = :id LIMIT 1';

    out("Product #{$id}: " . implode(', ', array_keys($updates)));

    if ($apply) {
      $upd = $pdo->prepare($sql);
      $upd->execute($params);
    }
  }

  out('---');
  out('Produits scannés: ' . (string) $total);
  out('Produits modifiés: ' . (string) $rowsChanged);
  out('Valeurs normalisées: ' . (string) $valuesNormalized);
  out('Valeurs nullifiées (fichier manquant): ' . (string) $valuesNullified);
  out('Backfill image1/image_path: ' . (string) $backfilled);
  out('Terminé.');
} catch (Throwable $e) {
  out('Erreur: ' . $e->getMessage());
  exit(1);
}

