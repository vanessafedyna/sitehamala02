<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CmsPageModel.php';
require_once __DIR__ . '/../app/services/CmsSanitizer.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$page_title = 'Admin - Éditer une page CMS';
$page_css = 'pages/admin-products.css';
$page_js = '';
$body_class = trim((string) ($body_class ?? '') . ' page-cms-edit');

/**
 * @return array<string,array<string,string>>
 */
function cms_admin_page_catalog(): array
{
  return array(
    'mentions-legales' => array(
      'title' => 'Mentions légales',
      'slug' => 'mentions-legales',
    ),
    'politique-confidentialite' => array(
      'title' => 'Politique de confidentialité',
      'slug' => 'politique-confidentialite',
    ),
    'conditions-generales-vente' => array(
      'title' => 'Conditions générales de vente',
      'slug' => 'conditions-generales-vente',
    ),
    'livraison' => array(
      'title' => 'Livraison',
      'slug' => 'livraison',
    ),
    'retours' => array(
      'title' => 'Retours',
      'slug' => 'retours',
    ),
    'faq' => array(
      'title' => 'FAQ',
      'slug' => 'faq',
    ),
    'about' => array(
      'title' => 'À propos',
      'slug' => 'a-propos',
    ),
  );
}

function cms_admin_public_url_by_values(array $values): string
{
  $key = trim((string) ($values['key_name'] ?? ''));
  $slug = trim((string) ($values['slug'] ?? ''));

  $map = array(
    'mentions-legales' => base_url('pages/mentions-legales.php'),
    'politique-confidentialite' => base_url('pages/politique-confidentialite.php'),
    'conditions-generales-vente' => base_url('pages/conditions-generales-vente.php'),
    'livraison' => base_url('pages/livraison.php'),
    'retours' => base_url('pages/retours.php'),
    'faq' => base_url('pages/faq.php'),
    'about' => base_url('pages/apropos.php'),
  );

  if (isset($map[$key])) {
    return $map[$key];
  }

  if ($slug !== '') {
    return base_url('page.php?slug=' . urlencode($slug));
  }

  return '';
}

function cms_admin_normalize_key(string $value): string
{
  $value = trim($value);
  if ($value === '') {
    return '';
  }
  if (function_exists('slugify')) {
    return trim((string) slugify($value), '-');
  }
  $value = strtolower($value);
  $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
  return trim($value, '-');
}

function cms_admin_preview_html(string $html): string
{
  $html = trim($html);
  if ($html === '') {
    return '';
  }

  $previewHtml = CmsSanitizer::sanitize($html);
  if ($previewHtml !== '') {
    return $previewHtml;
  }

  // Fallback local pour l'admin: si le sanitizer global renvoie vide malgré un HTML valide,
  // on conserve une version strictement limitée pour ne pas casser la prévisualisation.
  $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><hr>';
  $previewHtml = strip_tags($html, $allowedTags);
  $previewHtml = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\\1\s*>#is', '', $previewHtml) ?: '';
  $previewHtml = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\\\'[^\\\']*\\\'|[^\s>]+)/i', '', $previewHtml) ?: '';
  $previewHtml = preg_replace('/\sstyle\s*=\s*("[^"]*"|\\\'[^\\\']*\\\'|[^\s>]+)/i', '', $previewHtml) ?: '';
  $previewHtml = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', 'href="#"', $previewHtml) ?: '';

  return trim($previewHtml);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$requestedKey = isset($_GET['key']) ? cms_admin_normalize_key((string) $_GET['key']) : '';
$errors = array();
$created = false;

$pdo = db();
$model = new CmsPageModel($pdo);
$catalog = cms_admin_page_catalog();

if (!$model->exists()) {
  $errors[] = "Table `pages` manquante. Exécutez `database/patch_cms_pages.sql`.";
}

$page = null;
if (!$errors) {
  if ($id > 0) {
    $page = $model->findById($id);
    if (!$page) {
      http_response_code(404);
      $errors[] = 'Page introuvable.';
    }
  } elseif ($requestedKey !== '') {
    $page = $model->findByKey($requestedKey, false);
  }
}

$defaultKey = $page ? (string) ($page['key_name'] ?? '') : $requestedKey;
$defaultTitle = $page ? (string) ($page['title'] ?? '') : (string) (($catalog[$defaultKey]['title'] ?? ''));
$defaultSlug = $page ? (string) ($page['slug'] ?? '') : (string) (($catalog[$defaultKey]['slug'] ?? $defaultKey));

