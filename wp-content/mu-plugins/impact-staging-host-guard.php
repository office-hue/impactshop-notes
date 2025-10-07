<?php
/**
 * Plugin Name: Impact Staging Host Guard
 * Description: Forces the staging instance to use the canonical app.sharity.hu host to avoid unwanted redirects.
 * Version:     1.0.0
 * Author:      ImpactShop DevOps
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACT_STAGING_CANONICAL_URL')) {
    define('IMPACT_STAGING_CANONICAL_URL', 'https://app.sharity.hu/impactshop-staging');
}

if (!function_exists('impact_staging_host_guard_should_run')) {
    /**
     * Detect whether the current runtime belongs to the staging install.
     */
    function impact_staging_host_guard_should_run(): bool
    {
        if (defined('IMPACT_STAGING_HOST_GUARD_DISABLE') && IMPACT_STAGING_HOST_GUARD_DISABLE) {
            return false;
        }

        $contentDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '';
        if ($contentDir !== '' && strpos($contentDir, 'app-staging') !== false) {
            return true;
        }

        $absPath = defined('ABSPATH') ? ABSPATH : '';
        if ($absPath !== '' && strpos($absPath, 'app-staging') !== false) {
            return true;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        if ($host === 'app.sharity.hu' && strpos($uri, '/impactshop-staging') !== false) {
            return true;
        }

        if (defined('WP_CLI') && WP_CLI && $host === '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('impact_staging_host_guard_base')) {
    /**
     * Returns the canonical base URL for staging without trailing slash.
     */
    function impact_staging_host_guard_base(): string
    {
        return untrailingslashit(IMPACT_STAGING_CANONICAL_URL);
    }
}

if (!function_exists('impact_staging_host_guard_build_url')) {
    /**
     * Build a URL relative to the canonical base while respecting the requested scheme.
     */
    function impact_staging_host_guard_build_url($path, $scheme = null): string
    {
        $base = impact_staging_host_guard_base();
        $path = (string) $path;
        $url  = $path !== '' ? $base . '/' . ltrim($path, '/') : $base;

        if (function_exists('set_url_scheme')) {
            return set_url_scheme($url, $scheme);
        }

        if ($scheme === 'http' || $scheme === 'https') {
            $url = preg_replace('~^https?~', $scheme, $url, 1);
        }

        return $url;
    }
}

if (impact_staging_host_guard_should_run()) {
    $base = impact_staging_host_guard_base();

    $forceOption = static function () use ($base) {
        return $base;
    };

    add_filter('pre_option_home', $forceOption, 1);
    add_filter('pre_option_siteurl', $forceOption, 1);
    add_filter('option_home', $forceOption, 1);
    add_filter('option_siteurl', $forceOption, 1);

    add_filter('home_url', static function ($url, $path, $origScheme) {
        return impact_staging_host_guard_build_url($path, $origScheme);
    }, 10, 3);

    add_filter('site_url', static function ($url, $path, $scheme) {
        return impact_staging_host_guard_build_url($path, $scheme);
    }, 10, 3);
}

