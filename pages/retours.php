<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';

$cms = cms_get_published_by_key('retours');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'Retours');
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
$page_title = 'Retours';
$page_meta_description = 'Informations sur les retours, les remboursements et l annulation de commande.';
$page_css = 'pages/cms.css';
$page_js = '';
include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>Retours</h1>
      <p class="subtitle">Informations sur les retours, les remboursements et les annulations applicables a la boutique.</p>
    </div>
  </section>
  <section class="cms-body">
    <div class="container">
      <div class="card cms-content">
        <h2>Introduction</h2>
        <p>Cette page presente les conditions applicables aux retours, remboursements et annulations de commande.</p>

        <h2>Conditions de retour</h2>
        <p>Les retours ne sont pas acceptes pour le moment.</p>

        <h2>Remboursements</h2>
        <p>Aucun remboursement n'est propose pour le moment.</p>

        <h2>Reclamations</h2>
        <p>En cas de probleme relatif a un article recu ou a une erreur de commande, le client est invite a contacter rapidement le service client afin qu'une verification puisse etre effectuee.</p>

        <h2>Produits non repris</h2>
        <p>Aucun produit n'est repris pour le moment dans le cadre d'un retour standard.</p>

        <h2>Frais de retour</h2>
        <p>Les frais de retour ne s'appliquent pas dans le cadre d'un retour standard, les retours n'etant pas acceptes pour le moment.</p>

        <h2>Procedure de demande</h2>
        <p>Le client doit contacter le service client en indiquant son numero de commande, le produit concerne et le motif de sa demande. Aucune expedition ne doit etre effectuee sans instruction prealable de la boutique.</p>

        <h2>Annulation de commande</h2>
        <p>Une annulation de commande peut etre demandee avant la livraison.</p>

        <h2>Contact</h2>
        <p>Pour toute demande relative a une annulation ou a une reclamation, le client peut contacter la boutique par telephone au <strong>92828271</strong>, a l'adresse <strong>Torokorobougou, en face du terrain de la Commune V</strong> ou par email a <strong><?php echo e(public_support_email()); ?></strong>.</p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
