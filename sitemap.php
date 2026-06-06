<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/config/database.php';

header('Content-Type: application/xml; charset=utf-8');

/**
 * @param array<int,array<string,string>> $urls
 */
function sitemap_add_url(array &$urls, string $loc, string $lastmod = '', string $changefreq = '', string $priority = ''): void
{
  $loc = trim($loc);
  if ($loc === '') {
    return;
  }

  static $seen = array();
  if (isset($seen[$loc])) {
    return;
  }
  $seen[$loc] = true;

  $row = array('loc' => $loc);
  if ($lastmod !== '') $row['lastmod'] = $lastmod;
  if ($changefreq !== '') $row['changefreq'] = $changefreq;
  if ($priority !== '') $row['priority'] = $priority;
  $urls[] = $row;
}

function sitemap_lastmod_from_file(string $relativePath): string
{
  $fs = base_path($relativePath);
  if (!is_file($fs)) {
    return '';
  }
  $ts = @filemtime($fs);
  if (!is_int($ts) || $ts <= 0) {
    return '';
  }
  return gmdate('c', $ts);
}

function sitemap_lastmod_from_datetime($value): string
{
  $raw = trim((string) $value);
  if ($raw === '') {
    return '';
  }
  $ts = strtotime($raw);
  if ($ts === false || $ts <= 0) {
    return '';
  }
  return gmdate('c', $ts);
}

function sitemap_xml_escape(string $v): string
{
  return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

$urls = array();

$staticPages = array(
  array('path' => 'index.php', 'changefreq' => 'daily', 'priority' => '1.0'),
  array('path' => 'pages/catalogue.php', 'changefreq' => 'daily', 'priority' => '0.9'),
  array('path' => 'pages/apropos.php', 'changefreq' => 'monthly', 'priority' => '0.6'),
  array('path' => 'pages/contact.php', 'changefreq' => 'monthly', 'priority' => '0.6'),
  array('path' => 'pages/faq.php', 'changefreq' => 'weekly', 'priority' => '0.6'),
  array('path' => 'pages/livraison.php', 'changefreq' => 'monthly', 'priority' => '0.5'),
  array('path' => 'pages/retours.php', 'changefreq' => 'monthly', 'priority' => '0.5'),
  array('path' => 'pages/suivi.php', 'changefreq' => 'monthly', 'priority' => '0.4'),
);

foreach ($staticPages as $page) {
  $path = (string) ($page['path'] ?? '');
  if ($path === '' || !is_file(base_path($path))) {
    continue;
  }
  sitemap_add_url(
    $urls,
    absolute_url($path),
    sitemap_lastmod_from_file($path),
    (string) ($page['changefreq'] ?? ''),
    (string) ($page['priority'] ?? '')
  );
}

try {
  $pdo = db();
  $cols = db_table_columns($pdo, 'products');

  if ($cols && in_array('id', $cols, true)) {
    $conditions = array('1=1');
    if (in_array('status', $cols, true)) $conditions[] = "status = 'published'";
    if (in_array('is_active', $cols, true)) $conditions[] = 'is_active = 1';
    if (in_array('deleted_at', $cols, true)) $conditions[] = 'deleted_at IS NULL';
    if (in_array('is_deleted', $cols, true)) $conditions[] = '(is_deleted = 0 OR is_deleted IS NULL)';

    $select = array('id');
    if (in_array('sku', $cols, true)) $select[] = 'sku';
    if (in_array('category', $cols, true)) $select[] = 'category';
    if (in_array('updated_at', $cols, true)) $select[] = 'updated_at';
    if (in_array('created_at', $cols, true)) $select[] = 'created_at';

    $sql = 'SELECT ' . implode(', ', $select)
      . ' FROM products'
      . ' WHERE ' . implode(' AND ', $conditions)
      . ' ORDER BY id DESC LIMIT 5000';

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: array();
    $categorySlugs = array();

    foreach ($rows as $r) {
      $id = (int) ($r['id'] ?? 0);
      if ($id <= 0) {
        continue;
      }

      $sku = trim((string) ($r['sku'] ?? ''));
      $queryPath = $sku !== ''
        ? ('pages/produit.php?sku=' . rawurlencode($sku))
        : ('pages/produit.php?id=' . $id);

      $lastmod = sitemap_lastmod_from_datetime($r['updated_at'] ?? '');
      if ($lastmod === '') {
        $lastmod = sitemap_lastmod_from_datetime($r['created_at'] ?? '');
      }

      sitemap_add_url($urls, absolute_url($queryPath), $lastmod, 'weekly', '0.8');

      $cat = trim((string) ($r['category'] ?? ''));
      if ($cat !== '') {
        $slug = function_exists('slugify') ? slugify($cat) : strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $cat) ?: ''));
        $slug = trim((string) $slug, '-');
        if ($slug !== '') {
          $categorySlugs[$slug] = true;
        }
      }
    }

    foreach (array_keys($categorySlugs) as $catSlug) {
      sitemap_add_url($urls, absolute_url('pages/catalogue.php?cat=' . rawurlencode((string) $catSlug)), '', 'weekly', '0.7');
    }
  }
} catch (Throwable $e) {
  // Sitemaps must stay resilient even when DB is temporarily unavailable.
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $u) {
  echo "  <url>\n";
  echo '    <loc>' . sitemap_xml_escape((string) ($u['loc'] ?? '')) . "</loc>\n";
  if (!empty($u['lastmod'])) echo '    <lastmod>' . sitemap_xml_escape((string) $u['lastmod']) . "</lastmod>\n";
  if (!empty($u['changefreq'])) echo '    <changefreq>' . sitemap_xml_escape((string) $u['changefreq']) . "</changefreq>\n";
  if (!empty($u['priority'])) echo '    <priority>' . sitemap_xml_escape((string) $u['priority']) . "</priority>\n";
  echo "  </url>\n";
}
echo "</urlset>\n";

