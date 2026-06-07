<?php
declare(strict_types=1);

/* Journal d'audit admin */

final class AdminAuditService
{
  public static function log(PDO $pdo, ?int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = array()): void
  {
    $action = trim($action);
    if ($action === '') {
      return;
    }

    $adminId = $adminId !== null ? (int) $adminId : null;
    if ($adminId !== null && $adminId <= 0) {
      $adminId = null;
    }

    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
    if ($ip !== null) {
      $ip = trim($ip);
      if ($ip === '') $ip = null;
    }

    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;
    if ($ua !== null) {
      $ua = trim($ua);
      if ($ua === '') $ua = null;
      if ($ua !== null && function_exists('mb_strlen') && mb_strlen($ua) > 255) {
        $ua = (string) mb_substr($ua, 0, 255);
      } elseif ($ua !== null && strlen($ua) > 255) {
        $ua = substr($ua, 0, 255);
      }
    }

    try {
      $cols = function_exists('db_table_columns') ? db_table_columns($pdo, 'admin_audit_logs') : array();
      $hasMetadata = in_array('metadata', $cols, true);
      $metadataJson = null;
      if ($hasMetadata && $metadata !== array()) {
        try {
          $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          if (!is_string($metadataJson) || $metadataJson === '') {
            $metadataJson = null;
          }
        } catch (Throwable $e) {
          $metadataJson = null;
        }
      }

      $sql = 'INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, ip, user_agent';
      if ($hasMetadata) {
        $sql .= ', metadata';
      }
      $sql .= ') VALUES (:admin_id, :action, :entity_type, :entity_id, :ip, :user_agent';
      if ($hasMetadata) {
        $sql .= ', :metadata';
      }
      $sql .= ')';

      $stmt = $pdo->prepare($sql);

      if ($adminId === null) $stmt->bindValue(':admin_id', null, PDO::PARAM_NULL);
      else $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);

      $stmt->bindValue(':action', $action, PDO::PARAM_STR);

      $entityType = $entityType !== null ? trim((string) $entityType) : '';
      if ($entityType === '') $stmt->bindValue(':entity_type', null, PDO::PARAM_NULL);
      else $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);

      if ($entityId === null || (int) $entityId <= 0) $stmt->bindValue(':entity_id', null, PDO::PARAM_NULL);
      else $stmt->bindValue(':entity_id', (int) $entityId, PDO::PARAM_INT);

      if ($ip === null) $stmt->bindValue(':ip', null, PDO::PARAM_NULL);
      else $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);

      if ($ua === null) $stmt->bindValue(':user_agent', null, PDO::PARAM_NULL);
      else $stmt->bindValue(':user_agent', $ua, PDO::PARAM_STR);

      if ($hasMetadata) {
        if ($metadataJson === null) $stmt->bindValue(':metadata', null, PDO::PARAM_NULL);
        else $stmt->bindValue(':metadata', $metadataJson, PDO::PARAM_STR);
      }

      $stmt->execute();
    } catch (Throwable $e) {
      // Ne jamais casser l'app si la table n'existe pas ou si la DB est en erreur.
      return;
    }
  }
}

