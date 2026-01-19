<?php
/**
 * Plugin Name: ImpactShop Identity PIN Cleanup
 * Description: Daily cleanup for wp_impact_pin_tokens (expired/used rows).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!wp_next_scheduled('impactshop_pin_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'impactshop_pin_cleanup');
    }
});

add_action('impactshop_pin_cleanup', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'impact_pin_tokens';
    $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * DAY_IN_SECONDS));

    $batch_size = 1000;
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table
             WHERE (used_at IS NOT NULL AND used_at < %s)
                OR (expires_at < %s AND used_at IS NULL)
             LIMIT %d",
            $cutoff,
            $cutoff,
            $batch_size
        )
    );

    if ($deleted === $batch_size) {
        wp_schedule_single_event(time() + 60, 'impactshop_pin_cleanup');
    }

    $upload = wp_upload_dir();
    if (empty($upload['basedir'])) {
        return;
    }

    $base = trailingslashit($upload['basedir']);
    $logs = ['impactshop-pin-audit.log', 'impactshop-pin-delivery.log'];
    $max_size = 10 * 1024 * 1024;
    $cutoff_ts = time() - (30 * DAY_IN_SECONDS);

    foreach ($logs as $log_file) {
        $path = $base . $log_file;
        if (!file_exists($path)) {
            continue;
        }

        if (filesize($path) > $max_size) {
            $backup = $path . '.' . gmdate('Ymd-His');
            @rename($path, $backup);
        }

        foreach (glob($base . $log_file . '.*') ?: [] as $old_backup) {
            if (filemtime($old_backup) < $cutoff_ts) {
                @unlink($old_backup);
            }
        }
    }
});
