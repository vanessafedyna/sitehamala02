<?php
declare(strict_types=1);

final class ProductVariantService
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function isSupported(): bool
  {
    return db_table_columns($this->pdo, 'product_variants') !== array();
  }

  public static function normalizePublicSize(string $size): string
  {
    $value = trim($size);
    if ($value === '') {
      return '';
    }

    $value = strtoupper($value);
    return preg_replace('/\s+/', '', $value) ?: '';
  }

  public static function isAllowedPublicSize(string $size): bool
  {
    $value = self::normalizePublicSize($size);
    if ($value === '' || preg_match('/,/', $value)) {
      return false;
    }

    $allowedTextSizes = array('XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL');
    if (in_array($value, $allowedTextSizes, true)) {
      return true;
    }

    $allowedNumericSizes = array('34', '36', '38', '40', '42', '44', '46');
    return in_array($value, $allowedNumericSizes, true);
  }

  /**
   * @param array<string,mixed>|null $variant
   */
  public function isPurchasableVariant(?array $variant): bool
  {
    if (!$variant) {
      return false;
    }

    if ((int) ($variant['id'] ?? 0) <= 0) {
      return false;
    }
    if ((int) ($variant['product_id'] ?? 0) <= 0) {
      return false;
    }
    if ((int) ($variant['is_active'] ?? 0) !== 1) {
      return false;
    }
    if ((int) ($variant['stock'] ?? 0) <= 0) {
      return false;
    }

    return self::isAllowedPublicSize((string) ($variant['size'] ?? ''));
  }

  /**
   * @return array<int, array<string,mixed>>
   */
  public function listByProduct(int $productId, bool $activeOnly = false, bool $inStockOnly = false): array
  {
    if ($productId <= 0 || !$this->isSupported()) {
      return array();
    }

    $where = array('product_id = :product_id');
    $params = array('product_id' => $productId);

    if ($activeOnly) {
      $where[] = 'is_active = 1';
    }
    if ($inStockOnly) {
      $where[] = 'stock > 0';
    }

    $sql = 'SELECT id, product_id, size, color, stock, is_active, created_at
      FROM product_variants
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY size ASC, color ASC, id ASC';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    foreach ($rows as &$row) {
      $row['id'] = (int) ($row['id'] ?? 0);
      $row['product_id'] = (int) ($row['product_id'] ?? 0);
      $row['size'] = trim((string) ($row['size'] ?? ''));
      $color = trim((string) ($row['color'] ?? ''));
      $row['color'] = ($color === '' ? null : $color);
      $row['stock'] = (int) ($row['stock'] ?? 0);
      $row['is_active'] = (int) ($row['is_active'] ?? 0);
      $row['created_at'] = (string) ($row['created_at'] ?? '');
    }
    unset($row);

    return $rows;
  }

  /**
   * @return array<string,mixed>|null
   */
  public function findById(int $variantId): ?array
  {
    if ($variantId <= 0 || !$this->isSupported()) {
      return null;
    }

    $stmt = $this->pdo->prepare(
      'SELECT id, product_id, size, color, stock, is_active, created_at
       FROM product_variants
       WHERE id = :id
       LIMIT 1'
    );
    $stmt->execute(array('id' => $variantId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
      return null;
    }

    $rows = $this->normalizeRows(array($row));
    return $rows ? $rows[0] : null;
  }

  /**
   * @return array<string,mixed>|null
   */
  public function findForProduct(int $productId, int $variantId, bool $mustBeActive = false): ?array
  {
    $variant = $this->findById($variantId);
    if (!$variant) {
      return null;
    }
    if ((int) ($variant['product_id'] ?? 0) !== $productId) {
      return null;
    }
    if ($mustBeActive && (int) ($variant['is_active'] ?? 0) !== 1) {
      return null;
    }
    return $variant;
  }

  public function belongsToProduct(int $productId, int $variantId): bool
  {
    return $this->findForProduct($productId, $variantId, false) !== null;
  }

  /**
   * @param array<int, array<string,mixed>> $rows
   */
  public function replaceForProduct(int $productId, array $rows): void
  {
    if ($productId <= 0 || !$this->isSupported()) {
      return;
    }

    $existing = array();
    foreach ($this->listByProduct($productId) as $row) {
      $variantId = (int) ($row['id'] ?? 0);
      if ($variantId > 0) {
        $existing[$variantId] = $row;
      }
    }

    $stmtInsert = $this->pdo->prepare(
      'INSERT INTO product_variants (product_id, size, color, stock, is_active)
       VALUES (:product_id, :size, :color, :stock, :is_active)'
    );
    $stmtUpdate = $this->pdo->prepare(
      'UPDATE product_variants
       SET size = :size, color = :color, stock = :stock, is_active = :is_active
       WHERE id = :id AND product_id = :product_id'
    );
    $keptIds = array();

    foreach ($rows as $row) {
      $variantId = (int) ($row['id'] ?? 0);
      $size = trim((string) ($row['size'] ?? ''));
      $color = trim((string) ($row['color'] ?? ''));
      $stock = (int) ($row['stock'] ?? 0);
      $isActive = array_key_exists('is_active', $row) ? (int) $row['is_active'] : 1;

      if ($size === '') {
        continue;
      }

      $params = array(
        'product_id' => $productId,
        'size' => $size,
        'color' => ($color === '' ? null : $color),
        'stock' => max(0, $stock),
        'is_active' => $isActive === 1 ? 1 : 0,
      );

      if ($variantId > 0 && isset($existing[$variantId])) {
        $params['id'] = $variantId;
        $stmtUpdate->execute($params);
        $keptIds[$variantId] = true;
        continue;
      }

      $stmtInsert->execute($params);
      $newId = (int) $this->pdo->lastInsertId();
      if ($newId > 0) {
        $keptIds[$newId] = true;
      }
    }

    foreach (array_keys($existing) as $existingId) {
      if (isset($keptIds[$existingId])) {
        continue;
      }
      $stmtDelete = $this->pdo->prepare('DELETE FROM product_variants WHERE id = :id AND product_id = :product_id');
      $stmtDelete->execute(array(
        'id' => $existingId,
        'product_id' => $productId,
      ));
    }
  }

  public function calculateActiveStock(int $productId): int
  {
    if ($productId <= 0 || !$this->isSupported()) {
      return 0;
    }

    $stmt = $this->pdo->prepare(
      'SELECT COALESCE(SUM(stock), 0)
       FROM product_variants
       WHERE product_id = :product_id AND is_active = 1'
    );
    $stmt->execute(array('product_id' => $productId));
    return (int) $stmt->fetchColumn();
  }

  public function hasAnyVariants(int $productId): bool
  {
    if ($productId <= 0 || !$this->isSupported()) {
      return false;
    }

    $stmt = $this->pdo->prepare('SELECT id FROM product_variants WHERE product_id = :product_id LIMIT 1');
    $stmt->execute(array('product_id' => $productId));
    return (bool) $stmt->fetchColumn();
  }

  public function decrementStock(int $variantId, int $qty): bool
  {
    if ($variantId <= 0 || $qty <= 0 || !$this->isSupported()) {
      return false;
    }

    $stmt = $this->pdo->prepare(
      'UPDATE product_variants
       SET stock = stock - :qty_dec
       WHERE id = :id AND is_active = 1 AND stock >= :qty_chk'
    );
    $stmt->execute(array(
      'qty_dec' => $qty,
      'qty_chk' => $qty,
      'id' => $variantId,
    ));

    return $stmt->rowCount() === 1;
  }

  public function incrementStock(int $variantId, int $qty): bool
  {
    if ($variantId <= 0 || $qty <= 0 || !$this->isSupported()) {
      return false;
    }

    $stmt = $this->pdo->prepare(
      'UPDATE product_variants
       SET stock = stock + :qty
       WHERE id = :id'
    );
    $stmt->execute(array(
      'qty' => $qty,
      'id' => $variantId,
    ));

    return $stmt->rowCount() === 1;
  }

  /**
   * @param array<int, array<string,mixed>> $rows
   * @return array<int, array<string,mixed>>
   */
  private function normalizeRows(array $rows): array
  {
    foreach ($rows as &$row) {
      $row['id'] = (int) ($row['id'] ?? 0);
      $row['product_id'] = (int) ($row['product_id'] ?? 0);
      $row['size'] = trim((string) ($row['size'] ?? ''));
      $color = trim((string) ($row['color'] ?? ''));
      $row['color'] = ($color === '' ? null : $color);
      $row['stock'] = (int) ($row['stock'] ?? 0);
      $row['is_active'] = (int) ($row['is_active'] ?? 0);
      $row['created_at'] = (string) ($row['created_at'] ?? '');
    }
    unset($row);

    return $rows;
  }
}
