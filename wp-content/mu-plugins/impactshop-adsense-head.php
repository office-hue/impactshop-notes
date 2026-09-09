<?php
/**
 * Google AdSense Head Script
 * 
 * Beilleszti a Google AdSense kódot a <head> részbe.
 *
 * @package ImpactShop
 */

defined('ABSPATH') || exit;

add_action('wp_head', function () {
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    // The profile and one-time code reveal pages must remain stable and
    // third-party-script free; other pages retain the existing AdSense setup.
    if (untrailingslashit($path) === '/profil' || str_starts_with(untrailingslashit($path), '/profil/')) {
        return;
    }
    ?>
    <!-- Google AdSense verification and script -->
    <meta name="google-adsense-account" content="ca-pub-3544330186801102">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3544330186801102"
         crossorigin="anonymous"></script>
    <?php
}, 1); // Prioritás 1 = nagyon korán fut, a head elején
