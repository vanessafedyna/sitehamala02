<?php
declare(strict_types=1);

if (!function_exists('canonical_shop_email')) {
  function canonical_shop_email(string $email, string $fallback = ''): string
  {
    $email = strtolower(trim($email));
    $fallback = strtolower(trim($fallback));

    $map = array(
      'support@sora-collection.com' => 'support@soracollectionmali.com',
      'support@malishop.test' => 'support@soracollectionmali.com',
      'contact@sora-collection.com' => 'contact@soracollectionmali.com',
      'admin@malishop.com' => 'admin@soracollectionmali.com',
    );

    if ($email !== '' && isset($map[$email])) {
      return $map[$email];
    }

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return $email;
    }

    return $fallback;
  }
}

if (!function_exists('public_contact_email')) {
  function public_contact_email(): string
  {
    static $cache = null;
    if (is_string($cache)) {
      return $cache;
    }

    $fallback = 'contact@soracollectionmali.com';
    $value = '';
    if (function_exists('setting')) {
      $value = trim((string) setting('public_contact_email', ''));
      if ($value === '') {
        $value = trim((string) setting('shop_public_email', ''));
      }
    }
    $cache = canonical_shop_email($value, $fallback);
    return $cache;
  }
}

if (!function_exists('public_support_email')) {
  function public_support_email(): string
  {
    static $cache = null;
    if (is_string($cache)) {
      return $cache;
    }

    $fallback = 'support@soracollectionmali.com';
    $value = '';
    if (function_exists('setting')) {
      $value = trim((string) setting('support_email', ''));
      if ($value === '') {
        $value = trim((string) setting('shop_email', ''));
      }
    }
    $cache = canonical_shop_email($value, $fallback);
    return $cache;
  }
}

if (!function_exists('public_admin_email')) {
  function public_admin_email(): string
  {
    static $cache = null;
    if (is_string($cache)) {
      return $cache;
    }

    $fallback = 'admin@soracollectionmali.com';
    $value = '';
    if (function_exists('setting')) {
      $value = trim((string) setting('notify_admin_email', ''));
    }

    $cache = canonical_shop_email($value, $fallback);
    return $cache;
  }
}

if (!function_exists('public_contact_whatsapp_number')) {
  function public_contact_whatsapp_number(): string
  {
    static $cache = null;
    if (is_string($cache)) {
      return $cache;
    }

    $fallback = '22392828271';
    $raw = '';
    if (function_exists('setting')) {
      $raw = trim((string) setting('shop_whatsapp_number', ''));
    }

    $digits = (string) preg_replace('/[^0-9]/', '', $raw);
    if ($digits === '' || $digits === '22300000000' || preg_match('/^0+$/', $digits)) {
      $digits = $fallback;
    }

    if (strlen($digits) === 8) {
      $digits = '223' . $digits;
    }

    if (!preg_match('/^\d{11,15}$/', $digits)) {
      $digits = $fallback;
    }

    $cache = $digits;
    return $cache;
  }
}

if (!function_exists('public_contact_phone_display')) {
  function public_contact_phone_display(): string
  {
    static $cache = null;
    if (is_string($cache)) {
      return $cache;
    }

    $fallback = '92828271';
    $digits = public_contact_whatsapp_number();

    if (strlen($digits) === 11 && str_starts_with($digits, '223')) {
      $digits = substr($digits, 3);
    }

    if ($digits === '' || preg_match('/^0+$/', $digits)) {
      $digits = $fallback;
    }

    $cache = $digits;
    return $cache;
  }
}

if (!function_exists('public_contact_whatsapp_url')) {
  function public_contact_whatsapp_url(string $message = ''): string
  {
    $number = public_contact_whatsapp_number();
    if ($message !== '') {
      return 'https://wa.me/' . rawurlencode($number) . '?text=' . rawurlencode($message);
    }
    return 'https://wa.me/' . rawurlencode($number);
  }
}
