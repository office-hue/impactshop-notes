<?php
/**
 * Plugin Name: Impact API URL Fix (wp-json)
 * Description: Az Impact URL-eket mindig a /wp-json/impact/v1/ alá irányítja. Duplázás ellen véd.
 * Version:     1.0.0
 * Author:      Sharity
 */
if (!defined('ABSPATH')) exit;

/** BASE host – csak hostot adj meg; NINCS /impact, /api, /wp-json a végén */
if (!defined('IMPACT_API_BASE')) {
  define('IMPACT_API_BASE', 'https://app.sharity.hu');
}

/** Normalizált host (levág minden /api, /impact vagy /wp-json toldalékot a végéről) */
if (!function_exists('impact__normalize_host_for_rest')) {
  function impact__normalize_host_for_rest() {
    $host = rtrim(IMPACT_API_BASE, '/');
    $host = preg_replace('~/(api|impact|wp-json)(/v1)?$~i', '', $host);
    return $host;
  }
}

/**
 * KANONIKUS URL-ÉPÍTŐ:
 * Mindig https://<host>/wp-json/impact/v1/<path>
 * – véd a duplázás ellen
 * – rövid aliasok: ticker | leaderboard | activity
 *
 * MU-pluginként töltődik be, így a Shortcodes plugin nem írja felül.
 */
if (!function_exists('impact_api_url')) {
  function impact_api_url($path) {
    $host = impact__normalize_host_for_rest();
    $p = '/' . ltrim((string)$path, '/');

    // normalizálás: ha már tartalmaz valamit, csináljunk EGY kanonikus előtagot
    $p = preg_replace('~(/wp-json)?(/impact(/v1)?/)+~i', '/wp-json/impact/v1/', $p, 1);

    // ha nem a REST előtaggal indul, egészítsük ki
    if (!preg_match('~^/wp-json/impact/v1/~i', $p)) {
      if (in_array($p, ['/ticker','/leaderboard','/activity'], true)) {
        $p = '/wp-json/impact/v1' . $p;
      } else {
        $p = '/wp-json/impact/v1' . $p;
        $p = preg_replace('~(/wp-json/impact/v1/){2,}~', '/wp-json/impact/v1/', $p);
      }
    }

    return rtrim($host, '/') . $p;
  }
}
