<?php

if (!function_exists('seo_prepare_meta')) {
  /**
   * Prepare SEO meta values with safe fallbacks.
   *
   * @param array<string,mixed> $input
   * @return array<string,string>
   */
  function seo_prepare_meta(array $input): array
  {
    $siteName = trim((string) ($input['site_name'] ?? ''));
    $pageTitle = trim((string) ($input['page_title'] ?? ''));
    $pageSeoTitle = trim((string) ($input['page_seo_title'] ?? ''));
    $pageMetaDescription = trim((string) ($input['page_meta_description'] ?? ''));
    $pageOgTitle = trim((string) ($input['page_og_title'] ?? ''));
    $pageOgDescription = trim((string) ($input['page_og_description'] ?? ''));
    $pageOgImage = trim((string) ($input['page_og_image'] ?? ''));

    $seoTitleFinal = $pageSeoTitle;
    if ($seoTitleFinal !== '') {
      $seoTitleFinal .= ($siteName !== '' ? (' - ' . $siteName) : '');
    } else {
      $seoTitleFinal = $pageTitle;
    }

    $metaDescFinal = $pageMetaDescription;

    $ogTitle = $pageOgTitle !== '' ? $pageOgTitle : $seoTitleFinal;
    $ogDescription = $pageOgDescription !== '' ? $pageOgDescription : $metaDescFinal;
    $ogImage = $pageOgImage;

    return array(
      'seo_title_final' => $seoTitleFinal,
      'meta_desc_final' => $metaDescFinal,
      'og_title' => $ogTitle,
      'og_description' => $ogDescription,
      'og_image' => $ogImage,
    );
  }
}

