<?php
declare(strict_types=1);

/**
 * @return array{
 *   page_title:string,
 *   page_meta_description:string,
 *   page_css:string,
 *   page_js:string,
 *   body_class:string
 * }
 */
function home_page_view_context(): array
{
  return array(
    'page_title' => 'Achetez facilement vos produits, payez à la livraison',
    'page_meta_description' => 'SORA Collection, boutique en ligne au Mali : découvrez nos collections de vêtements, commandez facilement et payez à la livraison.',
    'page_css' => '',
    'page_js' => 'pages/accueil.js',
    'body_class' => 'page-home',
  );
}

/**
 * @return array<int,array<string,mixed>>
 */
function home_fetch_featured_products(PDO $pdo, int $limit = 4): array
{
  $limit = max(1, min(12, $limit));

  $fields = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();

  $supports_featured = in_array('is_featured', $fields, true) && in_array('featured_rank', $fields, true);
  $supports_status = in_array('status', $fields, true);

  $select = array();
  foreach (array(
    'id',
    'name',
    'sku',
    'price',
    'price_fcfa',
    'prix',
    'stock',
    'category',
    'image1',
    'image2',
    'image3',
    'image_path',
    'image_main',
    'image',
    'is_featured',
    'featured_rank',
  ) as $col) {
    if (in_array($col, $fields, true)) $select[] = $col;
  }
  if (!$select) $select = array('*');

  if (!$supports_featured) {
    return array();
  }

  $sql = 'SELECT ' . implode(', ', $select) . ' FROM products';

  $where = array();
  if (in_array('is_active', $fields, true)) $where[] = 'is_active = 1';
  if ($supports_status) $where[] = "status = 'published'";
  $where[] = 'is_featured = 1';
  $sql .= ' WHERE ' . implode(' AND ', $where);

  $order = array(
    '(featured_rank IS NULL) ASC',
    'featured_rank ASC',
    'id DESC',
  );
  $sql .= ' ORDER BY ' . implode(', ', $order) . ' LIMIT ' . (int) $limit;

  $stmt = $pdo->prepare($sql);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
}

function home_product_image_url(array $product, string $base_url, string $placeholder_img): string
{
  $img = (string) ($product['image1'] ?? ($product['image_path'] ?? ($product['image_main'] ?? ($product['image'] ?? ''))));
  $img = trim($img);
  $img_src = $placeholder_img;

  if ($img !== '') {
    if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
      $img_src = $img;
    } elseif ($img[0] === '/') {
      $img_src = $img;
    } else {
      $img = str_replace('\\', '/', $img);
      $pos = stripos($img, 'uploads/products/');
      if ($pos !== false) {
        $img = substr($img, $pos);
      } elseif (preg_match('/^[a-zA-Z]:\\//', $img)) {
        $img = basename($img);
      }
      if (strpos($img, '/') === false) {
        $img = 'uploads/products/' . ltrim($img, '/');
      }

      if (function_exists('base_path')) {
        $fs = base_path($img);
        if (is_file($fs)) {
          $img_src = $base_url . ltrim($img, '/');
        }
      } else {
        $img_src = $base_url . ltrim($img, '/');
      }
    }
  }

  return $img_src;
}

/**
 * @return array<int,array<string,mixed>>
 */
function home_fetch_reviews(PDO $pdo, int $limit = 6): array
{
  $limit = max(1, min(20, $limit));
  $stmt = $pdo->prepare(
    'SELECT name, city, rating, message, created_at
     FROM reviews
     WHERE is_approved = 1
     ORDER BY created_at DESC
     LIMIT ' . (int) $limit
  );
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
}
