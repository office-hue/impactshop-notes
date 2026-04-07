<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_ADS_WATCH_UI_CTA_BUNDLE_VERSION = '20260407.2';

// HOTFIX 2026-04-07: CTA bundle disabled — MutationObserver causes UI freeze
// during ad playback (subtree+characterData triggers on every RAF progress update).
// The deferred-UI feature will be re-implemented without MutationObserver.
return;

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
