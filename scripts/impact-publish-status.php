<?php
// WHAT: Rövid queue státusz összesítő (DNS/Redis nélkül, MySQL tábla alapján).
// HOW: wp eval-file scripts/impact-publish-status.php

if (php_sapi_name() !== 'cli') {
    exit(0);
}

global $wpdb;
$table = $wpdb->prefix . 'impact_publish_queue';

$counts = $wpdb->get_results(
    "SELECT status, COUNT(*) as c FROM {$table} GROUP BY status",
    OBJECT_K
);

$out = [];
foreach ($counts as $status => $row) {
    $out[$status] = (int) $row->c;
}

// Top 5 legfrissebb queued/processing job röviden
$recent = $wpdb->get_results(
    "SELECT job_id, status, priority, created_at FROM {$table} ORDER BY created_at DESC LIMIT 5",
    ARRAY_A
);

echo wp_json_encode(['counts' => $out, 'recent' => $recent ?: []], JSON_PRETTY_PRINT) . PHP_EOL;

// WP-CLI parancs alias: wp impact-publisher queue-status
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('impact-publisher queue-status', function () use ($out, $recent) {
        WP_CLI::log(WP_CLI::colorize('%GQueue status%n'));
        WP_CLI\Utils\format_items('table', array_map(function ($k, $v) {
            return ['status' => $k, 'count' => $v];
        }, array_keys($out), $out), ['status', 'count']);
        if (!empty($recent)) {
            WP_CLI::log(WP_CLI::colorize('%GLatest jobs%n'));
            WP_CLI\Utils\format_items('table', $recent, ['job_id', 'status', 'priority', 'created_at']);
        }
    });
}
