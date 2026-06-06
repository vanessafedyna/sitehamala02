<?php
declare(strict_types=1);


require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/services/InventorySnapshotService.php';

final class AdminStats
{
  /** @var array<string, array<string,bool>> */
  private static array $hasCol = array();

  private static function revenueTimezone(): DateTimeZone
  {
    static $timezone = null;
    if ($timezone instanceof DateTimeZone) {
      return $timezone;
    }

    $candidate = '';
    if (function_exists('env')) {
      $candidate = trim((string) env('APP_TIMEZONE', env('TIMEZONE', '')));
    }
    if ($candidate === '') {
      $candidate = 'Africa/Bamako';
    }

    try {
      $timezone = new DateTimeZone($candidate);
    } catch (Throwable $e) {
      $timezone = new DateTimeZone('Africa/Bamako');
    }

    return $timezone;
  }

  private static function revenueToday(): DateTimeImmutable
  {
    return (new DateTimeImmutable('now', self::revenueTimezone()))->setTime(0, 0, 0);
  }

  private static function revenueTotalExpr(PDO $pdo, string $alias = ''): string
  {
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    if (self::hasColumn($pdo, 'orders', 'total_amount')) {
      return 'COALESCE(' . $prefix . 'total_amount, 0)';
    }
    if (self::hasColumn($pdo, 'orders', 'grand_total')) {
      return 'COALESCE(' . $prefix . 'grand_total, 0)';
    }
    if (self::hasColumn($pdo, 'orders', 'total_fcfa')) {
      return 'COALESCE(' . $prefix . 'total_fcfa, 0)';
    }

    return '0';
  }

  private static function revenueDateExpr(PDO $pdo, string $alias = ''): string
  {
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    if (self::hasColumn($pdo, 'orders', 'delivered_at') && self::hasColumn($pdo, 'orders', 'status_updated_at')) {
      return 'COALESCE(' . $prefix . 'delivered_at, ' . $prefix . 'status_updated_at, ' . $prefix . 'created_at)';
    }
    if (self::hasColumn($pdo, 'orders', 'delivered_at')) {
      return 'COALESCE(' . $prefix . 'delivered_at, ' . $prefix . 'created_at)';
    }
    if (self::hasColumn($pdo, 'orders', 'status_updated_at')) {
      return 'COALESCE(' . $prefix . 'status_updated_at, ' . $prefix . 'created_at)';
    }

    return $prefix . 'created_at';
  }

