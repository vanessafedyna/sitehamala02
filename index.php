<?php
require_once __DIR__ . '/app/bootstrap.php';
auth_start();
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/home_page.php';

$home_view = home_page_view_context();
$page_title = $home_view['page_title'];
$page_meta_description = $home_view['page_meta_description'];
$page_css = $home_view['page_css'];
$page_js = $home_view['page_js'];
$body_class = $home_view['body_class'];
$flash_messages = isset($_SESSION['flash']) && is_array($_SESSION['flash']) ? $_SESSION['flash'] : array();
unset($_SESSION['flash']);
include __DIR__ . '/includes/header.php';
?>

<main id="main">
<?php if (!empty($flash_messages)): ?>
<section class="section" style="padding:16px 0 0;">
  <div class="container">
    <?php foreach ($flash_messages as $fm): ?>
      <?php
        $msg = is_array($fm) ? (string) ($fm['message'] ?? '') : (string) $fm;
        if ($msg === '') continue;
      ?>
      <div class="site-flash" role="status" aria-live="polite"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Hero Section -->
 <section class="hero hero--bg hero--center">
  <div class="container">
    <div class="hero-content">
      <h1>Choisissez votre produit et commandez facilement</h1>
      <p class="hero-subtitle">Une boutique en ligne pensée pour le Mali : consultez le catalogue, passez votre commande simplement et payez à la livraison.</p>
      <div class="hero-buttons">
        <a href="<?php echo $base_url; ?>pages/catalogue.php" class="btn btn-primary btn-large">
          <i class="fas fa-shopping-cart"></i> Découvrir le catalogue
        </a>
      </div>
      <p class="hero-helper">
        <a href="<?php echo $base_url; ?>pages/inscription.php" class="hero-helper-link">
          <i class="fas fa-user-plus" aria-hidden="true"></i> Créer un compte
        </a>
      </p>
      <div class="hero-features">
        <div class="hero-feature">
          <i class="fas fa-money-bill-wave" aria-hidden="true"></i>
          <span>Paiement par Orange money, carte bancaire ou à la livraison</span>
        </div>

      </div>
    </div>
    <?php if (false): ?>
    <div class="hero-image">
      <div class="image-placeholder hero-placeholder" role="img" aria-label="Aperçu visuel (images à venir)">
        <div class="hero-placeholder__icon" aria-hidden="true">
          <i class="fas fa-image"></i>
        </div>
        <div class="hero-placeholder__text">
          <p class="hero-placeholder__title">Aperçu visuel</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>


<?php
$featured_products = array();
$featured_products_error = false;

try {
  $featured_products = home_fetch_featured_products(db(), 4);
} catch (Throwable $e) {
  $featured_products = array();
  $featured_products_error = true;
}
?>

