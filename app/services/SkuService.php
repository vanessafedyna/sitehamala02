<?php
declare(strict_types=1);

final class SkuService
{
  public static function generate(string $prefix = 'ML'): string
  {
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'ML');
    $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    return $prefix . '-' . $rand;
  }

  public static function isValid(string $sku): bool
  {
    return (bool) preg_match('/^[A-Z0-9]{2,8}-[A-Z0-9]{3,10}$/', strtoupper($sku));
  }
}