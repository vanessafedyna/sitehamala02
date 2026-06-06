<?php
declare(strict_types=1);

/**
 * Flash messages minimalistes (session).
 * Usage:
 *  - admin_flash_set('products', 'success', '...');
 *  - $flash = admin_flash_get('products');
 */

function admin_flash_set(string $key, string $type, string $message): void
{
  if (session_status() !== PHP_SESSION_ACTIVE) {
    return;
  }

  if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
    $_SESSION['_flash'] = array();
  }

  $_SESSION['_flash'][$key] = array(
    'type' => $type,
    'message' => $message,
  );
}

/**
 * @return array{type:string,message:string}|null
 */
function admin_flash_get(string $key): ?array
{
  if (session_status() !== PHP_SESSION_ACTIVE) {
    return null;
  }

  if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash']) || empty($_SESSION['_flash'][$key]) || !is_array($_SESSION['_flash'][$key])) {
    return null;
  }

  $val = $_SESSION['_flash'][$key];
  unset($_SESSION['_flash'][$key]);

  $type = isset($val['type']) ? (string) $val['type'] : '';
  $message = isset($val['message']) ? (string) $val['message'] : '';
  if ($type === '' || $message === '') {
    return null;
  }

  return array('type' => $type, 'message' => $message);
}