<section class="featured-products reveal">
  <div class="container">
    <h2>Produits</h2>
    <p class="section-subtitle">Découvrez une sélection de produits disponibles dès maintenant, avec leurs prix et informations essentielles.</p>

    <?php $placeholder_img = $base_url . 'assets/images/placeholders/product-placeholder.svg'; ?>

    <?php if (!$featured_products): ?>
      <div class="featured-empty" role="status" aria-live="polite">
        <i class="fas fa-box-open" aria-hidden="true"></i>
        <h3>Des produits arrivent bientôt</h3>
        <?php if ($featured_products_error): ?>
          <p>Impossible de charger les produits pour le moment. Veuillez réessayer.</p>
        <?php else: ?>
          <p>Revenez bientôt : le catalogue est en cours de préparation.</p>
        <?php endif; ?>
      </div>

      <div class="products-grid featured-products-grid" aria-hidden="true">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="product-card product-card--skeleton">
            <div class="product-image media">
              <div class="skeleton skeleton--media"></div>
            </div>
            <div class="product-info">
              <div class="skeleton skeleton--line"></div>
              <div class="skeleton skeleton--line skeleton--line-sm"></div>
              <div class="skeleton skeleton--line skeleton--line-lg"></div>
              <div class="product-actions">
                <div class="skeleton skeleton--btn"></div>
                <div class="skeleton skeleton--btn"></div>
              </div>
            </div>
          </div>
        <?php endfor; ?>
      </div>
    <?php else: ?>
      <div class="products-grid featured-products-grid">
        <?php foreach ($featured_products as $product): ?>
          <?php
            $id = (int) ($product['id'] ?? 0);
            $name = (string) ($product['name'] ?? 'Produit');
            $sku = (string) ($product['sku'] ?? '');
            $price = (int) ($product['price'] ?? ($product['price_fcfa'] ?? ($product['prix'] ?? 0)));
            $stock = (int) ($product['stock'] ?? 0);
            $cat = (string) ($product['category'] ?? '');

            $img_src = home_product_image_url($product, (string) $base_url, (string) $placeholder_img);

            $product_url = $base_url . 'pages/produit.php?id=' . $id;
          ?>

          <div class="product-card" data-category="<?php echo e($cat); ?>" data-sku="<?php echo e($sku); ?>">
            <div class="product-image media">
              <a href="<?php echo e($product_url); ?>" class="product-media-link" aria-label="Voir le produit <?php echo e($name); ?>">
                <img src="<?php echo e($img_src); ?>" alt="<?php echo e($name); ?>" loading="lazy" data-fallback-src="<?php echo e($placeholder_img); ?>">
              </a>
            </div>
            <div class="product-info">
              <h3><a href="<?php echo e($product_url); ?>" class="product-title-link"><?php echo e($name); ?></a></h3>
              <?php if ($sku !== ''): ?><p class="product-sku">SKU: <?php echo e($sku); ?></p><?php endif; ?>
              <?php if ($stock > 0): ?>
                <span class="stock-indicator stock-ok">En stock</span>
              <?php else: ?>
                <span class="stock-indicator stock-low">Disponibilité variable</span>
              <?php endif; ?>
              <div class="product-price"><?php echo e(number_format($price, 0, ',', ' ')); ?> FCFA</div>
              <div class="product-actions">
                <a href="<?php echo e($product_url); ?>" class="btn btn-secondary">Voir le produit</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
    
    <!-- Features Section -->
<section class="features reveal" id="comment-commander">
  <div class="container">
    <h2>Comment commander ?</h2>
    <p class="section-subtitle">Suivez 3 étapes simples : choisissez un produit, validez votre commande et payez à la livraison.</p>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-th-list"></i>
        </div>
        <h3>1. Choisissez votre produit</h3>
        <p>Parcourez le catalogue, consultez les photos, les prix et les informations utiles avant de faire votre choix.</p>
      </div>
      
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-cart-shopping"></i>
        </div>
        <h3>2. Commandez facilement</h3>
        <p>Ajoutez votre produit, confirmez votre commande et laissez-nous préparer votre livraison rapidement.</p>
      </div>
      
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fas fa-money-bill-wave"></i>
        </div>
        <h3>3. Payez par Orange money, carte bancaire ou à la livraison</h3>
        <p>Réglez votre achat au moment de recevoir votre commande, en toute simplicité.</p>
      </div>
    </div>
  </div>
</section>


<!-- Advantages Section -->
<section class="advantages reveal">
  <div class="container">
    <h2>Pourquoi nous choisir ?</h2>
    <p class="section-subtitle">Des avantages conçus pour vous</p>
    <div class="advantages-content">
      <div class="advantage-item">
        <i class="fas fa-flag"></i>
        <div>
          <h3>Adapté au Mali</h3>
          <p>Un service pensé pour les réalités locales.</p>
        </div>
      </div>
      
      <div class="advantage-item">
        <i class="fas fa-lock"></i>
        <div>
          <h3>Livraison sécurisée</h3>
          <p>Confirmation à la réception pour garantir la sécurité de vos achats.</p>
        </div>
      </div>
      
      <div class="advantage-item">
        <i class="fas fa-box"></i>
        <div>
          <h3>Produits disponibles</h3>
          <p>Stocks mis à jour en temps réel pour éviter les commandes annulées.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
$reviews = array();
try {
  $reviews = home_fetch_reviews(db(), 6);
} catch (Throwable $e) {
  // Table absente / DB indisponible => section vide (pas de faux avis).
  $reviews = array();
}
?>

