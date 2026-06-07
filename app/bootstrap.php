<?php
declare(strict_types=1);

// Bootstrap back-end (sans framework).
// Centralise: env + helpers + session + CSRF + auth.

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/core/security_headers.php';
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/csrf.php';
require_once __DIR__ . '/core/auth.php';

send_global_security_headers();
