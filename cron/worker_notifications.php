<?php
declare(strict_types=1);

// Worker notifications (queue + retry)
// Execution locale (Windows):
//   php cron/worker_notifications.php
// Execution cron (Linux):
//   */5 * * * * /usr/bin/php /path/to/project/cron/worker_notifications.php

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/OrderModel.php';
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/Mailer.php';
require_once __DIR__ . '/../includes/NotificationService.php';
require_once __DIR__ . '/../lib/NotificationQueue.php';

function worker_runtime_dir(): string
{
  $dir = __DIR__ . '/../app/logs/cron';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  if (!is_dir($dir) || !is_writable($dir)) {
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sitehamala-cron';
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }
  }
  return $dir;
}

function worker_runtime_paths(): array
{
  $dir = worker_runtime_dir();
  return array(
    'dir' => $dir,
    'lock' => $dir . DIRECTORY_SEPARATOR . 'worker_notifications.lock',
    'heartbeat' => $dir . DIRECTORY_SEPARATOR . 'worker_notifications.heartbeat.json',
  );
}

function worker_write_heartbeat(array $data): void
{
  $paths = worker_runtime_paths();
  $payload = array_merge(
    array(
      'script' => 'cron/worker_notifications.php',
      'hostname' => function_exists('gethostname') ? (gethostname() ?: null) : null,
      'pid' => function_exists('getmypid') ? getmypid() : null,
      'updated_at' => gmdate('c'),
    ),
    $data
  );

  @file_put_contents(
    $paths['heartbeat'],
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    LOCK_EX
  );
}

/**
 * @param int $nextAttempt attempt number AFTER increment (1..max)
 */
function notification_next_retry_at(int $nextAttempt): ?string
{
  $nextAttempt = max(1, $nextAttempt);
  $minutes = null;

  if ($nextAttempt === 1) $minutes = 5;
  elseif ($nextAttempt === 2) $minutes = 15;
  elseif ($nextAttempt === 3) $minutes = 60;
  elseif ($nextAttempt === 4) $minutes = 360;
  elseif ($nextAttempt === 5) $minutes = 1440;

  if ($minutes === null) return null;
  return (new DateTimeImmutable('now +' . $minutes . ' minutes'))->format('Y-m-d H:i:s');
}

function worker_parse_options(array $argv): array
{
  $limit = 20;
  $dryRun = false;

  foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    $arg = trim((string) $arg);
    if ($arg === '--dry-run') {
      $dryRun = true;
      continue;
    }
    if (strpos($arg, '--limit=') === 0) {
      $raw = (int) substr($arg, 8);
      if ($raw > 0) {
        $limit = max(1, min(200, $raw));
      }
      continue;
    }
  }

  return array('limit' => $limit, 'dry_run' => $dryRun);
}

function worker_token(): string
{
  try {
    return bin2hex(random_bytes(16));
  } catch (Throwable $e) {
    return sha1(uniqid('worker_', true));
  }
}

function worker_job_channel(PDO $pdo, int $jobId): string
{
  $jobId = (int) $jobId;
  if ($jobId <= 0) return 'email';

  try {
    $stmt = $pdo->prepare('SELECT channel FROM notification_jobs WHERE id = :id LIMIT 1');
    $stmt->execute(array('id' => $jobId));
    $channel = strtolower(trim((string) ($stmt->fetchColumn() ?: 'email')));
    if (!in_array($channel, array('email', 'whatsapp'), true)) {
      return 'email';
    }
    return $channel;
  } catch (Throwable $e) {
    return 'email';
  }
}

