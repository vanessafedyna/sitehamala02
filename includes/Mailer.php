<?php
declare(strict_types=1);

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Logger.php';

final class Mailer
{
  public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
  {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return false;
    }

    $subject = trim($subject);
    if ($subject === '') $subject = 'Notification';

    $smtpEnvGroups = array(
      'host' => array('SMTP_HOST', 'MAIL_SMTP_HOST'),
      'port' => array('SMTP_PORT', 'MAIL_SMTP_PORT'),
      'secure' => array('SMTP_SECURE', 'MAIL_SMTP_SECURE'),
      'user' => array('SMTP_USER', 'MAIL_SMTP_USER'),
      'pass' => array('SMTP_PASS', 'MAIL_SMTP_PASS'),
      'from_email' => array('SMTP_FROM_EMAIL', 'MAIL_SMTP_FROM_EMAIL'),
      'from_name' => array('SMTP_FROM_NAME', 'MAIL_SMTP_FROM_NAME'),
    );
    $smtpEnvKeys = array_merge(
      $smtpEnvGroups['host'],
      $smtpEnvGroups['port'],
      $smtpEnvGroups['secure'],
      $smtpEnvGroups['user'],
      $smtpEnvGroups['pass'],
      $smtpEnvGroups['from_email'],
      $smtpEnvGroups['from_name']
    );
    $useEnvSmtp = env_has_any($smtpEnvKeys);

