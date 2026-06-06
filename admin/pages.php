<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireRole('owner');
require_once __DIR__ . '/_flash.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/CmsPageModel.php';

$page_title = 'Admin - Pages CMS';
$page_css = 'pages/admin-products.css';
$page_js = '';

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

function cms_admin_public_url(array $page): string
{
  $key = trim((string) ($page['key_name'] ?? ''));
  $slug = trim((string) ($page['slug'] ?? ''));

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

function cms_admin_normalize_page_row(array $row, array $fallback = array()): array
{
  $key = trim((string) ($row['key_name'] ?? $row['key'] ?? ($fallback['key_name'] ?? '')));
  $title = trim((string) ($row['title'] ?? ($fallback['title'] ?? '')));
  $slug = trim((string) ($row['slug'] ?? ($fallback['slug'] ?? '')));

  if ($title === '' && $key !== '') {
    $title = (string) ($fallback['title'] ?? $key);
  }

  if ($slug === '' && $key !== '') {
    $slug = (string) ($fallback['slug'] ?? $key);
  }

  return array(
    'id' => (int) ($row['id'] ?? 0),
    'key_name' => $key,
    'title' => $title,
    'slug' => $slug,
    'content' => (string) ($row['content'] ?? ''),
    'is_published' => (int) ($row['is_published'] ?? 0),
    'updated_at' => (string) ($row['updated_at'] ?? ''),
    '__missing' => (int) ($row['__missing'] ?? 0),
  );
}

$flash = admin_flash_get('pages');
$errors = array();
$pageItems = array();

try {
  $pdo = db();
  $model = new CmsPageModel($pdo);

  if (!$model->exists()) {
    $errors[] = "Table `pages` manquante. Exécutez `database/patch_cms_pages.sql`.";
  } else {
    $catalog = cms_admin_page_catalog();
    $rows = $model->listAll();
    $indexed = array();

    foreach ($rows as $row) {
      $normalized = cms_admin_normalize_page_row($row);
      $key = (string) ($normalized['key_name'] ?? '');
      if ($key === '') {
        continue;
      }
      $indexed[$key] = $normalized;
    }

    foreach ($catalog as $key => $spec) {
      $fallback = array(
        'key_name' => $key,
        'title' => (string) ($spec['title'] ?? $key),
        'slug' => (string) ($spec['slug'] ?? $key),
      );

      if (isset($indexed[$key])) {
        $pageItems[] = cms_admin_normalize_page_row($indexed[$key], $fallback);
        unset($indexed[$key]);
        continue;
      }

      $pageItems[] = cms_admin_normalize_page_row(array(
        'id' => 0,
        'key_name' => $key,
        'title' => (string) ($spec['title'] ?? $key),
        'slug' => (string) ($spec['slug'] ?? $key),
        'content' => '',
        'is_published' => 0,
        'updated_at' => '',
        '__missing' => 1,
      ), $fallback);
    }

    if ($indexed) {
      foreach ($indexed as $row) {
        $pageItems[] = cms_admin_normalize_page_row($row);
      }
    }
  }
} catch (Throwable $e) {
  $errors[] = 'Impossible de charger les pages CMS (base de données).';
}

require_once __DIR__ . '/_layout_header.php';
?>

<style>
  .admin-cms-page {
    display: grid;
    gap: 16px;
    overflow-x: clip;
  }
  .admin-cms-reveal {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 220ms ease, transform 220ms ease;
  }
  .admin-cms-reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
  }
  .admin-cms-page .admin-page-header {
    align-items: flex-start;
    gap: 18px;
  }
  .admin-cms-page .admin-page-header__actions {
    justify-content: flex-end;
  }
  .admin-cms-page .admin-alert {
    margin-bottom: 0;
  }
  .admin-cms-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }
  .admin-cms-meta__chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.78);
    color: var(--admin-text-muted);
    font-size: 0.84rem;
  }
  .admin-cms-meta__chip strong {
    color: var(--admin-ink);
  }
  .admin-cms-table-panel {
    overflow: hidden;
  }
  .admin-cms-table-shell {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
  }
  .admin-cms-table-shell .admin-table {
    min-width: 760px;
  }
  .admin-cms-table-shell thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f7faf8;
  }
  .admin-cms-table-shell td,
  .admin-cms-table-shell th {
    padding-top: 14px;
    padding-bottom: 14px;
    vertical-align: middle;
  }
  .admin-cms-table-shell tbody tr {
    transition: background-color 140ms ease, box-shadow 140ms ease;
  }
  .admin-cms-table-shell tbody tr:hover {
    background: rgba(31, 122, 79, 0.035);
  }
  .admin-cms-title {
    display: grid;
    gap: 4px;
  }
  .admin-cms-title strong {
    color: var(--admin-ink);
    font-size: 0.96rem;
  }
  .admin-cms-title__meta {
    color: var(--admin-text-muted);
    font-size: 0.82rem;
  }
  .admin-cms-key {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 0.82rem;
    color: var(--admin-text-muted);
  }
  .admin-cms-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  .admin-cms-mobile-list {
    display: grid;
    gap: 14px;
  }
  .admin-cms-mobile-card {
    display: grid;
    gap: 14px;
  }
  .admin-cms-mobile-card .admin-mobile-card__header {
    align-items: flex-start;
    gap: 12px;
  }
  .admin-cms-mobile-card .admin-mobile-card__title {
    margin-bottom: 4px;
  }
  .admin-cms-mobile-card .admin-mobile-card__meta {
    color: var(--admin-text-muted);
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 0.8rem;
  }
  .admin-cms-mobile-card .admin-mobile-card__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .admin-cms-mobile-card .admin-btn {
    flex: 1 1 160px;
    justify-content: center;
  }
  @media (max-width: 1024px) {
    .admin-cms-page .admin-page-header {
      gap: 16px;
    }
  }
  @media (max-width: 768px) {
    .admin-cms-meta {
      gap: 8px;
    }
    .admin-cms-meta__chip {
      width: 100%;
      justify-content: space-between;
    }
  }
  @media (max-width: 520px) {
    .admin-cms-mobile-card .admin-btn {
      flex-basis: 100%;
    }
  }
