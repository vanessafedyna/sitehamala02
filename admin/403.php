<?php
declare(strict_types=1);

/* Refus d'accès admin */

// Ce fichier est inclus par admin/_auth.php en cas de 403.
// Variables attendues:
// - $forbidden_message (string)

$page_title = 'Acces refuse';
$page_css = 'pages/admin-products.css';
$page_js = '';
require_once __DIR__ . '/_layout_header.php';
?>

<style>
  #main {
    min-height: calc(100vh - 88px);
  }
  .admin-403-page {
    display: grid;
    align-items: center;
    min-height: calc(100vh - 120px);
    padding: 24px 0 36px;
  }
  .admin-403-shell {
    width: min(100%, 760px);
    margin: 0 auto;
  }
  .admin-403-card {
    position: relative;
    overflow: hidden;
    display: grid;
    gap: 24px;
    padding: 34px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 28px;
    background:
      radial-gradient(circle at top right, rgba(31, 122, 79, 0.08), transparent 34%),
      linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(249, 252, 250, 0.96) 100%);
    box-shadow: 0 20px 44px rgba(18, 52, 36, 0.08);
  }
  .admin-403-card > * {
    position: relative;
    z-index: 1;
    min-width: 0;
  }
  .admin-403-head {
    display: grid;
    gap: 14px;
    justify-items: center;
    text-align: center;
  }
  .admin-403-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    padding: 8px 12px;
    border: 1px solid rgba(31, 122, 79, 0.12);
    border-radius: 999px;
    background: rgba(248, 251, 249, 0.92);
    color: #1f7a4f;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .admin-403-code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 22px;
    background: linear-gradient(180deg, #1f7a4f 0%, #17613f 100%);
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 800;
    box-shadow: 0 16px 30px rgba(31, 122, 79, 0.2);
  }
  .admin-403-title {
    margin: 0;
    color: #153222;
    font-size: clamp(1.9rem, 3vw, 2.6rem);
    line-height: 1.06;
  }
  .admin-403-text {
    margin: 0 auto;
    max-width: 52ch;
    color: rgba(21, 50, 34, 0.72);
    font-size: 1rem;
    line-height: 1.7;
  }
  .admin-403-note {
    display: grid;
    gap: 8px;
    padding: 18px 20px;
    border: 1px solid rgba(31, 122, 79, 0.08);
    border-radius: 20px;
    background: rgba(251, 252, 251, 0.94);
  }
  .admin-403-note__label {
    color: rgba(21, 50, 34, 0.58);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .admin-403-note__value {
    margin: 0;
    color: #153222;
    font-size: 1rem;
    line-height: 1.65;
    word-break: break-word;
    overflow-wrap: anywhere;
  }
  .admin-403-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
  }
  .admin-403-actions .btn {
    min-height: 46px;
    border-radius: 14px;
    background-image: none;
  }
  .admin-403-actions .btn.btn-primary {
    background: #1f7a4f;
    border-color: #1f7a4f;
    color: #ffffff;
  }
  .admin-403-actions .btn.btn-primary:hover,
  .admin-403-actions .btn.btn-primary:focus-visible {
    background: #17613f;
    border-color: #17613f;
    color: #ffffff;
  }
  .admin-403-actions .btn.btn-outline {
    background: rgba(248, 251, 249, 0.96);
    border-color: rgba(31, 122, 79, 0.14);
    color: #1f7a4f;
  }
  .admin-403-actions .btn.btn-outline:hover,
  .admin-403-actions .btn.btn-outline:focus-visible {
    background: rgba(31, 122, 79, 0.08);
    border-color: rgba(31, 122, 79, 0.22);
    color: #17613f;
  }
  .admin-403-actions .btn:focus-visible {
    outline: 0;
    box-shadow: 0 0 0 3px rgba(31, 122, 79, 0.14);
  }
  @media (max-width: 1024px) {
    .admin-403-shell {
      width: min(100%, 700px);
    }
  }
  @media (max-width: 820px) {
    .admin-403-page {
      padding-top: 16px;
    }
    .admin-403-card {
      padding: 28px;
    }
  }
  @media (max-width: 768px) {
    .admin-403-card {
      gap: 20px;
      border-radius: 24px;
    }
  }
  @media (max-width: 430px) {
    .admin-403-page {
      min-height: auto;
      padding: 10px 0 24px;
    }
    .admin-403-card {
      padding: 20px;
      border-radius: 20px;
    }
    .admin-403-code {
      width: 64px;
      height: 64px;
      border-radius: 18px;
      font-size: 1.35rem;
    }
    .admin-403-actions {
      display: grid;
      grid-template-columns: 1fr;
    }
    .admin-403-actions .btn {
      width: 100%;
      justify-content: center;
    }
  }
  @media (max-width: 360px) {
    .admin-403-card {
      padding: 18px;
    }
    .admin-403-title {
      font-size: 1.65rem;
    }
  }
</style>

<main id="main">
  <section>
    <div class="container">
      <div class="admin-403-page">
        <div class="admin-403-shell">
          <div class="admin-403-card" aria-label="Acces refuse">
            <div class="admin-403-head">
              <div class="admin-403-badge">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                Back-office admin
              </div>
              <div class="admin-403-code" aria-hidden="true">403</div>
              <h1 class="admin-403-title">Acces refuse</h1>
              <p class="admin-403-text">Vous n'avez pas les autorisations necessaires pour acceder a cette section du back-office.</p>
            </div>

            <div class="admin-403-note">
              <span class="admin-403-note__label">Details</span>
              <p class="admin-403-note__value"><?php echo e((string) ($forbidden_message ?? 'Acces reserve')); ?></p>
            </div>

            <div class="admin-403-actions">
              <a class="btn btn-primary" href="<?php echo e(base_url('admin/index.php')); ?>">
                <i class="fas fa-gauge-high" aria-hidden="true"></i> Retour au tableau de bord
              </a>
              <a class="btn btn-outline" href="<?php echo e(base_url('admin/logout.php')); ?>">
                <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Deconnexion
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
