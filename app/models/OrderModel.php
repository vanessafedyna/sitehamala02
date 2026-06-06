<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/Logger.php';
require_once dirname(__DIR__) . '/helpers/cart.php';
require_once dirname(__DIR__) . '/services/StockMovementService.php';
require_once dirname(__DIR__) . '/services/ProductVariantService.php';

final class OrderModel
{
  private PDO $pdo;

  /** @var string[]|null */
  private ?array $orderCols = null;
  /** @var string[]|null */
  private ?array $itemCols = null;
  /** @var string[]|null */
  private ?array $productCols = null;

  private ?bool $historyExists = null;
  /** @var string[]|null */
  private ?array $historyCols = null;
  private ?bool $orderUserIdNullable = null;
  private ?string $initialStatusCache = null;
  /** @var string[]|null */
  private ?array $statusEnumValuesCache = null;
  private ?bool $statusIsEnumCache = null;
  /** @var array<string,array<string,mixed>> */
  private array $columnMetaCache = array();
  /** @var array<string,bool> */
  private array $tableExistsCache = array();

  /** @var string[] */
  public const STATUSES = array('nouveau', 'confirme', 'en_preparation', 'en_livraison', 'livre', 'annulee');
  /** @var string[] */
  private const FLOW = array('nouveau', 'confirme', 'en_preparation', 'en_livraison', 'livre');

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Liste commandes (admin).
   * Filtres supportés:
   * - status (string)
   * - q (string) recherche sur order_number + téléphone
   * - limit/offset
   *
   * @return array<int, array<string,mixed>>
   */
  public function all(array $filters = array()): array
  {
    return $this->fetchAllWithFilters($filters);
  }

  public function countAll(array $filters = array()): int
  {
    return $this->countWithFilters($filters);
  }

  /**
   * Retourne la commande + ses items.
   *
   * @return array{order: array<string,mixed>, items: array<int, array<string,mixed>>}|null
   */
  public function find(int $id): ?array
  {
    return $this->findWithItems($id);
  }

  /**
   * Alias explicite pour l'admin.
   *
   * @return array{order: array<string,mixed>, items: array<int, array<string,mixed>>}|null
   */
  public function findWithItems(int $id): ?array
  {
    $order = $this->findById($id);
    if (!$order) {
      return null;
    }

    $items = $this->items((int) $order['id']);
    return array('order' => $order, 'items' => $items);
  }

  /**
   * Retourne une commande (normalisée) par numéro.
   *
   * @return array<string,mixed>|null
   */
  public function getByOrderNumber(string $orderNumber): ?array
  {
    $orderNumber = trim($orderNumber);
    if ($orderNumber === '') {
      return null;
    }

    [$selectSql, $joinSql] = $this->ordersSelectSql();
    $stmt = $this->pdo->prepare($selectSql . $joinSql . ' WHERE o.order_number = :n LIMIT 1');
    $stmt->execute(array('n' => $orderNumber));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row ? $this->normalizeOrderRow($row) : null;
  }

  /**
   * Retourne la derniere commande connue d'un utilisateur connecte.
   *
   * @return array<string,mixed>|null
   */
  public function findLatestForUser(int $userId): ?array
  {
    $userId = (int) $userId;
    if ($userId <= 0) {
      return null;
    }

    $cols = $this->orderColumns();
    $userCol = in_array('user_id', $cols, true) ? 'user_id'
      : (in_array('customer_id', $cols, true) ? 'customer_id' : '');
    if ($userCol === '') {
      return null;
    }

    [$selectSql, $joinSql] = $this->ordersSelectSql();
    $stmt = $this->pdo->prepare(
      $selectSql . $joinSql . ' WHERE o.' . $userCol . ' = :uid ORDER BY o.created_at DESC, o.id DESC LIMIT 1'
    );
    $stmt->execute(array('uid' => $userId));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row ? $this->normalizeOrderRow($row) : null;
  }

  /**
   * Retourne la derniere commande connue par telephone.
   *
   * @return array<string,mixed>|null
   */
  public function findLatestByPhone(string $phone): ?array
  {
    $phone = trim($phone);
    if ($phone === '') {
      return null;
    }

    $phoneCol = $this->hasOrderColumn('customer_phone') ? 'customer_phone'
      : ($this->hasOrderColumn('phone') ? 'phone' : '');
    if ($phoneCol === '') {
      return null;
    }

    [$selectSql, $joinSql] = $this->ordersSelectSql();
    $stmt = $this->pdo->prepare(
      $selectSql . $joinSql . ' WHERE o.' . $phoneCol . ' = :phone ORDER BY o.created_at DESC, o.id DESC LIMIT 1'
    );
    $stmt->execute(array('phone' => $phone));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row ? $this->normalizeOrderRow($row) : null;
  }