  private static function hasColumn(PDO $pdo, string $table, string $column): bool
  {
    $table = trim($table);
    $column = trim($column);
    if ($table === '' || $column === '') return false;

    if (isset(self::$hasCol[$table]) && array_key_exists($column, self::$hasCol[$table])) {
      return (bool) self::$hasCol[$table][$column];
    }
    if (!isset(self::$hasCol[$table])) {
      self::$hasCol[$table] = array();
    }

    try {
      if (function_exists('db_has_column')) {
        $ok = db_has_column($pdo, $table, $column);
        self::$hasCol[$table][$column] = $ok;
        return $ok;
      }
      $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
      $stmt->execute(array('c' => $column));
      $ok = (bool) ($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
      self::$hasCol[$table][$column] = $ok;
      return $ok;
    } catch (Throwable $e) {
      self::$hasCol[$table][$column] = false;
      return false;
    }
  }

  /**
   * @return array{stock_out:int,stock_low:int}
   */
  private static function stockSnapshotCounts(PDO $pdo): array
  {
    $counts = array(
      'stock_out' => 0,
      'stock_low' => 0,
    );

    try {
      $productCols = function_exists('db_table_columns') ? db_table_columns($pdo, 'products') : array();
      if ($productCols === array() || !in_array('stock', $productCols, true)) {
        return $counts;
      }

      $stockSelect = in_array('low_stock_threshold', $productCols, true)
        ? 'SELECT id, stock, low_stock_threshold FROM products ORDER BY id DESC'
        : 'SELECT id, stock FROM products ORDER BY id DESC';

      $inventory = new InventorySnapshotService($pdo);
      $rows = $inventory->hydrateProductRows($pdo->query($stockSelect)->fetchAll(PDO::FETCH_ASSOC) ?: array());

      foreach ($rows as $row) {
        if ((int) ($row['is_out_of_stock_effective'] ?? 0) === 1) {
          $counts['stock_out']++;
        } elseif ((int) ($row['is_low_stock_effective'] ?? 0) === 1) {
          $counts['stock_low']++;
        }
      }
    } catch (Throwable $e) {
      return array(
        'stock_out' => 0,
        'stock_low' => 0,
      );
    }

    return $counts;
  }

  /**
   * KPIs mensuels (owner).
   *
   * @return array<string,int>
   */
  public static function overviewMonthly(PDO $pdo): array
  {
    $today = self::revenueToday();
    $start = $today->modify('first day of this month')->format('Y-m-d 00:00:00');
    $end = $today->modify('last day of this month')->format('Y-m-d 23:59:59');
    $revenueExpr = self::revenueTotalExpr($pdo);
    $revenueDateExpr = self::revenueDateExpr($pdo);

    // CA basé sur livrée (statuts DB FR actuels)
    $stmt = $pdo->prepare(
      'SELECT COALESCE(SUM(' . $revenueExpr . '),0) AS ca, COUNT(*) AS cnt
       FROM orders
       WHERE status IN (:st_new, :st_legacy) AND ' . $revenueDateExpr . ' BETWEEN :start AND :end'
    );
    $stmt->execute(array('st_new' => 'livre', 'st_legacy' => 'livree', 'start' => $start, 'end' => $end));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: array();
    $ca = (int) ($row['ca'] ?? 0);
    $cnt = (int) ($row['cnt'] ?? 0);

    $avg = $cnt > 0 ? (int) round($ca / $cnt) : 0;

    $toProcess = self::countOrdersByStatus($pdo, array('nouveau', 'confirme', 'en_preparation', 'en_livraison'));

    $stockCounts = self::stockSnapshotCounts($pdo);
    $stockOut = (int) ($stockCounts['stock_out'] ?? 0);
    $stockLow = (int) ($stockCounts['stock_low'] ?? 0);

    $pendingReviews = 0;
    try {
      if (self::hasColumn($pdo, 'reviews', 'is_approved')) {
        $pendingReviews = (int) ($pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved = 0')->fetchColumn() ?: 0);
      }
      if (self::hasColumn($pdo, 'product_reviews', 'is_approved')) {
        $pendingReviews += (int) ($pdo->query('SELECT COUNT(*) FROM product_reviews WHERE is_approved = 0')->fetchColumn() ?: 0);
      }
    } catch (Throwable $e) {
      $pendingReviews = 0;
    }

    $pendingProducts = 0;
    try {
      if (self::hasColumn($pdo, 'products', 'status')) {
        $pendingProducts = (int) ($pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn() ?: 0);
      }
    } catch (Throwable $e) {
      $pendingProducts = 0;
    }

    return array(
      'ca_month' => $ca,
      'orders_month' => $cnt,
      'avg_basket_month' => $avg,
      'to_process' => $toProcess,
      'stock_out' => $stockOut,
      'stock_low' => $stockLow,
      'reviews_pending' => $pendingReviews,
      'products_pending' => $pendingProducts,
    );
  }

  /**
   * Badges légers pour la sidebar (owner).
   *
   * @return array<string,int>
   */
  public static function sidebarBadges(PDO $pdo): array
  {
    $badges = array(
      'orders_todo' => 0,
      'reviews_pending' => 0,
      'product_reviews_pending' => 0,
      'products_pending' => 0,
      'stock_out' => 0,
      'stock_low' => 0,
    );

    try {
      $badges['orders_todo'] = self::countOrdersByStatus($pdo, array('nouveau', 'confirme', 'en_preparation', 'en_livraison'));
    } catch (Throwable $e) {
      $badges['orders_todo'] = 0;
    }

    try {
      if (self::hasColumn($pdo, 'reviews', 'is_approved')) {
        $badges['reviews_pending'] = (int) ($pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved = 0')->fetchColumn() ?: 0);
      }
      if (self::hasColumn($pdo, 'product_reviews', 'is_approved')) {
        $badges['product_reviews_pending'] = (int) ($pdo->query('SELECT COUNT(*) FROM product_reviews WHERE is_approved = 0')->fetchColumn() ?: 0);
      }
    } catch (Throwable $e) {
      $badges['reviews_pending'] = 0;
      $badges['product_reviews_pending'] = 0;
    }

    try {
      if (self::hasColumn($pdo, 'products', 'status')) {
        $badges['products_pending'] = (int) ($pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn() ?: 0);
      }
    } catch (Throwable $e) {
      $badges['products_pending'] = 0;
    }

    $stockCounts = self::stockSnapshotCounts($pdo);
    $badges['stock_out'] = (int) ($stockCounts['stock_out'] ?? 0);
    $badges['stock_low'] = (int) ($stockCounts['stock_low'] ?? 0);

    return $badges;
  }

  /**
   * @param string[] $statuses
   */
  private static function countOrdersByStatus(PDO $pdo, array $statuses): int
  {
    $statuses = array_values(array_filter(array_map('strval', $statuses), fn ($s) => trim($s) !== ''));
    if (!$statuses) return 0;

    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status IN ($in)");
    $stmt->execute($statuses);
    return (int) ($stmt->fetchColumn() ?: 0);
  }
}


