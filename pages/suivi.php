<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();
require_once __DIR__ . '/../app/helpers/suivi_page.php';

$suivi_context = suivi_page_context($_GET);
$prefill_order_number = $suivi_context['prefill_order_number'];
$page_title = $suivi_context['page_title'];
$page_meta_description = $suivi_context['page_meta_description'];
$page_css = $suivi_context['page_css'];
$page_js = $suivi_context['page_js'];
$public_email = $suivi_context['public_email'];
$public_whatsapp_url = $suivi_context['public_whatsapp_url'];
$public_phone = function_exists('public_contact_phone_display') ? public_contact_phone_display() : '92828271';
$public_phone_tel = function_exists('public_contact_whatsapp_number') ? ('+' . public_contact_whatsapp_number()) : '+22392828271';

include __DIR__ . '/../includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <h1>Suivre ma commande</h1>
    <p class="subtitle">Entrez votre numéro de commande pour voir l’état et l’historique.</p>
  </div>
</section>

<main id="main" class="track" tabindex="-1">
  <div class="container">
    <section class="card form-card" aria-labelledby="formTitle">
      <h2 id="formTitle">Suivi</h2>

      <form id="trackForm" class="form" novalidate data-endpoint="<?php echo e(base_url('public/api/order_track.php')); ?>">
        <?php echo csrf_field(); ?>
        <div class="field">
          <label for="orderNumber">Numéro de commande</label>
          <input
            id="orderNumber"
            name="orderNumber"
            type="text"
            inputmode="text"
            autocomplete="off"
            placeholder="ML-2026-ABC123"
            aria-describedby="orderHelp orderError"
            value="<?php echo e($prefill_order_number); ?>"
          />
          <div id="orderHelp" class="help">Vous le recevez après validation de la commande.</div>
          <div id="orderError" class="error" role="alert" aria-live="polite"></div>
        </div>

        <div class="field track-phone-field">
          <label for="phone">Téléphone</label>
          <input
            id="phone"
            name="phone"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="+22370123456"
            aria-describedby="phoneHelp phoneError"
          />
          <div id="phoneHelp" class="help">Pour sécuriser le suivi, nous vérifions votre téléphone.</div>
          <div id="phoneError" class="error" role="alert" aria-live="polite"></div>
        </div>

        <div class="form-actions">
          <button id="trackBtn" class="btn btn-primary" type="submit">Suivre ma commande</button>
          <div id="message" class="message" role="status" aria-live="polite" aria-atomic="true"></div>
        </div>
      </form>
    </section>

    <section id="results" class="results" hidden aria-label="Résultats du suivi">
      <div class="results-grid">
        <div class="results-col">
          <section class="card" aria-labelledby="statusTitle">
            <div class="card-head status-head">
              <h2 id="statusTitle">Statut actuel</h2>
              <div class="pill payment-pill">Paiement à la livraison</div>
            </div>

            <div class="status-row">
              <div class="status-label">Commande</div>
              <div class="status-value" id="outOrderNumber">—</div>
            </div>
            <div class="status-row">
              <div class="status-label">Statut</div>
              <div class="status-value">
                <span id="outStatusBadge" class="status-badge" data-status="">—</span>
              </div>
            </div>
            <div class="status-row">
              <div class="status-label">Dernière mise à jour</div>
              <div class="status-value" id="outUpdatedAt">—</div>
            </div>

            <div class="alert" id="cancelAlert" hidden>
              <strong>Commande annulée</strong>
              <p class="muted" style="margin:6px 0 0;">Besoin d’aide ? Contactez le support.</p>
              <a class="btn btn-secondary" href="<?php echo e(base_url('pages/contact.php')); ?>">Contacter le support</a>
            </div>

            <div class="steps" id="steps" aria-label="Étapes de suivi">
              <div class="step is-upcoming">
                <div class="step-dot" aria-hidden="true"></div>
                <div class="step-label">Nouvelle</div>
              </div>
              <div class="step is-upcoming">
                <div class="step-dot" aria-hidden="true"></div>
                <div class="step-label">Confirmée</div>
              </div>
              <div class="step is-upcoming">
                <div class="step-dot" aria-hidden="true"></div>
                <div class="step-label">Préparée</div>
              </div>
              <div class="step is-upcoming">
                <div class="step-dot" aria-hidden="true"></div>
                <div class="step-label">En livraison</div>
              </div>
              <div class="step is-upcoming">
                <div class="step-dot" aria-hidden="true"></div>
                <div class="step-label">Livrée</div>
              </div>
            </div>

            <div class="divider"></div>
            <h3 class="subhead">Articles</h3>
            <div id="itemsList" class="items-list" aria-label="Articles de la commande"></div>

            <div class="divider"></div>
            <div class="status-row">
              <div class="status-label">Total</div>
              <div class="status-value" id="outTotal">—</div>
            </div>
          </section>
        </div>

        <div class="results-col">
          <section class="card" aria-labelledby="timelineTitle">
            <h2 id="timelineTitle">Historique</h2>
            <div class="timeline" id="timeline" aria-label="Timeline de la commande"></div>
          </section>
        </div>
      </div>
    </section>

    <section class="card help-card" aria-labelledby="helpTitle">
      <div class="card-head">
        <h2 id="helpTitle">Besoin d’aide ?</h2>
        <a class="btn btn-secondary support-cta" href="<?php echo e(base_url('pages/contact.php')); ?>">Contacter le support</a>
      </div>
      <p class="help-text">Si vous n’avez pas votre numéro de commande, contactez-nous et indiquez votre téléphone.</p>

      <div class="contact-grid" aria-label="Infos contact">
        <div class="contact-item">
          <div class="contact-label">Téléphone</div>
          <a class="contact-value" href="tel:<?php echo e($public_phone_tel); ?>"><?php echo e($public_phone); ?></a>
        </div>
        <div class="contact-item">
          <div class="contact-label">WhatsApp</div>
          <a class="contact-value" href="<?php echo e($public_whatsapp_url); ?>" target="_blank" rel="noopener">Contacter sur WhatsApp</a>
        </div>
        <div class="contact-item">
          <div class="contact-label">Email</div>
          <a class="contact-value" href="mailto:<?php echo e($public_email); ?>"><?php echo e($public_email); ?></a>
        </div>
      </div>
    </section>

    <section class="card faq" aria-labelledby="faqTitle">
      <div class="faq-head">
        <h2 id="faqTitle">FAQ</h2>
        <p class="faq-intro">Questions fréquentes sur le suivi de votre commande.</p>
      </div>

      <div class="accordion" data-accordion>
        <div class="accordion-item">
          <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="f1">
            Je n’ai pas mon numéro de commande, que faire ?
            <span class="accordion-icon" aria-hidden="true">+</span>
          </button>
          <div class="accordion-panel" id="f1" aria-hidden="true" hidden>
            <p>Contactez le support avec votre téléphone. Nous vous aiderons à retrouver votre commande.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="f2">
            Pourquoi le statut ne change pas ?
            <span class="accordion-icon" aria-hidden="true">+</span>
          </button>
          <div class="accordion-panel" id="f2" aria-hidden="true" hidden>
            <p>Certaines étapes prennent du temps. Si le statut reste bloqué, contactez le support.</p>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" type="button" aria-expanded="false" aria-controls="f3">
            Comment se passe la confirmation de livraison ?
            <span class="accordion-icon" aria-hidden="true">+</span>
          </button>
          <div class="accordion-panel" id="f3" aria-hidden="true" hidden>
            <p>La commande est confirmée à la réception, directement avec le livreur.</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

