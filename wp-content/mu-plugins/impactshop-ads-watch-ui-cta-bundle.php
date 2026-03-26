<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_ADS_WATCH_UI_CTA_BUNDLE_VERSION = '20260326.1';

add_action('wp_print_footer_scripts', 'impactshop_ads_watch_ui_cta_bundle_enqueue', 5);

function impactshop_ads_watch_ui_cta_bundle_enqueue(): void
{
    if (is_admin()) {
        return;
    }

    if (
        !wp_script_is('impactshop-ads-watch', 'enqueued')
        && !wp_script_is('impactshop-ads-watch', 'to_do')
        && !wp_script_is('impactshop-ads-watch', 'done')
    ) {
        return;
    }

    $handle = 'impactshop-ads-watch-ui-cta-bundle';
    if (!wp_script_is($handle, 'registered')) {
        wp_register_script(
            $handle,
            plugins_url('impactshop-ads-watch-ui-cta-bundle.js', __FILE__),
            ['jquery', 'impactshop-ads-watch'],
            IMPACTSHOP_ADS_WATCH_UI_CTA_BUNDLE_VERSION,
            true
        );
    }

    wp_enqueue_script($handle);
}
