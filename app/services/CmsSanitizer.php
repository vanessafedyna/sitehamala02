<?php
declare(strict_types=1);

final class CmsSanitizer
{
  private const LEGACY_EMAIL_MAP = array(
    'support@sora-collection.com' => 'support@soracollectionmali.com',
    'support@malishop.test' => 'support@soracollectionmali.com',
    'contact@sora-collection.com' => 'contact@soracollectionmali.com',
    'admin@malishop.com' => 'admin@soracollectionmali.com',
  );

  /**
   * Whitelist simple: pas de JS, pas de styles inline.
   * Objectif: permettre un contenu "CMS" basique sans exécuter de scripts.
   */
  public static function sanitize(string $html): string
  {
    $html = trim($html);
    if ($html === '') return '';

    $html = str_ireplace(
      array_keys(self::LEGACY_EMAIL_MAP),
      array_values(self::LEGACY_EMAIL_MAP),
      $html
    );

    // Retirer blocs dangereux en premier
    $html = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\\1\s*>#is', '', $html) ?: '';

    $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><hr>';
    $html = strip_tags($html, $allowedTags);

    if (!class_exists(DOMDocument::class)) {
      // Fallback: retirer handlers + href javascript:
      $html = preg_replace('/\son[a-z]+\\s*=\\s*(\"[^\"]*\"|\\\'[^\\\']*\\\'|[^\\s>]+)/i', '', $html) ?: '';
      $html = preg_replace('/\sstyle\\s*=\\s*(\"[^\"]*\"|\\\'[^\\\']*\\\'|[^\\s>]+)/i', '', $html) ?: '';
      $html = preg_replace('/href\\s*=\\s*(\"|\\\')\\s*javascript:[^\\1]*(\\1)/i', 'href="#"', $html) ?: '';
      return $html;
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $container = $doc->getElementsByTagName('div')->item(0);
    if (!$container) {
      return '';
    }

    $allowed = array(
      'p' => array(),
      'br' => array(),
      'strong' => array(),
      'b' => array(),
      'em' => array(),
      'i' => array(),
      'u' => array(),
      'ul' => array(),
      'ol' => array(),
      'li' => array(),
      'h2' => array(),
      'h3' => array(),
      'h4' => array(),
      'blockquote' => array(),
      'hr' => array(),
      'a' => array('href', 'title', 'target', 'rel'),
    );

    self::sanitizeNode($container, $allowed);
    $out = '';
    foreach (iterator_to_array($container->childNodes) as $child) {
      $out .= $doc->saveHTML($child);
    }
    return trim($out);
  }

  /**
   * @param array<string,string[]> $allowed
   */
  private static function sanitizeNode(DOMNode $node, array $allowed): void
  {
    if ($node->nodeType === XML_ELEMENT_NODE) {
      $tag = strtolower((string) $node->nodeName);
      if (!isset($allowed[$tag])) {
        // Retirer le noeud mais garder ses enfants (dégradation douce)
        $parent = $node->parentNode;
        if ($parent) {
          while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
          }
          $parent->removeChild($node);
          return;
        }
      } else {
        // Nettoyer les attributs
        if ($node->hasAttributes()) {
          $keep = $allowed[$tag];
          /** @var DOMNamedNodeMap $attrs */
          $attrs = $node->attributes;
          $toRemove = array();
          foreach ($attrs as $attr) {
            $name = strtolower((string) $attr->nodeName);
            if (!in_array($name, $keep, true)) {
              $toRemove[] = $attr->nodeName;
              continue;
            }
            // Retirer handlers + style
            if (str_starts_with($name, 'on') || $name === 'style') {
              $toRemove[] = $attr->nodeName;
            }
          }
          foreach ($toRemove as $n) {
            $node->removeAttribute($n);
          }
        }

        // Spécial <a>: sécuriser href/rel/target
        if ($tag === 'a' && $node instanceof DOMElement) {
          $href = trim((string) $node->getAttribute('href'));
          if ($href === '') {
            $node->removeAttribute('href');
          } else {
            $hrefLower = strtolower($href);
            $ok =
              str_starts_with($hrefLower, 'http://')
              || str_starts_with($hrefLower, 'https://')
              || str_starts_with($hrefLower, 'mailto:')
              || str_starts_with($hrefLower, 'tel:')
              || str_starts_with($href, '/')
              || str_starts_with($href, '#');
            if (!$ok) {
              $node->setAttribute('href', '#');
            }
          }

          $target = trim((string) $node->getAttribute('target'));
          if ($target !== '') {
            $node->setAttribute('rel', 'noopener noreferrer');
          } else {
            $node->removeAttribute('rel');
          }
        }
      }
    }

    // Parcours enfants (copie la liste car elle peut être modifiée)
    $children = array();
    foreach (iterator_to_array($node->childNodes) as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      self::sanitizeNode($child, $allowed);
    }
  }
}