  /**
   * Retourne une commande normalisée par ID.
   *
   * @return array<string,mixed>|null
   */
  public function findById(int $id): ?array
  {
    $id = (int) $id;
    if ($id <= 0) {
      return null;
    }

    [$selectSql, $joinSql] = $this->ordersSelectSql();
    $stmt = $this->pdo->prepare($selectSql . $joinSql . ' WHERE o.id = :id LIMIT 1');
    $stmt->execute(array('id' => $id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row ? $this->normalizeOrderRow($row) : null;
  }

  /**
   * Items normalisés (compat schémas différents).
   * Clés garanties: product_name_snapshot, sku_snapshot, qty, unit_price_snapshot, line_total
   *
   * @return array<int, array<string,mixed>>
   */
  public function items(int $orderId): array
  {
    $orderId = (int) $orderId;
    if ($orderId <= 0) {
      return array();
    }

    $cols = $this->orderItemColumns();

    $nameCol = in_array('product_name_snapshot', $cols, true) ? 'product_name_snapshot'
      : (in_array('product_name', $cols, true) ? 'product_name' : "''");
    $skuCol = in_array('sku_snapshot', $cols, true) ? 'sku_snapshot' : "''";
    $variantCol = in_array('variant_id', $cols, true) ? 'variant_id' : 'NULL';
    $sizeCol = in_array('size_snapshot', $cols, true) ? 'size_snapshot' : "''";
    $colorCol = in_array('color_snapshot', $cols, true) ? 'color_snapshot' : "''";
    $qtyCol = in_array('qty', $cols, true) ? 'qty'
      : (in_array('quantity', $cols, true) ? 'quantity' : '0');
    $unitCol = in_array('unit_price_snapshot', $cols, true) ? 'unit_price_snapshot'
      : (in_array('price_fcfa', $cols, true) ? 'price_fcfa' : '0');
    $lineCol = in_array('line_total', $cols, true) ? 'line_total'
      : (in_array('subtotal_fcfa', $cols, true) ? 'subtotal_fcfa' : '0');

    $sql = 'SELECT id, order_id, product_id, '
      . $variantCol . ' AS variant_id, '
      . $nameCol . ' AS product_name_snapshot, '
      . $skuCol . ' AS sku_snapshot, '
      . $sizeCol . ' AS size_snapshot, '
      . $colorCol . ' AS color_snapshot, '
      . $qtyCol . ' AS qty, '
      . $unitCol . ' AS unit_price_snapshot, '
      . $lineCol . ' AS line_total'
      . ' FROM order_items WHERE order_id = :id ORDER BY id ASC';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array('id' => $orderId));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    foreach ($rows as &$r) {
      $r['id'] = (int) ($r['id'] ?? 0);
      $r['order_id'] = (int) ($r['order_id'] ?? 0);
      $r['product_id'] = (int) ($r['product_id'] ?? 0);
      $variantId = isset($r['variant_id']) ? (int) $r['variant_id'] : 0;
      $r['variant_id'] = $variantId > 0 ? $variantId : null;
      $r['size_snapshot'] = trim((string) ($r['size_snapshot'] ?? ''));
      $color = trim((string) ($r['color_snapshot'] ?? ''));
      $r['color_snapshot'] = $color !== '' ? $color : null;
      $r['qty'] = (int) ($r['qty'] ?? 0);
      $r['unit_price_snapshot'] = (int) ($r['unit_price_snapshot'] ?? 0);
      $r['line_total'] = (int) ($r['line_total'] ?? 0);
    }
    unset($r);

    return $rows;
  }

  /**
   * Met à jour le statut avec validation des transitions + historique.
   * - Annulation autorisée tant que non livrée.
   * - Une fois livrée/annulée => plus de changement.
   * - En cas d'annulation, restock des items (best-effort) dans la transaction.
   */
  public function updateStatus(int $id, string $newStatus, ?int $changedByUserId = null, ?string $note = null): bool
  {
    $id = (int) $id;
    $newStatus = self::normalizeStatus($newStatus);

    if ($id <= 0) {
      throw new InvalidArgumentException('Commande invalide.');
    }
    if (!in_array($newStatus, self::STATUSES, true)) {
      throw new InvalidArgumentException('Statut invalide.');
    }
    if ($newStatus === 'annulee' && !$this->isStatusEnumValueAllowed('annulee')) {
      throw new InvalidArgumentException('Statut invalide.');
    }

    $this->pdo->beginTransaction();
    try {
      $stmt = $this->pdo->prepare('SELECT id, status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE');
      $stmt->execute(array('id' => $id));
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      if (!$row) {
        throw new RuntimeException('Commande introuvable.');
      }

      $current = self::normalizeStatus((string) ($row['status'] ?? ''));
      if ($current === '') {
        $current = 'nouveau';
      }

      if ($newStatus === $current) {
        $this->pdo->commit();
        return true;
      }

      if (!$this->isTransitionAllowed($current, $newStatus)) {
        throw new RuntimeException('Transition de statut non autorisée.');
      }

      $sets = array('status = :status');
      $params = array('status' => $this->statusToDb($newStatus), 'id' => $id);

      if ($this->hasOrderColumn('status_updated_at')) {
        $sets[] = 'status_updated_at = NOW()';
      }

     
      if ($newStatus === 'confirme' && $this->hasOrderColumn('paid_at')) {
        $sets[] = 'paid_at = COALESCE(paid_at, NOW())';
      }
      if ($newStatus === 'livre' && $this->hasOrderColumn('delivered_at')) {
        $sets[] = 'delivered_at = COALESCE(delivered_at, NOW())';
      }

      $upd = $this->pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1');
      $upd->execute($params);

      // Historique (si table disponible)
      if ($this->historyTableExists()) {
        $hcols = $this->historyColumns();
        $fields = array('order_id', 'old_status', 'new_status');
        $placeholders = array(':order_id', ':old_status', ':new_status');
        $hparams = array(
          'order_id' => $id,
          'old_status' => $current,
          'new_status' => $newStatus,
        );

        if (in_array('note', $hcols, true)) {
          $fields[] = 'note';
          $placeholders[] = ':note';
          $n = trim((string) $note);
          $hparams['note'] = ($n === '' ? null : $n);
        }

        if (in_array('changed_by', $hcols, true)) {
          $fields[] = 'changed_by';
          $placeholders[] = ':changed_by';
          $hparams['changed_by'] = $changedByUserId ? (int) $changedByUserId : null;
        }

        $ins = 'INSERT INTO order_status_history (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmtHist = $this->pdo->prepare($ins);
        $stmtHist->execute($hparams);
      }

      // Restock si annulation (avant livrée)
      if ($newStatus === 'annulee' && $current !== 'livre' && $current !== 'annulee') {
        $items = $this->itemsForRestock($id);
        $variantService = new ProductVariantService($this->pdo);
        $productsToSync = array();
        foreach ($items as $it) {
          $pid = (int) ($it['product_id'] ?? 0);
          $variantId = (int) ($it['variant_id'] ?? 0);
          $qty = (int) ($it['qty'] ?? 0);
          if ($pid <= 0 || $qty <= 0) continue;
          if ($variantId > 0) {
            if (!$variantService->incrementStock($variantId, $qty)) {
              throw new RuntimeException('Impossible de restaurer le stock de la variante.');
            }
            $productsToSync[$pid] = true;
            try {
              StockMovementService::record(
                $this->pdo,
                $pid,
                $qty,
                'restock',
                $id,
                $changedByUserId ? (int) $changedByUserId : null,
                'Restock suite à annulation commande'
              );
            } catch (Throwable $e) {
              // best-effort
            }
            continue;
          }
          $rst = $this->pdo->prepare('UPDATE products SET stock = stock + :qty WHERE id = :id');
          $rst->execute(array('qty' => $qty, 'id' => $pid));
          try {
            StockMovementService::record(
              $this->pdo,
              $pid,
              $qty,
              'restock',
              $id,
              $changedByUserId ? (int) $changedByUserId : null,
              'Restock suite à annulation commande'
            );
          } catch (Throwable $e) {
            // best-effort
          }
        }
        foreach (array_keys($productsToSync) as $productId) {
          $this->syncProductStockFromVariants((int) $productId, $variantService);
        }
      }

      $this->pdo->commit();
      return true;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      if ($e instanceof PDOException) {
        error_log('[OrderModel::updateStatus] ' . $e->getMessage());
        throw new RuntimeException('Impossible de mettre a jour le statut.');
      }
      if (false && $e instanceof PDOException) {
        error_log('[OrderModel::updateStatus] ' . $e->getMessage());
        throw new RuntimeException('Impossible de créer la commande.');
      }
      throw $e;
    }
  }

  /**
   * Historique des statuts (timeline). Retourne [] si la table n'existe pas.
   *
   * @return array<int, array<string,mixed>>
   */
  public function getHistory(int $orderId): array
  {
    $orderId = (int) $orderId;
    if ($orderId <= 0) {
      return array();
    }
    if (!$this->historyTableExists()) {
      return array();
    }

    $cols = $this->historyColumns();
    $select = 'h.id, h.order_id, h.old_status, h.new_status';
    if (in_array('note', $cols, true)) {
      $select .= ', h.note';
    }
    $select .= ', h.changed_at';
    $join = '';
    if (in_array('changed_by', $cols, true)) {
      $select .= ', h.changed_by, u.name AS changed_by_name';
      $join = ' LEFT JOIN users u ON u.id = h.changed_by';
    }

    $stmt = $this->pdo->prepare(
      'SELECT ' . $select . ' FROM order_status_history h' . $join . ' WHERE h.order_id = :id ORDER BY h.changed_at ASC, h.id ASC'
    );
    $stmt->execute(array('id' => $orderId));
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  /**
   * Crée une commande depuis un panier (transaction) et décrémente le stock.
   *
   * @param array<string,mixed> $customerData
   * @param array<mixed,mixed> $cartItems map item_key => qty
   * @return array{id:int,order_number:string,total_amount:int}
   */
  public function createFromCart(array $customerData, array $cartItems): array
  {
    $customerName = trim((string) ($customerData['customer_name'] ?? ''));
    $customerPhone = trim((string) ($customerData['customer_phone'] ?? ''));
    $customerEmail = trim((string) ($customerData['customer_email'] ?? ''));
    $city = trim((string) ($customerData['city'] ?? ''));
    $district = trim((string) ($customerData['district'] ?? ''));
    $landmark = array_key_exists('landmark', $customerData) ? trim((string) $customerData['landmark']) : '';
   
    $couponCode = strtoupper(trim((string) ($customerData['coupon_code'] ?? '')));
    $paymentMethod = trim((string) ($customerData['payment_method'] ?? 'cod'));
    if ($paymentMethod === '') {
      $paymentMethod = 'cod';
    }
    if ($paymentMethod !== 'cod') {
      throw new InvalidArgumentException('Mode de paiement invalide.');
    }
    $paymentStatus = 'pending';
    $paymentProvider = null;
    $paymentReference = null;

    $customerId = null;
    if (array_key_exists('customer_id', $customerData) && $customerData['customer_id'] !== null) {
      $cid = (int) $customerData['customer_id'];
      $customerId = $cid > 0 ? $cid : null;
    }

   
    $customerProfileId = null;
    if (array_key_exists('customer_profile_id', $customerData) && $customerData['customer_profile_id'] !== null) {
      $cpid = (int) $customerData['customer_profile_id'];
      $customerProfileId = $cpid > 0 ? $cpid : null;
    }

    if ($customerName === '' || $customerPhone === '' || $city === '') {
      throw new InvalidArgumentException('Informations client incomplètes.');
    }
    /* Valide l'email uniquement lorsqu'il est renseigné. */
    if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
      throw new InvalidArgumentException('Email invalide.');
    }

    $cartLines = cart_normalize_lines($cartItems);
    if (!$cartLines) {
      throw new InvalidArgumentException('Panier vide.');
    }

    $orderNumber = '';

    $this->pdo->beginTransaction();
    try {
      $variantService = new ProductVariantService($this->pdo);
      $variantIds = array();
      foreach ($cartLines as $line) {
        $variantId = (int) ($line['variant_id'] ?? 0);
        if ($variantId > 0) {
          $variantIds[$variantId] = $variantId;
        }
      }

      if ($variantIds && !$variantService->isSupported()) {
        throw new RuntimeException('Variantes indisponibles.');
      }

      $variantsById = array();
      if ($variantIds) {
        $variantPlaceholders = implode(',', array_fill(0, count($variantIds), '?'));
        $variantSql = 'SELECT id, product_id, size, color, stock, is_active
          FROM product_variants
          WHERE id IN (' . $variantPlaceholders . ')';
        try {
          $stmtVariants = $this->pdo->prepare($variantSql . ' FOR UPDATE');
          $stmtVariants->execute(array_values($variantIds));
        } catch (Throwable $e) {
          $stmtVariants = $this->pdo->prepare($variantSql);
          $stmtVariants->execute(array_values($variantIds));
        }

        foreach (($stmtVariants->fetchAll(PDO::FETCH_ASSOC) ?: array()) as $row) {
          $variantId = (int) ($row['id'] ?? 0);
          if ($variantId <= 0) {
            continue;
          }
          $color = trim((string) ($row['color'] ?? ''));
          $variantsById[$variantId] = array(
            'id' => $variantId,
            'product_id' => (int) ($row['product_id'] ?? 0),
            'size' => trim((string) ($row['size'] ?? '')),
            'color' => $color !== '' ? $color : null,
            'stock' => (int) ($row['stock'] ?? 0),
            'is_active' => (int) ($row['is_active'] ?? 0),
          );
        }
      }

      $productIds = array();
      foreach ($cartLines as $line) {
        $variantId = (int) ($line['variant_id'] ?? 0);
        if ($variantId > 0) {
          $variantRow = $variantsById[$variantId] ?? null;
          if (!$variantRow) {
            throw new RuntimeException('Variante introuvable.');
          }
          $variantProductId = (int) ($variantRow['product_id'] ?? 0);
          if ($variantProductId <= 0) {
            throw new RuntimeException('Variante introuvable.');
          }
          $trustedVariant = $variantService->findForProduct($variantProductId, $variantId, true);
          if (!$trustedVariant) {
            throw new RuntimeException('Variante indisponible.');
          }
          $variantsById[$variantId] = $trustedVariant;
          $productIds[$variantProductId] = $variantProductId;
          continue;
        }

        $productId = (int) ($line['product_id'] ?? 0);
        if ($productId > 0) {
          $productIds[$productId] = $productId;
        }
      }

      if (!$productIds) {
        throw new InvalidArgumentException('Panier vide.');
      }

      // Verrouiller produits du panier
      $ids = array_values($productIds);
      $placeholders = implode(',', array_fill(0, count($ids), '?'));

      $pcols = $this->productColumns();
      $priceExpr = $this->productPriceExpr($pcols);
      $select = array('id', 'name', 'sku', $priceExpr, 'stock');
      if (in_array('is_active', $pcols, true)) {
        $select[] = 'is_active';
      }
      if (in_array('status', $pcols, true)) {
        $select[] = 'status';
      }

      $sqlBase = 'SELECT ' . implode(', ', $select) . ' FROM products WHERE id IN (' . $placeholders . ')';
      try {
        $stmt = $this->pdo->prepare($sqlBase . ' FOR UPDATE');
        $stmt->execute($ids);
      } catch (Throwable $e) {
        // Fallback (engine/driver): stock guard restera protégé via UPDATE ... stock >= qty
        $stmt = $this->pdo->prepare($sqlBase);
        $stmt->execute($ids);
      }
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

      $byId = array();
      foreach ($rows as $r) {
        $byId[(int) ($r['id'] ?? 0)] = $r;
      }

      foreach ($ids as $pid) {
        if (!isset($byId[(int) $pid])) {
          throw new RuntimeException('Un produit du panier est introuvable.');
        }
      }

      $items = array();
      $total = 0;

      $productsToSync = array();
      foreach ($cartLines as $line) {
        $variantId = (int) ($line['variant_id'] ?? 0);
        $productId = (int) ($line['product_id'] ?? 0);
        $qty = (int) ($line['qty'] ?? 0);
        if ($qty < 1) {
          $qty = 1;
        }

        $variant = null;
        if ($variantId > 0) {
          $variant = $variantsById[$variantId] ?? null;
          if (!$variant) {
            throw new RuntimeException('Variante introuvable.');
          }
          $productId = (int) ($variant['product_id'] ?? 0);
        }

        if ($productId <= 0 || !isset($byId[$productId])) {
          throw new RuntimeException('Un produit du panier est introuvable.');
        }

        $p = $byId[$productId];
        $name = (string) ($p['name'] ?? 'Produit');
        $sku = (string) ($p['sku'] ?? '');
        $price = (int) ($p['price'] ?? 0);
        $stock = (int) ($p['stock'] ?? 0);
        $isActive = array_key_exists('is_active', $p) ? (int) ($p['is_active'] ?? 1) : 1;
        $status = array_key_exists('status', $p) ? strtolower(trim((string) ($p['status'] ?? ''))) : '';

        if ($isActive !== 1) {
          throw new RuntimeException('Produit indisponible');
        }
        if ($status !== '' && !in_array($status, array('published', 'active', 'enabled'), true)) {
          throw new RuntimeException('Produit indisponible');
        }
        if ($variantId > 0) {
          if ((int) ($variant['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Variante indisponible.');
          }
          if (!$variantService->isPurchasableVariant($variant)) {
            throw new RuntimeException('Variante invalide.');
          }
          if ($qty > (int) ($variant['stock'] ?? 0)) {
            throw new RuntimeException('Stock insuffisant');
          }
          $productsToSync[$productId] = true;
        } else {
          if ($variantService->hasAnyVariants($productId)) {
            throw new RuntimeException('Choix de taille obligatoire.');
          }
          if ($qty > $stock) {
            throw new RuntimeException('Stock insuffisant');
          }
        }

        $lineTotal = $price * $qty;
        $total += $lineTotal;

        $items[] = array(
          'product_id' => $productId,
          'variant_id' => $variantId > 0 ? $variantId : null,
          'size_snapshot' => $variant ? (string) ($variant['size'] ?? '') : '',
          'color_snapshot' => $variant ? ($variant['color'] ?? null) : null,
          'sku_snapshot' => $sku,
          'product_name_snapshot' => $name,
          'unit_price_snapshot' => $price,
          'qty' => $qty,
          'line_total' => $lineTotal,
        );
      }

      if ($total < 0) {
        throw new RuntimeException('Total invalide.');
      }

      /* coupon/discount (DB-driven, best-effort si tables/colonnes manquantes) */
      $subtotalAmount = $total;
      $discountAmount = 0;
      $couponId = null;
      $couponToIncrement = null;
      $couponModel = null;

      if ($couponCode !== '') {
        try {
          if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $couponCode)) {
            throw new RuntimeException('Code promo invalide.');
          }

          require_once __DIR__ . '/CouponModel.php';
          $couponModel = new CouponModel($this->pdo);
          if (!$couponModel->exists()) {
            throw new RuntimeException('Codes promo indisponibles.');
          }

          $coupon = $couponModel->findByCodeForUpdate($couponCode);
          if (!$coupon) {
            throw new RuntimeException('Code promo invalide.');
          }

        $isActive = (int) ($coupon['is_active'] ?? 0);
        if ($isActive !== 1) {
          throw new RuntimeException('Ce code promo est inactif.');
        }

        $nowTs = time();
        $startsAt = trim((string) ($coupon['starts_at'] ?? ''));
        $endsAt = trim((string) ($coupon['ends_at'] ?? ''));
        if ($startsAt !== '') {
          $st = strtotime($startsAt);
          if ($st !== false && $nowTs < $st) {
            throw new RuntimeException('Ce code promo n\'est pas encore disponible.');
          }
        }
        if ($endsAt !== '') {
          $et = strtotime($endsAt);
          if ($et !== false && $nowTs > $et) {
            throw new RuntimeException('Ce code promo est expir?.');
          }
        }

        $maxUses = $coupon['max_uses'] ?? null;
        $usesCount = (int) ($coupon['uses_count'] ?? 0);
        if ($maxUses !== null && $maxUses !== '' && (int) $maxUses > 0 && $usesCount >= (int) $maxUses) {
          throw new RuntimeException('Ce code promo a atteint sa limite d\'utilisation.');
        }

        $minSubtotal = $coupon['min_subtotal'] ?? null;
        if ($minSubtotal !== null && $minSubtotal !== '') {
          $min = (int) round((float) $minSubtotal);
          if ($subtotalAmount < $min) {
            throw new RuntimeException('Montant minimum non atteint pour ce code promo.');
          }
        }

        $eligibleSubtotal = $subtotalAmount;
        $cid = (int) ($coupon['id'] ?? 0);
        $restrictedCats = $cid > 0 ? $couponModel->getCategoryIds($cid) : array();
        if ($restrictedCats) {
          $eligibleSubtotal = 0;
          try {
            $catPh = implode(',', array_fill(0, count($restrictedCats), '?'));
            $pidPh = implode(',', array_fill(0, count($ids), '?'));
            $stmtElig = $this->pdo->prepare(
              "SELECT DISTINCT product_id FROM product_categories WHERE category_id IN ($catPh) AND product_id IN ($pidPh)"
            );
            $stmtElig->execute(array_merge($restrictedCats, $ids));
            $eligibleIds = $stmtElig->fetchAll(PDO::FETCH_COLUMN, 0) ?: array();
            $set = array();
            foreach ($eligibleIds as $eid) {
              $set[(int) $eid] = true;
            }
            foreach ($items as $it) {
              $pid = (int) ($it['product_id'] ?? 0);
              if ($pid > 0 && !empty($set[$pid])) {
                $eligibleSubtotal += (int) ($it['line_total'] ?? 0);
              }
            }
          } catch (Throwable $e) {
            $eligibleSubtotal = 0;
          }

          if ($eligibleSubtotal <= 0) {
            throw new RuntimeException('Code promo non applicable ? votre panier.');
          }
        }

        $type = (string) ($coupon['type'] ?? '');
        $value = (float) ($coupon['value'] ?? 0);
        $discount = 0;

        if ($type === 'percent') {
          if ($value <= 0 || $value > 100) {
            throw new RuntimeException('Code promo invalide.');
          }
          $discount = (int) floor($eligibleSubtotal * ($value / 100.0));
        } elseif ($type === 'fixed') {
          if ($value <= 0) {
            throw new RuntimeException('Code promo invalide.');
          }
          $discount = (int) round($value);
        } else {
          throw new RuntimeException('Code promo invalide.');
        }

        $discount = max(0, min($discount, $eligibleSubtotal));
        if ($discount <= 0) {
          throw new RuntimeException('Code promo non applicable ? votre panier.');
        }

          $discountAmount = $discount;
          $couponId = $cid > 0 ? $cid : null;
          $couponToIncrement = $couponId;
          $total = max(0, $subtotalAmount - $discountAmount);
        } catch (Throwable $couponError) {
          // Compat checkout: coupon invalide/indisponible ne bloque pas la commande.
          $discountAmount = 0;
          $couponId = null;
          $couponToIncrement = null;
          $couponModel = null;
          $total = $subtotalAmount;
        }
      }

      /* Calcule les frais de livraison et la taxe depuis la configuration disponible. */
      $shippingFee = 0;
      $taxAmount = 0;
      $taxBase = max(0, $subtotalAmount - $discountAmount);

      try {
        // shipping_zones: chercher une fee par city (+ option zone exact = district)
        $hasZones = $this->tableExists('shipping_zones');
        if ($hasZones) {
          $stmt = $this->pdo->prepare(
            'SELECT zone, fee FROM shipping_zones WHERE is_active = 1 AND city = :city ORDER BY (zone IS NULL) ASC, sort_order ASC, id ASC'
          );
          $stmt->execute(array('city' => $city));
          $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

          $districtNorm = strtolower(trim($district));
          $defaultFee = null;
          foreach ($rows as $r) {
            $z = isset($r['zone']) ? trim((string) $r['zone']) : '';
            $fee = (float) ($r['fee'] ?? 0);
            if ($z === '') {
              if ($defaultFee === null) $defaultFee = $fee;
              continue;
            }
            if (strtolower(trim($z)) === $districtNorm) {
              $defaultFee = $fee;
              break;
            }
          }

          if ($defaultFee !== null) {
            $shippingFee = (int) max(0, round($defaultFee));
          }
        }

        // Free shipping threshold
        $hasSettings = $this->tableExists('settings');
        if ($hasSettings) {
          $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE key_name = :k LIMIT 1');
          $stmt->execute(array('k' => 'free_shipping_threshold'));
          $thr = (string) ($stmt->fetchColumn() ?: '');
          $thrVal = (float) (trim($thr) !== '' ? $thr : 0);
          if ($thrVal > 0 && $subtotalAmount >= (int) round($thrVal)) {
            $shippingFee = 0;
          }

          // Taxes
          $stmt->execute(array('k' => 'tax_rate_percent'));
          $rateRaw = (string) ($stmt->fetchColumn() ?: '');
          $rate = (float) (trim($rateRaw) !== '' ? $rateRaw : 0);
          if ($rate > 0) {
            $taxAmount = (int) max(0, floor($taxBase * ($rate / 100.0)));
          }
        }
      } catch (Throwable $e) {
        // best-effort: ignore if DB/table missing
        $shippingFee = 0;
        $taxAmount = 0;
      }

      $total = max(0, $taxBase + $shippingFee + $taxAmount);

      // Insert order (retry si collision order_number)
      $orderId = 0;

      $ocols = $this->orderColumns();
      $hasUserId = in_array('user_id', $ocols, true);
      $hasCustomerId = in_array('customer_id', $ocols, true);
     
      $hasCustomerProfileId = in_array('customer_profile_id', $ocols, true);
     
      $hasCouponId = in_array('coupon_id', $ocols, true);
      $hasSubtotalAmount = in_array('subtotal_amount', $ocols, true);
      $hasDiscountAmount = in_array('discount_amount', $ocols, true);
      /* Détecte les colonnes optionnelles utilisées pour détailler la commande. */
      $hasCustomerEmail = in_array('customer_email', $ocols, true);
      $hasShippingFee = in_array('shipping_fee_amount', $ocols, true);
      $hasTaxAmount = in_array('tax_amount', $ocols, true);

      for ($attempt = 0; $attempt < 10; $attempt += 1) {
        $orderNumber = self::generateOrderNumber();

        $fields = array();
        $placeholdersIns = array();
        $params = array();

        $fields[] = 'order_number';
        $placeholdersIns[] = ':order_number';
        $params['order_number'] = $orderNumber;

        if ($hasCustomerId) {
          $fields[] = 'customer_id';
          $placeholdersIns[] = ':customer_id';
          $params['customer_id'] = $customerId;
        } elseif ($hasUserId) {
          if ($customerId === null && !$this->isOrderUserIdNullable()) {
            throw new RuntimeException('Votre base exige un compte (orders.user_id NOT NULL). Exécutez le patch SQL checkout (database/patch_checkout_orders.sql) ou connectez-vous.');
          }
          $fields[] = 'user_id';
          $placeholdersIns[] = ':user_id';
          $params['user_id'] = $customerId ? (int) $customerId : null;
        }

       
        if ($hasCustomerProfileId) {
          $fields[] = 'customer_profile_id';
          $placeholdersIns[] = ':customer_profile_id';
          $params['customer_profile_id'] = $customerProfileId;
        }

       
        if ($hasCouponId) {
          $fields[] = 'coupon_id';
          $placeholdersIns[] = ':coupon_id';
          $params['coupon_id'] = $couponId;
        }
        if ($hasSubtotalAmount) {
          $fields[] = 'subtotal_amount';
          $placeholdersIns[] = ':subtotal_amount';
          $params['subtotal_amount'] = (int) $subtotalAmount;
        }
        if ($hasDiscountAmount) {
          $fields[] = 'discount_amount';
          $placeholdersIns[] = ':discount_amount';
          $params['discount_amount'] = (int) $discountAmount;
        }
        /* Persiste les montants calculés seulement si les colonnes existent. */
        if ($hasShippingFee) {
          $fields[] = 'shipping_fee_amount';
          $placeholdersIns[] = ':shipping_fee_amount';
          $params['shipping_fee_amount'] = (int) $shippingFee;
        }
        if ($hasTaxAmount) {
          $fields[] = 'tax_amount';
          $placeholdersIns[] = ':tax_amount';
          $params['tax_amount'] = (int) $taxAmount;
        }

        if (in_array('customer_name', $ocols, true)) {
          $fields[] = 'customer_name';
          $placeholdersIns[] = ':customer_name';
          $params['customer_name'] = $customerName;
        }

        if (in_array('customer_phone', $ocols, true)) {
          $fields[] = 'customer_phone';
          $placeholdersIns[] = ':customer_phone';
          $params['customer_phone'] = $customerPhone;
        } elseif (in_array('phone', $ocols, true)) {
          $fields[] = 'phone';
          $placeholdersIns[] = ':phone';
          $params['phone'] = $customerPhone;
        }

        /* Persiste l'email client lorsque le schéma le permet. */
        if ($hasCustomerEmail) {
          $fields[] = 'customer_email';
          $placeholdersIns[] = ':customer_email';
          $params['customer_email'] = ($customerEmail === '' ? null : $customerEmail);
        }

        if (in_array('city', $ocols, true)) {
          $fields[] = 'city';
          $placeholdersIns[] = ':city';
          $params['city'] = $city;
        }
        if (in_array('district', $ocols, true)) {
          $fields[] = 'district';
          $placeholdersIns[] = ':district';
          $params['district'] = $district;
        }
        if (in_array('landmark', $ocols, true)) {
          $fields[] = 'landmark';
          $placeholdersIns[] = ':landmark';
          $params['landmark'] = ($landmark === '' ? null : $landmark);
        }

        if (in_array('status', $ocols, true)) {
          $fields[] = 'status';
          $placeholdersIns[] = ':status';
          $params['status'] = $this->initialStatusValue();
        }

        if (in_array('payment_method', $ocols, true)) {
          $fields[] = 'payment_method';
          $placeholdersIns[] = ':payment_method';
          $params['payment_method'] = $paymentMethod;
        }
        if (in_array('payment_status', $ocols, true)) {
          $fields[] = 'payment_status';
          $placeholdersIns[] = ':payment_status';
          $params['payment_status'] = $paymentStatus;
        }
        if (in_array('payment_provider', $ocols, true)) {
          $fields[] = 'payment_provider';
          $placeholdersIns[] = ':payment_provider';
          $params['payment_provider'] = $paymentProvider;
        }
        if (in_array('payment_reference', $ocols, true)) {
          $fields[] = 'payment_reference';
          $placeholdersIns[] = ':payment_reference';
          $params['payment_reference'] = $paymentReference;
        }
        if (in_array('paid_at', $ocols, true)) {
          $fields[] = 'paid_at';
          $placeholdersIns[] = ':paid_at';
          $params['paid_at'] = null;
        }

        if (in_array('total_amount', $ocols, true)) {
          $fields[] = 'total_amount';
          $placeholdersIns[] = ':total_amount';
          $params['total_amount'] = $total;
        } elseif (in_array('total_fcfa', $ocols, true)) {
          $fields[] = 'total_fcfa';
          $placeholdersIns[] = ':total_fcfa';
          $params['total_fcfa'] = $total;
        }

        if ($this->hasOrderColumn('status_updated_at')) {
          $fields[] = 'status_updated_at';
          $placeholdersIns[] = 'NOW()';
        }

        $ins = 'INSERT INTO orders (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholdersIns) . ')';

        try {
          $stmtIns = $this->pdo->prepare($ins);
          $stmtIns->execute($params);
          $orderId = (int) $this->pdo->lastInsertId();
          break;
        } catch (PDOException $e) {
          // Duplicate order_number => retry
          $info = $e->errorInfo;
          $sqlState = is_array($info) && isset($info[0]) ? (string) $info[0] : '';
          $driverCode = is_array($info) && isset($info[1]) ? (string) $info[1] : '';
          if ($sqlState === '23000' || $driverCode === '1062') {
            continue;
          }
          throw $e;
        }
      }

      if ($orderId <= 0 || $orderNumber === '') {
        throw new RuntimeException('Impossible de créer la commande.');
      }

      /* incrémenter uses_count seulement si la commande est créée */
      if ($couponToIncrement && $couponModel) {
        $couponModel->incrementUses((int) $couponToIncrement);
      }

      // Historique initial (si table disponible)
      if ($this->historyTableExists()) {
        $hcols = $this->historyColumns();
        $fields = array('order_id', 'old_status', 'new_status');
        $placeholdersHist = array(':order_id', ':old_status', ':new_status');
        $paramsHist = array(
          'order_id' => $orderId,
          'old_status' => null,
          'new_status' => $this->initialStatusValue(),
        );

        if (in_array('note', $hcols, true)) {
          $fields[] = 'note';
          $placeholdersHist[] = ':note';
          $paramsHist['note'] = 'Commande créée';
        }

        if (in_array('changed_by', $hcols, true)) {
          $fields[] = 'changed_by';
          $placeholdersHist[] = ':changed_by';
          $paramsHist['changed_by'] = $customerId ? (int) $customerId : null;
        }

        $insHist = 'INSERT INTO order_status_history (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholdersHist) . ')';
        $stmtHist = $this->pdo->prepare($insHist);
        $stmtHist->execute($paramsHist);
      }

