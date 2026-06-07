<?php
declare(strict_types=1);

final class ProductModel
{
  private PDO $pdo;
  /** @var string[]|null */
  private ?array $columns = null;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  // =========================================================
  // API demandée (CRUD admin) + helpers admin
  // =========================================================

  /**
   * Liste produits (admin): actifs + inactifs, recherche optionnelle sur name/sku.
   *
   * @return array<int, array>
   */
  public function all($q = null): array
  {
    $filters = array();
    $q = trim((string) ($q ?? ''));
    if ($q !== '') {
      $filters['q'] = $q;
    }
    return $this->fetchAllWithFilters($filters, false);
  }

  public function find(int $id): ?array
  {
    return $this->findById($id);
  }

  /**
   * Crée un produit et retourne son ID.
   */
  public function create(array $data): int
  {
    $cols = $this->productColumns();
    if (!$cols) {
      $cols = array('id', 'name', 'sku', 'price', 'description', 'category', 'gender', 'stock', 'image_main', 'is_active', 'created_at', 'status');
    }

    $fields = array();
    $placeholders = array();
    $params = array();

    $name = trim((string) ($data['name'] ?? ''));
    $sku = trim((string) ($data['sku'] ?? ''));
    $price = self::normalize_price_value($data['price'] ?? 0);
    $description = array_key_exists('description', $data) ? trim((string) $data['description']) : null;
    $category = array_key_exists('category', $data) ? trim((string) $data['category']) : null;
    $gender = array_key_exists('gender', $data) ? strtolower(trim((string) $data['gender'])) : 'unisex';
    if (!in_array($gender, array('homme', 'femme', 'unisex'), true)) {
      $gender = 'unisex';
    }
    $stock = (int) ($data['stock'] ?? 0);
    $isActive = ((int) ($data['is_active'] ?? 1)) ? 1 : 0;
   
    $status = strtolower(trim((string) ($data['status'] ?? '')));
    if (!in_array($status, array('pending', 'published'), true)) {
      $status = 'pending';
    }

    if (in_array('name', $cols, true)) {
      $fields[] = 'name';
      $placeholders[] = ':name';
      $params['name'] = $name;
    }
    if (in_array('sku', $cols, true)) {
      $fields[] = 'sku';
      $placeholders[] = ':sku';
      $params['sku'] = $sku;
    }
    $priceCols = $this->priceColumns($cols);
    if ($priceCols) {
      $params['price'] = $price;
      foreach ($priceCols as $col) {
        $fields[] = $col;
        $placeholders[] = ':price';
      }
    }
    if (in_array('description', $cols, true)) {
      $fields[] = 'description';
      $placeholders[] = ':description';
      $params['description'] = ($description === '' ? null : $description);
    }
    if (in_array('category', $cols, true)) {
      $fields[] = 'category';
      $placeholders[] = ':category';
      $params['category'] = ($category === '' ? null : $category);
    }
    if (in_array('gender', $cols, true)) {
      $fields[] = 'gender';
      $placeholders[] = ':gender';
      $params['gender'] = $gender;
    }
    if (in_array('stock', $cols, true)) {
      $fields[] = 'stock';
      $placeholders[] = ':stock';
      $params['stock'] = $stock;
    }
    if (in_array('is_active', $cols, true)) {
      $fields[] = 'is_active';
      $placeholders[] = ':is_active';
      $params['is_active'] = $isActive;
    }
   
    if (in_array('status', $cols, true)) {
      $fields[] = 'status';
      $placeholders[] = ':status';
      $params['status'] = $status;
    }

    // Caracteristiques produit (optionnelles)
    foreach (array('material', 'style', 'occasion', 'cut', 'finishes', 'inspiration') as $attrCol) {
      if (!in_array($attrCol, $cols, true)) {
        continue;
      }
      $fields[] = $attrCol;
      $placeholders[] = ':' . $attrCol;
      $raw = array_key_exists($attrCol, $data) ? trim((string) $data[$attrCol]) : '';
      $params[$attrCol] = ($raw === '' ? null : $raw);
    }

    // Images (compat + multi): image1/image2/image3 + legacy image_path/image_main/image
    $imageCols = $this->imageColumns($cols);
    if ($imageCols) {
      $toSet = array();

      if (array_key_exists('image1', $data)) {
        $toSet['image1'] = $data['image1'];
        if (!array_key_exists('image_path', $data)) {
          $toSet['image_path'] = $data['image1'];
        }
      } elseif (array_key_exists('image_path', $data)) {
        $toSet['image_path'] = $data['image_path'];
        if (!array_key_exists('image1', $data)) {
          $toSet['image1'] = $data['image_path'];
        }
      } elseif (array_key_exists('image_main', $data)) {
        $toSet['image_main'] = $data['image_main'];
        if (!array_key_exists('image1', $data)) {
          $toSet['image1'] = $data['image_main'];
        }
        if (!array_key_exists('image_path', $data)) {
          $toSet['image_path'] = $data['image_main'];
        }
      } elseif (array_key_exists('image', $data)) {
        $toSet['image'] = $data['image'];
        if (!array_key_exists('image1', $data)) {
          $toSet['image1'] = $data['image'];
        }
        if (!array_key_exists('image_path', $data)) {
          $toSet['image_path'] = $data['image'];
        }
      }

      if (array_key_exists('image2', $data)) $toSet['image2'] = $data['image2'];
      if (array_key_exists('image3', $data)) $toSet['image3'] = $data['image3'];

      foreach ($toSet as $col => $val) {
        if (!in_array($col, $imageCols, true)) {
          continue;
        }
        $fields[] = $col;
        $ph = ':' . $col;
        $placeholders[] = $ph;
        $v = trim((string) $val);
        $params[$col] = ($v === '' ? null : $v);
      }
    }

    if (!$fields) {
      throw new RuntimeException('Schéma products invalide.');
    }

    $sql = 'INSERT INTO products (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $this->pdo->lastInsertId();
  }

