<?php
declare(strict_types=1);

function product_page_normalize_image_url($path, $base_url): string
{
  $path = trim((string) $path);
  if ($path === '') return '';
  if (preg_match('#^https?://#i', $path)) return $path;
  if (strpos($path, '/') === 0) return $path;
  $path = str_replace('\\', '/', $path);

  $pos = stripos($path, 'uploads/products/');
  if ($pos !== false) {
    $path = substr($path, $pos);
  } elseif (preg_match('/^[a-zA-Z]:\\//', $path)) {
    $path = basename($path);
  }

  if (strpos($path, '/') === false) {
    $path = 'uploads/products/' . ltrim($path, '/');
  }

  // Fallback si fichier manquant
  $fs = base_path($path);
  if (!is_file($fs)) {
    return rtrim((string) $base_url, '/') . '/assets/images/placeholders/product-placeholder.svg';
  }

  return rtrim((string) $base_url, '/') . '/' . ltrim($path, '/');
}

/**
 * @return array{0:string,1:string}
 */
function product_page_stock_badge($stock): array
{
  $stock = (int) $stock;
  if ($stock > 5) return array('En stock (' . $stock . ')', 'badge-stock--ok');
  if ($stock > 0) return array('Stock limite (' . $stock . ')', 'badge-stock--low');
  return array('Rupture de stock', 'badge-stock--out');
}

/**
 * @param array<string,mixed> $row
 * @return array<int,array{label:string,class:string}>
 */
function product_page_badges_data(array $row, int $max = 2): array
{
  $max = max(1, $max);
  $badges = array();

  $isFeatured = false;
  if (array_key_exists('is_featured', $row) && (int) ($row['is_featured'] ?? 0) === 1) {
    $isFeatured = true;
  }
  if (!$isFeatured && array_key_exists('featured', $row) && (int) ($row['featured'] ?? 0) === 1) {
    $isFeatured = true;
  }
  if (!$isFeatured && array_key_exists('featured_rank', $row)) {
    $isFeatured = ((int) ($row['featured_rank'] ?? 0) > 0);
  }
  if ($isFeatured) {
    $badges[] = array('label' => 'En vedette', 'class' => 'is-featured');
  }

  if (count($badges) < $max && array_key_exists('created_at', $row)) {
    $ts = strtotime((string) ($row['created_at'] ?? ''));
    if ($ts !== false && $ts >= (time() - (86400 * 30))) {
      $badges[] = array('label' => 'Nouveau', 'class' => 'is-new');
    }
  }

  if (count($badges) < $max && array_key_exists('stock', $row)) {
    $stock = (int) ($row['stock'] ?? 0);
    if ($stock > 0 && $stock <= 5) {
      $badges[] = array('label' => 'Stock limite', 'class' => 'is-low-stock');
    }
  }

  return array_slice($badges, 0, $max);
}

/**
 * @param array<string,mixed> $row
 * @return array<int,array{label:string,class:string}>
 */
function product_page_card_badges_data(array $row, int $max = 2): array
{
  $max = max(1, $max);
  $badges = array();
  $priority = array(
    'En vedette' => 1,
    'Nouveau' => 2,
    'Stock limite' => 3,
    'En stock' => 4,
  );

  $isFeatured = false;
  if (array_key_exists('is_featured', $row) && (int) ($row['is_featured'] ?? 0) === 1) $isFeatured = true;
  if (!$isFeatured && array_key_exists('featured', $row) && (int) ($row['featured'] ?? 0) === 1) $isFeatured = true;
  if (!$isFeatured && array_key_exists('featured_rank', $row)) $isFeatured = ((int) ($row['featured_rank'] ?? 0) > 0);
  if ($isFeatured) $badges[] = array('label' => 'En vedette', 'class' => 'is-featured');

  if (array_key_exists('created_at', $row)) {
    $ts = strtotime((string) ($row['created_at'] ?? ''));
    if ($ts !== false && $ts >= (time() - (86400 * 30))) {
      $badges[] = array('label' => 'Nouveau', 'class' => 'is-new');
    }
  }

  if (array_key_exists('stock', $row)) {
    $stock = (int) ($row['stock'] ?? 0);
    if ($stock > 0 && $stock <= 5) {
      $badges[] = array('label' => 'Stock limite', 'class' => 'is-low-stock');
    } elseif ($stock > 5) {
      $badges[] = array('label' => 'En stock', 'class' => 'is-in-stock');
    }
  }

  usort($badges, static function (array $a, array $b) use ($priority): int {
    $pa = $priority[(string) ($a['label'] ?? '')] ?? 99;
    $pb = $priority[(string) ($b['label'] ?? '')] ?? 99;
    return $pa <=> $pb;
  });

  return array_slice($badges, 0, $max);
}

/**
 * @return string[]
 */
function product_page_table_columns(PDO $pdo, string $table): array
{
  if (function_exists('db_table_columns')) {
    return db_table_columns($pdo, $table);
  }
  return array();
}
