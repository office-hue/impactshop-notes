<?php
/**
 * Plugin Name: Sharity – Migrated Snippets (mu-plugins)
 * Description: DB alól fájlba migrált kódrészletek egységes betöltése.
 * Author: Sharity
 * Version: 1.0.0
 */

// Példa: init hook guard + létezésellenőrzések
add_action('init', function () {
  // --- IDE JÖNNEK A KORÁBBI SNIPPETEK ---
  // 1) Rövidkód alias/minimal UI biztosíték (példa):
  if (!shortcode_exists('impact_ticker') && function_exists('ims_ticker')) {
    add_shortcode('impact_ticker','ims_ticker');
  }

  // 2) Egyedi REST/redirect/guard kódok (csak ha itt akarsz tartani belőlük)
  // ...
});