$values = array(
  'id' => (string) ((int) ($page['id'] ?? 0)),
  'title' => $defaultTitle,
  'key_name' => $defaultKey,
  'slug' => $defaultSlug,
  'content' => (string) ($page['content'] ?? ''),
  'is_published' => ((int) ($page['is_published'] ?? 0)) ? '1' : '0',
  'seo_title' => (string) ($page['seo_title'] ?? ''),
  'seo_description' => (string) ($page['seo_description'] ?? ''),
  'og_image' => (string) ($page['og_image'] ?? ''),
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$errors) {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    $currentId = (int) ($_POST['page_id'] ?? 0);
    $editingRow = $currentId > 0 ? $model->findById($currentId) : null;
    if ($currentId > 0 && !$editingRow) {
      http_response_code(404);
      $errors[] = 'Page introuvable.';
    }

    $values['id'] = (string) $currentId;
    $values['title'] = trim((string) ($_POST['title'] ?? ''));
    $values['key_name'] = cms_admin_normalize_key((string) ($_POST['key_name'] ?? ''));
    $values['slug'] = cms_admin_normalize_key((string) ($_POST['slug'] ?? ''));
    $values['content'] = (string) ($_POST['content'] ?? '');
    $values['is_published'] = isset($_POST['is_published']) ? '1' : '0';
    $values['seo_title'] = trim((string) ($_POST['seo_title'] ?? ''));
    $values['seo_description'] = trim((string) ($_POST['seo_description'] ?? ''));
    $values['og_image'] = trim((string) ($_POST['og_image'] ?? ''));

    if ($values['title'] === '') {
      $errors[] = 'Le titre est obligatoire.';
    }

    if ($values['key_name'] === '') {
      $errors[] = 'La clé CMS est obligatoire.';
    } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $values['key_name'])) {
      $errors[] = 'La clé CMS doit contenir uniquement des lettres minuscules, chiffres et tirets.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($values['key_name'], 'UTF-8') : strlen($values['key_name'])) > 60) {
      $errors[] = 'La clé CMS est trop longue.';
    }

    if ($values['slug'] === '') {
      $values['slug'] = $values['key_name'];
    }

    if ($values['slug'] === '') {
      $errors[] = 'Le slug public est obligatoire.';
    } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $values['slug'])) {
      $errors[] = 'Le slug public doit contenir uniquement des lettres minuscules, chiffres et tirets.';
    } elseif ((function_exists('mb_strlen') ? mb_strlen($values['slug'], 'UTF-8') : strlen($values['slug'])) > 140) {
      $errors[] = 'Le slug public est trop long.';
    }

    if (!$errors && $model->keyExists($values['key_name'], $currentId)) {
      $existing = $model->findByKey($values['key_name'], false);
      if (!$editingRow && $existing) {
        admin_flash_set('pages', 'info', 'La page existe déjà. Vous pouvez la modifier.');
        redirect('admin/page_edit.php?key=' . urlencode($values['key_name']));
      }
      $errors[] = 'Une page avec cette clé CMS existe déjà.';
    }

    if (!$errors && $model->slugExists($values['slug'], $currentId)) {
      $errors[] = 'Une page avec ce slug public existe déjà.';
    }

    if (!$errors) {
      try {
        $payload = array(
          'key_name' => $values['key_name'],
          'title' => $values['title'],
          'slug' => $values['slug'],
          'content' => $values['content'],
          'is_published' => (int) $values['is_published'],
          'seo_title' => $values['seo_title'],
          'seo_description' => $values['seo_description'],
          'og_image' => $values['og_image'],
        );

        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

        if ($currentId > 0) {
          $model->updateById($currentId, $payload);
          AdminAuditService::log($pdo, $adminId, 'owner_updated_page', 'page', $currentId);
          admin_flash_set('pages', 'success', 'Page CMS mise à jour.');
        } else {
          $currentId = $model->create($payload);
          $values['id'] = (string) $currentId;
          $created = true;
          AdminAuditService::log($pdo, $adminId, 'owner_created_page', 'page', $currentId);
          admin_flash_set('pages', 'success', 'Page CMS créée.');
        }

        redirect('admin/page_edit.php?id=' . $currentId);
      } catch (Throwable $e) {
        $errors[] = "L'enregistrement de la page a échoué. Vérifiez les champs requis puis réessayez.";
      }
    }
  }
}

