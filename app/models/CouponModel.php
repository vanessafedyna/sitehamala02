<?php
declare(strict_types=1);

final class CouponModel
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function exists(): bool
  {
    try {
      $this->pdo->query("SELECT 1 FROM coupons LIMIT 1");
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }

  /**
   * @return array<string,mixed>|null
   */
  public function findByCodeForUpdate(string $code): ?array
  {
    $code = strtoupper(trim($code));
    if ($code === '') return null;

    $stmt = $this->pdo->prepare('SELECT * FROM coupons WHERE code = :code LIMIT 1 FOR UPDATE');
    $stmt->execute(array('code' => $code));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  /**
   * @return int[]
   */
  public function getCategoryIds(int $couponId): array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT category_id FROM coupon_categories WHERE coupon_id = :id');
      $stmt->execute(array('id' => (int) $couponId));
      $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: array();
      return array_values(array_filter(array_map('intval', $rows), fn ($v) => $v > 0));
    } catch (Throwable $e) {
      return array();
    }
  }

  public function incrementUses(int $couponId): void
  {
    $stmt = $this->pdo->prepare('UPDATE coupons SET uses_count = uses_count + 1 WHERE id = :id');
    $stmt->execute(array('id' => (int) $couponId));
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public function list(array $filters = array()): array
  {
    $where = array();
    $params = array();

    $q = strtoupper(trim((string) ($filters['q'] ?? '')));
    if ($q !== '') {
      $where[] = 'code LIKE :q';
      $params['q'] = '%' . $q . '%';
    }

    if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
      $where[] = 'is_active = :a';
      $params['a'] = ((int) $filters['is_active']) ? 1 : 0;
    }

    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $sql = 'SELECT * FROM coupons' . $sqlWhere . ' ORDER BY id DESC';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => (int) $id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public function create(array $data): int
  {
    $code = strtoupper(trim((string) ($data['code'] ?? '')));
    $type = (string) ($data['type'] ?? 'percent');
    $value = (string) ($data['value'] ?? '0');

    $stmt = $this->pdo->prepare(
      'INSERT INTO coupons (code, type, value, starts_at, ends_at, min_subtotal, max_uses, uses_count, is_active, created_at)
       VALUES (:code, :type, :value, :starts_at, :ends_at, :min_subtotal, :max_uses, 0, :is_active, NOW())'
    );
    $stmt->execute(array(
      'code' => $code,
      'type' => $type,
      'value' => $value,
      'starts_at' => self::nullIfEmpty($data['starts_at'] ?? null),
      'ends_at' => self::nullIfEmpty($data['ends_at'] ?? null),
      'min_subtotal' => self::nullIfEmpty($data['min_subtotal'] ?? null),
      'max_uses' => ($data['max_uses'] === '' || $data['max_uses'] === null) ? null : (int) $data['max_uses'],
      'is_active' => ((int) ($data['is_active'] ?? 1)) ? 1 : 0,
    ));
    return (int) $this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void
  {
    $stmt = $this->pdo->prepare(
      'UPDATE coupons
       SET code=:code, type=:type, value=:value, starts_at=:starts_at, ends_at=:ends_at, min_subtotal=:min_subtotal,
           max_uses=:max_uses, is_active=:is_active
       WHERE id=:id'
    );
    $stmt->execute(array(
      'id' => (int) $id,
      'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
      'type' => (string) ($data['type'] ?? 'percent'),
      'value' => (string) ($data['value'] ?? '0'),
      'starts_at' => self::nullIfEmpty($data['starts_at'] ?? null),
      'ends_at' => self::nullIfEmpty($data['ends_at'] ?? null),
      'min_subtotal' => self::nullIfEmpty($data['min_subtotal'] ?? null),
      'max_uses' => ($data['max_uses'] === '' || $data['max_uses'] === null) ? null : (int) $data['max_uses'],
      'is_active' => ((int) ($data['is_active'] ?? 1)) ? 1 : 0,
    ));
  }

  public function delete(int $id): void
  {
    $stmt = $this->pdo->prepare('DELETE FROM coupons WHERE id = :id');
    $stmt->execute(array('id' => (int) $id));
  }

  /**
   * @param int[] $categoryIds
   */
  public function setCategories(int $couponId, array $categoryIds): void
  {
    $couponId = (int) $couponId;
    $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), fn ($v) => $v > 0));
    $categoryIds = array_values(array_unique($categoryIds));

    $this->pdo->prepare('DELETE FROM coupon_categories WHERE coupon_id = :id')->execute(array('id' => $couponId));
    if (!$categoryIds) return;

    $stmt = $this->pdo->prepare('INSERT IGNORE INTO coupon_categories (coupon_id, category_id) VALUES (:cid, :catid)');
    foreach ($categoryIds as $catId) {
      $stmt->execute(array('cid' => $couponId, 'catid' => (int) $catId));
    }
  }

  /**
   * @return int[]
   */
  public function getCategories(int $couponId): array
  {
    return $this->getCategoryIds($couponId);
  }

  private static function nullIfEmpty($value): ?string
  {
    $v = trim((string) ($value ?? ''));
    return $v === '' ? null : $v;
  }
}

