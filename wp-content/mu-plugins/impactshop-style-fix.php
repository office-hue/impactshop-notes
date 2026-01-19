<?php
/**
 * ImpactShop page style hotfix (disabled).
 *
 * Korábban inline stílust injektált a 16348-as oldalra, most teljesen no-op,
 * hogy az Elementor saját CSS-e érvényesüljön.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ne tegyen semmit a head-be.
add_action('wp_head', function () {
    return;
}, 1);

// Ne injektáljon inline CSS-t, hagyjuk az Elementor `post-16348.css`-t.
add_action('wp_enqueue_scripts', function () {
    return;
}, 20);
