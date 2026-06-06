<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';

$cms = cms_get_published_by_key('livraison');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'Livraison');
  $page_css = 'pages/cms.css';
  $page_js = '';

 
  $page_seo_title = trim((string) ($cms['seo_title'] ?? ''));
  $page_meta_description = trim((string) ($cms['seo_description'] ?? ''));
  $page_og_image = cms_asset_url($cms['og_image'] ?? '');
  $cms_html = cms_sanitize_html((string) ($cms['content'] ?? ''));

  /* si contenu CMS vide => fallback sur placeholder (evite une page blanche). */
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

// Fallback simple (si DB non configuree)
$page_title = 'Livraison';
$page_meta_description = 'Informations sur la livraison, les zones desservies et les delais applicables.';
$page_css = 'pages/cms.css';
$page_js = '';
include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>Livraison</h1>
      <p class="subtitle">Informations sur les zones desservies et les delais de livraison applicables a la boutique.</p>
    </div>
  </section>
  <section class="cms-body">
    <div class="container">
      <div class="card cms-content">
        <h2>Introduction</h2>
        <p>Cette page presente les informations applicables a la livraison des commandes passees sur la boutique.</p>

        <h2>Zones de livraison</h2>
        <p>La boutique dessert l'ensemble du Mali. Des livraisons peuvent egalement etre organisees hors du Mali selon la destination. Les livraisons hors Afrique sont assurees avec GP.</p>

        <h2>Delais de livraison</h2>
        <p>A Bamako, la livraison est assuree le jour meme. Hors Bamako, la livraison intervient sous 48 h. Hors Mali, la livraison intervient sous 72 h, sous reserve de validation de la commande et de la disponibilite des articles.</p>

        <h2>Frais de livraison</h2>
        <p>Les frais de livraison ne sont pas detailes sur cette page. Ils sont communiques au client avant la finalisation de la commande lorsque cela est necessaire.</p>

        <h2>Paiement a la livraison</h2>
        <p>Les modalites de paiement applicables sont celles presentees lors de la commande. Le client est invite a verifier les informations communiquees avant validation.</p>

        <h2>Suivi / contact</h2>
        <p>Apres validation, le client peut etre contacte pour confirmer les informations utiles a la livraison. En cas de question sur le suivi ou les conditions de remise, il peut joindre la boutique par telephone au <strong>92828271</strong> ou par email a <strong><?php echo e(public_support_email()); ?></strong>.</p>

        <h2>Informations complementaires</h2>
        <p>Lorsque la destination, le volume de commande ou les contraintes logistiques l'exigent, des precisions complementaires peuvent etre communiquees au client avant expedition.</p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