try {
  $opts = worker_parse_options($argv ?? array());
  $limit = (int) ($opts['limit'] ?? 20);
  $dryRun = (bool) ($opts['dry_run'] ?? false);
  $token = worker_token();
  $paths = worker_runtime_paths();
  $lockHandle = @fopen($paths['lock'], 'c+');
  if (!is_resource($lockHandle)) {
    throw new RuntimeException('Unable to open worker lock file.');
  }

  if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    worker_write_heartbeat(array(
      'status' => 'skipped_locked',
      'message' => 'Another worker_notifications instance is already running.',
      'dry_run' => $dryRun,
      'limit' => $limit,
    ));
    exit(0);
  }

  @ftruncate($lockHandle, 0);
  @fwrite($lockHandle, (string) (function_exists('getmypid') ? getmypid() : 'unknown'));
  @fflush($lockHandle);

  $processed = 0;
  $sent = 0;
  $failed = 0;
  $invalid = 0;
  $claimed = 0;
  $startedAt = gmdate('c');
  worker_write_heartbeat(array(
    'status' => 'running',
    'started_at' => $startedAt,
    'dry_run' => $dryRun,
    'limit' => $limit,
  ));

  $pdo = db();
  $queue = new NotificationQueue($pdo);
  $jobs = $queue->claimDueJobs($limit, $token, 300);
  $claimed = count($jobs);
  worker_write_heartbeat(array(
    'status' => 'running',
    'started_at' => $startedAt,
    'dry_run' => $dryRun,
    'limit' => $limit,
    'claimed_jobs' => $claimed,
    'processed_jobs' => $processed,
    'sent_jobs' => $sent,
    'failed_jobs' => $failed,
    'invalid_jobs' => $invalid,
  ));

  foreach ($jobs as $job) {
    $jobId = (int) ($job['id'] ?? 0);
    if ($jobId <= 0) {
      continue;
    }

    try {
      $orderId = (int) ($job['order_id'] ?? 0);
      $type = trim((string) ($job['type'] ?? ''));
      $recipient = trim((string) ($job['recipient'] ?? ''));
      $attempts = (int) ($job['attempts'] ?? 0);
      $maxAttempts = max(1, (int) ($job['max_attempts'] ?? 5));
      $payloadRaw = (string) ($job['payload_json'] ?? '');
      $channel = worker_job_channel($pdo, $jobId);

      if ($orderId <= 0 || $type === '' || $recipient === '') {
        if ($dryRun) {
          echo '[DRY] job#' . $jobId . ' type=' . $type . ' to=' . $recipient . ' attempts=' . $attempts . PHP_EOL;
          $processed++;
          continue;
        }
        $nextAttempt = $attempts + 1;
        $queue->markFailed($jobId, 'Job invalide (order/type/recipient).', notification_next_retry_at($nextAttempt), $token);
        Logger::error('worker_job_failed', array('job_id' => $jobId, 'order_id' => $orderId, 'type' => $type, 'recipient' => $recipient, 'reason' => 'invalid_job'));
        $processed++;
        $failed++;
        $invalid++;
        continue;
      }

      $payload = array();
      if ($payloadRaw !== '') {
        try {
          $decoded = json_decode($payloadRaw, true, 512, JSON_THROW_ON_ERROR);
          if (is_array($decoded)) $payload = $decoded;
        } catch (Throwable $e) {
          $payload = array();
        }
      }

      if ($dryRun) {
        echo '[DRY] job#' . $jobId . ' type=' . $type . ' to=' . $recipient . ' attempts=' . $attempts . PHP_EOL;
        $processed++;
        continue;
      }

      if ($channel === 'whatsapp') {
        $queue->markFailed($jobId, 'whatsapp_api_disabled', null, $token);
        Logger::info('worker_job_skipped', array(
          'job_id' => $jobId,
          'order_id' => $orderId,
          'type' => $type,
          'channel' => $channel,
          'recipient' => $recipient,
          'reason' => 'whatsapp_api_disabled',
        ));
        $failed++;
        $processed++;
        worker_write_heartbeat(array(
          'status' => 'running',
          'started_at' => $startedAt,
          'dry_run' => $dryRun,
          'limit' => $limit,
          'claimed_jobs' => $claimed,
          'processed_jobs' => $processed,
          'sent_jobs' => $sent,
          'failed_jobs' => $failed,
          'invalid_jobs' => $invalid,
          'last_job_id' => $jobId,
        ));
        continue;
      }

      $mail = NotificationService::buildEmailForJob($type, $orderId, $payload);
      if (!$mail || !is_array($mail)) {
        $nextAttempt = $attempts + 1;
        $queue->markFailed($jobId, 'Impossible de construire le message.', notification_next_retry_at($nextAttempt), $token);
        Logger::error('worker_job_failed', array('job_id' => $jobId, 'order_id' => $orderId, 'type' => $type, 'recipient' => $recipient, 'reason' => 'build_failed'));
        $processed++;
        $failed++;
        continue;
      }

      $subject = trim((string) ($mail['subject'] ?? ''));
      $html = (string) ($mail['html'] ?? '');
      $text = isset($mail['text']) ? (string) $mail['text'] : null;
      if ($subject === '' || $html === '') {
        $nextAttempt = $attempts + 1;
        $queue->markFailed($jobId, 'Message vide (subject/html).', notification_next_retry_at($nextAttempt), $token);
        Logger::error('worker_job_failed', array('job_id' => $jobId, 'order_id' => $orderId, 'type' => $type, 'recipient' => $recipient, 'reason' => 'empty_message'));
        $processed++;
        $failed++;
        continue;
      }

      $ok = Mailer::send($recipient, $subject, $html, $text);
      if ($ok) {
        $queue->markSent($jobId, $token);
        Logger::info('worker_job_sent', array('job_id' => $jobId, 'order_id' => $orderId, 'type' => $type, 'recipient' => $recipient));
        $sent++;
      } else {
        $nextAttempt = $attempts + 1;
        $nextRetryAt = ($nextAttempt < $maxAttempts) ? notification_next_retry_at($nextAttempt) : null;
        $queue->markFailed($jobId, 'Mailer::send returned false', $nextRetryAt, $token);
        Logger::error('worker_job_failed', array('job_id' => $jobId, 'order_id' => $orderId, 'type' => $type, 'recipient' => $recipient, 'reason' => 'mailer_false'));
        $failed++;
      }
      $processed++;
      worker_write_heartbeat(array(
        'status' => 'running',
        'started_at' => $startedAt,
        'dry_run' => $dryRun,
        'limit' => $limit,
        'claimed_jobs' => $claimed,
        'processed_jobs' => $processed,
        'sent_jobs' => $sent,
        'failed_jobs' => $failed,
        'invalid_jobs' => $invalid,
        'last_job_id' => $jobId,
      ));
    } catch (Throwable $e) {
      try {
        $attempts = (int) ($job['attempts'] ?? 0);
        $nextAttempt = $attempts + 1;
        $queue->markFailed($jobId, $e->getMessage(), notification_next_retry_at($nextAttempt), $token);
        Logger::error('worker_job_failed', array('job_id' => $jobId, 'error' => $e->getMessage()));
        $processed++;
        $failed++;
        worker_write_heartbeat(array(
          'status' => 'running',
          'started_at' => $startedAt,
          'dry_run' => $dryRun,
          'limit' => $limit,
          'claimed_jobs' => $claimed,
          'processed_jobs' => $processed,
          'sent_jobs' => $sent,
          'failed_jobs' => $failed,
          'invalid_jobs' => $invalid,
          'last_job_id' => $jobId,
          'last_error' => $e->getMessage(),
        ));
      } catch (Throwable $inner) {
        // Never stop worker.
      }
    }
  }

  worker_write_heartbeat(array(
    'status' => 'ok',
    'started_at' => $startedAt,
    'finished_at' => gmdate('c'),
    'dry_run' => $dryRun,
    'limit' => $limit,
    'claimed_jobs' => $claimed,
    'processed_jobs' => $processed,
    'sent_jobs' => $sent,
    'failed_jobs' => $failed,
    'invalid_jobs' => $invalid,
  ));
} catch (Throwable $e) {
  worker_write_heartbeat(array(
    'status' => 'fatal',
    'finished_at' => gmdate('c'),
    'last_error' => $e->getMessage(),
  ));
  Logger::error('worker_fatal_error', array('error' => $e->getMessage()));
  exit(1);
}

exit(0);
