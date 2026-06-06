<?php
declare(strict_types=1);

final class NotificationQueue
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  public function enqueue(int $orderId, string $type, string $recipient, array $payload, int $maxAttempts = 5, string $channel = 'email'): bool
  {
    $orderId = (int) $orderId;
    $type = trim($type);
    $channel = strtolower(trim($channel));
    $maxAttempts = max(1, min(10, (int) $maxAttempts));

    if (!in_array($channel, array('email', 'whatsapp'), true)) {
      return false;
    }

    if ($channel === 'email') {
      $recipient = strtolower(trim($recipient));
      if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
      }
    } else {
      // WhatsApp: normaliser en supprimant les espaces (format E.164 ou digits).
      $recipient = preg_replace('/\s+/', '', trim($recipient));
      $recipient = is_string($recipient) ? $recipient : '';
      if ($recipient === '' || !preg_match('/^\+?\d{8,18}$/', $recipient)) {
        return false;
      }
    }

    if ($orderId <= 0 || $type === '' || $recipient === '') {
      return false;
    }

    try {
      $stmt = $this->pdo->prepare(
        'INSERT INTO notification_jobs (order_id, type, channel, recipient, payload_json, status, attempts, max_attempts, last_error, next_retry_at, created_at, updated_at)
         VALUES (:order_id, :type, :channel, :recipient, :payload_json, :status, 0, :max_attempts, NULL, NULL, NOW(), NOW())'
      );
      $stmt->execute(array(
        'order_id' => $orderId,
        'type' => $type,
        'channel' => $channel,
        'recipient' => $recipient,
        'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'status' => 'pending',
        'max_attempts' => $maxAttempts,
      ));
      return true;
    } catch (Throwable $e) {
      return false;
    }
  }

  /**
   * @return array<int, array<string,mixed>>
   */
  public function getDueJobs(int $limit = 20): array
  {
    $limit = max(1, min(200, (int) $limit));

    $sql = 'SELECT id, order_id, type, recipient, payload_json, status, attempts, max_attempts, last_error, next_retry_at, created_at, updated_at
            FROM notification_jobs
            WHERE
              (status = :pending AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
              OR
              (status = :failed AND attempts < max_attempts AND next_retry_at IS NOT NULL AND next_retry_at <= NOW())
            ORDER BY created_at ASC, id ASC
            LIMIT ' . $limit;

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array('pending' => 'pending', 'failed' => 'failed'));
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  /**
   * Claim due jobs atomically using a lock token.
   *
   * @return array<int, array<string,mixed>>
   */
  public function claimDueJobs(int $limit, string $token, int $lockSeconds = 300): array
  {
    $limit = max(1, min(200, (int) $limit));
    $token = trim($token);
    $lockSeconds = max(30, min(3600, (int) $lockSeconds));
    if ($token === '') {
      return array();
    }

    $lockCutoff = (new DateTimeImmutable('now -' . $lockSeconds . ' seconds'))->format('Y-m-d H:i:s');

    $sqlSelect = 'SELECT id
                  FROM notification_jobs
                  WHERE
                    (
                      (status = :pending AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
                      OR
                      (status = :failed AND attempts < max_attempts AND next_retry_at IS NOT NULL AND next_retry_at <= NOW())
                    )
                    AND (locked_at IS NULL OR locked_at < :lock_cutoff)
                  ORDER BY created_at ASC, id ASC
                  LIMIT ' . $limit;
    $stmt = $this->pdo->prepare($sqlSelect);
    $stmt->execute(array(
      'pending' => 'pending',
      'failed' => 'failed',
      'lock_cutoff' => $lockCutoff,
    ));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: array();
    if (!$rows) {
      return array();
    }

    $ids = array();
    foreach ($rows as $row) {
      $id = (int) ($row['id'] ?? 0);
      if ($id > 0) $ids[] = $id;
    }
    if (!$ids) {
      return array();
    }

    $idParams = array();
    foreach ($ids as $i => $id) {
      $idParams[] = ':id' . $i;
    }
    $idSql = implode(',', $idParams);

    $sqlUpdate = 'UPDATE notification_jobs
                  SET locked_at = NOW(), lock_token = :token, updated_at = NOW()
                  WHERE id IN (' . $idSql . ')
                    AND (locked_at IS NULL OR locked_at < :lock_cutoff)
                    AND (
                      (status = :pending AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
                      OR
                      (status = :failed AND attempts < max_attempts AND next_retry_at IS NOT NULL AND next_retry_at <= NOW())
                    )';
    $stmtUpdate = $this->pdo->prepare($sqlUpdate);
    $stmtUpdate->bindValue(':token', $token, PDO::PARAM_STR);
    $stmtUpdate->bindValue(':lock_cutoff', $lockCutoff, PDO::PARAM_STR);
    $stmtUpdate->bindValue(':pending', 'pending', PDO::PARAM_STR);
    $stmtUpdate->bindValue(':failed', 'failed', PDO::PARAM_STR);
    foreach ($ids as $i => $id) {
      $stmtUpdate->bindValue(':id' . $i, $id, PDO::PARAM_INT);
    }
    $stmtUpdate->execute();

    $sqlClaimed = 'SELECT id, order_id, type, recipient, payload_json, status, attempts, max_attempts, last_error, next_retry_at, created_at, updated_at
                   FROM notification_jobs
                   WHERE lock_token = :token
                     AND id IN (' . $idSql . ')
                   ORDER BY created_at ASC, id ASC';
    $stmtClaimed = $this->pdo->prepare($sqlClaimed);
    $stmtClaimed->bindValue(':token', $token, PDO::PARAM_STR);
    foreach ($ids as $i => $id) {
      $stmtClaimed->bindValue(':id' . $i, $id, PDO::PARAM_INT);
    }
    $stmtClaimed->execute();

    return $stmtClaimed->fetchAll(PDO::FETCH_ASSOC) ?: array();
  }

  public function markSent(int $jobId, ?string $token = null): bool
  {
    $jobId = (int) $jobId;
    if ($jobId <= 0) return false;

    try {
      $job = $this->fetchJob($jobId, $token);
      if (!$job) return false;

      $sql = 'UPDATE notification_jobs
              SET status = :status,
                  last_error = NULL,
                  next_retry_at = NULL,
                  lock_token = NULL,
                  locked_at = NULL,
                  updated_at = NOW()
              WHERE id = :id';
      $params = array('status' => 'sent', 'id' => $jobId);
      if ($token !== null && trim($token) !== '') {
        $sql .= ' AND lock_token = :token';
        $params['token'] = trim($token);
      }
      $stmt = $this->pdo->prepare($sql);
      $ok = $stmt->execute($params);
      if ($ok) {
        $this->log(
          (int) ($job['order_id'] ?? 0),
          (string) ($job['type'] ?? ''),
          (string) ($job['recipient'] ?? ''),
          'sent',
          null
        );
      }
      return $ok;
    } catch (Throwable $e) {
      return false;
    }
  }

  public function markFailed(int $jobId, string $error, ?string $nextRetryAt, ?string $token = null): bool
  {
    $jobId = (int) $jobId;
    if ($jobId <= 0) return false;

    try {
      $job = $this->fetchJob($jobId, $token);
      if (!$job) return false;

      $attempts = (int) ($job['attempts'] ?? 0) + 1;
      $maxAttempts = max(1, (int) ($job['max_attempts'] ?? 5));
      $hasMoreRetries = $attempts < $maxAttempts;

      $sql = 'UPDATE notification_jobs
              SET status = :status,
                  attempts = :attempts,
                  last_error = :last_error,
                  next_retry_at = :next_retry_at,
                  lock_token = NULL,
                  locked_at = NULL,
                  updated_at = NOW()
              WHERE id = :id';
      if ($token !== null && trim($token) !== '') {
        $sql .= ' AND lock_token = :token';
      }
      $stmt = $this->pdo->prepare($sql);
      $stmt->bindValue(':status', 'failed', PDO::PARAM_STR);
      $stmt->bindValue(':attempts', $attempts, PDO::PARAM_INT);
      $errVal = trim($error);
      if (function_exists('mb_substr')) $errVal = (string) mb_substr($errVal, 0, 2000);
      else $errVal = substr($errVal, 0, 2000);
      $stmt->bindValue(':last_error', $errVal, PDO::PARAM_STR);
      if ($hasMoreRetries && $nextRetryAt !== null && trim($nextRetryAt) !== '') {
        $stmt->bindValue(':next_retry_at', trim($nextRetryAt), PDO::PARAM_STR);
      } else {
        $stmt->bindValue(':next_retry_at', null, PDO::PARAM_NULL);
      }
      $stmt->bindValue(':id', $jobId, PDO::PARAM_INT);
      if ($token !== null && trim($token) !== '') {
        $stmt->bindValue(':token', trim($token), PDO::PARAM_STR);
      }

      $ok = $stmt->execute();
      if ($ok) {
        $this->log(
          (int) ($job['order_id'] ?? 0),
          (string) ($job['type'] ?? ''),
          (string) ($job['recipient'] ?? ''),
          'failed',
          trim($error)
        );
      }
      return $ok;
    } catch (Throwable $e) {
      return false;
    }
  }

  /**
   * @return array<string,mixed>|null
   */
  private function fetchJob(int $jobId, ?string $token = null): ?array
  {
    $sql = 'SELECT id, order_id, type, recipient, attempts, max_attempts
            FROM notification_jobs
            WHERE id = :id';
    $params = array('id' => $jobId);
    if ($token !== null && trim($token) !== '') {
      $sql .= ' AND lock_token = :token';
      $params['token'] = trim($token);
    }
    $sql .= ' LIMIT 1';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $row;
  }

  private function log(int $orderId, string $type, string $recipient, string $status, ?string $error): void
  {
    try {
      $stmt = $this->pdo->prepare(
        'INSERT INTO notification_log (order_id, type, recipient, status, error, created_at)
         VALUES (:order_id, :type, :recipient, :status, :error, NOW())'
      );
      if ($orderId > 0) $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
      else $stmt->bindValue(':order_id', null, PDO::PARAM_NULL);
      $stmt->bindValue(':type', trim($type), PDO::PARAM_STR);
      $stmt->bindValue(':recipient', strtolower(trim($recipient)), PDO::PARAM_STR);
      $stmt->bindValue(':status', trim($status), PDO::PARAM_STR);
      if ($error !== null && trim($error) !== '') {
        $errVal = trim($error);
        if (function_exists('mb_substr')) $errVal = (string) mb_substr($errVal, 0, 2000);
        else $errVal = substr($errVal, 0, 2000);
        $stmt->bindValue(':error', $errVal, PDO::PARAM_STR);
      }
      else $stmt->bindValue(':error', null, PDO::PARAM_NULL);
      $stmt->execute();
    } catch (Throwable $e) {
      // Never break caller.
    }
  }
}
