<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CategoryModel.php';
require_once __DIR__ . '/../app/services/CategoryImageService.php';
require_once __DIR__ . '/../app/services/AdminAuditService.php';

$page_title = 'Admin - Ajouter une catégorie';
$page_css = 'pages/admin-products.css';
$page_js = '';

$errors = array();
$values = array(
  'name' => '',
  'slug' => '',
  'description' => '',
  'sort_order' => '0',
  'is_active' => '1',
  'seo_title' => '',
  'seo_description' => '',
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_verify($_POST['_csrf'] ?? null)) {
    $errors[] = 'Session expirée. Veuillez réessayer.';
  } else {
    $values['name'] = trim((string) ($_POST['name'] ?? ''));
    $values['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $values['description'] = trim((string) ($_POST['description'] ?? ''));
    $values['sort_order'] = trim((string) ($_POST['sort_order'] ?? '0'));
    $values['is_active'] = isset($_POST['is_active']) ? '1' : '0';
    $values['seo_title'] = trim((string) ($_POST['seo_title'] ?? ''));
    $values['seo_description'] = trim((string) ($_POST['seo_description'] ?? ''));

    if ($values['name'] === '') $errors[] = 'Le nom est obligatoire.';

    if (!$errors) {
      $pdo = db();
      $model = new CategoryModel($pdo);
      if (!$model->exists()) {
        $errors[] = "Table `categories` manquante. Exécutez `database/patch_categories.sql`.";
      } else {
        $banner = null;
        $og = null;
        $saved = array();
        try {
          $fileBanner = $_FILES['banner_image'] ?? null;
          if ($fileBanner && is_array($fileBanner) && (int) ($fileBanner['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $banner = CategoryImageService::saveUploaded($fileBanner, 'category_banner');
            $saved[] = $banner;
          }
          $fileOg = $_FILES['og_image'] ?? null;
          if ($fileOg && is_array($fileOg) && (int) ($fileOg['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $og = CategoryImageService::saveUploaded($fileOg, 'category_og');
            $saved[] = $og;
          }

          $id = $model->create(array(
            'name' => $values['name'],
            'slug' => $values['slug'],
            'description' => $values['description'],
            'banner_image' => $banner,
            'sort_order' => (int) $values['sort_order'],
            'is_active' => (int) $values['is_active'],
            'seo_title' => $values['seo_title'],
            'seo_description' => $values['seo_description'],
            'og_image' => $og,
          ));

          $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
          AdminAuditService::log($pdo, $adminId, 'owner_created_category', 'category', (int) $id);

          admin_flash_set('categories', 'success', 'Catégorie créée.');
          redirect('admin/categories.php');
        } catch (Throwable $e) {
          foreach ($saved as $rel) {
            CategoryImageService::deleteIfLocal($rel);
          }
          $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Erreur lors de la création.';
        }
      }
    }
  }
}

require_once __DIR__ . '/_layout_header.php';
?>

<style>
  .admin-category-form-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-category-form-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-category-form-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-category-form-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-category-form-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-category-form-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-category-form-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-category-form-meta__chip {
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
  .admin-category-form-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-category-form {
    display: grid;
    gap: 16px;
  }
  .admin-category-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.85fr);
    gap: 16px;
    align-items: start;
  }
  .admin-category-form-stack {
    display: grid;
    gap: 16px;
    min-width: 0;
  }
  .admin-category-form-section {
    display: grid;
    gap: 18px;
    min-width: 0;
  }
  .admin-category-form-section__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
  }
  .admin-category-form-kicker {
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
  .admin-category-form-section__title {
    margin: 0;
    color: var(--admin-ink);
    font-size: 1.05rem;
    font-weight: 700;
  }
  .admin-category-form-section__text {
    margin: 6px 0 0;
    color: var(--admin-text-muted);
    line-height: 1.55;
  }
  .admin-category-form-fields {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .admin-category-form-side-fields {
    display: grid;
    gap: 16px;
  }
  .admin-category-form-field {
    min-width: 0;
    display: grid;
    gap: 8px;
  }
  .admin-category-form-field--full {
    grid-column: 1 / -1;
  }
  .admin-category-form-field .admin-field,
  .admin-category-form-field .admin-select,
  .admin-category-form-field .admin-textarea {
    width: 100%;
    min-width: 0;
    background-image: none;
  }
  .admin-category-form-field .admin-field[type="file"] {
    padding: 13px 14px;
    border-style: dashed;
    background: linear-gradient(180deg, rgba(250, 252, 250, 0.96), rgba(245, 249, 246, 0.98));
  }
  .admin-category-form-field .admin-field[type="file"]::file-selector-button {
    margin-right: 12px;
    padding: 9px 12px;
    border: 0;
    border-radius: 10px;
    background: rgba(31, 122, 79, 0.11);
    color: #28513d;
    font-weight: 700;
    cursor: pointer;
  }
  .admin-category-form-field .admin-field:focus,
  .admin-category-form-field .admin-select:focus,
  .admin-category-form-field .admin-textarea:focus,
  .admin-category-form-field .admin-field:focus-visible,
  .admin-category-form-field .admin-select:focus-visible,
  .admin-category-form-field .admin-textarea:focus-visible {
    outline: 0;
    border-color: rgba(31, 122, 79, 0.45);
    box-shadow: 0 0 0 4px rgba(31, 122, 79, 0.12);
  }
  .admin-category-form-toggle {
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
  .admin-category-form-toggle input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-top: 1px;
    accent-color: var(--admin-accent);
  }
  .admin-category-form-toggle__body {
    display: grid;
    gap: 4px;
    min-width: 0;
  }
  .admin-category-form-toggle__title {
    color: var(--admin-ink);
    font-weight: 700;
  }
  .admin-category-form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
  }
  .admin-category-form-page .admin-btn--primary,
  .admin-category-form-page .admin-btn--secondary {
    background-image: none;
  }
  .admin-category-form-page .admin-btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.16);
  }
  @media (max-width: 980px) {
    .admin-category-form-grid {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 768px) {
    .admin-category-form-page .admin-page-header,
    .admin-category-form-page .admin-panel--padded {
      padding: 16px;
    }
    .admin-category-form-fields {
      grid-template-columns: minmax(0, 1fr);
    }
  }
  @media (max-width: 430px) {
    .admin-category-form-meta__chip,
    .admin-category-form-page .admin-page-header__actions,
    .admin-category-form-actions,
    .admin-category-form-actions .admin-btn,
    .admin-category-form-page .admin-page-header__actions .admin-btn {
      width: 100%;
    }
    .admin-category-form-actions .admin-btn,
    .admin-category-form-page .admin-page-header__actions .admin-btn {
      justify-content: center;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var revealNodes = Array.prototype.slice.call(document.querySelectorAll('.admin-category-form-reveal'));
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
      <div class="admin-category-form-page">
        <div class="admin-page-header admin-category-form-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Catalogue</p>
            <h1 class="admin-page-header__title">Ajouter une catégorie</h1>
            <p class="admin-page-header__subtitle">Préparez une collection claire pour structurer la navigation et les pages catalogue.</p>
            <div class="admin-category-form-meta" aria-label="Contexte catégorie">
              <span class="admin-category-form-meta__chip"><strong>Type</strong> Collection</span>
              <span class="<?php echo $values['is_active'] === '1' ? 'admin-status-pill admin-status-pill--success' : 'admin-status-pill admin-status-pill--neutral'; ?>">
                <?php echo $values['is_active'] === '1' ? 'Active' : 'Inactive'; ?>
              </span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/categories.php')); ?>">
              <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
            </a>
          </div>
        </div>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-category-form-reveal is-visible" role="alert">
            <strong>Merci de corriger :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="admin-category-form" novalidate>
          <?php echo csrf_field(); ?>

          <div class="admin-category-form-grid">
            <div class="admin-category-form-stack">
              <section class="admin-panel admin-panel--padded admin-category-form-section admin-category-form-reveal" aria-labelledby="categoryMainTitle">
                <div class="admin-category-form-section__head">
                  <div>
                    <span class="admin-category-form-kicker">Essentiel</span>
                    <h2 id="categoryMainTitle" class="admin-category-form-section__title">Informations de base</h2>
                    <p class="admin-category-form-section__text">Nom, slug et ordre d'affichage de la catégorie.</p>
                  </div>
                </div>

                <div class="admin-category-form-fields">
                  <div class="admin-category-form-field admin-category-form-field--full">
                    <label class="admin-field-label" for="name">Nom *</label>
                    <input id="name" name="name" class="admin-field" required value="<?php echo e($values['name']); ?>">
                  </div>

                  <div class="admin-category-form-field">
                    <label class="admin-field-label" for="slug">Slug (optionnel)</label>
                    <input id="slug" name="slug" class="admin-field" value="<?php echo e($values['slug']); ?>" placeholder="homme, nouveautes...">
                    <div class="admin-help">Si vide, généré automatiquement.</div>
                  </div>

                  <div class="admin-category-form-field">
                    <label class="admin-field-label" for="sort_order">Ordre</label>
                    <input id="sort_order" name="sort_order" type="number" class="admin-field" value="<?php echo e($values['sort_order']); ?>">
                  </div>
                </div>
              </section>

              <section class="admin-panel admin-panel--padded admin-category-form-section admin-category-form-reveal" aria-labelledby="categoryDescriptionTitle">
                <div class="admin-category-form-section__head">
                  <div>
                    <span class="admin-category-form-kicker">Contenu</span>
                    <h2 id="categoryDescriptionTitle" class="admin-category-form-section__title">Description et visuels</h2>
                    <p class="admin-category-form-section__text">Texte de présentation et image de bannière associée à la collection.</p>
                  </div>
                </div>

                <div class="admin-category-form-fields">
                  <div class="admin-category-form-field admin-category-form-field--full">
                    <label class="admin-field-label" for="description">Description</label>
                    <textarea id="description" name="description" class="admin-field admin-textarea" rows="4"><?php echo e($values['description']); ?></textarea>
                  </div>

                  <div class="admin-category-form-field admin-category-form-field--full">
                    <label class="admin-field-label" for="banner_image">Bannière (jpg/png/webp, 2MB max)</label>
                    <input id="banner_image" name="banner_image" type="file" class="admin-field" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                  </div>
                </div>
              </section>
            </div>

            <aside class="admin-category-form-stack" aria-label="Publication et SEO">
              <section class="admin-panel admin-panel--padded admin-category-form-section admin-category-form-reveal" aria-labelledby="categoryPublishTitle">
                <div class="admin-category-form-section__head">
                  <div>
                    <span class="admin-category-form-kicker">Statut</span>
                    <h2 id="categoryPublishTitle" class="admin-category-form-section__title">Publication</h2>
                    <p class="admin-category-form-section__text">Contrôle la visibilité de la catégorie.</p>
                  </div>
                </div>

                <label class="admin-category-form-toggle">
                  <input type="checkbox" name="is_active" value="1" <?php echo $values['is_active'] === '1' ? 'checked' : ''; ?>>
                  <span class="admin-category-form-toggle__body">
                    <span class="admin-category-form-toggle__title">Active</span>
                    <span class="admin-help">Conserve le même champ de statut.</span>
                  </span>
                </label>
              </section>

              <section class="admin-panel admin-panel--padded admin-category-form-section admin-category-form-reveal" aria-labelledby="categorySeoTitle">
                <div class="admin-category-form-section__head">
                  <div>
                    <span class="admin-category-form-kicker">SEO</span>
                    <h2 id="categorySeoTitle" class="admin-category-form-section__title">Métadonnées</h2>
                    <p class="admin-category-form-section__text">Titre, description et image de partage.</p>
                  </div>
                </div>

                <div class="admin-category-form-side-fields">
                  <div class="admin-category-form-field">
                    <label class="admin-field-label" for="seo_title">SEO title</label>
                    <input id="seo_title" name="seo_title" class="admin-field" value="<?php echo e($values['seo_title']); ?>">
                  </div>

                  <div class="admin-category-form-field">
                    <label class="admin-field-label" for="seo_description">SEO description</label>
                    <textarea id="seo_description" name="seo_description" class="admin-field admin-textarea" rows="3"><?php echo e($values['seo_description']); ?></textarea>
                  </div>

                  <div class="admin-category-form-field">
                    <label class="admin-field-label" for="og_image">OG image (optionnel)</label>
                    <input id="og_image" name="og_image" type="file" class="admin-field" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                  </div>
                </div>
              </section>

              <div class="admin-category-form-actions admin-category-form-reveal is-visible">
                <button class="btn admin-btn admin-btn--primary" type="submit">Enregistrer</button>
              </div>
            </aside>
          </div>
        </form>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
