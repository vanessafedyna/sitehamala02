<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

api_require_method('POST');
api_rate_limit('logout', 60, 60);

try {
  logout_user();
  json_response(200, array('ok' => true));
} catch (Throwable $e) {
  error_log('[api/logout] ' . $e->getMessage());
  json_response(500, array('ok' => false, 'message' => 'Impossible de se deconnecter.'));
}