      // Insert items (compat)
      $icols = $this->orderItemColumns();
      $modeSnapshot = in_array('product_name_snapshot', $icols, true) && in_array('qty', $icols, true) && in_array('line_total', $icols, true);

      if ($modeSnapshot) {
        $itemFields = array('order_id', 'product_id');
        $itemPlaceholders = array(':order_id', ':product_id');
        if (in_array('variant_id', $icols, true)) {
          $itemFields[] = 'variant_id';
          $itemPlaceholders[] = ':variant_id';
        }
        $itemFields[] = 'sku_snapshot';
        $itemPlaceholders[] = ':sku_snapshot';
        if (in_array('size_snapshot', $icols, true)) {
          $itemFields[] = 'size_snapshot';
          $itemPlaceholders[] = ':size_snapshot';
        }
        if (in_array('color_snapshot', $icols, true)) {
          $itemFields[] = 'color_snapshot';
          $itemPlaceholders[] = ':color_snapshot';
        }
        $itemFields = array_merge($itemFields, array('product_name_snapshot', 'unit_price_snapshot', 'qty', 'line_total'));
        $itemPlaceholders = array_merge($itemPlaceholders, array(':product_name_snapshot', ':unit_price_snapshot', ':qty', ':line_total'));
        $stmtItem = $this->pdo->prepare(
          'INSERT INTO order_items (' . implode(', ', $itemFields) . ')
           VALUES (' . implode(', ', $itemPlaceholders) . ')'
        );
        foreach ($items as $it) {
          $itemParams = array(
            'order_id' => $orderId,
            'product_id' => (int) $it['product_id'],
            'sku_snapshot' => (string) $it['sku_snapshot'],
            'product_name_snapshot' => (string) $it['product_name_snapshot'],
            'unit_price_snapshot' => (int) $it['unit_price_snapshot'],
            'qty' => (int) $it['qty'],
            'line_total' => (int) $it['line_total'],
          );
          if (in_array('variant_id', $icols, true)) {
            $itemParams['variant_id'] = $it['variant_id'];
          }
          if (in_array('size_snapshot', $icols, true)) {
            $itemParams['size_snapshot'] = (string) ($it['size_snapshot'] ?? '');
          }
          if (in_array('color_snapshot', $icols, true)) {
            $itemParams['color_snapshot'] = $it['color_snapshot'];
          }
          $stmtItem->execute($itemParams);
        }
      } else {
        $stmtItem = $this->pdo->prepare(
          'INSERT INTO order_items (order_id, product_id, product_name, price_fcfa, quantity, subtotal_fcfa)
           VALUES (:order_id, :product_id, :product_name, :price_fcfa, :quantity, :subtotal_fcfa)'
        );
        foreach ($items as $it) {
          $stmtItem->execute(array(
            'order_id' => $orderId,
            'product_id' => (int) $it['product_id'],
            'product_name' => (string) $it['product_name_snapshot'],
            'price_fcfa' => (int) $it['unit_price_snapshot'],
            'quantity' => (int) $it['qty'],
            'subtotal_fcfa' => (int) $it['line_total'],
          ));
        }
      }

