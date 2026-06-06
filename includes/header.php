<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Logger.php';

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/_header_context.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($seo_title_final); ?></title>
  <?php if ($meta_desc_final !== ''): ?>
    <meta name="description" content="<?php echo e($meta_desc_final); ?>">
  <?php endif; ?>
  <?php if ($canonical_url !== ''): ?>
    <link rel="canonical" href="<?php echo e($canonical_url); ?>">
  <?php endif; ?>
  <?php if ($og_title_final !== ''): ?>
    <meta property="og:title" content="<?php echo e($og_title_final); ?>">
  <?php endif; ?>
  <?php if ($og_desc_final !== ''): ?>
    <meta property="og:description" content="<?php echo e($og_desc_final); ?>">
  <?php endif; ?>
  <?php if ($og_image_final !== ''): ?>
    <meta property="og:image" content="<?php echo e($og_image_final); ?>">
  <?php endif; ?>
  <?php if ($canonical_url !== ''): ?>
    <meta property="og:url" content="<?php echo e($canonical_url); ?>">
  <?php endif; ?>
  <meta property="og:type" content="website">
  <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>assets/images/branding/logo-sitehamala.svg">
  <?php if (isset($json_ld_blocks) && is_array($json_ld_blocks)): ?>
    <?php foreach ($json_ld_blocks as $json_ld): ?>
      <?php if (is_array($json_ld) && !empty($json_ld)): ?>
        <?php $json_ld_encoded = json_encode($json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>
        <?php if (is_string($json_ld_encoded) && $json_ld_encoded !== ''): ?>
          <script type="application/ld+json"><?php echo $json_ld_encoded; ?></script>
        <?php endif; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- CSS Principal -->
  <?php /* Cache-busting CSS (dev). */ ?>
  <?php $main_css_version = (int) (@filemtime(__DIR__ . '/../assets/css/main.css') ?: time()); ?>
  <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/main.css?v=<?php echo $main_css_version; ?>">

  <!-- CSS specifique a la page -->
  <?php
    $page_css_files = array();
    if (isset($page_css) && $page_css) {
      $page_css_files = is_array($page_css) ? $page_css : array($page_css);
    }
  ?>
  <?php foreach ($page_css_files as $css_file): ?>
    <?php $page_css_v = @filemtime(__DIR__ . '/../assets/css/' . $css_file) ?: $main_css_version; ?>
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/<?php echo $css_file; ?>?v=<?php echo (int) $page_css_v; ?>">
  <?php endforeach; ?>

  <!-- CSS Responsive -->
  <!-- (responsive.css merged into main.css) -->

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body<?php echo $body_class_attr; ?> data-base-url="<?php echo e($base_url); ?>" data-auth="<?php echo $isClientUser ? '1' : '0'; ?>">
<a class="skip-link" href="#main">Aller au contenu</a>
<header class="site-header">
  <div class="container">
    <div class="nav-container">
      <div class="nav-main">
        <a href="<?php echo $base_url; ?>index.php" class="logo" aria-label="Accueil <?php echo e(SITE_NAME); ?>">
          <img
            src="<?php echo $base_url; ?>assets/images/branding/logo-sitehamala.svg"
            class="brand-logo logo-image"
            alt="<?php echo e(SITE_NAME); ?>"
            width="3000"
            height="3000"
            decoding="async"
          >
          <span class="sr-only"><?php echo SITE_NAME; ?></span>
        </a>

        <!-- Barre de recherche (desktop) -->
        <form
          class="nav-search"
          method="get"
          action="<?php echo $base_url; ?>pages/recherche.php"
          role="search"
          aria-label="Rechercher un produit"
        >
          <label class="sr-only" for="navSearchInput">Rechercher</label>
          <div class="nav-search__inner">
            <input
              id="navSearchInput"
              class="nav-search__input"
              type="search"
              name="q"
              value="<?php echo isset($_GET['q']) ? e((string) $_GET['q']) : ''; ?>"
              placeholder="Rechercher des produits, boutiques..."
              autocomplete="off"
            >
            <button type="submit" class="nav-search__submit" aria-label="Rechercher">
              <i class="fas fa-search" aria-hidden="true"></i>
            </button>
          </div>
        </form>

        <nav class="primary-nav" aria-label="Navigation principale">
          <?php
            $catalog_menu = array(
              'femme' => array(
                'label' => 'Femme',
                'icon' => 'fa-female',
                'items' => array(
                  array('slug' => '', 'label' => 'Toute la collection'),
                  array('slug' => 'robes', 'label' => 'Robes'),
                  array('slug' => 'boubous', 'label' => 'Boubous'),
                  array('slug' => 'ensembles', 'label' => 'Ensembles'),
                  array('slug' => 'chemises', 'label' => 'Chemises &amp; Tops'),
                ),
              ),
              'homme' => array(
                'label' => 'Homme',
                'icon' => 'fa-male',
                'items' => array(
                  array('slug' => '', 'label' => 'Toute la collection'),
                  array('slug' => 'boubous', 'label' => 'Boubous'),
                  array('slug' => 'ensembles', 'label' => 'Ensembles'),
                  array('slug' => 'chemises', 'label' => 'Chemises &amp; Tops'),
                ),
              ),
              'unisex' => array(
                'label' => 'Unisexe',
                'icon' => 'fa-venus-mars',
                'items' => array(
                  array('slug' => '', 'label' => 'Toute la collection'),
                ),
              ),
            );
          ?>
          <ul class="nav-menu">
          <li>
            <a
              href="<?php echo $base_url; ?>index.php"
              class="<?php echo $nav_active_class('index.php'); ?> <?php echo $nav_is_active_class('index.php'); ?>"
              <?php echo $nav_aria_current('index.php'); ?>
            >
              Accueil
            </a>
          </li>
          
          <!-- Menu deroulant Catalogue -->
          <li class="dropdown" data-dropdown data-dropdown-click="navigate">
            <a
              href="<?php echo $base_url; ?>pages/catalogue.php"
              id="catalogueDropdownBtn"
              class="dropdown-toggle <?php echo $is_catalogue_related ? 'active is-active' : ''; ?>"
              aria-haspopup="true"
              aria-expanded="false"
              aria-controls="catalogueDropdownMenu"
            >
              Catalogue <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </a>
                        <div class="dropdown-menu" id="catalogueDropdownMenu" role="menu" aria-labelledby="catalogueDropdownBtn">
              <a href="<?php echo $base_url; ?>pages/catalogue.php" role="menuitem" class="<?php echo ($current_category == '' && $is_catalogue_related) ? 'active is-active' : ''; ?>">
                <i class="fas fa-th" aria-hidden="true"></i>
                Tous les produits
              </a>

              <?php foreach ($catalog_menu as $gender_slug => $group): ?>
                <?php
                  $is_group_active = ($current_category === $gender_slug);
                  $group_label = (string) ($group['label'] ?? ucfirst($gender_slug));
                  $group_icon = (string) ($group['icon'] ?? 'fa-tag');
                  $submenu_id = 'catalogueSubmenu' . ucfirst($gender_slug);
                ?>
                <div class="has-submenu <?php echo $is_group_active ? 'is-open' : ''; ?>" role="none">
                  <button
                    type="button"
                    class="submenu-parent <?php echo $is_group_active ? 'active is-active' : ''; ?>"
                    aria-expanded="<?php echo $is_group_active ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo e($submenu_id); ?>"
                  >
                    <i class="fas <?php echo e($group_icon); ?>" aria-hidden="true"></i>
                    <?php echo e($group_label); ?>
                    <i class="fas fa-chevron-down submenu-caret" aria-hidden="true"></i>
                  </button>
                  <div class="submenu" id="<?php echo e($submenu_id); ?>" role="menu">
                    <?php foreach ((array) ($group['items'] ?? array()) as $item): ?>
                      <?php
                        $sub_slug = trim((string) ($item['slug'] ?? ''));
                        $item_label = (string) ($item['label'] ?? '');
                        if ($item_label === '') continue;
                        $href = $base_url . 'pages/catalogue.php?categorie=' . rawurlencode($gender_slug);
                        if ($sub_slug !== '') {
                          $href .= '&sous_categorie=' . rawurlencode($sub_slug);
                        }
                        $is_item_active = ($current_category === $gender_slug && $current_sous_categorie === $sub_slug);
                      ?>
                      <a href="<?php echo e($href); ?>" role="menuitem" class="<?php echo $is_item_active ? 'active is-active' : ''; ?>">
                        <?php echo $item_label; ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </li>
          
          <li>
            <a
              href="<?php echo $base_url; ?>pages/faq.php"
              class="<?php echo $nav_active_class('faq.php'); ?> <?php echo $nav_is_active_class('faq.php'); ?>"
              <?php echo $nav_aria_current('faq.php'); ?>
            >
              FAQ
            </a>
          </li>
          <li>
            <a
              href="<?php echo $base_url; ?>pages/contact.php"
              class="<?php echo $nav_active_class('contact.php'); ?> <?php echo $nav_is_active_class('contact.php'); ?>"
              <?php echo $nav_aria_current('contact.php'); ?>
            >
              Contact
            </a>
          </li>

        </ul>
        </nav>
      </div>

      <div class="nav-actions">
        <a
          href="<?php echo $base_url; ?>pages/recherche.php"
          class="search-btn <?php echo $nav_active_class('recherche.php'); ?> <?php echo $nav_is_active_class('recherche.php'); ?>"
          aria-label="Rechercher"
          title="Rechercher"
          <?php echo $nav_aria_current('recherche.php'); ?>
        >
          <i class="fas fa-search"></i>
        </a>
        <a
          href="<?php echo $base_url; ?>pages/panier.php"
          class="cart-btn <?php echo $nav_active_class('panier.php'); ?> <?php echo $nav_is_active_class('panier.php'); ?>"
          aria-label="Panier"
          title="Panier (<?php echo (int) $cart_count; ?>)"
          <?php echo $nav_aria_current('panier.php'); ?>
        >
          <i class="fas fa-shopping-cart"></i>
          <span
            class="cart-count"
            data-count="<?php echo (int) $cart_count; ?>"
            <?php echo $cart_count > 0 ? '' : 'hidden'; ?>
          ><?php echo (int) $cart_count; ?></span>
        </a>
        <div class="nav-account" data-account-dropdown>
          <a
            href="#"
            id="accountDropdownBtn"
            class="user-btn nav-account__btn <?php echo $is_account_related ? 'active is-active' : ''; ?> <?php echo (!$isClientUser && !$isStaff) ? 'nav-account__btn--guest' : ''; ?> <?php echo ($isClientUser && $userLastName !== '') ? 'nav-account__btn--with-label' : ''; ?>"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="accountDropdownMenu"
            aria-label="Compte"
            title="Compte"
          >
            <i class="fas fa-user" aria-hidden="true"></i>
            <?php if (!$isClientUser && !$isStaff): ?>
              <span class="nav-account__label">Connexion</span>
            <?php elseif ($isClientUser && $userLastName !== ''): ?>
              <span class="nav-account__label"><?php echo e('Bonjour ' . $userLastName); ?></span>
            <?php endif; ?>
          </a>
          <div class="nav-account__menu" id="accountDropdownMenu" role="menu">
            <?php if ($isStaff): ?>
              <a href="<?php echo $base_url; ?>admin/index.php" role="menuitem">
                <i class="fas fa-gauge-high" aria-hidden="true"></i> Tableau de bord
              </a>
                <a href="<?php echo e(base_url('pages/suivi.php')); ?>" role="menuitem">
                  <i class="fas fa-truck" aria-hidden="true"></i> Suivi commande
                </a>
                <a href="<?php echo $base_url; ?>admin/logout.php" role="menuitem">
                  <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Deconnexion
                </a>
              <?php else: ?>
                <?php if ($isClientUser): ?>
                  <a href="<?php echo e(base_url('pages/mon-compte.php')); ?>" role="menuitem">
                    <i class="fas fa-user-circle" aria-hidden="true"></i> Mon compte
                  </a>
                  <a href="<?php echo $base_url; ?>pages/mes-commandes.php" role="menuitem">
                    <i class="fas fa-list" aria-hidden="true"></i> Mes commandes
                  </a>
                  <a href="<?php echo e(base_url('pages/suivi.php')); ?>" role="menuitem">
                    <i class="fas fa-truck" aria-hidden="true"></i> Suivi commande
                  </a>
                  <a href="#" role="menuitem" data-action="logout">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Deconnexion
                  </a>
                  <hr class="account-menu-separator">
                  <a class="account-menu-danger" href="<?php echo e(base_url('pages/supprimer-compte.php')); ?>" role="menuitem">
                    <i class="fas fa-trash-can" aria-hidden="true"></i> Supprimer mon compte
                  </a>
                <?php else: ?>
                  <a href="<?php echo $base_url; ?>pages/connexion.php" role="menuitem">
                    <i class="fas fa-right-to-bracket" aria-hidden="true"></i> Connexion
                  </a>
                  <a href="<?php echo $base_url; ?>pages/inscription.php" role="menuitem">
                    <i class="fas fa-user-plus" aria-hidden="true"></i> Creer un compte
                  </a>
                <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu" aria-controls="mobileMenu" aria-expanded="false">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <!-- Menu mobile -->
    <nav class="mobile-menu" id="mobileMenu" aria-label="Menu mobile" hidden>
      <ul>
        <li><a href="<?php echo $base_url; ?>index.php"><i class="fas fa-home"></i> Accueil</a></li>
        
        <!-- Menu deroulant mobile pour Catalogue -->
        <li class="mobile-dropdown">
          <button
            type="button"
            class="mobile-dropdown-toggle"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="mobileCatalogueMenu"
          >
            <span><i class="fas fa-th-list"></i> Catalogue</span>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
          </button>
                    <div class="mobile-dropdown-menu" id="mobileCatalogueMenu">
            <a href="<?php echo $base_url; ?>pages/catalogue.php" class="<?php echo ($current_category == '' && $is_catalogue_related) ? 'active is-active' : ''; ?>">
              <i class="fas fa-th" aria-hidden="true"></i> Tous les produits
            </a>
            <?php if (false): ?>

            <!-- Femme sous-accordéon -->
            <div class="mobile-subdropdown">
              <button type="button" class="mobile-subdropdown-toggle" aria-expanded="false" aria-controls="mobileFemmeMenu">
                <span><i class="fas fa-female" aria-hidden="true"></i> Femme</span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
              </button>
              <div class="mobile-subdropdown-menu <?php echo $current_category == 'femme' ? 'active' : ''; ?>" id="mobileFemmeMenu">
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=femme" class="<?php echo ($current_category == 'femme' && $current_sous_categorie == '') ? 'active is-active' : ''; ?>">
                  Toute la collection
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=femme&sous_categorie=robes" class="<?php echo ($current_category == 'femme' && $current_sous_categorie == 'robes') ? 'active is-active' : ''; ?>">
                  Robes &amp; Tenues
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=femme&sous_categorie=boubous" class="<?php echo ($current_category == 'femme' && $current_sous_categorie == 'boubous') ? 'active is-active' : ''; ?>">
                  Boubous
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=femme&sous_categorie=ensembles" class="<?php echo ($current_category == 'femme' && $current_sous_categorie == 'ensembles') ? 'active is-active' : ''; ?>">
                  Ensembles
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=femme&sous_categorie=accessoires" class="<?php echo ($current_category == 'femme' && $current_sous_categorie == 'accessoires') ? 'active is-active' : ''; ?>">
                  Accessoires
                </a>
              </div>
            </div>

            <!-- Homme sous-accordéon -->
            <div class="mobile-subdropdown">
              <button type="button" class="mobile-subdropdown-toggle" aria-expanded="false" aria-controls="mobileHommeMenu">
                <span><i class="fas fa-male" aria-hidden="true"></i> Homme</span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
              </button>
              <div class="mobile-subdropdown-menu <?php echo $current_category == 'homme' ? 'active' : ''; ?>" id="mobileHommeMenu">
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=homme" class="<?php echo ($current_category == 'homme' && $current_sous_categorie == '') ? 'active is-active' : ''; ?>">
                  Toute la collection
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=homme&sous_categorie=boubous" class="<?php echo ($current_category == 'homme' && $current_sous_categorie == 'boubous') ? 'active is-active' : ''; ?>">
                  Boubous
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=homme&sous_categorie=chemises" class="<?php echo ($current_category == 'homme' && $current_sous_categorie == 'chemises') ? 'active is-active' : ''; ?>">
                  Chemises &amp; Tops
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=homme&sous_categorie=ensembles" class="<?php echo ($current_category == 'homme' && $current_sous_categorie == 'ensembles') ? 'active is-active' : ''; ?>">
                  Ensembles
                </a>
                <a href="<?php echo $base_url; ?>pages/catalogue.php?categorie=homme&sous_categorie=accessoires" class="<?php echo ($current_category == 'homme' && $current_sous_categorie == 'accessoires') ? 'active is-active' : ''; ?>">
                  Accessoires
                </a>
              </div>
            </div>
            <?php endif; ?>
            <?php foreach ($catalog_menu as $gender_slug => $group): ?>
              <?php
                $is_group_active = ($current_category === $gender_slug);
                $group_label = (string) ($group['label'] ?? ucfirst($gender_slug));
                $group_icon = (string) ($group['icon'] ?? 'fa-tag');
                $submenu_id = 'mobile' . ucfirst($gender_slug) . 'Menu';
              ?>
              <div class="mobile-subdropdown">
                <button type="button" class="mobile-subdropdown-toggle" aria-expanded="<?php echo $is_group_active ? 'true' : 'false'; ?>" aria-controls="<?php echo e($submenu_id); ?>">
                  <span><i class="fas <?php echo e($group_icon); ?>" aria-hidden="true"></i> <?php echo e($group_label); ?></span>
                  <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="mobile-subdropdown-menu <?php echo $is_group_active ? 'active' : ''; ?>" id="<?php echo e($submenu_id); ?>">
                  <?php foreach ((array) ($group['items'] ?? array()) as $item): ?>
                    <?php
                      $sub_slug = trim((string) ($item['slug'] ?? ''));
                      $item_label = (string) ($item['label'] ?? '');
                      if ($item_label === '') continue;
                      $href = $base_url . 'pages/catalogue.php?categorie=' . rawurlencode($gender_slug);
                      if ($sub_slug !== '') {
                        $href .= '&sous_categorie=' . rawurlencode($sub_slug);
                      }
                      $is_item_active = ($current_category === $gender_slug && $current_sous_categorie === $sub_slug);
                    ?>
                    <a href="<?php echo e($href); ?>" class="<?php echo $is_item_active ? 'active is-active' : ''; ?>">
                      <?php echo $item_label; ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </li>
        
        <li><a href="<?php echo $base_url; ?>pages/faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
        <li><a href="<?php echo $base_url; ?>pages/contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
        <li class="mobile-dropdown">
          <button
            type="button"
            class="mobile-dropdown-toggle"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="mobileAccountMenu"
          >
            <span><i class="fas fa-user"></i> Compte</span>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="mobile-dropdown-menu" id="mobileAccountMenu">
            <?php if ($isStaff): ?>
              <a href="<?php echo $base_url; ?>admin/index.php"><i class="fas fa-gauge-high"></i> Tableau de bord</a>
              <a href="<?php echo e(base_url('pages/suivi.php')); ?>"><i class="fas fa-truck"></i> Suivi commande</a>
              <a href="<?php echo $base_url; ?>admin/logout.php"><i class="fas fa-right-from-bracket"></i> Deconnexion</a>
            <?php else: ?>
              <?php if ($isClientUser): ?>
                <a href="<?php echo e(base_url('pages/mon-compte.php')); ?>"><i class="fas fa-user-circle"></i> Mon compte</a>
                <a href="<?php echo $base_url; ?>pages/mes-commandes.php"><i class="fas fa-list"></i> Mes commandes</a>
                <a href="<?php echo e(base_url('pages/suivi.php')); ?>"><i class="fas fa-truck"></i> Suivi commande</a>
                <a href="#" data-action="logout"><i class="fas fa-right-from-bracket"></i> Deconnexion</a>
                <hr class="account-menu-separator">
                <a class="account-menu-danger" href="<?php echo e(base_url('pages/supprimer-compte.php')); ?>"><i class="fas fa-trash-can"></i> Supprimer mon compte</a>
              <?php else: ?>
                <a href="<?php echo $base_url; ?>pages/connexion.php"><i class="fas fa-right-to-bracket"></i> Connexion</a>
                <a href="<?php echo $base_url; ?>pages/inscription.php"><i class="fas fa-user-plus"></i> Creer un compte</a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </li>

        <li><a href="<?php echo $base_url; ?>pages/panier.php"><i class="fas fa-shopping-cart"></i> Panier</a></li>
      </ul>
    </nav>
  </div>
</header>


