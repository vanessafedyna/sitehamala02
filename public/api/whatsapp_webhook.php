<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

http_response_code(410);
echo json_encode(array(
  'ok' => false,
  'error' => 'disabled',
  'message' => 'WhatsApp API webhook disabled. Use the public wa.me link instead.',
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
