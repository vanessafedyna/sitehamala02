<?php
declare(strict_types=1);

if (!function_exists('cart_item_key')) {
  function cart_item_key(int $productId, ?int $variantId = null): string
  {
    $productId = max(0, $productId);
    $variantId = $variantId !== null ? max(0, $variantId) : null;
    if ($variantId !== null && $variantId > 0) {
      return 'pv:' . $productId . ':' . $variantId;
    }
    return 'p:' . $productId;
  }
}

if (!function_exists('cart_parse_key')) {
  /**
   * @return array{product_id:int,variant_id:?int}
   */
  function cart_parse_key($rawKey): array
  {
    if (is_int($rawKey) || ctype_digit((string) $rawKey)) {
      return array('product_id' => (int) $rawKey, 'variant_id' => null);
    }

    $key = trim((string) $rawKey);
    if ($key === '') {
      return array('product_id' => 0, 'variant_id' => null);
    }

    if (preg_match('/^p:(\d+)$/', $key, $m)) {
      return array('product_id' => (int) $m[1], 'variant_id' => null);
    }
    if (preg_match('/^pv:(\d+):(\d+)$/', $key, $m)) {
      return array('product_id' => (int) $m[1], 'variant_id' => (int) $m[2]);
    }
    if (preg_match('/^v:(\d+)$/', $key, $m)) {
      return array('product_id' => 0, 'variant_id' => (int) $m[1]);
    }

    return array('product_id' => 0, 'variant_id' => null);
  }
}

if (!function_exists('cart_normalize_lines')) {
  /**
   * @param array<mixed,mixed> $cart
   * @return array<int, array{key:string,product_id:int,variant_id:?int,qty:int}>
   */
  function cart_normalize_lines(array $cart): array
  {
    $lines = array();

    foreach ($cart as $rawKey => $rawValue) {
      $qty = (int) $rawValue;
      if ($qty < 1) {
        $qty = 1;
      }

      if (is_array($rawValue)) {
        $qty = (int) ($rawValue['qty'] ?? 0);
        if ($qty < 1) {
          continue;
        }
        $productId = (int) ($rawValue['product_id'] ?? 0);
        $variantId = isset($rawValue['variant_id']) ? (int) $rawValue['variant_id'] : 0;
        $key = cart_item_key($productId, $variantId > 0 ? $variantId : null);
        $lines[$key] = array(
          'key' => $key,
          'product_id' => $productId,
          'variant_id' => $variantId > 0 ? $variantId : null,
          'qty' => $qty,
        );
        continue;
      }

      $parsed = cart_parse_key($rawKey);
      $productId = (int) ($parsed['product_id'] ?? 0);
      $variantId = isset($parsed['variant_id']) ? (int) ($parsed['variant_id'] ?? 0) : 0;
      $key = cart_item_key($productId, $variantId > 0 ? $variantId : null);

      if ($variantId <= 0 && $productId <= 0) {
        continue;
      }

      $lines[$key] = array(
        'key' => $key,
        'product_id' => $productId,
        'variant_id' => $variantId > 0 ? $variantId : null,
        'qty' => $qty,
      );
    }

    return array_values($lines);
  }
}

if (!function_exists('cart_normalize_map')) {
  /**
   * @param array<mixed,mixed> $cart
   * @return array<string,int>
   */
  function cart_normalize_map(array $cart): array
  {
    $normalized = array();
    foreach (cart_normalize_lines($cart) as $line) {
      $normalized[(string) $line['key']] = (int) $line['qty'];
    }
    return $normalized;
  }
}

if (!function_exists('cart_index_by_variant_id')) {
  /**
   * @param array<int, array<string,mixed>> $lines
   * @return array<int, array<string,mixed>>
   */
  function cart_index_by_variant_id(array $lines): array
  {
    $indexed = array();
    foreach ($lines as $line) {
      $variantId = (int) ($line['variant_id'] ?? 0);
      if ($variantId > 0) {
        $indexed[$variantId] = $line;
      }
    }
    return $indexed;
  }
}
