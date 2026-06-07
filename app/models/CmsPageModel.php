<?php
declare(strict_types=1);

final class CmsPageModel
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function exists(): bool
  {
    try {
      $this->pdo->query("SELECT 1 FROM pages LIMIT 1");
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public function listAll(): array
  {
    $stmt = $this->pdo->query('SELECT * FROM pages ORDER BY key_name ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  public function findById(int $id): ?array
  {
    if ($id <= 0) return null;

    $stmt = $this->pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => $id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public function findByKey(string $key, bool $publishedOnly = false): ?array
  {
    $key = trim($key);
    if ($key === '') return null;

    $sql = 'SELECT * FROM pages WHERE key_name = :k';
    if ($publishedOnly) {
      $sql .= ' AND is_published = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array('k' => $key));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public function findBySlug(string $slug, bool $publishedOnly = false): ?array
  {
    $slug = trim($slug);
    if ($slug === '') return null;

    $sql = 'SELECT * FROM pages WHERE slug = :s';
    if ($publishedOnly) {
      $sql .= ' AND is_published = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array('s' => $slug));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
  }

  public function updateByKey(string $key, array $data): void
  {
    $stmt = $this->pdo->prepare(
      'UPDATE pages
       SET title=:title, slug=:slug, content=:content, is_published=:pub,
           seo_title=:seo_title, seo_description=:seo_description, og_image=:og_image, updated_at=NOW()
       WHERE key_name=:key_name'
    );
    $stmt->execute(array(
      'key_name' => $key,
      'title' => (string) ($data['title'] ?? ''),
      'slug' => (string) ($data['slug'] ?? ''),
      'content' => (string) ($data['content'] ?? ''),
      'pub' => ((int) ($data['is_published'] ?? 1)) ? 1 : 0,
      'seo_title' => self::nullIfEmpty($data['seo_title'] ?? null),
      'seo_description' => self::nullIfEmpty($data['seo_description'] ?? null),
      'og_image' => self::nullIfEmpty($data['og_image'] ?? null),
    ));
  }

  public function updateById(int $id, array $data): void
  {
    if ($id <= 0) {
      throw new InvalidArgumentException('Identifiant CMS invalide.');
    }

    $stmt = $this->pdo->prepare(
      'UPDATE pages
       SET key_name=:key_name, title=:title, slug=:slug, content=:content, is_published=:pub,
           seo_title=:seo_title, seo_description=:seo_description, og_image=:og_image, updated_at=NOW()
       WHERE id=:id'
    );
    $stmt->execute(array(
      'id' => $id,
      'key_name' => (string) ($data['key_name'] ?? ''),
      'title' => (string) ($data['title'] ?? ''),
      'slug' => (string) ($data['slug'] ?? ''),
      'content' => (string) ($data['content'] ?? ''),
      'pub' => ((int) ($data['is_published'] ?? 1)) ? 1 : 0,
      'seo_title' => self::nullIfEmpty($data['seo_title'] ?? null),
      'seo_description' => self::nullIfEmpty($data['seo_description'] ?? null),
      'og_image' => self::nullIfEmpty($data['og_image'] ?? null),
    ));
  }

  public function create(array $data): int
  {
    $stmt = $this->pdo->prepare(
      'INSERT INTO pages (key_name, title, slug, content, is_published, seo_title, seo_description, og_image, updated_at)
       VALUES (:key_name, :title, :slug, :content, :pub, :seo_title, :seo_description, :og_image, NOW())'
    );
    $stmt->execute(array(
      'key_name' => (string) ($data['key_name'] ?? ''),
      'title' => (string) ($data['title'] ?? ''),
      'slug' => (string) ($data['slug'] ?? ''),
      'content' => (string) ($data['content'] ?? ''),
      'pub' => ((int) ($data['is_published'] ?? 1)) ? 1 : 0,
      'seo_title' => self::nullIfEmpty($data['seo_title'] ?? null),
      'seo_description' => self::nullIfEmpty($data['seo_description'] ?? null),
      'og_image' => self::nullIfEmpty($data['og_image'] ?? null),
    ));

    return (int) $this->pdo->lastInsertId();
  }

  public function keyExists(string $key, int $excludeId = 0): bool
  {
    $key = trim($key);
    if ($key === '') return false;

    $sql = 'SELECT id FROM pages WHERE key_name = :key_name';
    $params = array('key_name' => $key);
    if ($excludeId > 0) {
      $sql .= ' AND id <> :exclude_id';
      $params['exclude_id'] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function slugExists(string $slug, int $excludeId = 0): bool
  {
    $slug = trim($slug);
    if ($slug === '') return false;

    $sql = 'SELECT id FROM pages WHERE slug = :slug';
    $params = array('slug' => $slug);
    if ($excludeId > 0) {
      $sql .= ' AND id <> :exclude_id';
      $params['exclude_id'] = $excludeId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function createIfMissing(string $key, array $data): void
  {
    $existing = $this->findByKey($key, false);
    if ($existing) return;

    $stmt = $this->pdo->prepare(
      'INSERT INTO pages (key_name, title, slug, content, is_published, seo_title, seo_description, og_image, updated_at)
       VALUES (:key_name, :title, :slug, :content, :pub, :seo_title, :seo_description, :og_image, NOW())'
    );
    $stmt->execute(array(
      'key_name' => $key,
      'title' => (string) ($data['title'] ?? ''),
      'slug' => (string) ($data['slug'] ?? ''),
      'content' => (string) ($data['content'] ?? ''),
      'pub' => ((int) ($data['is_published'] ?? 1)) ? 1 : 0,
      'seo_title' => self::nullIfEmpty($data['seo_title'] ?? null),
      'seo_description' => self::nullIfEmpty($data['seo_description'] ?? null),
      'og_image' => self::nullIfEmpty($data['og_image'] ?? null),
    ));
  }

  private static function nullIfEmpty($value): ?string
  {
    $v = trim((string) ($value ?? ''));
    return $v === '' ? null : $v;
  }
}