  public function update(int $id, array $data): bool
  {
    $id = (int) $id;
    if ($id <= 0) {
      return false;
    }

    $cols = $this->productColumns();
    if (!$cols) {
      $cols = array('id', 'name', 'sku', 'price', 'description', 'category', 'gender', 'stock', 'image_main', 'is_active');
    }

    $sets = array();
    $params = array('id' => $id);

    if (array_key_exists('name', $data) && in_array('name', $cols, true)) {
      $sets[] = 'name = :name';
      $params['name'] = trim((string) $data['name']);
    }
    if (array_key_exists('sku', $data) && in_array('sku', $cols, true)) {
      $sets[] = 'sku = :sku';
      $params['sku'] = trim((string) $data['sku']);
    }
    if (array_key_exists('price', $data)) {
      $priceCols = $this->priceColumns($cols);
      if ($priceCols) {
        $sets[] = implode(', ', array_map(fn ($c) => $c . ' = :price', $priceCols));
        $params['price'] = self::normalize_price_value($data['price']);
      }
    }
    if (array_key_exists('description', $data) && in_array('description', $cols, true)) {
      $sets[] = 'description = :description';
      $desc = trim((string) $data['description']);
      $params['description'] = ($desc === '' ? null : $desc);
    }
    if (array_key_exists('category', $data) && in_array('category', $cols, true)) {
      $sets[] = 'category = :category';
      $cat = trim((string) $data['category']);
      $params['category'] = ($cat === '' ? null : $cat);
    }
    if (array_key_exists('gender', $data) && in_array('gender', $cols, true)) {
      $g = strtolower(trim((string) $data['gender']));
      if (!in_array($g, array('homme', 'femme', 'unisex'), true)) {
        $g = 'unisex';
      }
      $sets[] = 'gender = :gender';
      $params['gender'] = $g;
    }
    if (array_key_exists('stock', $data) && in_array('stock', $cols, true)) {
      $sets[] = 'stock = :stock';
      $params['stock'] = (int) $data['stock'];
    }
    if (array_key_exists('is_active', $data) && in_array('is_active', $cols, true)) {
      $sets[] = 'is_active = :is_active';
      $params['is_active'] = ((int) $data['is_active']) ? 1 : 0;
    }
   
    if (array_key_exists('status', $data) && in_array('status', $cols, true)) {
      $st = strtolower(trim((string) $data['status']));
      if (!in_array($st, array('pending', 'published'), true)) {
        throw new RuntimeException('Statut produit invalide.');
      }
      $sets[] = 'status = :status';
      $params['status'] = $st;
    }

    // Caracteristiques produit (optionnelles)
    foreach (array('material', 'style', 'occasion', 'cut', 'finishes', 'inspiration') as $attrCol) {
      if (!array_key_exists($attrCol, $data) || !in_array($attrCol, $cols, true)) {
        continue;
      }
      $sets[] = $attrCol . ' = :' . $attrCol;
      $raw = trim((string) $data[$attrCol]);
      $params[$attrCol] = ($raw === '' ? null : $raw);
    }

    // Images (compat + multi): image1/image2/image3 + legacy image_path/image_main/image
    $imageCols = $this->imageColumns($cols);
    if ($imageCols) {
      $toSet = array();

      if (array_key_exists('image1', $data)) {
        $toSet['image1'] = $data['image1'];
        if (!array_key_exists('image_path', $data)) {
          $toSet['image_path'] = $data['image1'];
        }
      } elseif (array_key_exists('image_path', $data)) {
        $toSet['image_path'] = $data['image_path'];
        if (!array_key_exists('image1', $data)) {
          $toSet['image1'] = $data['image_path'];
        }
      } elseif (array_key_exists('image_main', $data)) {
        $toSet['image_main'] = $data['image_main'];
        if (!array_key_exists('image1', $data)) {
          $toSet['image1'] = $data['image_main'];
        }
        if (!array_key_exists('image_path', $data)) {
          $toSet['image_path'] = $data['image_main'];
        }
      } elseif (array_key_exists('image', $data)) {
        $toSet['image'] = $data['image'];
        if (!array_key_exists('image1', $data)) {
          $toSet['image1'] = $data['image'];
        }
        if (!array_key_exists('image_path', $data)) {
          $toSet['image_path'] = $data['image'];
        }
      }

      if (array_key_exists('image2', $data)) $toSet['image2'] = $data['image2'];
      if (array_key_exists('image3', $data)) $toSet['image3'] = $data['image3'];

      foreach ($toSet as $col => $val) {
        if (!in_array($col, $imageCols, true)) {
          continue;
        }
        $sets[] = $col . ' = :' . $col;
        $v = trim((string) $val);
        $params[$col] = ($v === '' ? null : $v);
      }
    }

    if (!$sets) {
      return true;
    }

    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return true;
  }

