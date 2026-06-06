<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once dirname(__DIR__, 2) . '/includes/Logger.php';

final class PasswordResetOtpService
{
  private const OTP_LENGTH = 6;
  private const OTP_TTL_MINUTES = 10;
  private const MAX_ATTEMPTS = 5;
  private const RESET_SESSION_TTL = 900; // 15 min
  private const SESSION_VERIFIED_KEY = 'password_reset_verified';

  /**
   * Demande de reset par numero.
   * Retourne toujours un message neutre cote metier, sauf erreur technique.
   *
   * @return array{ok:bool,message:string}
   */
  public static function requestOtp(string $phoneRaw): array
  {
    self::ensureSessionStarted();

    $phone = self::normalizePhoneStorage($phoneRaw);
    if (!self::isValidPhoneForReset($phone)) {
      return array('ok' => false, 'message' => 'Numero de telephone invalide.');
    }

    try {
      $pdo = db();
      $user = self::findActiveUserByPhone($pdo, $phone);
      if (!$user) {
        // Reponse neutre (pas de fuite d'information).
        return array('ok' => true, 'message' => 'Si ce numero existe, un code a ete genere.');
      }

      $userId = (int) ($user['id'] ?? 0);
      if ($userId <= 0) {
        return array('ok' => true, 'message' => 'Si ce numero existe, un code a ete genere.');
      }

      // Invalider tout OTP precedent encore actif.
      $invalidate = $pdo->prepare(
        'UPDATE password_resets_otp
         SET used = 1, used_at = NOW()
         WHERE user_id = :uid AND used = 0'
      );
      $invalidate->execute(array('uid' => $userId));

      $otp = self::generateOtpCode();
      $otpHash = self::hashOtp($otp);
      $expiresAt = (new DateTimeImmutable('now +' . self::OTP_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

      $insert = $pdo->prepare(
        'INSERT INTO password_resets_otp (user_id, phone, otp_code, expires_at, used, attempts, created_at)
         VALUES (:uid, :phone, :otp, :expires_at, 0, 0, NOW())'
      );
      $insert->bindValue(':uid', $userId, PDO::PARAM_INT);
      $insert->bindValue(':phone', $phone, PDO::PARAM_STR);
      $insert->bindValue(':otp', $otpHash, PDO::PARAM_STR);
      $insert->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
      $insert->execute();

      $resetId = (int) $pdo->lastInsertId();
      self::sendOtpPlaceholder($phone, $otp, array('reset_id' => $resetId, 'user_id' => $userId));

      return array('ok' => true, 'message' => 'Si ce numero existe, un code a ete genere.');
    } catch (Throwable $e) {
      Logger::error('password_reset_request_failed', array('error' => $e->getMessage()));
      return array('ok' => false, 'message' => 'Impossible de traiter la demande pour le moment.');
    }
  }

  /**
   * Verification du code OTP.
   * @return array{ok:bool,message:string}
   */
  public static function verifyOtp(string $phoneRaw, string $otpRaw): array
  {
    self::ensureSessionStarted();

    $phone = self::normalizePhoneStorage($phoneRaw);
    $otp = trim($otpRaw);

    if (!self::isValidPhoneForReset($phone) || !preg_match('/^\d{6}$/', $otp)) {
      return array('ok' => false, 'message' => 'Code invalide ou expire.');
    }

    try {
      $pdo = db();
      $stmt = $pdo->prepare(
        'SELECT id, user_id, otp_code, expires_at, attempts
         FROM password_resets_otp
         WHERE phone = :phone AND used = 0
         ORDER BY id DESC
         LIMIT 1'
      );
      $stmt->execute(array('phone' => $phone));
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

      if (!$row) {
        return array('ok' => false, 'message' => 'Code invalide ou expire.');
      }

      $resetId = (int) ($row['id'] ?? 0);
      $userId = (int) ($row['user_id'] ?? 0);
      $attempts = (int) ($row['attempts'] ?? 0);
      $expiresAt = (string) ($row['expires_at'] ?? '');
      $otpHashStored = (string) ($row['otp_code'] ?? '');

      if ($resetId <= 0 || $userId <= 0 || $expiresAt === '' || $otpHashStored === '') {
        return array('ok' => false, 'message' => 'Code invalide ou expire.');
      }

      $isExpired = (strtotime($expiresAt) ?: 0) < time();
      if ($isExpired || $attempts >= self::MAX_ATTEMPTS) {
        self::markOtpUsed($pdo, $resetId);
        return array('ok' => false, 'message' => 'Code invalide ou expire.');
      }

      $isValid = self::verifyOtpHash($otp, $otpHashStored);
      if (!$isValid) {
        $newAttempts = $attempts + 1;
        $upd = $pdo->prepare(
          'UPDATE password_resets_otp
           SET attempts = :attempts,
               used = CASE WHEN :attempts >= :max_attempts THEN 1 ELSE used END,
               used_at = CASE WHEN :attempts >= :max_attempts THEN NOW() ELSE used_at END
           WHERE id = :id
           LIMIT 1'
        );
        $upd->execute(array(
          'attempts' => $newAttempts,
          'max_attempts' => self::MAX_ATTEMPTS,
          'id' => $resetId,
        ));
        return array('ok' => false, 'message' => 'Code invalide ou expire.');
      }

      // OTP consomme immediatement apres verification.
      self::markOtpUsed($pdo, $resetId);

      $_SESSION[self::SESSION_VERIFIED_KEY] = array(
        'user_id' => $userId,
        'phone' => $phone,
        'reset_id' => $resetId,
        'expires_at' => time() + self::RESET_SESSION_TTL,
      );
      session_regenerate_id(true);

      return array('ok' => true, 'message' => 'Code valide.');
    } catch (Throwable $e) {
      Logger::error('password_reset_verify_failed', array('error' => $e->getMessage()));
      return array('ok' => false, 'message' => 'Code invalide ou expire.');
    }
  }

  /**
   * Mise a jour securisee du mot de passe apres verification OTP.
   * @return array{ok:bool,message:string}
   */
  public static function resetPassword(string $password, string $confirmPassword): array
  {
    self::ensureSessionStarted();

    $context = self::verifiedContext();
    if (!$context) {
      return array('ok' => false, 'message' => 'Session de reinitialisation expiree. Recommencez.');
    }

    if ($password === '' || $confirmPassword === '') {
      return array('ok' => false, 'message' => 'Veuillez renseigner les deux champs mot de passe.');
    }
    if ($password !== $confirmPassword) {
      return array('ok' => false, 'message' => 'Les mots de passe ne correspondent pas.');
    }
    if (!password_meets_policy($password)) {
      return array('ok' => false, 'message' => password_policy_message());
    }

    try {
      $pdo = db();
      $cols = self::usersColumns($pdo);
      $passCol = in_array('password_hash', $cols, true) ? 'password_hash'
        : (in_array('password', $cols, true) ? 'password' : '');
      if ($passCol === '') {
        return array('ok' => false, 'message' => 'Configuration users invalide.');
      }

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $upd = $pdo->prepare('UPDATE users SET ' . $passCol . ' = :hash WHERE id = :id LIMIT 1');
      $upd->execute(array(
        'hash' => $hash,
        'id' => (int) $context['user_id'],
      ));

      // Securite: invalider tout OTP restant pour ce user.
      $invalidate = $pdo->prepare(
        'UPDATE password_resets_otp
         SET used = 1, used_at = NOW()
         WHERE user_id = :uid AND used = 0'
      );
      $invalidate->execute(array('uid' => (int) $context['user_id']));

      unset($_SESSION[self::SESSION_VERIFIED_KEY], $_SESSION['password_reset_last_otp']);
      session_regenerate_id(true);

      return array('ok' => true, 'message' => 'Mot de passe reinitialise avec succes.');
    } catch (Throwable $e) {
      Logger::error('password_reset_apply_failed', array('error' => $e->getMessage()));
      return array('ok' => false, 'message' => 'Impossible de reinitialiser le mot de passe.');
    }
  }

  public static function hasVerifiedContext(): bool
  {
    self::ensureSessionStarted();
    return self::verifiedContext() !== null;
  }

  /**
   * Point d'extension WhatsApp Business (placeholder uniquement).
   * Remplacer cette methode plus tard par le connecteur officiel WhatsApp API.
   */
  public static function sendOtpPlaceholder(string $phone, string $otp, array $context = array()): bool
  {
    self::ensureSessionStarted();

    $payload = array(
      'phone' => self::maskPhone($phone),
      'context' => $context,
      'note' => 'Brancher ici WhatsApp Business API plus tard.',
    );

    Logger::info('password_reset_otp_placeholder', $payload);

    // En dev local uniquement: garder le code en session pour test manuel.
    if (defined('APP_DEBUG') && APP_DEBUG === true && function_exists('is_debug_allowed') && is_debug_allowed()) {
      $_SESSION['password_reset_last_otp'] = array(
        'phone' => $phone,
        'otp' => $otp,
        'created_at' => date('Y-m-d H:i:s'),
      );
    }

    return true;
  }

  private static function markOtpUsed(PDO $pdo, int $resetId): void
  {
    $upd = $pdo->prepare('UPDATE password_resets_otp SET used = 1, used_at = NOW() WHERE id = :id LIMIT 1');
    $upd->execute(array('id' => $resetId));
  }

  /**
   * @return array{user_id:int,phone:string,reset_id:int,expires_at:int}|null
   */
  private static function verifiedContext(): ?array
  {
    $ctx = $_SESSION[self::SESSION_VERIFIED_KEY] ?? null;
    if (!is_array($ctx)) {
      return null;
    }
    $expiresAt = (int) ($ctx['expires_at'] ?? 0);
    if ($expiresAt <= time()) {
      unset($_SESSION[self::SESSION_VERIFIED_KEY]);
      return null;
    }
    $userId = (int) ($ctx['user_id'] ?? 0);
    $phone = (string) ($ctx['phone'] ?? '');
    $resetId = (int) ($ctx['reset_id'] ?? 0);
    if ($userId <= 0 || $phone === '' || $resetId <= 0) {
      unset($_SESSION[self::SESSION_VERIFIED_KEY]);
      return null;
    }
    return array(
      'user_id' => $userId,
      'phone' => $phone,
      'reset_id' => $resetId,
      'expires_at' => $expiresAt,
    );
  }

  /**
   * @return array<string,mixed>|null
   */
  private static function findActiveUserByPhone(PDO $pdo, string $phone): ?array
  {
    $cols = self::usersColumns($pdo);
    if (!in_array('phone', $cols, true)) {
      return null;
    }

    $select = array('id', 'phone');
    $hasDeletedAt = in_array('deleted_at', $cols, true);
    if ($hasDeletedAt) {
      $select[] = 'deleted_at';
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE phone = :phone LIMIT 1');
    $stmt->execute(array('phone' => $phone));
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) {
      return null;
    }
    if ($hasDeletedAt && !empty((string) ($user['deleted_at'] ?? ''))) {
      return null;
    }

    return $user;
  }

  /**
   * @return string[]
   */
  private static function usersColumns(PDO $pdo): array
  {
    if (function_exists('db_table_columns')) {
      return db_table_columns($pdo, 'users');
    }
    $rows = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC) ?: array();
    $out = array();
    foreach ($rows as $row) {
      $f = (string) ($row['Field'] ?? '');
      if ($f !== '') $out[] = $f;
    }
    return $out;
  }

  private static function ensureSessionStarted(): void
  {
    if (function_exists('auth_start')) {
      auth_start();
      return;
    }
    if (function_exists('session_start_secure')) {
      session_start_secure();
      return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
      @session_start();
    }
  }

  private static function generateOtpCode(): string
  {
    $min = 100000;
    $max = 999999;
    return (string) random_int($min, $max);
  }

  private static function hashOtp(string $otp): string
  {
    return password_hash($otp, PASSWORD_DEFAULT);
  }

  private static function verifyOtpHash(string $otp, string $stored): bool
  {
    // Nouveau format (recommande)
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
      return password_verify($otp, $stored);
    }
    // Compat descendante: anciens OTP SHA-256
    return hash_equals($stored, hash('sha256', $otp));
  }