      // Decrement stock (sécurisé)
      // MySQL PDO (emulate_prepares=false) : ne pas réutiliser un même placeholder nommé (HY093).
      $stmtUpd = $this->pdo->prepare('UPDATE products SET stock = stock - :qty_dec WHERE id = :id AND stock >= :qty_chk');
      foreach ($items as $it) {
        $pid = (int) $it['product_id'];
        $variantId = (int) ($it['variant_id'] ?? 0);
        $qty = (int) $it['qty'];
        if ($variantId > 0) {
          if (!$variantService->decrementStock($variantId, $qty)) {
            throw new RuntimeException('Stock insuffisant');
          }
          continue;
        }
        $stmtUpd->execute(array('qty_dec' => $qty, 'qty_chk' => $qty, 'id' => $pid));
        if ($stmtUpd->rowCount() !== 1) {
          throw new RuntimeException('Stock insuffisant');
        }
      }
      foreach (array_keys($productsToSync) as $productId) {
        $this->syncProductStockFromVariants((int) $productId, $variantService);
      }

     
      // Stock movements (best-effort, si table existe)
      try {
        $hasTable = $this->tableExists('stock_movements');
        if ($hasTable) {
          $smFields = $this->tableColumns('stock_movements');

          $hasChangeQty = in_array('change_qty', $smFields, true);
          $hasReason = in_array('reason', $smFields, true);
          $hasRelatedOrder = in_array('related_order_id', $smFields, true);
          $hasAdminId = in_array('admin_id', $smFields, true);
          $hasIp = in_array('ip', $smFields, true);
          $hasType = in_array('type', $smFields, true);
          $hasQty = in_array('qty', $smFields, true);
          $hasNote = in_array('note', $smFields, true);
          $hasUserId = in_array('user_id', $smFields, true);

          $fieldsSm = array('product_id');
          $phSm = array(':product_id');

          if ($hasType) { $fieldsSm[] = 'type'; $phSm[] = ':type'; }
          if ($hasQty) { $fieldsSm[] = 'qty'; $phSm[] = ':qty'; }
          if ($hasChangeQty) { $fieldsSm[] = 'change_qty'; $phSm[] = ':change_qty'; }
          if ($hasReason) { $fieldsSm[] = 'reason'; $phSm[] = ':reason'; }
          if ($hasRelatedOrder) { $fieldsSm[] = 'related_order_id'; $phSm[] = ':related_order_id'; }
          if ($hasAdminId) { $fieldsSm[] = 'admin_id'; $phSm[] = ':admin_id'; }
          elseif ($hasUserId) { $fieldsSm[] = 'user_id'; $phSm[] = ':user_id'; }
          if ($hasIp) { $fieldsSm[] = 'ip'; $phSm[] = ':ip'; }
          if ($hasNote) { $fieldsSm[] = 'note'; $phSm[] = ':note'; }

          $sqlSm = 'INSERT INTO stock_movements (' . implode(', ', $fieldsSm) . ') VALUES (' . implode(', ', $phSm) . ')';
          $stmtSm = $this->pdo->prepare($sqlSm);

          $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
          foreach ($items as $it) {
            $pid = (int) $it['product_id'];
            $qty = (int) $it['qty'];

            $pSm = array('product_id' => $pid);
            if ($hasType) $pSm['type'] = 'remove';
            if ($hasQty) $pSm['qty'] = $qty;
            if ($hasChangeQty) $pSm['change_qty'] = 0 - $qty;
            if ($hasReason) $pSm['reason'] = 'order';
            if ($hasRelatedOrder) $pSm['related_order_id'] = $orderId;
            if ($hasAdminId) $pSm['admin_id'] = null;
            if ($hasUserId && !$hasAdminId) $pSm['user_id'] = $customerId;
            if ($hasIp) $pSm['ip'] = ($ip !== '' ? $ip : null);
            if ($hasNote) $pSm['note'] = 'Décrément stock (commande)';

            $stmtSm->execute($pSm);
          }
        }
      } catch (Throwable $e) {
        // best-effort
      }

