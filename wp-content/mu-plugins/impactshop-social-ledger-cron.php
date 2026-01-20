<?php
/**
 * Plugin Name: ImpactShop Social Ledger Cron
 * Description: WP-Cron wrapper for the Dognet -> impact_ledger sync script.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('cron_schedules', function (array $schedules): array {
    if (!isset($schedules['impactshop_10min'])) {
        $schedules['impactshop_10min'] = [
            'interval' => 600,
            'display'  => 'Every 10 minutes (ImpactShop)',
        ];
    }
    return $schedules;
});

add_action('init', function (): void {
    if (!wp_next_scheduled('impactshop_social_ledger_sync')) {
        wp_schedule_event(time() + 60, 'impactshop_10min', 'impactshop_social_ledger_sync');
    }
});

add_action('impactshop_social_ledger_sync', function (): void {
    $script_paths = [
        ABSPATH . '.codex/scripts/impact-social-ledger-sync.php',
        ABSPATH . '../.codex/scripts/impact-social-ledger-sync.php',
        '/home/sharityh/app/.codex/scripts/impact-social-ledger-sync.php',
        '/home/sharityh/app-staging/.codex/scripts/impact-social-ledger-sync.php',
    ];

    foreach ($script_paths as $path) {
        if (file_exists($path)) {
            include $path;
            return;
        }
    }
});