$isEditing = ((int) $values['id']) > 0 && !$created;
$previewHtml = cms_admin_preview_html((string) ($values['content'] ?? ''));
$publicUrl = cms_admin_public_url_by_values($values);
$publishedLabel = $values['is_published'] === '1' ? 'Publié' : 'Brouillon';
$publishedClass = $values['is_published'] === '1' ? 'admin-status-pill admin-status-pill--success' : 'admin-status-pill admin-status-pill--warning';

require_once __DIR__ . '/_layout_header.php';
?>

<style>
  .page-cms-edit .container {
    max-width: 1360px;
  }
  .admin-cms-edit-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-cms-edit-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-cms-edit-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-cms-edit-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-cms-edit-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-cms-edit-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-cms-edit-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-cms-edit-meta__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
    overflow-wrap: anywhere;
  }
  .admin-cms-edit-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-cms-edit-form {
    display: grid;
    gap: 16px;
  }
  .admin-cms-edit-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(320px, 0.85fr);
    gap: 16px;
    align-items: start;
  }
  .admin-cms-edit-stack {
    display: grid;
    gap: 16px;
    min-width: 0;
  }
  .admin-cms-edit-section {
    display: grid;
    gap: 18px;
    min-width: 0;
  }
  .admin-cms-edit-section__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
  }
  .admin-cms-edit-kicker {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(31, 122, 79, 0.08);
    color: #28513d;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .admin-cms-edit-section__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1.05rem;
    font-weight: 700;
  }
  .admin-cms-edit-section__text {
    margin: 6px 0 0;
    color: var(--admin-text-muted);
    line-height: 1.55;
  }
  .admin-cms-edit-fields,
  .admin-cms-edit-side-fields {
    display: grid;
    gap: 16px;
  }
  .admin-cms-edit-fields {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-cms-edit-field {
    min-width: 0;
    display: grid;
    gap: 8px;
  }
  .admin-cms-edit-field--full {
    grid-column: 1 / -1;
  }
  .admin-cms-edit-field .admin-field,
  .admin-cms-edit-field .admin-select,
  .admin-cms-edit-field .admin-textarea {
    width: 100%;
    min-width: 0;
    background-image: none;
  }
  .admin-cms-edit-field .admin-field:focus,
  .admin-cms-edit-field .admin-select:focus,
  .admin-cms-edit-field .admin-textarea:focus,
  .admin-cms-edit-field .admin-field:focus-visible,
  .admin-cms-edit-field .admin-select:focus-visible,
  .admin-cms-edit-field .admin-textarea:focus-visible {
    outline: 0;
    border-color: rgba(31, 122, 79, 0.45);
    box-shadow: 0 0 0 4px rgba(31, 122, 79, 0.12);
  }
  .admin-cms-edit-content {
    min-height: 460px;
    max-height: 72vh;
    font-family: Consolas, "Courier New", monospace;
    line-height: 1.55;
    resize: vertical;
  }
  .admin-cms-edit-toggle {
    display: inline-flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--admin-border);
    border-radius: 16px;
    background: var(--admin-surface-soft);
    color: var(--admin-text);
    line-height: 1.45;
  }
  .admin-cms-edit-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 1px;
    accent-color: var(--admin-accent);
  }
  .admin-cms-edit-toggle__body {
    display: grid;
    gap: 4px;
    min-width: 0;
  }
  .admin-cms-edit-toggle__title {
    color: var(--admin-ink);
    font-weight: 700;
  }
  .admin-cms-edit-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
  }
  .admin-cms-edit-preview__body {
    min-height: 180px;
    max-width: 100%;
    line-height: 1.65;
    overflow-wrap: anywhere;
  }
  .admin-cms-edit-preview__body > :first-child {
    margin-top: 0;
  }
  .admin-cms-edit-preview__body > :last-child {
    margin-bottom: 0;
  }
  .admin-cms-edit-page .admin-btn--primary,
  .admin-cms-edit-page .admin-btn--secondary {
    background-image: none;
  }
  .admin-cms-edit-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 1040px) {
    .admin-cms-edit-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 768px) {
    .page-cms-edit .container {
      max-width: 100%;
    }
    .admin-cms-edit-page .admin-page-header,
    .admin-cms-edit-page .admin-panel--padded {
      padding: 16px;
    }
    .admin-cms-edit-fields {
      grid-template-columns: minmax(0, 1fr);
    }
    .admin-cms-edit-content {
      min-height: 320px;
      max-height: none;
      font-size: 0.95rem;
    }
  }
  @media (max-width: 430px) {
    .admin-cms-edit-meta__chip,
    .admin-cms-edit-page .admin-page-header__actions,
    .admin-cms-edit-actions,
    .admin-cms-edit-actions .admin-btn,
    .admin-cms-edit-page .admin-page-header__actions .admin-btn {
      width: 100%;
    }
    .admin-cms-edit-actions .admin-btn,
    .admin-cms-edit-page .admin-page-header__actions .admin-btn {
      justify-content: center;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-cms-edit-reveal'));
    if (!revealNodes.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || window.innerWidth <= 768) {
      revealNodes.forEach(function (node) {
        node.classList.add('is-visible');
        node.style.transitionDelay = '0ms';
      });
      return;
    }

    var revealObserver = new IntersectionObserver(function (entries, observer) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.14 });

    revealNodes.forEach(function (node, index) {
      node.style.transitionDelay = Math.min(index * 45, 220) + 'ms';
      revealObserver.observe(node);
    });
  });