      $this->pdo->commit();
      return array(
        'id' => $orderId,
        'order_number' => $orderNumber,
        'total_amount' => $total,
        /* Retourne les montants détaillés avec la commande créée. */
        'subtotal_amount' => $subtotalAmount,
        'discount_amount' => $discountAmount,
        'shipping_fee_amount' => $shippingFee,
        'tax_amount' => $taxAmount,
      );
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      if (class_exists('Logger')) {
        Logger::error('order_create_failed', array(
          'error' => $e->getMessage(),
          'order_number' => $orderNumber,
          'customer_phone' => $customerPhone,
        ));
      }
      throw $e;
    }
  }

  // =========================================================
  // Internals
  // =========================================================

  private function isOrderUserIdNullable(): bool
  {
    if (is_bool($this->orderUserIdNullable)) {
      return $this->orderUserIdNullable;
    }

    try {
      $row = $this->columnMeta('orders', 'user_id');
      $nullable = $row && strtoupper((string) ($row['Null'] ?? '')) === 'YES';
      $this->orderUserIdNullable = $nullable;
      return $nullable;
    } catch (Throwable $e) {
      $this->orderUserIdNullable = false;
      return false;
    }
  }

  private static function generateOrderNumber(): string
  {
    $year = date('Y');
    $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    return 'ML-' . $year . '-' . $rand;
  }

  private static function normalizeStatus(string $status): string
  {
    $s = strtolower(trim($status));
    $map = array(
      'nouvelle' => 'nouveau',
      'confirmee' => 'confirme',
      'preparee' => 'en_preparation',
      'livree' => 'livre',
      // keep if already new or legacy-cancelled
      'nouveau' => 'nouveau',
      'confirme' => 'confirme',
      'en_preparation' => 'en_preparation',
      'en_livraison' => 'en_livraison',
      'livre' => 'livre',
      'annulee' => 'annulee',
    );
    return $map[$s] ?? $s;
  }

  private function initialStatusValue(): string
  {
    if ($this->initialStatusCache !== null) {
      return $this->initialStatusCache;
    }

    try {
      $row = $this->columnMeta('orders', 'status');
      $type = strtolower((string) ($row['Type'] ?? ''));
      if ($type !== '' && strpos($type, 'enum(') === 0 && strpos($type, "'nouveau'") === false && strpos($type, "'nouvelle'") !== false) {
        $this->initialStatusCache = 'nouvelle';
        return $this->initialStatusCache;
      }
    } catch (Throwable $e) {
      // fallback
    }

    $this->initialStatusCache = 'nouveau';
    return $this->initialStatusCache;
  }

  private function statusToDb(string $normalized): string
  {
    $normalized = self::normalizeStatus($normalized);
    if ($normalized === 'annulee') {
      if ($this->initialStatusValue() !== 'nouvelle') {
        return 'annulee';
      }
      if ($this->isStatusEnumValueAllowed('annulee')) {
        return 'annulee';
      }
      throw new InvalidArgumentException('Statut invalide.');
    }
    if ($this->initialStatusValue() !== 'nouvelle') {
      return $normalized;
    }

    $map = array(
      'nouveau' => 'nouvelle',
      'confirme' => 'confirmee',
      'en_preparation' => 'preparee',
      'en_livraison' => 'en_livraison',
      'livre' => 'livree',
    );
    return $map[$normalized] ?? $normalized;
  }

  private function isStatusEnumValueAllowed(string $value): bool
  {
    if ($this->statusIsEnumCache === null) {
      $this->statusIsEnumCache = false;
      $this->statusEnumValuesCache = array();

      try {
        $row = $this->columnMeta('orders', 'status');
        $type = trim((string) ($row['Type'] ?? ''));
        if ($type !== '' && stripos($type, 'enum(') === 0) {
          $this->statusIsEnumCache = true;
          $values = array();
          if (preg_match_all("/'((?:\\\\'|[^'])*)'/", $type, $m)) {
            foreach (($m[1] ?? array()) as $raw) {
              $values[] = str_replace("\\'", "'", (string) $raw);
            }
          }
          $this->statusEnumValuesCache = array_values(array_unique($values));
        }
      } catch (Throwable $e) {
        // fallback: treat as non-enum (allow)
      }
    }

    if ($this->statusIsEnumCache !== true) {
      return true;
    }
    return in_array($value, $this->statusEnumValuesCache ?? array(), true);
  }

  /**
   * @return string[]
   */
  private static function statusDbValues(string $normalized): array
  {
    $normalized = self::normalizeStatus($normalized);
    $map = array(
      'nouveau' => array('nouveau', 'nouvelle'),
      'confirme' => array('confirme', 'confirmee'),
      'en_preparation' => array('en_preparation', 'preparee'),
      'en_livraison' => array('en_livraison'),
      'livre' => array('livre', 'livree'),
      'annulee' => array('annulee'),
    );
    return $map[$normalized] ?? array($normalized);
  }

  private function isTransitionAllowed(string $current, string $next): bool
  {
    $current = self::normalizeStatus($current);
    $next = self::normalizeStatus($next);

    if ($current === 'livre') return false;
    if ($current === 'annulee') return false;

    if (!in_array($next, self::STATUSES, true)) return false;

    if ($next === 'annulee') {
      return in_array($current, array('nouveau', 'confirme', 'en_preparation', 'en_livraison'), true);
    }

    $i = array_search($current, self::FLOW, true);
    $j = array_search($next, self::FLOW, true);
    if ($i === false || $j === false) {
      return false;
    }

    return $j === ($i + 1);
  }

  // =========================================================
  // Lecture admin: query-building (listes / filtres / comptage)
  // =========================================================

  /**
   * @return array{string, array<string,mixed>}
   */
  private function buildWhere(array $filters): array
  {
    $where = array();
    $params = array();

    $this->appendWhereDateFilters($filters, $where, $params);
    $this->appendWhereStatusFilter($filters, $where, $params);
    $this->appendWherePaymentStatusFilter($filters, $where, $params);
    $this->appendWhereSearchFilter($filters, $where, $params);

    $sql = '';
    if ($where) {
      $sql = ' WHERE ' . implode(' AND ', $where);
    }
    return array($sql, $params);
  }

  /**
   * @param array<string,mixed> $filters
   * @param array<int,string> $where
   * @param array<string,mixed> $params
   */
  private function appendWhereDateFilters(array $filters, array &$where, array &$params): void
  {
    $from = !empty($filters['from']) ? trim((string) $filters['from']) : '';
    $to = !empty($filters['to']) ? trim((string) $filters['to']) : '';
    $fromValid = ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from));
    $toValid = ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to));

    if ($fromValid && $toValid) {
      $where[] = 'o.created_at BETWEEN :from_dt AND :to_dt';
      $params['from_dt'] = $from . ' 00:00:00';
      $params['to_dt'] = $to . ' 23:59:59';
      return;
    }

    if ($fromValid) {
      $where[] = 'o.created_at >= :from_dt';
      $params['from_dt'] = $from . ' 00:00:00';
    }
    if ($toValid) {
      $where[] = 'o.created_at <= :to_dt';
      $params['to_dt'] = $to . ' 23:59:59';
    }
  }

  /**
   * @param array<string,mixed> $filters
   * @param array<int,string> $where
   * @param array<string,mixed> $params
   */
  private function appendWhereStatusFilter(array $filters, array &$where, array &$params): void
  {
    if (empty($filters['status'])) {
      return;
    }

    $status = self::normalizeStatus((string) $filters['status']);
    if (!in_array($status, self::STATUSES, true) && $status !== 'annulee') {
      return;
    }

    $values = self::statusDbValues($status);
    if (count($values) === 1) {
      $where[] = 'o.status = :status';
      $params['status'] = $values[0];
      return;
    }

    $ph = array();
    foreach ($values as $i => $v) {
      $k = 'status_' . $i;
      $ph[] = ':' . $k;
      $params[$k] = $v;
    }
    $where[] = 'o.status IN (' . implode(', ', $ph) . ')';
  }

  /**
   * @param array<string,mixed> $filters
   * @param array<int,string> $where
   * @param array<string,mixed> $params
   */
  private function appendWherePaymentStatusFilter(array $filters, array &$where, array &$params): void
  {
    if (empty($filters['payment_status'])) {
      return;
    }

    $where[] = 'o.payment_status = :payment_status';
    $params['payment_status'] = $filters['payment_status'];
  }

  /**
   * @param array<string,mixed> $filters
   * @param array<int,string> $where
   * @param array<string,mixed> $params
   */
  private function appendWhereSearchFilter(array $filters, array &$where, array &$params): void
  {
    if (empty($filters['q'])) {
      return;
    }

    $q = '%' . trim((string) $filters['q']) . '%';
    $phoneCol = $this->hasOrderColumn('customer_phone') ? 'o.customer_phone'
      : ($this->hasOrderColumn('phone') ? 'o.phone' : 'o.order_number');

    // PDO MySQL (emulate_prepares=false): ne pas reutiliser un meme placeholder nomme (HY093).
    $where[] = '(o.order_number LIKE :q_number OR ' . $phoneCol . ' LIKE :q_phone)';
    $params['q_number'] = $q;
    $params['q_phone'] = $q;
  }

  /**
   * @return array<int, array<string,mixed>>
   */
  private function fetchAllWithFilters(array $filters): array
  {
    [$sql, $params] = $this->buildAdminListQuery($filters);
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    return array_map(fn ($r) => $this->normalizeOrderRow($r), $rows);
  }

  private function countWithFilters(array $filters): int
  {
    [$sql, $params] = $this->buildAdminCountQuery($filters);
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
  }

  /**
   * @param array<string,mixed> $filters
   * @return array{0:string,1:array<string,mixed>}
   */
  private function buildAdminListQuery(array $filters): array
  {
    [$whereSql, $params] = $this->buildWhere($filters);
    [$selectSql, $joinSql] = $this->ordersSelectSql(true);
    $sql = $selectSql . $joinSql . $whereSql . ' ORDER BY o.created_at DESC, o.id DESC';

    if (isset($filters['limit'])) {
      $limit = max(1, (int) $filters['limit']);
      $offset = max(0, (int) ($filters['offset'] ?? 0));
      $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    return array($sql, $params);
  }

  /**
   * @param array<string,mixed> $filters
   * @return array{0:string,1:array<string,mixed>}
   */
  private function buildAdminCountQuery(array $filters): array
  {
    unset($filters['limit'], $filters['offset']);
    [$whereSql, $params] = $this->buildWhere($filters);
    return array('SELECT COUNT(*) AS cnt FROM orders o' . $whereSql, $params);
  }

  /**
   * Construit un SELECT compatible avec plusieurs schémas.
   * Alias garantis: customer_name, customer_phone, total_amount.
   *
   * @return array{0:string,1:string} [selectFromOrders, joinSql]
   */
  private function ordersSelectSql(bool $list = false): array
  {
    $cols = $this->orderColumns();
    $couponCols = $this->tableColumns('coupons');
    $hasCouponTable = $couponCols !== array();
    $hasCouponId = in_array('coupon_id', $cols, true);

    $select = array(
      'o.id',
      'o.order_number',
      'o.status',
      'o.city',
      'o.district',
      'o.landmark',
      'o.created_at',
      'o.updated_at',
    );

    if (in_array('customer_id', $cols, true)) {
      $select[] = 'o.customer_id';
    }
    if (in_array('customer_profile_id', $cols, true)) {
      $select[] = 'o.customer_profile_id';
    }
    if ($hasCouponId) {
      $select[] = 'o.coupon_id';
    }

    if (in_array('status_updated_at', $cols, true)) {
      $select[] = 'o.status_updated_at';
    }
    foreach (array('payment_method', 'payment_status', 'payment_provider', 'payment_reference', 'paid_at') as $paymentCol) {
      if (in_array($paymentCol, $cols, true)) {
        $select[] = 'o.' . $paymentCol;
      }
    }

    if (in_array('total_amount', $cols, true)) {
      $select[] = 'o.total_amount AS total_amount';
    } elseif (in_array('total_fcfa', $cols, true)) {
      $select[] = 'o.total_fcfa AS total_amount';
    } else {
      $select[] = '0 AS total_amount';
    }

    /* Charge les montants détaillés seulement si le schéma les expose. */
    if (in_array('subtotal_amount', $cols, true)) {
      $select[] = 'o.subtotal_amount';
    }
    if (in_array('discount_amount', $cols, true)) {
      $select[] = 'o.discount_amount';
    }
    if (in_array('shipping_fee_amount', $cols, true)) {
      $select[] = 'o.shipping_fee_amount';
    }
    if (in_array('tax_amount', $cols, true)) {
      $select[] = 'o.tax_amount';
    }
    if (in_array('customer_email', $cols, true)) {
      $select[] = 'o.customer_email';
    }

    if (in_array('customer_phone', $cols, true)) {
      $select[] = 'o.customer_phone AS customer_phone';
    } elseif (in_array('phone', $cols, true)) {
      $select[] = 'o.phone AS customer_phone';
    } else {
      $select[] = "'' AS customer_phone";
    }

    $join = '';

    if ($hasCouponId && $hasCouponTable && in_array('code', $couponCols, true)) {
      $join .= ' LEFT JOIN coupons cpn ON cpn.id = o.coupon_id';
      $select[] = 'COALESCE(cpn.code, \'\') AS coupon_code';
    } else {
      $select[] = "'' AS coupon_code";
    }

    if (in_array('customer_name', $cols, true)) {
      $select[] = 'o.customer_name AS customer_name';
    } else {
      $userKey = null;
      if (in_array('user_id', $cols, true)) {
        $userKey = 'o.user_id';
      } elseif (in_array('customer_id', $cols, true)) {
        $userKey = 'o.customer_id';
      }

      if ($userKey !== null) {
        $join = ' LEFT JOIN users u ON u.id = ' . $userKey;
        $select[] = 'COALESCE(u.name, \'\') AS customer_name';
      } else {
        $select[] = "'' AS customer_name";
      }
    }

    $sql = 'SELECT ' . implode(', ', $select) . ' FROM orders o';
    return array($sql, $join);
  }

  /**
   * @param array<string,mixed> $row
   * @return array<string,mixed>
   */
  private function normalizeOrderRow(array $row): array
  {
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['order_number'] = (string) ($row['order_number'] ?? '');
    $row['status'] = self::normalizeStatus((string) ($row['status'] ?? 'nouveau'));
    $row['customer_name'] = (string) ($row['customer_name'] ?? '');
    $row['customer_phone'] = (string) ($row['customer_phone'] ?? '');
    $row['total_amount'] = (int) ($row['total_amount'] ?? 0);
    /* Normalise les champs monétaires optionnels avant retour. */
    if (array_key_exists('subtotal_amount', $row)) $row['subtotal_amount'] = (int) ($row['subtotal_amount'] ?? 0);
    if (array_key_exists('discount_amount', $row)) $row['discount_amount'] = (int) ($row['discount_amount'] ?? 0);
    if (array_key_exists('shipping_fee_amount', $row)) $row['shipping_fee_amount'] = (int) ($row['shipping_fee_amount'] ?? 0);
    if (array_key_exists('tax_amount', $row)) $row['tax_amount'] = (int) ($row['tax_amount'] ?? 0);
    if (array_key_exists('customer_email', $row)) $row['customer_email'] = (string) ($row['customer_email'] ?? '');
    if (array_key_exists('customer_id', $row)) $row['customer_id'] = (int) ($row['customer_id'] ?? 0);
    if (array_key_exists('customer_profile_id', $row)) $row['customer_profile_id'] = (int) ($row['customer_profile_id'] ?? 0);
    if (array_key_exists('coupon_id', $row)) $row['coupon_id'] = (int) ($row['coupon_id'] ?? 0);
    if (array_key_exists('coupon_code', $row)) $row['coupon_code'] = strtoupper(trim((string) ($row['coupon_code'] ?? '')));
    $row['city'] = (string) ($row['city'] ?? '');
    $row['district'] = (string) ($row['district'] ?? '');
    $row['landmark'] = (string) ($row['landmark'] ?? '');
    $row['created_at'] = (string) ($row['created_at'] ?? '');
    $row['updated_at'] = (string) ($row['updated_at'] ?? '');
    if (array_key_exists('status_updated_at', $row)) {
      $row['status_updated_at'] = (string) ($row['status_updated_at'] ?? '');
    }
    foreach (array('payment_method', 'payment_status', 'payment_provider', 'payment_reference', 'paid_at') as $paymentCol) {
      if (array_key_exists($paymentCol, $row)) {
        $row[$paymentCol] = (string) ($row[$paymentCol] ?? '');
      }
    }
    return $row;
  }

  // =========================================================
  // Support technique: schema/compatibilite DB (non-metier)
  // =========================================================

  private function normalizeSchemaTableName(string $table): string
  {
    $table = trim($table);
    if ($table === '') {
      return '';
    }
    return preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  private function fetchSchemaColumnsRows(string $table): array
  {
    $safeTable = $this->normalizeSchemaTableName($table);
    if ($safeTable === '') {
      return array();
    }

    try {
      return $this->pdo->query('SHOW COLUMNS FROM `' . $safeTable . '`')->fetchAll(PDO::FETCH_ASSOC) ?: array();
    } catch (Throwable $e) {
      return array();
    }
  }

  /**
   * @return string[]
   */
  private function tableColumns(string $table): array
  {
    $table = trim($table);
    if ($table === '') {
      return array();
    }

    $cacheKey = strtolower($table);
    if (isset($this->columnMetaCache[$cacheKey]['__cols']) && is_array($this->columnMetaCache[$cacheKey]['__cols'])) {
      /** @var string[] $cachedCols */
      $cachedCols = $this->columnMetaCache[$cacheKey]['__cols'];
      return $cachedCols;
    }

    // Preferred path: helper central avec cache statique request-level.
    if (function_exists('db_table_columns')) {
      $cols = db_table_columns($this->pdo, $table);
      $this->columnMetaCache[$cacheKey]['__cols'] = $cols;
      return $cols;
    }

    // Fallback defensif si helper indisponible.
    $rows = $this->fetchSchemaColumnsRows($table);
    if (!$rows) {
      $this->columnMetaCache[$cacheKey]['__cols'] = array();
      return array();
    }

    $cols = array();
    foreach ($rows as $row) {
      if (!empty($row['Field'])) {
        $cols[] = (string) $row['Field'];
      }
    }
    $this->columnMetaCache[$cacheKey]['__cols'] = $cols;
    return $cols;
  }

  private function tableExists(string $table): bool
  {
    $table = trim($table);
    if ($table === '') {
      return false;
    }
    $cacheKey = strtolower($table);
    if (array_key_exists($cacheKey, $this->tableExistsCache)) {
      return $this->tableExistsCache[$cacheKey];
    }
    $this->tableExistsCache[$cacheKey] = !empty($this->tableColumns($table));
    return $this->tableExistsCache[$cacheKey];
  }

  /**
   * @return array<string,mixed>|null
   */
  private function columnMeta(string $table, string $column): ?array
  {
    $table = trim($table);
    $column = trim($column);
    if ($table === '' || $column === '') {
      return null;
    }

    $cacheKey = strtolower($table);
    $metaKey = '__meta';
    if (!isset($this->columnMetaCache[$cacheKey][$metaKey]) || !is_array($this->columnMetaCache[$cacheKey][$metaKey])) {
      $this->columnMetaCache[$cacheKey][$metaKey] = array();
      $rows = $this->fetchSchemaColumnsRows($table);
      if ($rows) {
        $cols = array();
        foreach ($rows as $row) {
          $field = (string) ($row['Field'] ?? '');
          if ($field === '') continue;
          $cols[] = $field;
          $this->columnMetaCache[$cacheKey][$metaKey][strtolower($field)] = $row;
        }
        if (!isset($this->columnMetaCache[$cacheKey]['__cols']) || !is_array($this->columnMetaCache[$cacheKey]['__cols'])) {
          $this->columnMetaCache[$cacheKey]['__cols'] = $cols;
        }
      }
    }

    $lookup = strtolower($column);
    if (isset($this->columnMetaCache[$cacheKey][$metaKey][$lookup]) && is_array($this->columnMetaCache[$cacheKey][$metaKey][$lookup])) {
      /** @var array<string,mixed> $meta */
      $meta = $this->columnMetaCache[$cacheKey][$metaKey][$lookup];
      return $meta;
    }
    return null;
  }

  /**
   * @return string[]
   */
  private function orderColumns(): array
  {
    if (is_array($this->orderCols)) {
      return $this->orderCols;
    }

    $this->orderCols = $this->tableColumns('orders');
    return $this->orderCols;
  }

  private function hasOrderColumn(string $name): bool
  {
    return in_array($name, $this->orderColumns(), true);
  }

  /**
   * @return string[]
   */
  private function orderItemColumns(): array
  {
    if (is_array($this->itemCols)) {
      return $this->itemCols;
    }

    $this->itemCols = $this->tableColumns('order_items');
    return $this->itemCols;
  }

  /**
   * @return array<int, array{product_id:int, variant_id:?int, qty:int}>
   */
  private function itemsForRestock(int $orderId): array
  {
    $orderId = (int) $orderId;
    if ($orderId <= 0) return array();

    $cols = $this->orderItemColumns();
    $variantCol = in_array('variant_id', $cols, true) ? 'variant_id' : 'NULL';
    $qtyCol = in_array('qty', $cols, true) ? 'qty'
      : (in_array('quantity', $cols, true) ? 'quantity' : '0');

    $stmt = $this->pdo->prepare(
      'SELECT product_id, ' . $variantCol . ' AS variant_id, ' . $qtyCol . ' AS qty FROM order_items WHERE order_id = :id'
    );
    $stmt->execute(array('id' => $orderId));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();

    $out = array();
    foreach ($rows as $r) {
      $variantId = isset($r['variant_id']) ? (int) $r['variant_id'] : 0;
      $out[] = array(
        'product_id' => (int) ($r['product_id'] ?? 0),
        'variant_id' => $variantId > 0 ? $variantId : null,
        'qty' => (int) ($r['qty'] ?? 0),
      );
    }
    return $out;
  }

  private function syncProductStockFromVariants(int $productId, ProductVariantService $variantService): void
  {
    $productId = (int) $productId;
    if ($productId <= 0 || !$variantService->hasAnyVariants($productId)) {
      return;
    }

    $stmt = $this->pdo->prepare('UPDATE products SET stock = :stock WHERE id = :id');
    $stmt->execute(array(
      'stock' => $variantService->calculateActiveStock($productId),
      'id' => $productId,
    ));
  }

  private function historyTableExists(): bool
  {
    if (is_bool($this->historyExists)) {
      return $this->historyExists;
    }

    $this->historyExists = $this->tableExists('order_status_history');
    return $this->historyExists;
  }

  /**
   * @return string[]
   */
  private function historyColumns(): array
  {
    if (is_array($this->historyCols)) {
      return $this->historyCols;
    }

    if (!$this->historyTableExists()) {
      $this->historyCols = array();
      return array();
    }

    $this->historyCols = $this->tableColumns('order_status_history');
    return $this->historyCols;
  }

  /**
   * @return string[]
   */
  private function productColumns(): array
  {
    if (is_array($this->productCols)) {
      return $this->productCols;
    }
    $this->productCols = $this->tableColumns('products');
    return $this->productCols;
  }

  /**
   * Retourne une expression SQL qui expose un champ `price` (int FCFA) compatible avec le schéma.
   *
   * @param string[] $cols
   */
  private function productPriceExpr(array $cols): string
  {
    if (in_array('price', $cols, true)) return 'price';
    if (in_array('price_fcfa', $cols, true)) return 'price_fcfa AS price';
    if (in_array('prix', $cols, true)) return 'prix AS price';
    if (in_array('unit_price', $cols, true)) return 'unit_price AS price';
    if (in_array('montant', $cols, true)) return 'montant AS price';
    if (in_array('amount', $cols, true)) return 'amount AS price';
    return '0 AS price';
  }

}

