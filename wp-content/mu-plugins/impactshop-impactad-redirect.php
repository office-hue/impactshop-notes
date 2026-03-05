<?php
/**
 * Redirect legacy Impact Ad URL to new Impact Challenge slug.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }
    if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'GET') {
        return;
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $is_legacy = ($uri !== '' && stripos($uri, '/impactad-2') === 0);
    $is_misspelled = ($uri !== '' && stripos($uri, '/impact-challange') === 0);
    if (!$is_legacy && !$is_misspelled) {
        return;
    }
    $target = site_url('/impact-challenge/');
    wp_redirect($target, 301);
    exit;
});
