<?php
/**
 * Impact Ledger skeleton (Sprint 3 preparation).
 * Owner: Dev B
 *
 * Provides scaffolding for the unified donation ledger. Real implementation
 * lands with Sprint 3 T-3.1/T-3.2 tasks.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Impact_Ledger {
    /**
     * Bootstrap hook.
     */
    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('impact_ledger_generate_reports', [__CLASS__, 'report_cron']);
    }

    public static function register_routes(): void {
        // TODO Sprint 3: expose read-only REST endpoints.
    }

    public static function report_cron(): void {
        // TODO Sprint 3: generate monthly rollup + export to CSV.
        error_log('[impact-ledger] report cron deferred (skeleton).');
    }
}

Impact_Ledger::init();

register_activation_hook(__FILE__, static function () {
    // TODO Sprint 3: dbDelta schema creation for wp_impact_ledger table.
});