  public function delete(int $id): bool
  {
    $id = (int) $id;
    if ($id <= 0) {
      return false;
    }

    $startedTx = false;
    if (!$this->pdo->inTransaction()) {
      $this->pdo->beginTransaction();
      $startedTx = true;
    }

    try {
      // Nettoyer les dépendances qui ne sont pas en cascade dans certains schémas.
      try {
        $stmtSm = $this->pdo->prepare('DELETE FROM stock_movements WHERE product_id = :id');
        $stmtSm->execute(array('id' => $id));
      } catch (Throwable $e) {
        // Table absente ou schéma ancien: ignorer et laisser le DELETE produit décider.
      }

      try {
        $stmtPc = $this->pdo->prepare('DELETE FROM product_categories WHERE product_id = :id');
        $stmtPc->execute(array('id' => $id));
      } catch (Throwable $e) {
        // Pivot absent ou géré en cascade.
      }

      $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id LIMIT 1');
      $stmt->execute(array('id' => $id));
      $deleted = ($stmt->rowCount() > 0);

      if ($startedTx) {
        $this->pdo->commit();
      }

      return $deleted;
    } catch (Throwable $e) {
      if ($startedTx && $this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * Liste paginée (admin): actifs + inactifs.
   *
   * @return array<int, array>
   */
  public function adminList(array $filters = array()): array
  {
    return $this->fetchAllWithFilters($filters, false);
  }

  public function adminCount(array $filters = array()): int
  {
    return $this->countWithFilters($filters, false);
  }

  public function isSkuTaken(string $sku, int $excludeId = 0): bool
  {
    $sku = trim($sku);
    if ($sku === '') {
      return false;
    }

    $sql = 'SELECT id FROM products WHERE sku = :sku';
    $params = array('sku' => $sku);

    $excludeId = (int) $excludeId;
    if ($excludeId > 0) {
      $sql .= ' AND id <> :id';
      $params['id'] = $excludeId;
    }

    $sql .= ' LIMIT 1';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
  }

  // =========================================================
  // API existante (front) - ne pas casser
  // =========================================================

  /**
   * Lecture produits (V1).
   * Filtres supportés:
   * - category (string)
   * - q (string) recherche sur name/sku
   * - is_active (int 0/1) (optionnel, défaut=1)
   * - limit (int) / offset (int)
   *
   * @return array<int, array>
   */
  public function getAll(array $filters = []): array
  {
    return $this->fetchAllWithFilters($filters, true);
  }

  public function getById(int $id): ?array
  {
   
    $id = (int) $id;
    if ($id <= 0) return null;

    try {
      $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id AND status = :st LIMIT 1');
      $stmt->execute(array('id' => $id, 'st' => 'published'));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      return $row ? $this->normalizeRow($row) : null;
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'status') !== false) {
        return $this->findById($id);
      }
      throw $e;
    }
  }

  public function getBySku(string $sku): ?array
  {
   
    $stmt = null;
    try {
      $stmt = $this->pdo->prepare('SELECT * FROM products WHERE sku = :sku AND status = :st LIMIT 1');
      $stmt->execute(array('sku' => $sku, 'st' => 'published'));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      return $row ? $this->normalizeRow($row) : null;
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'status') !== false) {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE sku = :sku LIMIT 1');
        $stmt->execute(array('sku' => $sku));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeRow($row) : null;
      }
      throw $e;
    }
  }

  /**
   * Recherche simple sur `name` ou `sku`.
   *
   * @return array<int, array>
   */
  public function search(string $q, int $limit = 50): array
  {
    $q = trim($q);
    if ($q === '') {
      return array();
    }
    $limit = max(1, min(200, (int) $limit));
    return $this->fetchAllWithFilters(array('q' => $q, 'limit' => $limit), true);
  }

  public function countAll(array $filters = []): int
  {
    return $this->countWithFilters($filters, true);
  }

  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => $id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $this->normalizeRow($row) : null;
  }

  /**
   * @param int[] $ids
   * @return array<int, array>
   */
  public function findByIds(array $ids): array
  {
    $ids = array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
    if (!$ids) {
      return array();
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
   
    try {
      $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND status = 'published'");
      $stmt->execute($ids);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
      return array_map(fn ($r) => $this->normalizeRow($r), $rows);
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'status') !== false) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        return array_map(fn ($r) => $this->normalizeRow($r), $rows);
      }
      throw $e;
    }
  }

  // =========================================================
  // Internals
  // =========================================================

  private function buildWhere(array $filters): array
  {
    $where = array();
    $params = array();
    $cols = $this->productColumns();

   
    if (array_key_exists('status', $filters)) {
      $st = strtolower(trim((string) $filters['status']));
      if ($st !== '') {
        $where[] = 'status = :status';
        $params['status'] = $st;
      }
    }

    if (array_key_exists('is_active', $filters)) {
      $where[] = 'is_active = :is_active';
      $params['is_active'] = (int) $filters['is_active'];
    }

    if (!empty($filters['category'])) {
      $cat = strtolower(trim((string) $filters['category']));
      $cat = preg_replace('/\\s+/', ' ', $cat);

      // Tolérer les variations fréquentes (ex: robe/robes) pour éviter qu'un produit
      // n'apparaisse pas dans le menu de catégories.
      $aliases = array(
        'robes' => array('robes', 'robe'),
        'chemises' => array('chemises', 'chemise'),
        'pantalons' => array('pantalons', 'pantalon'),
        'vestes' => array('vestes', 'veste'),
        'chandails' => array('chandails', 'chandail'),
        't-shirts' => array('t-shirts', 't-shirt', 'tshirt', 'tshirts'),
        'autres' => array('autres', 'autre'),
      );

      $cats = $aliases[$cat] ?? array($cat);
      if ($cat !== '' && str_ends_with($cat, 's')) {
        $cats[] = rtrim($cat, 's');
      }

      $cats = array_values(array_unique(array_filter(array_map('trim', $cats), fn ($v) => $v !== '')));
      if ($cats) {
        $parts = array();
        foreach ($cats as $i => $c) {
          $k = 'category_' . (string) $i;
          $parts[] = 'TRIM(category) = :' . $k;
          $params[$k] = $c;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
      }
    }

    if (!empty($filters['gender'])) {
      $gender = strtolower(trim((string) $filters['gender']));
      if (in_array($gender, array('homme', 'femme', 'unisex'), true)) {
        $where[] = 'TRIM(LOWER(gender)) = :gender';
        $params['gender'] = $gender;
      }
    }

    if (!empty($filters['q'])) {
      // Important: MySQL PDO (emulate_prepares=false) n'autorise pas la réutilisation
      // d'un même paramètre nommé plusieurs fois (HY093). On duplique donc les placeholders.
      $where[] = '(name LIKE :q_name OR sku LIKE :q_sku)';
      $like = '%' . (string) $filters['q'] . '%';
      $params['q_name'] = $like;
      $params['q_sku'] = $like;
    }

    $stockFilter = strtolower(trim((string) ($filters['stock_filter'] ?? '')));
    if ($stockFilter !== '' && in_array('stock', $cols, true)) {
      if ($stockFilter === 'out') {
        $where[] = 'stock <= 0';
      } elseif ($stockFilter === 'low') {
        $where[] = '(stock > 0 AND stock <= 10)';
      } elseif ($stockFilter === 'in') {
        $where[] = 'stock > 10';
      }
    }

    $sql = '';
    if ($where) {
      $sql = ' WHERE ' . implode(' AND ', $where);
    }

    return array($sql, $params);
  }

  private function fetchAllWithFilters(array $filters, bool $defaultActive): array
  {
    if ($defaultActive && !array_key_exists('is_active', $filters)) {
      $filters = array_merge(array('is_active' => 1), $filters);
    }
   
    if ($defaultActive && !array_key_exists('status', $filters)) {
      $filters = array_merge(array('status' => 'published'), $filters);
    }

    for ($attempt = 0; $attempt < 3; $attempt += 1) {
      $categorySlug = trim((string) ($filters['category_slug'] ?? ''));
      $filtersLocal = $filters;
      unset($filtersLocal['category_slug']);

      [$whereSql, $params] = $this->buildWhere($filtersLocal);

     
      $joinSql = '';
      if ($categorySlug !== '') {
        $joinSql = ' INNER JOIN product_categories pc ON pc.product_id = products.id INNER JOIN categories c ON c.id = pc.category_id';
        $cond = ' (c.slug = :cat_slug AND c.is_active = 1) ';
        $params['cat_slug'] = $categorySlug;
        $whereSql = ($whereSql !== '') ? ($whereSql . ' AND ' . $cond) : (' WHERE ' . $cond);
      }

      $orderBy = $this->orderByForSort((string) ($filters['sort'] ?? ''), $this->productColumns());
      $sql = 'SELECT DISTINCT products.* FROM products' . $joinSql . $whereSql . ' ORDER BY ' . $orderBy;

      if (isset($filters['limit'])) {
        $limit = max(1, (int) $filters['limit']);
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
      }

      try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
        return array_map(fn ($r) => $this->normalizeRow($r), $rows);
      } catch (PDOException $e) {
        $msg = $e->getMessage();
       
        if (strpos($msg, 'categories') !== false || strpos($msg, 'product_categories') !== false) {
          unset($filters['category_slug']);
          continue;
        }
        if (strpos($msg, 'Unknown column') !== false) {
         
          if (strpos($msg, 'status') !== false) {
            unset($filters['status']);
            continue;
          }
          if (strpos($msg, 'is_active') !== false) {
            unset($filters['is_active']);
            continue;
          }
          if (strpos($msg, 'category') !== false) {
            unset($filters['category']);
            continue;
          }
          if (strpos($msg, 'stock') !== false || strpos($msg, 'low_stock_threshold') !== false) {
            unset($filters['stock_filter']);
            continue;
          }
          if (strpos($msg, 'gender') !== false) {
            unset($filters['gender']);
            continue;
          }
          if (strpos($msg, 'name') !== false || strpos($msg, 'sku') !== false) {
            unset($filters['q']);
            continue;
          }
        }
        throw $e;
      }
    }

    return array();
  }

  /**
   * Tri catalogue (front):
   * - featured: colonnes de mise en avant si disponibles (fallback recents)
   * - newest: created_at DESC (fallback id DESC)
   * - price_asc / price_desc: colonne prix disponible
   */
  private function orderByForSort(string $sort, array $cols): string
  {
    $sort = strtolower(trim($sort));
    if ($sort === 'popular') {
      // Compat legacy: ancienne option front.
      $sort = 'featured';
    }
    $priceCols = $this->priceColumns($cols);
    $priceCol = $priceCols ? (string) $priceCols[0] : '';

    if ($sort === 'featured') {
      $parts = array();
      $hasFeaturedSignal = false;

      if (in_array('is_featured', $cols, true)) {
        $parts[] = 'products.is_featured DESC';
        $hasFeaturedSignal = true;
      }
      if (in_array('featured', $cols, true)) {
        $parts[] = 'products.featured DESC';
        $hasFeaturedSignal = true;
      }
      if (in_array('featured_rank', $cols, true)) {
        // Les rangs non definis passent apres les rangs explicites.
        $parts[] = '(CASE WHEN products.featured_rank IS NULL OR products.featured_rank = 0 THEN 1 ELSE 0 END) ASC';
        $parts[] = 'products.featured_rank ASC';
        $hasFeaturedSignal = true;
      }
      if (in_array('created_at', $cols, true)) {
        $parts[] = 'products.created_at DESC';
      }
      $parts[] = 'products.id DESC';

      if ($hasFeaturedSignal) {
        return implode(', ', $parts);
      }
      return in_array('created_at', $cols, true) ? 'products.created_at DESC, products.id DESC' : 'products.id DESC';
    }

    if ($sort === 'newest') {
      if (in_array('created_at', $cols, true)) {
        return 'products.created_at DESC, products.id DESC';
      }
      return 'products.id DESC';
    }

    if ($sort === 'price_asc') {
      if ($priceCol !== '') {
        return 'products.' . $priceCol . ' ASC, products.id DESC';
      }
      return 'products.id DESC';
    }

    if ($sort === 'price_desc') {
      if ($priceCol !== '') {
        return 'products.' . $priceCol . ' DESC, products.id DESC';
      }
      return 'products.id DESC';
    }

    return 'products.id DESC';
  }

  private function countWithFilters(array $filters, bool $defaultActive): int
  {
    if ($defaultActive && !array_key_exists('is_active', $filters)) {
      $filters = array_merge(array('is_active' => 1), $filters);
    }
   
    if ($defaultActive && !array_key_exists('status', $filters)) {
      $filters = array_merge(array('status' => 'published'), $filters);
    }
    unset($filters['limit'], $filters['offset']);

    for ($attempt = 0; $attempt < 3; $attempt += 1) {
      $categorySlug = trim((string) ($filters['category_slug'] ?? ''));
      $filtersLocal = $filters;
      unset($filtersLocal['category_slug']);

      [$whereSql, $params] = $this->buildWhere($filtersLocal);

     
      $joinSql = '';
      $countExpr = 'COUNT(*) AS cnt';
      if ($categorySlug !== '') {
        $joinSql = ' INNER JOIN product_categories pc ON pc.product_id = products.id INNER JOIN categories c ON c.id = pc.category_id';
        $cond = ' (c.slug = :cat_slug AND c.is_active = 1) ';
        $params['cat_slug'] = $categorySlug;
        $whereSql = ($whereSql !== '') ? ($whereSql . ' AND ' . $cond) : (' WHERE ' . $cond);
        $countExpr = 'COUNT(DISTINCT products.id) AS cnt';
      }

      $sql = 'SELECT ' . $countExpr . ' FROM products' . $joinSql . $whereSql;

      try {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
      } catch (PDOException $e) {
        $msg = $e->getMessage();
       
        if (strpos($msg, 'categories') !== false || strpos($msg, 'product_categories') !== false) {
          unset($filters['category_slug']);
          continue;
        }
        if (strpos($msg, 'Unknown column') !== false) {
         
          if (strpos($msg, 'status') !== false) {
            unset($filters['status']);
            continue;
          }
          if (strpos($msg, 'is_active') !== false) {
            unset($filters['is_active']);
            continue;
          }
          if (strpos($msg, 'category') !== false) {
            unset($filters['category']);
            continue;
          }
          if (strpos($msg, 'stock') !== false || strpos($msg, 'low_stock_threshold') !== false) {
            unset($filters['stock_filter']);
            continue;
          }
          if (strpos($msg, 'gender') !== false) {
            unset($filters['gender']);
            continue;
          }
          if (strpos($msg, 'name') !== false || strpos($msg, 'sku') !== false) {
            unset($filters['q']);
            continue;
          }
        }
        throw $e;
      }
    }

    return 0;
  }

  /**
   * @return string[]
   */
  private function productColumns(): array
  {
    if (is_array($this->columns)) {
      return $this->columns;
    }

    if (function_exists('db_table_columns')) {
      $this->columns = db_table_columns($this->pdo, 'products');
      return $this->columns;
    }

    try {
      $rows = $this->pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC) ?: array();
      $fields = array();
      foreach ($rows as $row) {
        if (!empty($row['Field'])) {
          $fields[] = (string) $row['Field'];
        }
      }
      $this->columns = $fields;
      return $fields;
    } catch (Throwable $e) {
      $this->columns = array();
      return array();
    }
  }

  /**
   * @param string[] $cols
   * @return string[]
   */
  private function imageColumns(array $cols): array
  {
    $out = array();
    // Multi + compat: image1/image2/image3, puis legacy image_path/image_main/image.
    foreach (array('image1', 'image2', 'image3', 'image_path', 'image_main', 'image') as $col) {
      if (in_array($col, $cols, true)) {
        $out[] = $col;
      }
    }
    return $out;
  }

  /**
   * @param string[] $cols
   * @return string[]
   */
  private function priceColumns(array $cols): array
  {
    $out = array();
    // Préférence: price (spec), puis variantes rencontrées.
    foreach (array('price', 'price_fcfa', 'prix', 'unit_price', 'amount', 'montant') as $col) {
      if (in_array($col, $cols, true)) {
        $out[] = $col;
      }
    }
    return $out;
  }

  /**
   * Normalise une ligne produit pour le front/admin:
   * - garantit une clé `price` (int FCFA) même si la DB utilise un autre nom.
   * - garantit une cl? `image_path` (chemin relatif) + compat `image_main`/`image`.
   *
   * @param array<string,mixed> $row
   * @return array<string,mixed>
   */
  private function normalizeRow(array $row): array
  {
    // ---- Price (compat) ----
    if (array_key_exists('price', $row)) {
      $row['price'] = self::normalize_price_value($row['price']);
    } elseif (array_key_exists('price_fcfa', $row)) {
      $row['price'] = self::normalize_price_value($row['price_fcfa']);
    } elseif (array_key_exists('prix', $row)) {
      $row['price'] = self::normalize_price_value($row['prix']);
    } elseif (array_key_exists('unit_price', $row)) {
      $row['price'] = self::normalize_price_value($row['unit_price']);
    } elseif (array_key_exists('amount', $row)) {
      $row['price'] = self::normalize_price_value($row['amount']);
    } elseif (array_key_exists('montant', $row)) {
      $row['price'] = self::normalize_price_value($row['montant']);
    } else {
      $row['price'] = 0;
    }

    // ---- Image (compat) ----
    $img1 = $this->normalizeImageValue((string) ($row['image1'] ?? ''));
    $img2 = $this->normalizeImageValue((string) ($row['image2'] ?? ''));
    $img3 = $this->normalizeImageValue((string) ($row['image3'] ?? ''));

    $legacy = '';
    if (!empty($row['image_path'])) $legacy = (string) $row['image_path'];
    elseif (!empty($row['image_main'])) $legacy = (string) $row['image_main'];
    elseif (!empty($row['image'])) $legacy = (string) $row['image'];
    $legacy = $this->normalizeImageValue($legacy);

    if ($img1 === '' && $legacy !== '') {
      $img1 = $legacy;
    }

    $row['image1'] = $img1;
    $row['image2'] = $img2;
    $row['image3'] = $img3;

    // Compat anciennes pages
    if (!array_key_exists('image_path', $row) || trim((string) ($row['image_path'] ?? '')) === '') {
      $row['image_path'] = $img1;
    }
    if (!array_key_exists('image_main', $row) || trim((string) ($row['image_main'] ?? '')) === '') {
      $row['image_main'] = $img1;
    }
    if (!array_key_exists('image', $row) || trim((string) ($row['image'] ?? '')) === '') {
      $row['image'] = $img1;
    }

    return $row;
  }

  private function normalizeImageValue(string $img): string
  {
    $img = trim($img);
    if ($img === '') return '';

    $img = str_replace('\\', '/', $img);

    // Si on a un chemin absolu Windows ou un chemin serveur, essayer d'en extraire le relatif
    $pos = stripos($img, 'uploads/products/');
    if ($pos !== false) {
      $img = substr($img, $pos);
    } elseif (preg_match('/^[a-zA-Z]:\\//', $img)) {
      $img = basename($img);
    }

    // Si la DB stocke uniquement un nom de fichier, le remettre dans /uploads/products/
    if (!str_starts_with($img, 'http://') && !str_starts_with($img, 'https://') && $img[0] !== '/' && strpos($img, '/') === false) {
      $img = 'uploads/products/' . ltrim($img, '/');
    }

    return $img;
  }

  /**
   * Normalise un prix en FCFA (int).
   *
   * @param mixed $value
   */
  private static function normalize_price_value($value): int
  {
    if (is_int($value)) {
      return $value;
    }
    if (is_float($value)) {
      return (int) round($value);
    }
    if (is_numeric($value)) {
      return (int) round((float) $value);
    }

    $s = trim((string) $value);
    if ($s === '') {
      return 0;
    }

    // Retirer espaces/monnaie/etc; garder chiffres, signe et séparateur décimal.
    $s = preg_replace('/[^0-9,\\.-]/', '', $s);
    $s = (string) $s;
    if ($s === '' || $s === '-' || $s === '.' || $s === ',') {
      return 0;
    }

    // Si virgule décimale, la convertir.
    if (strpos($s, ',') !== false && strpos($s, '.') === false) {
      $s = str_replace(',', '.', $s);
    } else {
      // sinon retirer les virgules (séparateurs de milliers)
      $s = str_replace(',', '', $s);
    }

    return (int) round((float) $s);
  }
}
