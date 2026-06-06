<?php
declare(strict_types=1);

final class Logger
{
  private static function logPath(): string
  {
    // Outside public directory (repo internal)
    return __DIR__ . '/../app/logs/app.log';
  }

  private static function write(string $level, string $message, array $context = array()): void
  {
    $level = strtoupper(trim($level));
    $message = trim($message);
    if ($message === '') return;

    $dir = dirname(self::logPath());
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }

    $ctx = '';
    if ($context) {
      try {
        $ctx = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      } catch (Throwable $e) {
        $ctx = '';
      }
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $line = sprintf(
      "[%s] %s %s ip=%s%s\n",
      date('Y-m-d H:i:s'),
      $level,
      $message,
      $ip !== '' ? $ip : '-',
      $ctx
    );

    @file_put_contents(self::logPath(), $line, FILE_APPEND);
  }

  public static function info(string $message, array $context = array()): void
  {
    self::write('INFO', $message, $context);
  }

  public static function warn(string $message, array $context = array()): void
  {
    self::write('WARN', $message, $context);
  }

  public static function error(string $message, array $context = array()): void
  {
    self::write('ERROR', $message, $context);
  }
}

