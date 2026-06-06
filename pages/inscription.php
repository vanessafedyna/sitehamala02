<?php
require_once __DIR__ . '/../app/bootstrap.php';
auth_start();

$user = current_user();
if ($user) {
  $role = strtolower(trim((string) ($user['role'] ?? '')));
  if (in_array($role, array('admin', 'partner'), true)) {
    redirect('admin/index.php');
  }
  redirect('pages/mes-commandes.php');
}

$page_title = 'Inscription';
// Réutilise le design de la page connexion (même carte).
$body_class = 'page-auth-register';
$page_css = 'pages/inscription.css';
$page_js = 'pages/inscription.js';

include __DIR__ . '/../includes/header.php';
?>

<main class="auth-page" id="main">
  <header class="page-head">
    <div class="container">
      <h1>Créer un compte</h1>
      <p class="subtitle">Inscription rapide avec votre numéro de téléphone (email optionnel).</p>
    </div>
  </header>

  <section class="section" aria-label="Formulaire d'inscription">
    <div class="container">
      <div class="login-card">
        <div class="login-title">Inscription</div>

        <form id="registerForm" novalidate>
          <div class="field">
            <label class="sr-only" for="name">Nom</label>
            <input id="name" name="name" class="input" type="text" autocomplete="name" required placeholder=" " />
            <span class="label" aria-hidden="true">Prenom(s)</span>
            <div class="field-error" id="nameError" aria-live="polite"></div>
          </div>

          <div class="field">
            <label class="sr-only" for="last_name">Nom de famille</label>
            <input id="last_name" name="last_name" class="input" type="text" autocomplete="family-name" required placeholder=" " />
            <span class="label" aria-hidden="true">Nom de famille</span>
            <div class="field-error" id="lastNameError" aria-live="polite"></div>
          </div>

          <div class="field">
            <label class="sr-only" for="phone">Téléphone</label>
            <input id="phone" name="phone" class="input" type="tel" inputmode="tel" autocomplete="tel" required placeholder=" " />
            <span class="label" aria-hidden="true">Téléphone</span>
            <div class="field-error" id="phoneError" aria-live="polite"></div>
          </div>

          <div class="field">
            <label class="sr-only" for="email">Email (optionnel)</label>
            <input id="email" name="email" class="input" type="email" autocomplete="email" placeholder=" " />
            <span class="label" aria-hidden="true">Email (optionnel)</span>
            <div class="field-error" id="emailError" aria-live="polite"></div>
          </div>

          <div class="field">
            <label class="sr-only" for="password">Mot de passe</label>
            <input id="password" name="password" class="input" type="password" autocomplete="new-password" required minlength="10" placeholder=" " />
            <span class="label" aria-hidden="true">Mot de passe</span>
            <div class="field-error" id="passwordError" aria-live="polite"></div>
          </div>

          <div class="field">
            <label class="sr-only" for="password2">Confirmer</label>
            <input id="password2" name="password2" class="input" type="password" autocomplete="new-password" required minlength="10" placeholder=" " />
            <span class="label" aria-hidden="true">Confirmer</span>
            <div class="field-error" id="password2Error" aria-live="polite"></div>
          </div>

          <div class="options">
            <label class="check">
              <input id="showPassword" type="checkbox" />
              <span>Afficher les mots de passe</span>
            </label>
          </div>

          <div class="notice is-hidden" id="registerNotice" role="status" aria-live="polite"></div>

          <button class="button" id="registerBtn" type="submit">Créer mon compte</button>

          <div class="divider" role="separator" aria-hidden="true"></div>

          <div class="signup">
            <span class="muted">Déjà un compte ?</span>
            <a class="link" href="<?php echo $base_url; ?>pages/connexion.php">Se connecter</a>
          </div>
        </form>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

