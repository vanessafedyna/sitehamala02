<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';

final class CategoryModel
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function exists(): bool
  {
    try {
      $this->pdo->query("SELECT 1 FROM categories LIMIT 1");
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public function list(array $filters = array()): array
  {
    $where = array();
    $params = array();

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
      $where[] = '(name LIKE :q OR slug LIKE :q2)';
      $like = '%' . $q . '%';
      $params['q'] = $like;
      $params['q2'] = $like;
    }

    if (array_key_exists('is_active', $filters)) {
      $where[] = 'is_active = :is_active';
      $params['is_active'] = ((int) $filters['is_active']) ? 1 : 0;
    }

    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $sql = 'SELECT * FROM categories' . $sqlWhere . ' ORDER BY sort_order ASC, name ASC, id DESC';

    $limit = isset($filters['limit']) ? (int) $filters['limit'] : 0;
    $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
    if ($limit > 0) {
      $limit = max(1, min(200, $limit));
      $offset = max(0, $offset);
      $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  public function count(array $filters = array()): int
  {
    $where = array();
    $params = array();

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
      $where[] = '(name LIKE :q OR slug LIKE :q2)';
      $like = '%' . $q . '%';
      $params['q'] = $like;
      $params['q2'] = $like;
    }

    if (array_key_exists('is_active', $filters)) {
      $where[] = 'is_active = :is_active';
      $params['is_active'] = ((int) $filters['is_active']) ? 1 : 0;
    }

    $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM categories' . $sqlWhere);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
  }

  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => (int) $id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public function findBySlug(string $slug, bool $onlyActive = false): ?array
  {
    $slug = trim($slug);
    if ($slug === '') return null;

    $sql = 'SELECT * FROM categories WHERE slug = :slug';
    if ($onlyActive) {
      $sql .= ' AND is_active = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array('slug' => $slug));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public function create(array $data): int
  {
    $name = trim((string) ($data['name'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    if ($slug === '') {
      $slug = slugify($name);
    }

    $stmt = $this->pdo->prepare(
      'INSERT INTO categories (name, slug, description, banner_image, sort_order, is_active, seo_title, seo_description, og_image, created_at, updated_at)
       VALUES (:name, :slug, :description, :banner_image, :sort_order, :is_active, :seo_title, :seo_description, :og_image, NOW(), NOW())'
    );
    $stmt->execute(array(
      'name' => $name,
      'slug' => $slug,
      'description' => self::nullIfEmpty($data['description'] ?? null),
      'banner_image' => self::nullIfEmpty($data['banner_image'] ?? null),
      'sort_order' => (int) ($data['sort_order'] ?? 0),
      'is_active' => ((int) ($data['is_active'] ?? 1)) ? 1 : 0,
      'seo_title' => self::nullIfEmpty($data['seo_title'] ?? null),
      'seo_description' => self::nullIfEmpty($data['seo_description'] ?? null),
      'og_image' => self::nullIfEmpty($data['og_image'] ?? null),
    ));
    return (int) $this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): void
  {
    $id = (int) $id;
    if ($id <= 0) {
      throw new RuntimeException('Catégorie invalide.');
    }

    $name = trim((string) ($data['name'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    if ($slug === '') {
      $slug = slugify($name);
    }

    $stmt = $this->pdo->prepare(
      'UPDATE categories
       SET name=:name, slug=:slug, description=:description, banner_image=:banner_image, sort_order=:sort_order, is_active=:is_active,
           seo_title=:seo_title, seo_description=:seo_description, og_image=:og_image, updated_at=NOW()
       WHERE id=:id'
    );
    $stmt->execute(array(
      'id' => $id,
      'name' => $name,
      'slug' => $slug,
      'description' => self::nullIfEmpty($data['description'] ?? null),
      'banner_image' => self::nullIfEmpty($data['banner_image'] ?? null),
      'sort_order' => (int) ($data['sort_order'] ?? 0),
      'is_active' => ((int) ($data['is_active'] ?? 1)) ? 1 : 0,
      'seo_title' => self::nullIfEmpty($data['seo_title'] ?? null),
      'seo_description' => self::nullIfEmpty($data['seo_description'] ?? null),
      'og_image' => self::nullIfEmpty($data['og_image'] ?? null),
    ));
  }

  public function delete(int $id): void
  {
    $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute(array('id' => (int) $id));
  }

  public function setActive(int $id, bool $isActive): void
  {
    $id = (int) $id;
    if ($id <= 0) {
      throw new RuntimeException('Catégorie invalide.');
    }

    $stmt = $this->pdo->prepare('UPDATE categories SET is_active = :a, updated_at = NOW() WHERE id = :id');
    $stmt->execute(array('a' => $isActive ? 1 : 0, 'id' => $id));
  }

  public function countLinkedProducts(int $categoryId): int
  {
    $categoryId = (int) $categoryId;
    if ($categoryId <= 0) return 0;

    try {
      $stmt = $this->pdo->prepare('SELECT COUNT(DISTINCT product_id) FROM product_categories WHERE category_id = :id');
      $stmt->execute(array('id' => $categoryId));
      return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
      return 0;
    }
  }

  /**
   * @return array<int,int> ids
   */
  public function getProductCategoryIds(int $productId): array
  {
    try {
      $stmt = $this->pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = :pid');
      $stmt->execute(array('pid' => (int) $productId));
      $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: array();
      return array_values(array_filter(array_map('intval', $rows), fn ($v) => $v > 0));
    } catch (Throwable $e) {
      return array();
    }
  }

  /**
   * @param int[] $categoryIds
   */
  public function setProductCategories(int $productId, array $categoryIds): void
  {
    $productId = (int) $productId;
    $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), fn ($v) => $v > 0));
    $categoryIds = array_values(array_unique($categoryIds));

    $this->pdo->prepare('DELETE FROM product_categories WHERE product_id = :pid')->execute(array('pid' => $productId));

    if (!$categoryIds) {
      return;
    }

    $stmt = $this->pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (:pid, :cid)');
    foreach ($categoryIds as $cid) {
      $stmt->execute(array('pid' => $productId, 'cid' => (int) $cid));
    }
  }

  private static function nullIfEmpty($value): ?string
  {
    $v = trim((string) ($value ?? ''));
    return $v === '' ? null : $v;
  }
}