    // Mode coherent:
    // - si au moins une variable SMTP env est definie => lecture SMTP uniquement via env
    // - sinon => fallback DB complet (compatibilite)
    if ($useEnvSmtp) {
      $smtpHost = trim((string) env_value($smtpEnvGroups['host'], ''));
      $smtpUser = trim((string) env_value($smtpEnvGroups['user'], ''));
      $smtpPass = (string) env_value($smtpEnvGroups['pass'], '');
      $smtpPort = (int) trim((string) env_value($smtpEnvGroups['port'], '587'));
      $smtpSecure = strtolower(trim((string) env_value($smtpEnvGroups['secure'], '')));
      $fromEmail = trim((string) env_value($smtpEnvGroups['from_email'], ''));
      $fromName = trim((string) env_value($smtpEnvGroups['from_name'], ''));
    } else {
      $smtpHost = trim((string) setting('smtp_host', ''));
      $smtpUser = trim((string) setting('smtp_user', ''));
      $smtpPass = (string) setting('smtp_pass', '');
      $smtpPort = (int) trim((string) setting('smtp_port', '587'));
      $smtpSecure = strtolower(trim((string) setting('smtp_secure', '')));
      $fromEmail = trim((string) setting('smtp_from_email', ''));
      $fromName = trim((string) setting('smtp_from_name', ''));
    }

    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
      $fromEmail = trim((string) setting('shop_email', ''));
    }
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
      $fromEmail = 'no-reply@localhost';
    }

    if ($fromName === '') {
      $fromName = trim((string) setting('shop_name', 'SORA Collection'));
    }

    // 1) SMTP app-level (si configure)
    if ($smtpPort <= 0) $smtpPort = 587;

    if ($smtpSecure === '' || !in_array($smtpSecure, array('none', 'ssl', 'tls'), true)) {
      if ($smtpPort === 465) $smtpSecure = 'ssl';
      elseif ($smtpPort === 587) $smtpSecure = 'tls';
      else $smtpSecure = 'none';
    }

    if ($smtpHost !== '') {
      try {
        if (self::smtpSend(array(
          'host' => $smtpHost,
          'port' => $smtpPort,
          'user' => $smtpUser,
          'pass' => $smtpPass,
          'secure' => $smtpSecure,
          'timeout' => 10,
        ), $to, $subject, $htmlBody, $fromEmail, $fromName)) {
          Logger::info('mail_sent_smtp', array('to' => $to, 'host' => $smtpHost, 'port' => $smtpPort));
          return true;
        }
      } catch (Throwable $e) {
        Logger::warn('mail_smtp_exception', array('to' => $to, 'err' => $e->getMessage()));
      }
    }

    // 2) Fallback native mail()
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . self::formatAddress($fromEmail, $fromName);
    $headers[] = 'Reply-To: ' . $fromEmail;

    try {
      $ok = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
      if (!$ok) {
        Logger::warn('mail_failed', array('to' => $to, 'subject' => $subject));
      } else {
        Logger::info('mail_sent_php_mail', array('to' => $to));
      }
      return (bool) $ok;
    } catch (Throwable $e) {
      Logger::error('mail_exception', array('err' => $e->getMessage(), 'to' => $to));
      return false;
    }
  }

  /**
   * @param array{host:string,port:int,user:string,pass:string,secure:string,timeout:int} $cfg
   */
  private static function smtpSend(array $cfg, string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName): bool
  {
    $host = trim((string) ($cfg['host'] ?? ''));
    $port = (int) ($cfg['port'] ?? 587);
    $user = (string) ($cfg['user'] ?? '');
    $pass = (string) ($cfg['pass'] ?? '');
    $secure = (string) ($cfg['secure'] ?? 'none');
    $timeout = (int) ($cfg['timeout'] ?? 10);

    if ($host === '' || $port <= 0) {
      return false;
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $stream = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!is_resource($stream)) {
      Logger::warn('mail_smtp_connect_failed', array('host' => $host, 'port' => $port, 'err' => $errstr, 'errno' => $errno));
      return false;
    }

    stream_set_timeout($stream, $timeout);

    try {
      self::smtpExpect($stream, 220);
      self::smtpCommand($stream, 'EHLO localhost', array(250));

      if ($secure === 'tls') {
        self::smtpCommand($stream, 'STARTTLS', array(220));
        if (!@stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
          throw new RuntimeException('SMTP STARTTLS failed');
        }
        self::smtpCommand($stream, 'EHLO localhost', array(250));
      }

      if ($user !== '' || $pass !== '') {
        self::smtpCommand($stream, 'AUTH LOGIN', array(334));
        self::smtpCommand($stream, base64_encode($user), array(334));
        self::smtpCommand($stream, base64_encode($pass), array(235));
      }

      self::smtpCommand($stream, 'MAIL FROM:<' . $fromEmail . '>', array(250));
      self::smtpCommand($stream, 'RCPT TO:<' . $to . '>', array(250, 251));
      self::smtpCommand($stream, 'DATA', array(354));

      $headers = array();
      $headers[] = 'Date: ' . date('r');
      $headers[] = 'From: ' . self::formatAddress($fromEmail, $fromName);
      $headers[] = 'To: ' . $to;
      $headers[] = 'Subject: ' . self::smtpHeaderEncode($subject);
      $headers[] = 'MIME-Version: 1.0';
      $headers[] = 'Content-Type: text/html; charset=UTF-8';
      $headers[] = 'Content-Transfer-Encoding: 8bit';

      $data = implode("\r\n", $headers) . "\r\n\r\n" . self::smtpDotStuff($htmlBody) . "\r\n.\r\n";
      fwrite($stream, $data);
      self::smtpExpect($stream, 250);
      self::smtpCommand($stream, 'QUIT', array(221));
      fclose($stream);
      return true;
    } catch (Throwable $e) {
      @fwrite($stream, "QUIT\r\n");
      @fclose($stream);
      Logger::warn('mail_smtp_send_failed', array('host' => $host, 'port' => $port, 'err' => $e->getMessage()));
      return false;
    }
  }

  /**
   * @param int[] $okCodes
   */
  private static function smtpCommand($stream, string $cmd, array $okCodes): string
  {
    fwrite($stream, $cmd . "\r\n");
    return self::smtpExpect($stream, $okCodes);
  }

  /**
   * @param int|int[] $expected
   */
  private static function smtpExpect($stream, $expected): string
  {
    $expectedCodes = is_array($expected) ? $expected : array((int) $expected);
    $resp = '';

    while (($line = fgets($stream, 515)) !== false) {
      $resp .= $line;
      if (strlen($line) < 4) {
        continue;
      }
      // Multi-line replies end with "NNN "
      if ($line[3] === ' ') {
        break;
      }
    }

    $code = (int) substr($resp, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
      throw new RuntimeException('SMTP unexpected response: ' . trim($resp));
    }

    return $resp;
  }

  private static function smtpHeaderEncode(string $value): string
  {
    if ($value === '') return '';
    if (preg_match('/^[\x20-\x7E]+$/', $value)) {
      return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
  }

  private static function smtpDotStuff(string $body): string
  {
    $body = str_replace(array("\r\n", "\r"), "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
      if (isset($line[0]) && $line[0] === '.') {
        $line = '.' . $line;
      }
    }
    unset($line);
    return implode("\r\n", $lines);
  }

  private static function formatAddress(string $email, string $name): string
  {
    $email = trim($email);
    $name = trim($name);
    if ($name === '') return $email;
    $safeName = str_replace(array("\r", "\n"), ' ', $name);
    return sprintf('"%s" <%s>', addslashes($safeName), $email);
  }
}
