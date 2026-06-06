<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();
require_once __DIR__ . '/../app/helpers/contact_page.php';

$contact_context = contact_page_context();
$page_title = $contact_context['page_title'];
$page_meta_description = $contact_context['page_meta_description'];
$page_css = $contact_context['page_css'];
$page_js = $contact_context['page_js'];
$public_email = $contact_context['public_email'];
$public_whatsapp_url = $contact_context['public_whatsapp_url'];
$public_phone = function_exists('public_contact_phone_display') ? public_contact_phone_display() : '92828271';
$public_phone_tel = function_exists('public_contact_whatsapp_number') ? ('+' . public_contact_whatsapp_number()) : '+22392828271';

include __DIR__ . '/../includes/header.php';
?>

<main class="contact-page" id="main">
  <header class="page-head">
    <div class="container">
      <h1>Contact</h1>
      <p class="subtitle">Une question ? Besoin d’aide pour une commande ? Nous sommes disponibles.</p>
    </div>
  </header>

  <section class="section" aria-label="Contact et formulaire">
    <div class="container contact-grid">
      <aside class="stack" aria-label="Nos coordonnées">
        <h2 class="section-title">Nos coordonnées</h2>

        <div class="card">
          <h3 class="card-title">Téléphone</h3>
          <p class="muted">Contact téléphonique disponible via notre support.</p>
          <a class="link-cta" href="tel:<?php echo e($public_phone_tel); ?>"><?php echo e($public_phone); ?></a>
        </div>

        <div class="card">
          <h3 class="card-title">WhatsApp</h3>
          <p class="muted">Message WhatsApp (réponse rapide selon horaires).</p>
          <a class="link-cta" href="<?php echo e($public_whatsapp_url); ?>" target="_blank" rel="noopener">Ouvrir WhatsApp</a>
        </div>

        <div class="card">
          <h3 class="card-title">Email</h3>
          <p class="muted">Pour les demandes détaillées.</p>
          <a class="link-cta" href="mailto:<?php echo e($public_email); ?>"><?php echo e($public_email); ?></a>
        </div>

        <div class="card">
          <h3 class="card-title">Horaires</h3>
          <p class="muted">Lun–Ven 8h–18h • Sam 9h–13h</p>
          <p class="hint">Pour la livraison au Mali, gardez votre téléphone disponible.</p>
        </div>
      </aside>

      <section class="stack" aria-label="Envoyer un message">
        <h2 class="section-title">Envoyer un message</h2>

        <form class="card form" id="contactForm" novalidate>
          <div class="field">
            <label for="fullName">Nom complet <span aria-hidden="true">*</span></label>
            <input id="fullName" name="fullName" type="text" autocomplete="name" required />
            <div class="field-error" id="fullNameError" aria-live="polite"></div>
          </div>

          <div class="two-cols">
            <div class="field">
              <label for="phone">Téléphone</label>
              <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+223…" />
              <div class="field-help">Au moins un contact : téléphone ou email.</div>
              <div class="field-error" id="phoneError" aria-live="polite"></div>
            </div>

            <div class="field">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="ex: nom@email.com" />
              <div class="field-help">Au moins un contact : téléphone ou email.</div>
              <div class="field-error" id="emailError" aria-live="polite"></div>
            </div>
          </div>

          <div class="field">
            <label for="subject">Sujet</label>
            <select id="subject" name="subject">
              <option value="commande">Commande</option>
              <option value="livraison">Livraison</option>
              <option value="produit">Produit</option>
              <option value="autre">Autre</option>
            </select>
          </div>

          <div class="field">
            <label for="message">Message <span aria-hidden="true">*</span></label>
            <textarea id="message" name="message" rows="5" required placeholder="Dites-nous comment on peut vous aider…"></textarea>
            <div class="field-error" id="messageError" aria-live="polite"></div>
          </div>

          <label class="check">
            <input id="consent" name="consent" type="checkbox" />
            <span>J’accepte d’être contacté(e) par téléphone</span>
          </label>

          <div class="form-actions">
            <button class="btn btn-primary" id="submitBtn" type="submit">Envoyer</button>
          </div>

          <div class="notice" id="formNotice" role="status" aria-live="polite"></div>
        </form>
      </section>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
