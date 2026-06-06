<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../helpers/functions.php';

function csrf_token(): string
{
  session_start_secure();
  if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
  }
  return (string) $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
  session_start_secure();
  if (!$token || empty($_SESSION['_csrf'])) {
    return false;
  }
  return hash_equals((string) $_SESSION['_csrf'], (string) $token);
}

function csrf_field(): string
{
  return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