  public static function normalizePhoneStorage(string $raw): string
  {
    $raw = trim($raw);
    $digits = preg_replace('/\D+/', '', $raw);
    $digits = (string) $digits;
    if ($digits === '') return '';
    if (str_starts_with($digits, '00') && strlen($digits) > 2) {
      $digits = substr($digits, 2);
    }

    $hasPlus = str_starts_with($raw, '+');
    $has00 = str_starts_with($raw, '00');
    if ($hasPlus || $has00) {
      return '+' . $digits;
    }
    if (strlen($digits) >= 8 && strlen($digits) <= 10) {
      return '+223' . $digits;
    }
    return '+' . $digits;
  }

  public static function rateLimitPhoneKey(string $raw): string
  {
    return self::normalizePhoneStorage($raw);
  }

  private static function isValidPhoneForReset(string $phone): bool
  {
    if ($phone === '') return false;
    $digits = preg_replace('/\D+/', '', $phone);
    $digits = (string) $digits;
    return ($digits !== '' && strlen($digits) >= 8 && strlen($digits) <= 15);
  }

  private static function maskPhone(string $phone): string
  {
    $digits = preg_replace('/\D+/', '', $phone);
    $digits = (string) $digits;
    if ($digits === '') return '***';
    if (strlen($digits) <= 4) return str_repeat('*', strlen($digits));
    return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
  }
}