</script>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-cms-edit-page">
        <div class="admin-page-header admin-cms-edit-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Contenus éditoriaux</p>
            <h1 class="admin-page-header__title"><?php echo e($isEditing ? 'Modifier une page CMS' : 'Créer une page CMS'); ?></h1>
            <p class="admin-page-header__subtitle"><?php echo e($values['title'] !== '' ? $values['title'] : 'Nouvelle page éditoriale'); ?></p>
            <div class="admin-cms-edit-meta" aria-label="Contexte page CMS">
              <?php if ($values['key_name'] !== ''): ?>
                <span class="admin-cms-edit-meta__chip"><strong>Clé</strong> <?php echo e($values['key_name']); ?></span>
              <?php endif; ?>
              <?php if ($values['slug'] !== ''): ?>
                <span class="admin-cms-edit-meta__chip"><strong>Slug</strong> <?php echo e($values['slug']); ?></span>
              <?php endif; ?>
              <span class="<?php echo e($publishedClass); ?>"><?php echo e($publishedLabel); ?></span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/pages.php')); ?>">
              <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
            </a>
            <?php if ($publicUrl !== '' && $isEditing): ?>
              <a class="btn admin-btn admin-btn--primary" href="<?php echo e($publicUrl); ?>" target="_blank" rel="noopener noreferrer">Voir la page</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-cms-edit-reveal is-visible" role="alert">
            <strong>Merci de corriger :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" class="admin-cms-edit-form" novalidate>
          <?php echo csrf_field(); ?>
          <input type="hidden" name="page_id" value="<?php echo e($values['id']); ?>">

          <div class="admin-cms-edit-grid">
            <div class="admin-cms-edit-stack">
              <section class="admin-panel admin-panel--padded admin-cms-edit-section admin-cms-edit-reveal" aria-labelledby="cmsMainTitle">
                <div class="admin-cms-edit-section__head">
                  <div>
                    <span class="admin-cms-edit-kicker">Page</span>
                    <h2 id="cmsMainTitle" class="admin-cms-edit-section__title">Contenu principal</h2>
                    <p class="admin-cms-edit-section__text">Titre, clé technique, slug public et contenu HTML de la page.</p>
                  </div>
                </div>

                <div class="admin-cms-edit-fields">
                  <div class="admin-cms-edit-field admin-cms-edit-field--full">
                    <label class="admin-field-label" for="title">Titre *</label>
                    <input id="title" name="title" class="admin-field" required value="<?php echo e($values['title']); ?>">
                  </div>

                  <div class="admin-cms-edit-field">
                    <label class="admin-field-label" for="key_name">Clé CMS *</label>
                    <input id="key_name" name="key_name" class="admin-field" required value="<?php echo e($values['key_name']); ?>" placeholder="mentions-legales">
                    <div class="admin-help">Utilisez une clé stable pour les pages déjà reliées au front.</div>
                  </div>

                  <div class="admin-cms-edit-field">
                    <label class="admin-field-label" for="slug">Slug public</label>
                    <input id="slug" name="slug" class="admin-field" value="<?php echo e($values['slug']); ?>" placeholder="mentions-legales">
                    <div class="admin-help">Si vide, le slug reprend automatiquement la clé CMS.</div>
                  </div>
                </div>
              </section>

              <section class="admin-panel admin-panel--padded admin-cms-edit-section admin-cms-edit-reveal" aria-labelledby="cmsContentTitle">
                <div class="admin-cms-edit-section__head">
                  <div>
                    <span class="admin-cms-edit-kicker">HTML</span>
                    <h2 id="cmsContentTitle" class="admin-cms-edit-section__title">Contenu HTML</h2>
                    <p class="admin-cms-edit-section__text">Le HTML est sanitizé à l'affichage pour éviter le JavaScript et les attributs risqués.</p>
                  </div>
                </div>

                <div class="admin-cms-edit-field">
                  <label class="sr-only" for="content">Contenu HTML</label>
                  <textarea id="content" name="content" class="admin-field admin-textarea admin-cms-edit-content" rows="18"><?php echo e($values['content']); ?></textarea>
                </div>
              </section>
            </div>

            <aside class="admin-cms-edit-stack" aria-label="Publication et SEO">
              <section class="admin-panel admin-panel--padded admin-cms-edit-section admin-cms-edit-reveal" aria-labelledby="cmsPublishTitle">
                <div class="admin-cms-edit-section__head">
                  <div>
                    <span class="admin-cms-edit-kicker">Statut</span>
                    <h2 id="cmsPublishTitle" class="admin-cms-edit-section__title">Publication</h2>
                    <p class="admin-cms-edit-section__text">Contrôle de visibilité de la page CMS.</p>
                  </div>
                  <span class="<?php echo e($publishedClass); ?>"><?php echo e($publishedLabel); ?></span>
                </div>

                <label class="admin-cms-edit-toggle">
                  <input type="checkbox" name="is_published" value="1" <?php echo $values['is_published'] === '1' ? 'checked' : ''; ?>>
                  <span class="admin-cms-edit-toggle__body">
                    <span class="admin-cms-edit-toggle__title">Publiée</span>
                    <span class="admin-help">Conserve le même champ de publication.</span>
                  </span>
                </label>
              </section>

              <section class="admin-panel admin-panel--padded admin-cms-edit-section admin-cms-edit-reveal" aria-labelledby="cmsSeoTitle">
                <div class="admin-cms-edit-section__head">
                  <div>
                    <span class="admin-cms-edit-kicker">SEO</span>
                    <h2 id="cmsSeoTitle" class="admin-cms-edit-section__title">Métadonnées</h2>
                    <p class="admin-cms-edit-section__text">Titre, description et image de partage associés à la page.</p>
                  </div>
                </div>

                <div class="admin-cms-edit-side-fields">
                  <div class="admin-cms-edit-field">
                    <label class="admin-field-label" for="seo_title">SEO title</label>
                    <input id="seo_title" name="seo_title" class="admin-field" value="<?php echo e($values['seo_title']); ?>">
                  </div>

                  <div class="admin-cms-edit-field">
                    <label class="admin-field-label" for="seo_description">SEO description</label>
                    <textarea id="seo_description" name="seo_description" class="admin-field admin-textarea" rows="4"><?php echo e($values['seo_description']); ?></textarea>
                  </div>

                  <div class="admin-cms-edit-field">
                    <label class="admin-field-label" for="og_image">Image OG</label>
                    <input id="og_image" name="og_image" class="admin-field" value="<?php echo e($values['og_image']); ?>" placeholder="/uploads/... ou https://...">
                  </div>
                </div>
              </section>

              <div class="admin-cms-edit-actions admin-cms-edit-reveal is-visible">
                <button class="btn admin-btn admin-btn--primary" type="submit"><?php echo e($isEditing ? 'Enregistrer les modifications' : 'Créer la page'); ?></button>
              </div>
            </aside>
          </div>
        </form>

        <section class="admin-panel admin-panel--padded admin-cms-edit-section admin-cms-edit-reveal" aria-label="Prévisualisation">
          <div class="admin-cms-edit-section__head">
            <div>
              <span class="admin-cms-edit-kicker">Aperçu</span>
              <h2 class="admin-cms-edit-section__title">Prévisualisation sanitizée</h2>
              <p class="admin-cms-edit-section__text">Aperçu du rendu nettoyé, sans modifier le contenu enregistré.</p>
            </div>
          </div>
          <div class="cms-preview admin-cms-edit-preview__body">
            <?php echo $previewHtml !== '' ? $previewHtml : '<p class="admin-help">Aucun contenu à prévisualiser.</p>'; ?>
          </div>
        </section>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
