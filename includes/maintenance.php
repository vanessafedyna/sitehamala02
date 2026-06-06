<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
http_response_code(503);
header('Retry-After: 600');

$title = 'Maintenance';
$msg = isset($maintenance_message) ? (string) $maintenance_message : 'Maintenance en cours. Merci de revenir plus tard.';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <?php
    // charger CSS principal si possible
    $base = function_exists('base_url') ? base_url('') : '/';
  ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars($base . 'assets/css/main.css', ENT_QUOTES, 'UTF-8'); ?>">
  <style>
    .maintenance-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px;}
    .maintenance-card{max-width:720px;width:100%;background:rgba(255,255,255,0.92);border:1px solid rgba(14,11,8,0.12);border-radius:18px;padding:22px 20px;}
    .maintenance-title{margin:0 0 8px;font-size:2rem;}
    .maintenance-msg{margin:0;color:rgba(14,11,8,0.72);line-height:1.6;}
  </style>
</head>
<body>
  <div class="maintenance-wrap">
    <div class="maintenance-card" role="status" aria-live="polite">
      <h1 class="maintenance-title">Maintenance</h1>
      <p class="maintenance-msg"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
  </div>
</body>
</html>

