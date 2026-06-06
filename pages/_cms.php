<?php
declare(strict_types=1);


require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CmsPageModel.php';
require_once __DIR__ . '/../app/services/CmsSanitizer.php';

/**
 * Charge une page CMS publiée par key_name.
 *
 * @return array<string,mixed>|null
 */
function cms_get_published_by_key(string $key): ?array
{
  $key = trim($key);
  if ($key === '') return null;

  try {
    $m = new CmsPageModel(db());
    if (!$m->exists()) return null;
    return $m->findByKey($key, true);
  } catch (Throwable $e) {
    return null;
  }
}

function cms_sanitize_html(string $html): string
{
  return CmsSanitizer::sanitize($html);
}

function cms_asset_url(?string $path): string
{
  $path = trim((string) $path);
  if ($path === '') return '';

  if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || $path[0] === '/') {
    return $path;
  }
  return base_url(ltrim($path, '/'));
}

