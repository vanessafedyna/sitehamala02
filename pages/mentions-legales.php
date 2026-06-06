<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';

$cms = cms_get_published_by_key('mentions-legales');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'Mentions legales');
  $page_css = 'pages/cms.css';
  $page_js = '';

 
  $page_seo_title = trim((string) ($cms['seo_title'] ?? ''));
  $page_meta_description = trim((string) ($cms['seo_description'] ?? ''));
  if ($page_meta_description === '') {
    $page_meta_description = 'Informations legales relatives a l\'exploitation de la boutique en ligne au Mali.';
  }
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
$page_title = 'Mentions legales';
$page_meta_description = 'Informations legales relatives a l\'exploitation de la boutique en ligne au Mali.';
$page_css = 'pages/cms.css';
$page_js = '';
include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>Mentions legales</h1>
      <p class="subtitle">Informations legales et coordonnees utiles relatives a l'exploitation de cette boutique en ligne.</p>
    </div>
  </section>

  <section class="cms-body">
    <div class="container">
      <div class="card cms-content">
        <h2>Editeur du site</h2>
        <p>Le present site e-commerce est edite par une societe de forme <strong>SARL</strong>, etablie a <strong>Torokorobougou, en face du terrain de la Commune V</strong>.</p>
        <p>Contact telephonique : <strong>92828271</strong><br>Adresse email : aucune adresse email de contact n'est actuellement publiee sur cette page.</p>
        <p>Immatriculation / RCCM : <strong>ML.BKO.01.2026.B.3127</strong><br>NIF : <strong>425900105010001C0001P</strong></p>
        <p>Responsable de la publication : l'information n'est pas communiquee sur cette page.</p>

        <h2>Activite du site</h2>
        <p>Le site a pour objet la presentation et la vente en ligne de vetements, accessoires de mode et articles associes destines a une clientele situee notamment au Mali.</p>
        <p>Les produits proposes sur le site le sont dans la limite des stocks disponibles. Les descriptifs, visuels, tailles, coloris et disponibilites sont presentes avec le plus grand soin, sous reserve d'eventuelles mises a jour, erreurs ponctuelles ou ecarts mineurs de presentation.</p>
        <p>Les prix affiches sur le site sont indiques en <strong>FCFA</strong>. Les modalites applicables a la commande, au paiement, a la livraison et aux retours peuvent etre precisees dans les pages dediees du site lorsqu'elles sont disponibles.</p>

        <h2>Hebergement</h2>
        <p>Les informations relatives a l'hebergeur ne sont pas detaillees sur cette page.</p>

        <h2>Contact</h2>
        <p>Pour toute question relative a une commande, a un produit, a une livraison, a un retour ou a l'utilisation du site, vous pouvez contacter notre service client aux coordonnees suivantes :</p>
        <p>Telephone : <strong>92828271</strong><br>Adresse de contact : <strong>Torokorobougou, en face du terrain de la Commune V</strong><br>Adresse email : <strong><?php echo e(public_contact_email()); ?></strong></p>
        <p>Nous nous efforcons de repondre aux demandes dans les meilleurs delais ouvres.</p>

        <h2>Propriete intellectuelle</h2>
        <p>L'ensemble des elements figurant sur ce site, notamment les textes, descriptions, photographies, visuels, illustrations, logos, elements graphiques, charte visuelle, ainsi que la structure generale du site, est protege par les regles applicables en matiere de propriete intellectuelle.</p>
        <p>Sauf autorisation ecrite prealable de l'editeur du site, toute reproduction, representation, adaptation, diffusion, exploitation ou utilisation, totale ou partielle, de l'un quelconque de ces elements est interdite.</p>

        <h2>Responsabilite</h2>
        <p>L'editeur du site met en oeuvre des efforts raisonnables pour assurer l'exactitude et la mise a jour des informations publiees sur le site. Toutefois, des erreurs, omissions, indisponibilites temporaires ou interruptions techniques peuvent survenir.</p>
        <p>L'editeur ne saurait etre tenu responsable des dommages resultant d'une indisponibilite du site, d'une information erronee, d'un usage inadapte du site par l'utilisateur, ou de tout evenement exterieur ne relevant pas directement de son controle.</p>

        <h2>Donnees personnelles</h2>
        <p>Dans le cadre de l'utilisation du site, certaines donnees personnelles peuvent etre collectees, notamment lors de la creation d'un compte, du passage d'une commande, d'une demande de contact ou d'un echange avec le service client.</p>
        <p>Ces donnees sont principalement utilisees pour la gestion des commandes, le suivi de la relation client, l'organisation de la livraison, la gestion du service apres-vente ainsi que l'amelioration generale du service propose sur le site.</p>
        <p>Les donnees collectees sont accessibles uniquement aux personnes habilitees au sein de la societe exploitante ainsi qu'aux prestataires strictement necessaires au traitement de la commande, du paiement, de la livraison ou de l'assistance client.</p>
        <p>Tout utilisateur peut demander l'acces a ses donnees, leur rectification ou, lorsque cela est applicable, leur suppression, en contactant le service client par telephone au <strong>92828271</strong> ou a l'adresse de contact indiquee sur cette page.</p>
        <p>Pour des informations plus detaillees sur le traitement des donnees personnelles, une politique de confidentialite dediee peut etre mise a disposition sur le site.</p>

        <h2>Liens utiles</h2>
        <p>Pour completer ces informations, vous pouvez egalement consulter la <a href="<?php echo e(base_url('pages/politique-confidentialite.php')); ?>">politique de confidentialite</a>, les <a href="<?php echo e(base_url('pages/conditions-generales-vente.php')); ?>">conditions generales de vente</a>, la <a href="<?php echo e(base_url('pages/livraison.php')); ?>">politique de livraison</a> et la <a href="<?php echo e(base_url('pages/retours.php')); ?>">politique de retour</a>.</p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
