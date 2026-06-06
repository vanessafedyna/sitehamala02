<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

require_once __DIR__ . '/_cms.php';

/* CMS override (si publié) */
$cms = cms_get_published_by_key('faq');
if ($cms) {
  $page_title = (string) ($cms['title'] ?? 'FAQ');
  $page_css = 'pages/cms.css';
  $page_js = '';

  $page_seo_title = trim((string) ($cms['seo_title'] ?? ''));
  $page_meta_description = trim((string) ($cms['seo_description'] ?? ''));
  if ($page_meta_description === '') {
    $page_meta_description = 'Consultez la FAQ de SORA Collection : réponses claires sur les commandes, la livraison au Mali, le paiement et l’utilisation du site.';
  }
  $page_og_image = cms_asset_url($cms['og_image'] ?? '');
  $cms_html = cms_sanitize_html((string) ($cms['content'] ?? ''));

  /* si contenu CMS vide => fallback sur page FAQ existante (évite une page blanche). */
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

$page_title = 'FAQ';
$page_meta_description = 'Consultez la FAQ de SORA Collection : réponses claires sur les commandes, la livraison au Mali, le paiement et l’utilisation du site.';
$page_css = 'pages/faq.css';
$page_js = 'pages/faq.js';

include __DIR__ . '/../includes/header.php';
?>

<main id="main" class="faq-page" tabindex="-1">
  <section class="page-head">
    <div class="container">
      <h1>FAQ — Questions fréquentes</h1>
     
    </div>
  </section>

  <section class="faq-featured" aria-labelledby="featuredTitle">
    <div class="container">
      <div class="featured-card">
        <h2 id="featuredTitle">Questions les plus fréquentes</h2>
        <div class="featured-grid" role="list">
          <article class="featured-item" role="listitem">
            <h3>Comment se passe la livraison au Mali ?</h3>
            <p class="muted">Livraison organisée avec votre ville - quartier - numéro..</p>
            <a class="btn btn-secondary" href="#faq-livraison-mali">Voir la réponse</a>
          </article>
          <article class="featured-item" role="listitem">
            <h3>Quand est-ce que je paie ma commande ?</h3>
            <p class="muted">Le paiement se fait par Orange money, carte bancaire ou à la livraison, au moment où vous recevez votre commande.</p>
            <a class="btn btn-secondary" href="#faq-paiement-quand">Voir la réponse</a>
          </article>
          <article class="featured-item" role="listitem">
            <h3>Comment suivre ma commande ?</h3>
            <p class="muted">Utilisez la page de suivi avec votre numéro de commande. Vous voyez les étapes clairement, en quelques secondes.</p>
            <a class="btn btn-secondary" href="#faq-suivi-comment">Voir la réponse</a>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="faq-shell">
    <div class="container">
      <!-- Recherche -->
      <section class="card" aria-label="Recherche FAQ">
        <div class="card-head">
          <h2>Rechercher</h2>
          <div class="results-meta" aria-live="polite">
            <span class="count" id="resultCount">—</span>
            <button class="btn btn-secondary" id="clearBtn" type="button" hidden>Effacer</button>
          </div>
        </div>

        <div class="field">
          <label for="searchInput">Rechercher</label>
          <input id="searchInput" type="search" placeholder="Ex: paiement, livraison, suivi…" autocomplete="off" />
          <div class="help">Astuce : vous pouvez chercher un mot clé dans toutes les catégories.</div>
        </div>

        <div class="card no-results" id="noResults" hidden>
          <p class="muted" style="margin:0;">Aucune question trouvée.</p>
        </div>
      </section>

      <!-- Tabs -->
      <nav class="tabs" aria-label="Catégories FAQ">
        <button class="tab is-active" type="button" data-tab="commande">Commande</button>
        <button class="tab" type="button" data-tab="paiement">Paiement</button>
        <button class="tab" type="button" data-tab="livraison">Livraison (Mali)</button>
        <button class="tab" type="button" data-tab="suivi">Suivi de commande</button>
        <button class="tab" type="button" data-tab="produits">Produits / Stock</button>
        <button class="tab" type="button" data-tab="compte">Compte / Sécurité</button>
      </nav>

      <!-- Contenu -->
      <section class="faq-content" aria-label="Contenu FAQ">
        <!-- Commande -->
        <section class="card faq-section" data-section="commande">
          <h2>Commande</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="c1">
                Comment passer une commande ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="c1" aria-hidden="true" hidden data-a>
                <p>Choisissez un produit, ajoutez au panier, puis validez la commande avec vos informations de livraison.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="c2">
                Puis-je modifier ma commande après validation ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="c2" aria-hidden="true" hidden data-a>
                <p>Contactez-nous rapidement. Tant que la commande n’est pas expédiée, une modification est souvent possible.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="c4">
                Puis-je annuler ma commande ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="c4" aria-hidden="true" hidden data-a>
                <p>Oui, si la commande n’est pas encore en cours de livraison, l’annulation reste simple.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="c5">
                Combien de temps pour confirmer ma commande ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="c5" aria-hidden="true" hidden data-a>
                <p>En général, un agent confirme rapidement. Gardez votre téléphone joignable : la confirmation sert à valider la ville/quartier, le point de repère et la disponibilité.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="c3">
                Ai-je besoin d’un compte pour commander ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="c3" aria-hidden="true" hidden data-a>
                <p>Non. Vous pouvez commander sans compte. Un compte peut aider à retrouver plus facilement vos commandes.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Paiement -->
        <section class="card faq-section" data-section="paiement" hidden>
          <h2>Paiement</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item is-important" id="faq-paiement-quand" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="p0">
                Quand est-ce que je paie ma commande ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="p0" aria-hidden="true" hidden data-a>
                <p>Le paiement se fait par Orange money, carte bancaire ou la livraison au moment où vous recevez votre commande.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="p1">
                Puis-je payer à la livraison ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="p1" aria-hidden="true" hidden data-a>
                <p>Oui. Vous payez au moment de recevoir votre commande (paiement à la livraison).</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="p2">
                Quels moyens de paiement acceptez-vous à la livraison ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="p2" aria-hidden="true" hidden data-a>
                <p>Orange money ou en espèce.</p>
              </div>
            </div>
            
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="p4">
                Puis-je payer par Mobile Money ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="p4" aria-hidden="true" hidden data-a>
                <p>Le paiement se fait à la livraison. Pour le moment, nous ne proposons pas de paiement Mobile Money sur le site.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Livraison -->
        <section class="card faq-section" data-section="livraison" hidden>
          <h2>Livraison (Mali)</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item is-important" id="faq-livraison-mali" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="l0">
                Comment se passe la livraison au Mali ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="l0" aria-hidden="true" hidden data-a>
                <p>Après validation, la livraison est organisée avec votre ville et quartier. Le livreur vous appelle pour confirmer l’heure et le lieu de remise.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="l1">
                Comment fonctionne la livraison sans adresse précise ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="l1" aria-hidden="true" hidden data-a>
                <p>Indiquez votre ville, quartier. Le livreur vous appelle pour finaliser la remise.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="l2">
                Le livreur va-t-il m’appeler ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="l2" aria-hidden="true" hidden data-a>
                <p>Oui, généralement. Assurez-vous que votre numéro est correct et disponible.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="l4">
                Combien de temps dure la livraison à Bamako ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="l4" aria-hidden="true" hidden data-a>
                <p>À Bamako, la livraison est souvent rapide après confirmation. Le délai dépend aussi du quartier et de la disponibilité.</p>
              </div>
            </div>
           
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="l5">
                Que faire si je ne suis pas disponible ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="l5" aria-hidden="true" hidden data-a>
                <p>Prévenez-nous dès que possible. Nous pouvons reprogrammer la livraison ou convenir d’un autre créneau. Gardez votre téléphone joignable pour faciliter la coordination.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Suivi -->
        <section class="card faq-section" data-section="suivi" hidden>
          <h2>Suivi de commande</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item is-important" id="faq-suivi-comment" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="s1">
                Comment suivre ma commande ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="s1" aria-hidden="true" hidden data-a>
                <p>Rendez-vous sur la page Suivi et saisissez votre numéro de commande (ex: CMD-2026-00124).</p>
              </div>
            </div>
            <div class="accordion-item is-important" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="s3">
                Comment fonctionne la confirmation par téléphone ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="s3" aria-hidden="true" hidden data-a>
                <p>Après votre commande, un agent peut vous appeler pour valider les infos (ville, quartier, disponibilité). Cela évite les erreurs et accélère la livraison.</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="s2">
                Pourquoi mon statut ne change pas ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="s2" aria-hidden="true" hidden data-a>
                <p>Certaines étapes prennent du temps. Si le statut ne change pas depuis 24h, contactez le support.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Produits/Stock -->
        <section class="card faq-section" data-section="produits" hidden>
          <h2>Produits / Stock</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="ps1">
                Les produits affichés sont-ils disponibles ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="ps1" aria-hidden="true" hidden data-a>
                <p>Nous indiquons la disponibilité sur les fiches produits. En V1, les stocks sont simples (en stock / limité / rupture).</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="ps2">
                Que faire si un produit n’est plus disponible ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="ps2" aria-hidden="true" hidden data-a>
                <p>Contactez-nous. Nous pouvons proposer un modèle similaire ou vous prévenir en cas de réassort.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Compte/Sécurité -->
        <section class="card faq-section" data-section="compte" hidden>
          <h2>Compte / Sécurité</h2>
          <div class="accordion" data-accordion>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="cs1">
                Comment se passe la confirmation de réception ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="cs1" aria-hidden="true" hidden data-a>
                <p>La livraison est confirmée au moment où vous recevez l’article et validez avec le livreur (confirmation à la réception).</p>
              </div>
            </div>
            <div class="accordion-item" data-q>
              <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="cs2">
                Mes informations sont-elles protégées ?
                <span class="accordion-icon" aria-hidden="true">+</span>
              </button>
              <div class="accordion-panel" id="cs2" aria-hidden="true" hidden data-a>
                <p>Nous utilisons vos informations uniquement pour préparer et livrer votre commande.</p>
              </div>
            </div>
          </div>
        </section>
      </section>

      <!-- Besoin d'aide -->
      <section class="card help-card reassurance-card" aria-labelledby="reassuranceTitle">
        <div class="card-head">
          <h2 id="reassuranceTitle">Un service simple et fiable</h2>
          <a class="btn btn-primary" href="<?php echo $base_url; ?>pages/contact.php">Nous contacter</a>
        </div>
        <p class="help-text">Paiement à la livraison, suivi clair et support local au Mali. Nous répondons rapidement pour vous aider.</p>

        <ul class="reassurance-list" aria-label="Points clés">
          <li>Paiement à la livraison</li>
          <li>Suivi clair</li>
          <li>Support local au Mali</li>
          <li>Réponse rapide</li>
        </ul>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