</style>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-cms-page">
        <div class="admin-page-header admin-cms-reveal is-visible">
          <div class="admin-page-header__content">
            <p class="admin-page-header__eyebrow">Contenus éditoriaux</p>
            <h1 class="admin-page-header__title">Pages CMS</h1>
            <p class="admin-page-header__subtitle">Créez, modifiez et publiez les contenus éditoriaux du site depuis une vue plus claire, plus dense et plus homogène.</p>
            <div class="admin-cms-meta" aria-label="Indicateurs pages CMS">
              <span class="admin-cms-meta__chip"><strong><?php echo e((string) count($pageItems)); ?></strong> page(s) suivie(s)</span>
              <span class="admin-cms-meta__chip"><strong><?php echo e((string) count($errors)); ?></strong> alerte(s) active(s)</span>
            </div>
          </div>
          <div class="admin-page-header__actions">
            <a class="btn admin-btn admin-btn--primary" href="<?php echo e(base_url('admin/page_edit.php')); ?>">
              <i class="fas fa-plus" aria-hidden="true"></i> Nouvelle page
            </a>
            <a class="btn admin-btn admin-btn--secondary" href="<?php echo e(base_url('admin/index.php')); ?>">
              <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour dashboard
            </a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="admin-alert admin-alert--<?php echo e($flash['type']); ?> admin-panel admin-panel--padded admin-cms-reveal is-visible" role="status" aria-live="polite">
            <strong><?php echo e($flash['message']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="admin-alert admin-alert--error admin-panel admin-panel--padded admin-cms-reveal is-visible" role="alert">
            <strong>Erreur :</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else: ?>
          <div class="feature-card admin-panel admin-table-shell admin-table-wrap admin-desktop-only admin-cms-table-panel admin-cms-table-shell admin-cms-reveal is-visible" aria-label="Liste des pages CMS">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Titre</th>
                  <th>Clé</th>
                  <th>Statut</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pageItems as $item): ?>
                  <?php
                    $title = (string) ($item['title'] ?? '');
                    $key = (string) ($item['key_name'] ?? '');
                    $published = (int) ($item['is_published'] ?? 0) === 1;
                    $missing = (int) ($item['__missing'] ?? 0) === 1;
                    $editUrl = base_url('admin/page_edit.php?key=' . urlencode($key));
                    $publicUrl = cms_admin_public_url($item);
                  ?>
                  <tr>
                    <td>
                      <div class="admin-cms-title">
                        <strong><?php echo e($title !== '' ? $title : $key); ?></strong>
                        <span class="admin-cms-title__meta">Page éditoriale</span>
                      </div>
                    </td>
                    <td><span class="admin-cms-key"><?php echo e($key); ?></span></td>
                    <td>
                      <?php if ($published): ?>
                        <span class="admin-status-pill admin-status-pill--success">Publié</span>
                      <?php elseif ($missing): ?>
                        <span class="admin-status-pill admin-status-pill--neutral">À créer</span>
                      <?php else: ?>
                        <span class="admin-status-pill admin-status-pill--warning">Brouillon</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="admin-cms-actions">
                        <a class="btn admin-btn admin-btn--secondary" href="<?php echo e($editUrl); ?>">
                          <?php echo $missing ? 'Créer' : 'Modifier'; ?>
                        </a>
                        <?php if ($publicUrl !== '' && !$missing): ?>
                          <a class="btn admin-btn admin-btn--ghost" href="<?php echo e($publicUrl); ?>" target="_blank" rel="noopener noreferrer">Voir</a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="admin-mobile-only admin-cms-reveal is-visible" aria-label="Liste des pages CMS mobile">
            <div class="admin-mobile-cards admin-cms-mobile-list">
              <?php foreach ($pageItems as $item): ?>
                <?php
                  $title = (string) ($item['title'] ?? '');
                  $key = (string) ($item['key_name'] ?? '');
                  $published = (int) ($item['is_published'] ?? 0) === 1;
                  $missing = (int) ($item['__missing'] ?? 0) === 1;
                  $editUrl = base_url('admin/page_edit.php?key=' . urlencode($key));
                  $publicUrl = cms_admin_public_url($item);
                ?>
                <article class="admin-mobile-card admin-cms-mobile-card">
                  <div class="admin-mobile-card__header">
                    <div>
                      <h2 class="admin-mobile-card__title"><?php echo e($title !== '' ? $title : $key); ?></h2>
                      <div class="admin-mobile-card__meta"><?php echo e($key); ?></div>
                    </div>
                    <?php if ($published): ?>
                      <span class="admin-status-pill admin-status-pill--success">Publié</span>
                    <?php elseif ($missing): ?>
                      <span class="admin-status-pill admin-status-pill--neutral">À créer</span>
                    <?php else: ?>
                      <span class="admin-status-pill admin-status-pill--warning">Brouillon</span>
                    <?php endif; ?>
                  </div>

                  <div class="admin-mobile-card__actions">
                    <a class="btn admin-btn admin-btn--secondary" href="<?php echo e($editUrl); ?>">
                      <?php echo $missing ? 'Créer' : 'Modifier'; ?>
                    </a>
                    <?php if ($publicUrl !== '' && !$missing): ?>
                      <a class="btn admin-btn admin-btn--ghost" href="<?php echo e($publicUrl); ?>" target="_blank" rel="noopener noreferrer">Voir</a>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
