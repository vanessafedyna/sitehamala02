<?php
declare(strict_types=1);

/* Modèle clients admin */

final class CustomerModel
{
  private PDO $pdo;
  private ?bool $tableExists = null;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function exists(): bool
  {
    if (is_bool($this->tableExists)) {
      return $this->tableExists;
    }
    if (function_exists('db_table_columns')) {
      $this->tableExists = db_table_columns($this->pdo, 'customers') !== array();
      return $this->tableExists;
    }
    try {
      $stmt = $this->pdo->query("SHOW TABLES LIKE 'customers'");
      $this->tableExists = (bool) ($stmt && $stmt->fetchColumn());
      return $this->tableExists;
    } catch (Throwable $e) {
      $this->tableExists = false;
      return false;
    }
  }

  public function findById(int $id): ?array
  {
    $id = (int) $id;
    if ($id <= 0 || !$this->exists()) return null;

    $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => $id));
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function findByPhone(string $phone): ?array
  {
    $phone = trim($phone);
    if ($phone === '' || !$this->exists()) return null;

    $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE phone = :p LIMIT 1');
    $stmt->execute(array('p' => $phone));
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  /**
   * Crée ou met à jour un customer par téléphone (unique).
   *
   * @param array<string,mixed> $data
   * @return array{customer_id:int,is_blacklisted:bool}
   */
  public function findOrCreateByPhone(array $data): array
  {
    if (!$this->exists()) {
      return array('customer_id' => 0, 'is_blacklisted' => false);
    }

    $fullName = trim((string) ($data['full_name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $email = array_key_exists('email', $data) ? trim((string) $data['email']) : '';
    $city = trim((string) ($data['city'] ?? ''));
    $district = array_key_exists('district', $data) ? trim((string) $data['district']) : '';
    $addressNote = array_key_exists('address_note', $data) ? trim((string) $data['address_note']) : '';

    if ($fullName === '' || $phone === '' || $city === '') {
      return array('customer_id' => 0, 'is_blacklisted' => false);
    }

    $this->pdo->beginTransaction();
    try {
      $stmt = $this->pdo->prepare('SELECT id, is_blacklisted FROM customers WHERE phone = :p LIMIT 1 FOR UPDATE');
      $stmt->execute(array('p' => $phone));
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

      if ($row) {
        $id = (int) ($row['id'] ?? 0);
        $isBlacklisted = ((int) ($row['is_blacklisted'] ?? 0)) === 1;

        $upd = $this->pdo->prepare(
          'UPDATE customers
           SET full_name = :n, city = :c, district = :d, address_note = :a, email = :e
           WHERE id = :id
           LIMIT 1'
        );
        $upd->execute(array(
          'n' => $fullName,
          'c' => $city,
          'd' => ($district === '' ? null : $district),
          'a' => ($addressNote === '' ? null : $addressNote),
          'e' => ($email === '' ? null : $email),
          'id' => $id,
        ));

        $this->pdo->commit();
        return array('customer_id' => $id, 'is_blacklisted' => $isBlacklisted);
      }

      $ins = $this->pdo->prepare(
        'INSERT INTO customers (full_name, phone, email, city, district, address_note)
         VALUES (:n, :p, :e, :c, :d, :a)'
      );
      $ins->execute(array(
        'n' => $fullName,
        'p' => $phone,
        'e' => ($email === '' ? null : $email),
        'c' => $city,
        'd' => ($district === '' ? null : $district),
        'a' => ($addressNote === '' ? null : $addressNote),
      ));

      $newId = (int) $this->pdo->lastInsertId();
      $this->pdo->commit();
      return array('customer_id' => $newId, 'is_blacklisted' => false);
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  /**
   * @return array<int,array<string,mixed>>
   */
  public function list(array $filters = array()): array
  {
    if (!$this->exists()) return array();

    $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
    $blacklisted = isset($filters['blacklisted']) ? (string) $filters['blacklisted'] : '';
    $limit = isset($filters['limit']) ? max(1, min(200, (int) $filters['limit'])) : 20;
    $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

    $where = array();
    $params = array();

    if ($q !== '') {
      $where[] = '(full_name LIKE :q_name OR phone LIKE :q_phone OR email LIKE :q_email)';
      $qLike = '%' . $q . '%';
      $params['q_name'] = $qLike;
      $params['q_phone'] = $qLike;
      $params['q_email'] = $qLike;
    }
    if ($blacklisted === '1' || $blacklisted === '0') {
      $where[] = 'is_blacklisted = :b';
      $params['b'] = (int) $blacklisted;
    }

    $sql = 'SELECT * FROM customers';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

    $stmt = $this->pdo->prepare($sql);
    foreach ($params as $k => $v) {
      $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  public function count(array $filters = array()): int
  {
    if (!$this->exists()) return 0;

    $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
    $blacklisted = isset($filters['blacklisted']) ? (string) $filters['blacklisted'] : '';

    $where = array();
    $params = array();

    if ($q !== '') {
      $where[] = '(full_name LIKE :q_name OR phone LIKE :q_phone OR email LIKE :q_email)';
      $qLike = '%' . $q . '%';
      $params['q_name'] = $qLike;
      $params['q_phone'] = $qLike;
      $params['q_email'] = $qLike;
    }
    if ($blacklisted === '1' || $blacklisted === '0') {
      $where[] = 'is_blacklisted = :b';
      $params['b'] = (int) $blacklisted;
    }

    $sql = 'SELECT COUNT(*) FROM customers';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

    $stmt = $this->pdo->prepare($sql);
    foreach ($params as $k => $v) {
      $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) ($stmt->fetchColumn() ?: 0);
  }

  public function setBlacklisted(int $id, bool $val): bool
  {
    if (!$this->exists()) return false;
    $id = (int) $id;
    if ($id <= 0) return false;

    $stmt = $this->pdo->prepare('UPDATE customers SET is_blacklisted = :b WHERE id = :id LIMIT 1');
    $stmt->execute(array('b' => $val ? 1 : 0, 'id' => $id));
    return $stmt->rowCount() > 0;
  }
}

