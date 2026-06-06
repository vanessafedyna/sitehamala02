<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/functions.php';

final class ProductImageService
{
  private const MAX_BYTES = 20971520; // 20MB
  private const MIN_WIDTH = 32;
  private const MIN_HEIGHT = 32;
  private const MAX_WIDTH = 6000;
  private const MAX_HEIGHT = 6000;
  private const MAX_PIXELS = 24000000; // 24 MP
  /** @var array<string,string> */
  private const ALLOWED_MIME = array(
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  );

  /**
   * Enregistre un upload image dans /uploads/products/ et retourne le chemin relatif.
   *
   * @param array<string,mixed> $file Entrée de $_FILES['image']
   */
  public static function saveUploaded(array $file, int $productId): string
  {
    return self::saveUploadedWithName($file, $productId, "product_{$productId}");
  }

  /**
   * Enregistre un upload image pour un slot (image1/image2/image3) avec un nom explicite.
   *
   * Exemple de nom: product_ML-ABC123_1700000000_1_xxxxxx.jpg
   *
   * @param array<string,mixed> $file Entrée de $_FILES['image1']...
   */
  public static function saveUploadedSlot(array $file, int $productId, string $sku, int $slot): string
  {
    $sku = strtoupper(trim($sku));
    $skuSafe = preg_replace('/[^A-Z0-9-]/', '', $sku) ?: ('P' . (int) $productId);
    $slot = max(1, min(9, (int) $slot));

    $prefix = "product_{$skuSafe}_" . time() . "_{$slot}";
    return self::saveUploadedWithName($file, $productId, $prefix);
  }

  /**
   * @param array<string,mixed> $file
   */
  private static function saveUploadedWithName(array $file, int $productId, string $namePrefix): string
  {
    $productId = (int) $productId;
    if ($productId <= 0) {
      throw new RuntimeException('Produit invalide.');
    }

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
      throw new RuntimeException('Veuillez ajouter une image.');
    }
    if ($err !== UPLOAD_ERR_OK) {
      switch ($err) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
          throw new RuntimeException('Image trop lourde (limite serveur).');
        case UPLOAD_ERR_PARTIAL:
          throw new RuntimeException('Upload incomplet. Veuillez réessayer.');
        case UPLOAD_ERR_NO_TMP_DIR:
          throw new RuntimeException('Upload impossible (dossier temporaire manquant).');
        case UPLOAD_ERR_CANT_WRITE:
          throw new RuntimeException('Upload impossible (écriture sur disque refusée).');
        case UPLOAD_ERR_EXTENSION:
          throw new RuntimeException('Upload bloqué par une extension PHP.');
        default:
          throw new RuntimeException('Upload image invalide.');
      }
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      throw new RuntimeException('Upload image invalide.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > self::MAX_BYTES) {
      throw new RuntimeException('Image trop lourde (max 20MB).');
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

    $dirFs = base_path('uploads/products');
    if (!is_dir($dirFs)) {
      if (!@mkdir($dirFs, 0775, true) && !is_dir($dirFs)) {
        throw new RuntimeException('Dossier upload indisponible.');
      }
    }

    $rand = bin2hex(random_bytes(6));
    $prefix = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($namePrefix)) ?: ("product_{$productId}");
    $filename = "{$prefix}_{$rand}.{$ext}";

    $targetFs = rtrim($dirFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $targetFs)) {
      throw new RuntimeException('Impossible d\'enregistrer l\'image (vérifiez les droits du dossier uploads/products).');
    }

    @chmod($targetFs, 0644);
    return 'uploads/products/' . $filename;
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
      throw new RuntimeException('Image trop grande (max 6000x6000).');
    }
    if (((float) $w * (float) $h) > self::MAX_PIXELS) {
      throw new RuntimeException('Image trop grande (nombre de pixels).');
    }
  }

  public static function toUrl(string $path): string
  {
    static $cache = array();
    static $placeholder = null;

    if ($placeholder === null) {
      $placeholder = base_url('assets/images/placeholders/product-placeholder.svg');
    }

    $path = trim($path);
    if ($path === '') return '';

    if (isset($cache[$path])) {
      return $cache[$path];
    }

    $path = str_replace('\\', '/', $path);

    // Si la DB stocke un chemin complet, extraire le relatif à partir de /uploads/products/
    $pos = stripos($path, 'uploads/products/');
    if ($pos !== false) {
      $path = substr($path, $pos);
    } elseif (preg_match('/^[a-zA-Z]:\\//', $path)) {
      // Chemin Windows absolu => basename
      $path = basename($path);
    }

    // Autoriser URL absolue / chemin absolu serveur
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || $path[0] === '/') {
      $cache[$path] = $path;
      return $path;
    }

    // Si on a juste un nom de fichier, supposer /uploads/products/
    if (strpos($path, '/') === false) {
      $path = 'uploads/products/' . ltrim($path, '/');
    }

    // Fallback placeholder si fichier local manquant
    $rel = ltrim($path, '/');
    if (stripos($rel, 'uploads/products/') === 0) {
      $fs = base_path($rel);
      if (!is_file($fs)) {
        $cache[$path] = $placeholder;
        return $placeholder;
      }
    }

    $resolved = base_url($rel);
    $cache[$path] = $resolved;
    return $resolved;
  }

  /**
   * Supprime une image uniquement si elle est dans /uploads/products/.
   */
  public static function deleteIfLocal(?string $relativePath): void
  {
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '') {
      return;
    }

    $base = realpath(base_path('uploads/products'));
    if ($base === false) {
      return;
    }

    $target = realpath(base_path($relativePath));
    if ($target === false) {
      return;
    }

    $baseNorm = rtrim(str_replace('\\', '/', (string) $base), '/') . '/';
    $targetNorm = str_replace('\\', '/', (string) $target);

    if (strpos($targetNorm, $baseNorm) !== 0) {
      return;
    }

    @unlink($target);
  }
}
