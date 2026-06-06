<?php
if (!isset($base_url)) {
  require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/../app/helpers/public_contact.php';
if (!isset($page_js)) {
  $page_js = '';
}
$public_email = public_contact_email();
$public_whatsapp_url = public_contact_whatsapp_url();
?>
<footer>
  <div class="container">
    <div class="footer-content">
      <div class="footer-column">
        <a href="<?php echo $base_url; ?>index.php" class="footer-brand" aria-label="Accueil <?php echo e(SITE_NAME); ?>">
          <img
            src="<?php echo $base_url; ?>assets/images/branding/logo-sitehamala.svg"
            class="brand-logo footer-brand-logo"
            alt="<?php echo e(SITE_NAME); ?>"
            width="3000"
            height="3000"
            decoding="async"
          >
        </a>
        <p>Votre boutique en ligne au Mali. Commande simple, livraison fiable, paiement a la livraison.</p>
        <div class="footer-social" aria-label="Réseaux sociaux">
          <a href="https://www.tiktok.com/@bekayesora" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
            <i class="fab fa-tiktok" aria-hidden="true"></i>
          </a>
          <a href="<?php echo e($public_whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
            <i class="fab fa-whatsapp" aria-hidden="true"></i>
          </a>
        </div>
        <p><i class="fas fa-map-marker-alt"></i> Torokorobougou, en face du terrain de la Commune V</p>
        <p><i class="fas fa-envelope"></i> <?php echo e($public_email); ?></p>
      </div>

      <div class="footer-column">
        <h3>Liens Rapides</h3>
        <ul>
          <li><a href="<?php echo $base_url; ?>index.php">Accueil</a></li>
          <li><a href="<?php echo $base_url; ?>pages/catalogue.php">Catalogue</a></li>
          <li><a href="<?php echo $base_url; ?>pages/apropos.php">A propos</a></li>
          <li><a href="<?php echo $base_url; ?>pages/contact.php">Contact</a></li>
          <li><a href="<?php echo $base_url; ?>pages/faq.php">FAQ</a></li>
        </ul>
      </div>

      <div class="footer-column">
        <h3>Mon Compte</h3>
        <ul>
          <li><a href="<?php echo $base_url; ?>pages/connexion.php">Connexion</a></li>
          <li><a href="<?php echo $base_url; ?>pages/panier.php">Mon panier</a></li>
          <li><a data-auth="user" href="<?php echo $base_url; ?>pages/suivi-commande.php" hidden>Suivi commande</a></li>
          <li><a href="<?php echo $base_url; ?>pages/recherche.php">Recherche produit</a></li>
        </ul>
      </div>

      <div class="footer-column">
        <h3>Contactez-nous</h3>
        <p>Des questions ? Nous sommes la pour vous aider.</p>
        <a href="<?php echo $base_url; ?>pages/contact.php" class="btn btn-secondary">Nous contacter</a>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits reserves.</p>
      <p>
        <a href="<?php echo $base_url; ?>pages/mentions-legales.php">Mentions légales</a>
        &nbsp;·&nbsp;
        <a href="<?php echo $base_url; ?>pages/politique-confidentialite.php">Politique de confidentialité</a>
        &nbsp;·&nbsp;
        <a href="<?php echo $base_url; ?>pages/conditions-generales-vente.php">Conditions générales de vente</a>
        &nbsp;·&nbsp;
        <a href="<?php echo $base_url; ?>pages/livraison.php">Livraison</a>
        &nbsp;·&nbsp;
        <a href="<?php echo $base_url; ?>pages/retours.php">Retours</a>
      </p>
      <p>Boutique en ligne au Mali.</p>
    </div>
  </div>
</footer>

<?php $main_js_v = @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>
<script src="<?php echo $base_url; ?>assets/js/main.js?v=<?php echo (int) $main_js_v; ?>"></script>
<?php
  $page_js_files = array();
  if (isset($page_js) && $page_js) {
    $page_js_files = is_array($page_js) ? $page_js : array($page_js);
  }
?>
<?php foreach ($page_js_files as $js_file): ?>
  <?php $page_js_v = @filemtime(__DIR__ . '/../assets/js/' . $js_file) ?: $main_js_v; ?>
  <script src="<?php echo $base_url; ?>assets/js/<?php echo $js_file; ?>?v=<?php echo (int) $page_js_v; ?>"></script>
<?php endforeach; ?>
</body>
</html>
