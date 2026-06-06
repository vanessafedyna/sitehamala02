<?php

require_once __DIR__ . '/public_contact.php';

if (!function_exists('contact_page_context')) {
  /**
   * @return array{
   *   page_title:string,
   *   page_meta_description:string,
   *   page_css:string,
   *   page_js:string,
   *   public_email:string,
   *   public_whatsapp_url:string
   * }
   */
  function contact_page_context(): array
  {
    return array(
      'page_title' => 'Contact',
      'page_meta_description' => 'Contactez SORA Collection pour vos informations produit, votre suivi de commande ou une assistance rapide au Mali.',
      'page_css' => 'pages/contact.css',
      'page_js' => 'pages/contact.js',
      'public_email' => public_contact_email(),
      'public_whatsapp_url' => public_contact_whatsapp_url(),
    );
  }
}
