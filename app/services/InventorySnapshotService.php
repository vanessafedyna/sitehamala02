<?php
declare(strict_types=1);

require_once __DIR__ . '/ProductVariantService.php';

final class InventorySnapshotService
{
  private PDO $pdo;
  private ProductVariantService $variantService;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
    $this->variantService = new ProductVariantService($pdo);
  }

  /**
   * @param array<int, array<string,mixed>> $rows
   * @return array<int, array<string,mixed>>
   */
  public function hydrateProductRows(array $rows): array
  {
    if (!$rows) {
      return array();
    }

    $summaryByProduct = $this->variantSummaryByProduct($rows);
    foreach ($rows as &$row) {
      $productId = (int) ($row['id'] ?? 0);
      $summary = $summaryByProduct[$productId] ?? null;
      $stock = (int) ($row['stock'] ?? 0);
      $thresholdRaw = isset($row['low_stock_threshold']) ? (int) $row['low_stock_threshold'] : 0;
      $threshold = $thresholdRaw > 0 ? $thresholdRaw : 10;

      $row['uses_variants'] = is_array($summary) && !empty($summary['has_variants']) ? 1 : 0;
      $row['effective_stock'] = ($row['uses_variants'] ?? 0) ? (int) ($summary['stock_total'] ?? 0) : $stock;
      $row['variant_sizes'] = is_array($summary) ? (array) ($summary['sizes'] ?? array()) : array();
      $row['effective_low_stock_threshold'] = $threshold;
      $row['is_out_of_stock_effective'] = ((int) $row['effective_stock'] <= 0) ? 1 : 0;
      $row['is_low_stock_effective'] = (
        (int) $row['effective_stock'] > 0
        && (int) $row['effective_stock'] <= $threshold
      ) ? 1 : 0;
    }
    unset($row);

    return $rows;
  }

  /**
   * @param array<string,mixed> $row
   */
  public function hydrateProductRow(array $row): array
  {
    $rows = $this->hydrateProductRows(array($row));
    return $rows ? $rows[0] : $row;
  }

  /**
   * @param array<int, array<string,mixed>> $rows
   * @return array<int, array{has_variants:bool,sizes:array<int,string>,stock_total:int}>
   */
  private function variantSummaryByProduct(array $rows): array
  {
    if (!$this->variantService->isSupported()) {
      return array();
    }

    $productIds = array();
    foreach ($rows as $row) {
      $productId = (int) ($row['id'] ?? 0);
      if ($productId > 0) {
        $productIds[$productId] = $productId;
      }
    }

    if (!$productIds) {
      return array();
    }

    $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
    $stmt = $this->pdo->prepare(
      'SELECT product_id, size, stock, is_active
       FROM product_variants
       WHERE product_id IN (' . $placeholders . ')
       ORDER BY product_id ASC, id ASC'
    );
    $stmt->execute(array_values($productIds));

    $summary = array();
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: array()) as $variantRow) {
      $productId = (int) ($variantRow['product_id'] ?? 0);
      if ($productId <= 0) {
        continue;
      }

      if (!isset($summary[$productId])) {
        $summary[$productId] = array(
          'has_variants' => true,
          'sizes' => array(),
          'stock_total' => 0,
        );
      }

      $size = trim((string) ($variantRow['size'] ?? ''));
      if ($size !== '' && !in_array($size, $summary[$productId]['sizes'], true)) {
        $summary[$productId]['sizes'][] = $size;
      }

      if ((int) ($variantRow['is_active'] ?? 0) === 1) {
        $summary[$productId]['stock_total'] += (int) ($variantRow['stock'] ?? 0);
      }
    }

    return $summary;
  }
}