<!-- Testimonials Section -->
<section class="testimonials reveal">
  <div class="container">
    <h2>Ce que disent nos clients</h2>
    <p class="section-subtitle">Ils nous font confiance, rejoignez-les !</p>

    <div class="reviews-actions">
      <button type="button" class="btn btn-secondary" id="reviewsToggleBtn" aria-expanded="false" aria-controls="reviewsFormWrap">
        <i class="fas fa-pen-to-square" aria-hidden="true"></i> Laisser un avis
      </button>
    </div>

    <div class="reviews-notice" id="reviewsNotice" role="status" aria-live="polite" hidden></div>

    <div class="testimonials-grid">
      <?php if (!$reviews): ?>
        <div class="reviews-empty" id="reviewsEmpty">
          <p>Aucun avis pour le moment. Soyez le premier à partager votre expérience.</p>
          <button type="button" class="btn btn-primary" data-action="reviews-open">
            <i class="fas fa-pen-to-square" aria-hidden="true"></i> Laisser un avis
          </button>
        </div>
      <?php else: ?>
        <?php foreach ($reviews as $r): ?>
          <?php
            $rating = (int) ($r['rating'] ?? 0);
            if ($rating < 1) $rating = 1;
            if ($rating > 5) $rating = 5;
          ?>
          <div class="testimonial-card">
            <div class="testimonial-content">
              <p>"<?php echo e((string) ($r['message'] ?? '')); ?>"</p>
            </div>
            <div class="testimonial-author">
              <div class="author-info">
                <h4><?php echo e((string) ($r['name'] ?? '')); ?></h4>
                <span><?php echo e((string) ($r['city'] ?? '')); ?></span>
              </div>
              <div class="stars" aria-label="Note : <?php echo (int) $rating; ?>/5">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="<?php echo $i <= $rating ? 'fas fa-star' : 'far fa-star'; ?>" aria-hidden="true"></i>
                <?php endfor; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="reviews-form-wrap" id="reviewsFormWrap" hidden>
      <div class="testimonial-card reviews-form-card">
        <h3 class="reviews-form-title">Laisser un avis</h3>
        <form id="reviewsForm" data-endpoint="<?php echo e(base_url('public/api/reviews_create.php')); ?>" novalidate>
          <?php echo csrf_field(); ?>
          <div class="reviews-form-grid">
            <div class="reviews-field">
              <label class="reviews-label" for="reviewName">Nom</label>
              <input class="reviews-input" id="reviewName" name="name" type="text" maxlength="100" required autocomplete="name">
            </div>
            <div class="reviews-field">
              <label class="reviews-label" for="reviewCity">Ville</label>
              <input class="reviews-input" id="reviewCity" name="city" type="text" maxlength="100" required autocomplete="address-level2">
            </div>
            <div class="reviews-field">
              <label class="reviews-label" for="reviewRating">Note</label>
              <select class="reviews-input" id="reviewRating" name="rating" required>
                <option value="" selected disabled>Choisir</option>
                <option value="5">5 - Excellent</option>
                <option value="4">4 - Très bien</option>
                <option value="3">3 - Bien</option>
                <option value="2">2 - Moyen</option>
                <option value="1">1 - Déçu</option>
              </select>
            </div>
            <div class="reviews-field reviews-field--full">
              <label class="reviews-label" for="reviewMessage">Message</label>
              <textarea class="reviews-input reviews-textarea" id="reviewMessage" name="message" minlength="10" maxlength="1000" required rows="4" placeholder="Partagez votre expérience..."></textarea>
              <div class="reviews-hint">10 à 1000 caractères.</div>
            </div>
          </div>

          <div class="reviews-form-actions">
            <button class="btn btn-primary" type="submit" id="reviewsSubmitBtn">Envoyer mon avis</button>
            <button class="btn btn-outline" type="button" data-action="reviews-close">Annuler</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="cta-section animated-gradient-section reveal">
  <div class="container">
    <div class="cta-content">
      <h2>Prêt à commander ?</h2>
      <p>Consultez le catalogue, choisissez vos produits et finalisez votre commande simplement, avec paiement à la livraison.</p>
      <div class="cta-buttons">
        <a href="<?php echo $base_url; ?>pages/catalogue.php" class="btn btn-primary btn-large">
          <i class="fas fa-shopping-basket"></i> Voir le catalogue
        </a>
        <a href="<?php echo $base_url; ?>pages/inscription.php" class="btn btn-secondary btn-large">
          <i class="fas fa-user-plus"></i> Créer un compte
        </a>
      </div>
    </div>
  </div>
</section>

</main>

<!-- Transition douce vers le footer -->
<section class="footer-transition" aria-hidden="true"></section>

<?php include __DIR__ . '/includes/footer.php'; ?>

