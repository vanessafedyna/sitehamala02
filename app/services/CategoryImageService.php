<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';

final class CategoryImageService
{
  private const MAX_BYTES = 2097152; // 2MB
  private const MIN_WIDTH = 32;
  private const MIN_HEIGHT = 32;
  private const MAX_WIDTH = 5000;
  private const MAX_HEIGHT = 5000;
  private const MAX_PIXELS = 16000000; // 16 MP
  /** @var array<string,string> */
  private const ALLOWED_MIME = array(
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  );

  /**
   * Enregistre un upload image dans /uploads/categories/ et retourne le chemin relatif.
   *
   * @param array<string,mixed> $file Entrée de $_FILES['banner_image']
   */
  public static function saveUploaded(array $file, string $namePrefix = 'category'): string
  {
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
      throw new RuntimeException('Veuillez ajouter une image.');
    }
    if ($err !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Upload image invalide.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new RuntimeException('Upload image invalide.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > self::MAX_BYTES) {
      throw new RuntimeException('Image trop lourde (max 2MB).');
    }

    $origName = strtolower((string) ($file['name'] ?? ''));
    if ($origName !== '' && preg_match('/[\/\\\x00-\x1F]/', $origName)) {
      throw new RuntimeException('Nom de fichier refusé.');
    }
    if ($origName !== '' && preg_match('/\.(php\d*|phtml|phar|pht|jsp|asp|aspx|cgi|pl|py|sh|bat|cmd|exe|com)(\.|$)/', $origName)) {
      throw new RuntimeException('Nom de fichier refusé.');
    }

    if (!class_exists(finfo::class)) {
      throw new RuntimeException('Extension fileinfo manquante (PHP).');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) ($finfo->file($tmp) ?: '');
    if (!isset(self::ALLOWED_MIME[$mime])) {
      throw new RuntimeException('Format image non autorisé (jpg/png/webp).');
    }

    self::assertDimensions($tmp);

    $ext = self::ALLOWED_MIME[$mime];

    $dirFs = base_path('uploads/categories');
    if (!is_dir($dirFs)) {
      if (!@mkdir($dirFs, 0775, true) && !is_dir($dirFs)) {
        throw new RuntimeException('Dossier upload indisponible.');
      }
    }

    $rand = bin2hex(random_bytes(6));
    $prefix = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($namePrefix)) ?: 'category';
    $filename = "{$prefix}_" . time() . "_{$rand}.{$ext}";

    $targetFs = rtrim($dirFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $targetFs)) {
      throw new RuntimeException('Impossible d\'enregistrer l\'image (vérifiez les droits uploads/categories).');
    }

    @chmod($targetFs, 0644);
    return 'uploads/categories/' . $filename;
  }

  private static function assertDimensions(string $tmp): void
  {
    $meta = @getimagesize($tmp);
    if (!is_array($meta) || count($meta) < 2) {
      throw new RuntimeException('Fichier image invalide.');
    }

    $w = (int) ($meta[0] ?? 0);
    $h = (int) ($meta[1] ?? 0);
    if ($w < self::MIN_WIDTH || $h < self::MIN_HEIGHT) {
      throw new RuntimeException('Image trop petite.');
    }
    if ($w > self::MAX_WIDTH || $h > self::MAX_HEIGHT) {
      throw new RuntimeException('Image trop grande (max 5000x5000).');
    }
    if (((float) $w * (float) $h) > self::MAX_PIXELS) {
      throw new RuntimeException('Image trop grande (nombre de pixels).');
    }
  }

  public static function toUrl(string $path): string
  {
    $path = trim($path);
    if ($path === '') return '';

    $path = str_replace('\\', '/', $path);

    $pos = stripos($path, 'uploads/categories/');
    if ($pos !== false) {
      $path = substr($path, $pos);
    } elseif (preg_match('/^[a-zA-Z]:\\//', $path)) {
      $path = basename($path);
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || $path[0] === '/') {
      return $path;
    }
    if (strpos($path, '/') === false) {
      $path = 'uploads/categories/' . ltrim($path, '/');
    }

    $rel = ltrim($path, '/');
    if (stripos($rel, 'uploads/categories/') === 0) {
      $fs = base_path($rel);
      if (!is_file($fs)) {
        return '';
      }
    }
    return base_url($rel);
  }

  public static function deleteIfLocal(?string $relativePath): void
  {
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') return;

    $base = realpath(base_path('uploads/categories'));
    if ($base === false) return;

    $target = realpath(base_path($relativePath));
    if ($target === false) return;

    $baseNorm = rtrim(str_replace('\\', '/', (string) $base), '/') . '/';
    $targetNorm = str_replace('\\', '/', (string) $target);
    if (strpos($targetNorm, $baseNorm) !== 0) return;

    @unlink($target);
  }
}
