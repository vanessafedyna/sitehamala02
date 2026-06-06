<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';
require_once __DIR__ . '/../app/helpers/public_contact.php';

$cms = cms_get_published_by_key('politique-confidentialite');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'Politique de confidentialite');
  $page_css = 'pages/cms.css';
  $page_js = '';

 
  $page_seo_title = trim((string) ($cms['seo_title'] ?? ''));
  $page_meta_description = trim((string) ($cms['seo_description'] ?? ''));
  if ($page_meta_description === '') {
    $page_meta_description = 'Politique de confidentialite : collecte, utilisation et protection de vos donnees personnelles.';
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
$page_title = 'Politique de confidentialite';
$page_meta_description = 'Politique de confidentialite : collecte, utilisation et protection de vos donnees personnelles.';
$page_css = 'pages/cms.css';
$page_js = '';
include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>Politique de confidentialite</h1>
      <p class="subtitle">Informations relatives a la collecte, a l'utilisation et a la protection de vos donnees personnelles sur cette boutique en ligne.</p>
    </div>
  </section>

  <section class="cms-body">
    <div class="container">
      <div class="card cms-content">
        <h2>Introduction</h2>
        <p>La societe exploitante attache une importance particuliere a la protection de la vie privee et des donnees personnelles de ses clients et visiteurs. La presente politique de confidentialite explique de maniere simple comment les informations peuvent etre collectees et utilisees lors de la navigation sur le site, de la creation d'un compte, du passage d'une commande ou d'un echange avec le service client.</p>
        <p>Cette politique s'applique a l'ensemble des services proposes sur le site, en particulier a la consultation des produits, a l'achat en ligne, a la livraison, au suivi de commande et aux demandes adressees au support.</p>

        <h2>Donnees collectees</h2>
        <p>Selon votre utilisation du site, nous pouvons collecter differentes categories d'informations, notamment votre nom et prenom, votre numero de telephone, votre adresse email, votre adresse de livraison, ainsi que les informations necessaires au traitement de vos commandes.</p>
        <p>Peuvent egalement etre conserves les details lies a vos achats, l'historique de vos commandes, les messages envoyes au service client, ainsi que certaines donnees techniques de navigation utiles au bon fonctionnement du site, a la securite et a l'amelioration de l'experience utilisateur.</p>

        <h2>Finalites de collecte</h2>
        <p>Les donnees collectees sont utilisees principalement pour traiter les commandes, organiser la livraison, assurer la relation client, repondre aux demandes de support, gerer les retours lorsque cela est necessaire et vous informer du suivi de votre achat.</p>
        <p>Elles peuvent aussi etre utilisees pour ameliorer la qualite du service, faciliter votre navigation, mieux comprendre l'usage du site et prevenir les abus, tentatives de fraude ou utilisations contraires au bon fonctionnement de la boutique.</p>

        <h2>Base d'utilisation et cadre general</h2>
        <p>Les informations personnelles sont utilisees uniquement dans la mesure necessaire au fonctionnement normal du site, a l'execution des services proposes, a la gestion de la relation commerciale avec le client et au respect des obligations administratives, comptables ou legales applicables.</p>
        <p>Nous veillons a ne collecter et a ne traiter que les donnees utiles a ces finalites, dans un cadre proportionne a l'activite d'une boutique en ligne de vetements et accessoires.</p>

        <h2>Partage des donnees</h2>
        <p>Les donnees personnelles ne sont ni vendues, ni cedees a des tiers a des fins commerciales. Elles peuvent etre communiquees uniquement aux intervenants strictement necessaires au bon traitement du service, par exemple pour la livraison, le paiement si ce mode est propose, l'assistance technique ou l'hebergement du site.</p>
        <p>Elles peuvent egalement etre transmises lorsque la loi, une autorite competente ou une obligation reglementaire l'exige.</p>

        <h2>Duree de conservation</h2>
        <p>Les donnees sont conservees pendant la duree raisonnablement necessaire a la gestion des commandes, au suivi de la relation client, au traitement d'eventuels retours ou reclamations, ainsi qu'au respect des obligations administratives, comptables ou legales applicables.</p>
        <p>Au-dela, elles sont supprimees, anonymisees ou archivees de maniere adaptee lorsque cela est necessaire.</p>

        <h2>Securite</h2>
        <p>Des mesures techniques et organisationnelles raisonnables sont mises en place afin de proteger les donnees personnelles contre l'acces non autorise, la perte, l'alteration, la divulgation ou tout usage abusif.</p>
        <p>Malgre ces precautions, aucun systeme n'etant totalement exempt de risque, nous restons attentifs a renforcer la securite du site et des informations traitees chaque fois que cela est necessaire.</p>

        <h2>Droits des utilisateurs</h2>
        <p>Vous pouvez demander des informations sur les donnees vous concernant, solliciter leur acces, leur rectification ou, lorsque cela est applicable, leur suppression. Vous pouvez egalement demander des precisions complementaires sur la maniere dont vos informations sont utilisees.</p>
        <p>Pour toute demande relative a vos donnees personnelles, vous pouvez contacter le service client par telephone au <strong>92828271</strong> ou a l'adresse suivante : <strong>Torokorobougou, en face du terrain de la Commune V</strong>.</p>

        <h2>Cookies et donnees techniques</h2>
        <p>Le site peut utiliser des cookies ou des technologies similaires afin d'ameliorer la navigation, de maintenir certaines fonctionnalites essentielles, de mesurer l'usage du site et de mieux comprendre la frequentation des pages.</p>
        <p>Ces outils sont utilises dans un objectif pratique, technique et d'amelioration du service, sans remettre en cause le principe de protection de vos donnees personnelles.</p>

        <h2>Contact</h2>
        <p>Pour toute question relative a cette politique de confidentialite ou a l'utilisation de vos donnees, vous pouvez nous contacter aux coordonnees suivantes :</p>
        <p>Telephone : <strong>92828271</strong><br>Adresse : <strong>Torokorobougou, en face du terrain de la Commune V</strong><br>Adresse email : <strong><?php echo e(public_contact_email()); ?></strong></p>

        <h2>Liens utiles</h2>
        <p>Pour en savoir plus, vous pouvez egalement consulter les <a href="<?php echo e(base_url('pages/mentions-legales.php')); ?>">mentions legales</a>, les <a href="<?php echo e(base_url('pages/conditions-generales-vente.php')); ?>">conditions generales de vente</a>, la <a href="<?php echo e(base_url('pages/livraison.php')); ?>">politique de livraison</a> et la <a href="<?php echo e(base_url('pages/retours.php')); ?>">politique de retour</a>.</p>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
