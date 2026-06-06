<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

$displayTitle = 'Paiement en ligne indisponible';
$displayMessage = 'Le paiement en ligne n est plus disponible sur ce site.';
$displayNote = 'Veuillez finaliser vos commandes avec le paiement a la livraison.';

$page_title = $displayTitle;
$page_css = 'pages/cms.css';
$page_js = '';

include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="cms-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1><?php echo htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
  </section>

  <section class="cms-body">
    <div class="container" style="max-width:640px;margin:0 auto;padding:2rem 1rem;text-align:center;">
      <p style="font-size:1.1rem;margin-bottom:1.5rem;">
        <?php echo htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8'); ?>
      </p>

      <p style="margin-top:1.5rem;color:#666;font-size:.9rem;">
        <?php echo htmlspecialchars($displayNote, ENT_QUOTES, 'UTF-8'); ?><br>
        En cas de probleme, contactez-nous pour finaliser votre commande.
      </p>

      <div style="margin-top:2rem;">
        <a href="<?php echo htmlspecialchars(base_url('pages/commande.php'), ENT_QUOTES, 'UTF-8'); ?>"
           style="display:inline-block;padding:.75rem 1.5rem;background:#222;color:#fff;border-radius:4px;text-decoration:none;">
          Retour au checkout
        </a>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
