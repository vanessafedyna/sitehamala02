<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';

$cms = cms_get_published_by_key('conditions-generales-vente');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'Conditions generales de vente');
  $page_css = 'pages/cms.css';
  $page_js = '';

 
  $page_seo_title = trim((string) ($cms['seo_title'] ?? ''));
  $page_meta_description = trim((string) ($cms['seo_description'] ?? ''));
  if ($page_meta_description === '') {
    $page_meta_description = 'Conditions generales de vente : commande, paiement, livraison et retours au Mali.';
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
$page_title = 'Conditions generales de vente';
$page_meta_description = 'Conditions generales de vente : commande, paiement, livraison et retours au Mali.';
$page_css = 'pages/cms.css';
$page_js = '';
include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>Conditions generales de vente</h1>
      <p class="subtitle">Conditions applicables aux commandes passees sur cette boutique en ligne de vetements et accessoires.</p>
    </div>
  </section>

  <section class="cms-body">
    <div class="container">
      <div class="card cms-content">
        <h2>Objet</h2>
        <p>Les presentes conditions generales de vente encadrent les ventes realisees sur ce site par une societe de forme <strong>SARL</strong> aupres de toute personne souhaitant acheter des vetements, accessoires de mode ou articles associes proposes en ligne.</p>
        <p>En validant une commande sur le site, le client reconnait avoir pris connaissance des presentes conditions et les accepter dans leur version en vigueur au moment de l'achat.</p>

        <h2>Produits</h2>
        <p>Le site presente a la vente des vetements, accessoires et articles de mode destines a une clientele situee notamment au Mali. Les produits sont decrits avec le plus grand soin possible au moyen de textes, visuels, tailles, coloris et informations utiles.</p>
        <p>Les offres restent valables dans la limite des stocks disponibles. En cas d'indisponibilite apres validation d'une commande, le client sera informe dans les meilleurs delais afin de convenir d'une solution appropriee.</p>

        <h2>Prix</h2>
        <p>Les prix des produits sont indiques en <strong>FCFA</strong>. Sauf indication contraire, ils correspondent au prix applicable au moment de la commande. Les frais eventuels de livraison, lorsqu'ils s'appliquent, sont precises avant la validation finale de la commande.</p>
        <p>La boutique se reserve le droit de modifier ses prix a tout moment, sans effet retroactif sur les commandes deja confirmees.</p>

        <h2>Commande</h2>
        <p>Le client selectionne les produits qu'il souhaite acheter, renseigne les informations necessaires au traitement de sa commande puis valide son achat selon le parcours propose sur le site.</p>
        <p>La commande devient effective apres confirmation par le site et, le cas echeant, apres validation du paiement selon le mode choisi. La boutique se reserve le droit de refuser ou d'annuler une commande en cas d'information manifestement erronee, de suspicion de fraude, d'incident de paiement ou de difficulte serieuse d'execution.</p>

        <h2>Paiement</h2>
        <p>Les modalites de paiement proposees sur le site sont indiquees lors de la commande. Le client s'engage a fournir des informations exactes et a etre autorise a utiliser le moyen de paiement selectionne.</p>
        <p>En cas d'echec ou de refus du paiement, la commande peut etre suspendue, annulee ou mise en attente jusqu'a regularisation.</p>

        <h2>Livraison</h2>
        <p>Les produits commandes sont livres a l'adresse communiquee par le client lors de la commande. Le client est responsable de l'exactitude des informations de livraison fournies.</p>
        <p>Les produits sont livrables sur l'ensemble du Mali. A Bamako, la livraison est assuree le jour meme. Hors Bamako, la livraison intervient sous 48 h. Hors Mali, la livraison intervient sous 72 h. Les livraisons hors Afrique sont assurees avec GP. Pour plus de details, le client peut consulter la <a href="<?php echo e(base_url('pages/livraison.php')); ?>">politique de livraison</a>.</p>

        <h2>Retours et reclamations</h2>
        <p>Les retours ne sont pas acceptes pour le moment et aucun remboursement n'est propose pour le moment. En cas de question relative a un article recu ou a une erreur de commande, le client est invite a contacter rapidement le service client.</p>
        <p>Une annulation de commande peut etre demandee avant la livraison. Les informations complementaires applicables aux retours et annulations sont precisees dans la <a href="<?php echo e(base_url('pages/retours.php')); ?>">politique de retour</a>.</p>

        <h2>Responsabilite</h2>
        <p>La boutique met en oeuvre des efforts raisonnables pour assurer la disponibilite du site, la fiabilite des informations presentees et la bonne execution des commandes. Toutefois, sa responsabilite ne saurait etre engagee en cas d'indisponibilite temporaire du site, d'erreur non substantielle, de force majeure ou d'evenement exterieur echappant a son controle.</p>
        <p>Le client demeure responsable de l'utilisation qu'il fait du site ainsi que de la verification des informations qu'il saisit au moment de la commande.</p>

        <h2>Donnees personnelles</h2>
        <p>Les informations collectees dans le cadre d'une commande ou d'un contact client sont utilisees pour la gestion des achats, la livraison, le suivi de la relation commerciale et le service apres-vente. Pour en savoir plus, le client peut consulter la <a href="<?php echo e(base_url('pages/politique-confidentialite.php')); ?>">politique de confidentialite</a>.</p>

        <h2>Contact</h2>
        <p>Pour toute question relative a une commande, a un produit, a une livraison, a un retour ou aux presentes conditions generales de vente, le client peut contacter le service client :</p>
        <p>Telephone : <strong>92828271</strong><br>Adresse : <strong>Torokorobougou, en face du terrain de la Commune V</strong><br>Adresse email : <strong><?php echo e(public_support_email()); ?></strong></p>

        <h2>Informations complementaires</h2>
        <p>Le client peut egalement consulter les <a href="<?php echo e(base_url('pages/mentions-legales.php')); ?>">mentions legales</a> pour obtenir des informations complementaires sur l'editeur du site et l'utilisation generale de la boutique.</p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
