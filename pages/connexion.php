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

$password_reset_notice = trim((string) ($_SESSION['password_reset_notice'] ?? ''));
unset($_SESSION['password_reset_notice']);

$page_title = 'Connexion';
$body_class = 'page-auth-login';
$page_css = 'pages/connexion-site.css';
$page_js = 'pages/connexion.js';

include __DIR__ . '/../includes/header.php';
?>

<main class="auth-page" id="main">
  <header class="page-head">
    <div class="container">
      <h1>Connexion</h1>
      <p class="subtitle">Accedez a votre espace pour gerer votre compte.</p>
    </div>
  </header>

  <section class="section" aria-label="Formulaire de connexion">
    <div class="container">
      <div class="login-card">
        <div class="login-title">Login</div>

        <form id="loginForm" novalidate>
          <div class="field">
            <label class="sr-only" for="identifier">Telephone ou email</label>
            <input id="identifier" name="identifier" class="input" type="text" autocomplete="username" required placeholder=" " />
            <span class="icon" aria-hidden="true">
              <svg viewBox="0 0 512 512" width="22" height="22">
                <path
                  fill="currentColor"
                  d="M256 0c-74.439 0-135 60.561-135 135s60.561 135 135 135 135-60.561 135-135S330.439 0 256 0zM423.966 358.195C387.006 320.667 338.009 300 286 300h-60c-52.008 0-101.006 20.667-137.966 58.195C51.255 395.539 31 444.833 31 497c0 8.284 6.716 15 15 15h420c8.284 0 15-6.716 15-15 0-52.167-20.255-101.461-57.034-138.805z"
                />
              </svg>
            </span>
            <span class="label" aria-hidden="true">Telephone ou email</span>
            <div class="field-error" id="identifierError" aria-live="polite"></div>
          </div>

          <div class="field">
            <label class="sr-only" for="password">Mot de passe</label>
            <input
              id="password"
              name="password"
              class="input"
              type="password"
              autocomplete="current-password"
              required
              placeholder=" "
            />
            <span class="icon" aria-hidden="true">
              <svg viewBox="0 0 512 512" width="22" height="22">
                <path
                  fill="currentColor"
                  d="M336 192h-16v-64C320 57.406 262.594 0 192 0S64 57.406 64 128v64H48c-26.453 0-48 21.523-48 48v224c0 26.477 21.547 48 48 48h288c26.453 0 48-21.523 48-48V240c0-26.477-21.547-48-48-48zm-229.332-64c0-47.063 38.27-85.332 85.332-85.332s85.332 38.27 85.332 85.332v64H106.668z"
                />
              </svg>
            </span>
            <span class="label" aria-hidden="true">Mot de passe</span>
            <div class="field-error" id="passwordError" aria-live="polite"></div>
          </div>

          <div class="options">
            <label class="check">
              <input id="showPassword" type="checkbox" />
              <span>Afficher le mot de passe</span>
            </label>
            <a class="link" href="<?php echo e(base_url('pages/mot-de-passe-oublie.php')); ?>">Mot de passe oublie ?</a>
          </div>

          <div class="notice <?php echo $password_reset_notice === '' ? 'is-hidden' : 'is-success'; ?>" id="loginNotice" role="status" aria-live="polite"><?php echo e($password_reset_notice); ?></div>

          <button class="button" id="loginBtn" type="submit">Se connecter</button>

          <div class="divider" role="separator" aria-hidden="true"></div>

          <div class="signup">
            <span class="muted">Nouveau ?</span>
            <a class="link" href="<?php echo $base_url; ?>pages/inscription.php">Creer un compte</a>
          </div>
        </form>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
