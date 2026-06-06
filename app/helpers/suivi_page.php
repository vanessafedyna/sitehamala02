<?php

require_once __DIR__ . '/public_contact.php';

if (!function_exists('suivi_page_prefill_order_number')) {
  function suivi_page_prefill_order_number(array $query): string
  {
    $value = isset($query['order_number']) ? trim((string) $query['order_number']) : '';
    if ($value !== '' && strlen($value) > 80) {
      return '';
    }
    return $value;
  }
}

if (!function_exists('suivi_page_context')) {
  /**
   * @param array<string,mixed> $query
   * @return array{
   *   page_title:string,
   *   page_meta_description:string,
   *   page_css:string,
   *   page_js:string,
   *   prefill_order_number:string,
   *   public_email:string,
   *   public_whatsapp_url:string
   * }
   */
  function suivi_page_context(array $query): array
  {
    return array(
      'page_title' => 'Suivi commande',
      'page_meta_description' => 'Suivez votre commande SORA Collection et consultez son statut en ligne. En cas de besoin, notre support reste disponible au Mali.',
      'page_css' => 'pages/suivi.css',
      'page_js' => 'pages/suivi.js',
      'prefill_order_number' => suivi_page_prefill_order_number($query),
      'public_email' => public_support_email(),
      'public_whatsapp_url' => public_contact_whatsapp_url(),
    );
  }
}
