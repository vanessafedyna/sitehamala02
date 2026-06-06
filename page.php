<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
auth_start();

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/models/CmsPageModel.php';
require_once __DIR__ . '/app/services/CmsSanitizer.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '' || !preg_match('/^[a-z0-9-]{1,140}$/', $slug)) {
  http_response_code(404);
  $page_title = 'Page introuvable';
  $page_css = 'pages/cms.css';
  include __DIR__ . '/includes/header.php';
  ?>
  <main id="main" class="cms-page" tabindex="-1">
    <section class="page-head">
      <div class="container">
        <h1>Page introuvable</h1>
        <p class="subtitle">La page demandée n’existe pas.</p>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <?php
  exit;
}

$m = new CmsPageModel(db());
$row = $m->exists() ? $m->findBySlug($slug, true) : null;
if (!$row) {
  http_response_code(404);
  $page_title = 'Page introuvable';
  $page_css = 'pages/cms.css';
  include __DIR__ . '/includes/header.php';
  ?>
  <main id="main" class="cms-page" tabindex="-1">
    <section class="page-head">
      <div class="container">
        <h1>Page introuvable</h1>
        <p class="subtitle">La page demandée n’existe pas ou n’est pas publiée.</p>
      </div>
    </section>
  </main>
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <?php
  exit;
}

$page_title = (string) ($row['title'] ?? 'Page');
$page_css = 'pages/cms.css';
$page_js = '';

$page_seo_title = trim((string) ($row['seo_title'] ?? ''));
$page_meta_description = trim((string) ($row['seo_description'] ?? ''));
$og = trim((string) ($row['og_image'] ?? ''));
$page_og_image = ($og === '' ? '' : (str_starts_with($og, 'http://') || str_starts_with($og, 'https://') || $og[0] === '/' ? $og : base_url(ltrim($og, '/'))));

$content_html = CmsSanitizer::sanitize((string) ($row['content'] ?? ''));

include __DIR__ . '/includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1><?php echo e($page_title); ?></h1>
    </div>
  </section>

  <section class="cms-body">
    <div class="container">
      <div class="card cms-content">
        <?php echo $content_html; ?>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

