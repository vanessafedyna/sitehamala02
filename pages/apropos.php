<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';

/* CMS override (si publi?) */
$cms = cms_get_published_by_key('about');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'À propos');
  $page_css = 'pages/cms.css';
  $page_js = '';

  $page_seo_title = trim((string) ($cms['seo_title'] ?? ''));
  $page_meta_description = trim((string) ($cms['seo_description'] ?? ''));
  if ($page_meta_description === '') {
    $page_meta_description = 'Découvrez l’univers SORA Collection, nos collections et notre approche de la vente en ligne adaptée au Mali.';
  }
  $page_og_image = cms_asset_url($cms['og_image'] ?? '');
  $cms_html = cms_sanitize_html((string) ($cms['content'] ?? ''));

  /* si contenu CMS vide => fallback sur page "ancienne" (?vite une page blanche). */
  $cms_text = trim((string) preg_replace('/\s+/', ' ', strip_tags($cms_html)));
  if ($cms_text !== '') {
    include __DIR__ . '/../includes/header.php';
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
            <?php echo $cms_html; ?>
          </div>
        </div>
      </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <?php
    exit;
  }
}

$page_title = 'À propos';
$page_meta_description = 'Découvrez l’univers SORA Collection, nos collections et notre approche de la vente en ligne adaptée au Mali.';
$page_css = 'pages/apropos.css';
$page_js = 'pages/apropos.js';

include __DIR__ . '/../includes/header.php';
?>

<main class="about-page" id="main">
  <section class="hero" aria-labelledby="page-title">
    <div class="container hero-grid">
      <div class="hero-copy">
        <p class="kicker">À propos</p>
        <h1 id="page-title">À propos de SORA Collection</h1>
        <p class="subtitle">Une boutique en ligne pensée pour le Mali, avec un suivi fiable.</p>

        <div class="hero-actions">
          <a class="btn btn-primary" href="<?php echo $base_url; ?>pages/catalogue.php">Découvrir le catalogue</a>
          <a class="btn btn-outline" href="<?php echo $base_url; ?>pages/contact.php">Nous contacter</a>
        </div>

        <p class="hero-note">
          Paiement à la livraison ? simple, clair et rassurant. Livraison organisée avec téléphone, quartier et point de repère.
        </p>
      </div>

      <div class="hero-media" aria-hidden="true">
        <div class="media-placeholder">
          <div class="media-shine"></div>
          <div class="media-label">Visuel de collection</div>
        </div>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="mission-vision">
    <div class="container">
      <h2 id="mission-vision" class="section-title">Mission, Vision &amp; Valeurs</h2>
      <div class="grid grid-3">
        <article class="card">
          <h3 class="card-title">Mission</h3>
          <p class="muted">Notre mission est d'offrir des vêtements alliant qualité, élégance et accessibilité, en proposant à la fois des tenues traditionnelles et modernes adaptées à toutes les occasions du quotidien aux événements majeurs tout en valorisant le savoir-faire local et l'identité culturelle malienne.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Vision</h3>
          <p class="muted">Notre vision est de devenir la marque vestimentaire familiale de référence au Mali, reconnue pour son excellence, sa diversité de collections et sa capacité à unir tradition et modernité, puis d'étendre notre présence en Afrique en bâtissant une marque forte, durable et respectée.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Valeurs</h3>
          <p class="muted">Nous plaçons au cœur de notre entreprise l'excellence dans la qualité, le respect du client, l'intégrité dans nos pratiques, la valorisation de la culture malienne et la contribution au développement économique local, afin de construire une marque responsable, inspirante et durable pour les générations futures.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="why-us">
    <div class="container">
      <h2 id="why-us" class="section-title">Pourquoi nous ?</h2>
      <div class="grid grid-3">
        <article class="card">
          <h3 class="card-title">Adapté au Mali</h3>
          <p class="muted">Livraison basée sur téléphone, ville, quartier et point de repère ? sans jargon.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Paiement à la livraison</h3>
          <p class="muted">Vous payez uniquement à la réception. Une approche simple et rassurante.</p>
        </article>
        <article class="card">
          <h3 class="card-title">Livraison sécurisée</h3>
          <p class="muted">Confirmation à la réception et suivi clair des Étapes de votre commande.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="section" aria-labelledby="how-it-works">
    <div class="container">
      <h2 id="how-it-works" class="section-title">Comment ça marche ?</h2>
      <ol class="steps" aria-label="Étapes de commande">
        <li class="step">
          <div class="step-title">Choisissez vos produits</div>
          <div class="muted">Parcourez le catalogue et ouvrez une fiche produit.</div>
        </li>
        <li class="step">
          <div class="step-title">Passez commande en ligne</div>
          <div class="muted">Renseignez vos informations de livraison.</div>
        </li>
        <li class="step">
          <div class="step-title">Un partenaire vous contacte</div>
          <div class="muted">Validation par téléphone si nécessaire, selon votre ville/quartier.</div>
        </li>
        <li class="step">
          <div class="step-title">Livraison + paiement à la réception</div>
          <div class="muted">Vous payez à la livraison, en toute simplicité.</div>
        </li>
      </ol>
    </div>
  </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
