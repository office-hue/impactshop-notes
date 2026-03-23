<?php
/**
 * Auto Banner Sync from Google Sheets CSV
 * 
 * Syncs product offers from the deals netflix CSV to auto_banners table.
 * Runs via WP Cron once daily at 6:00 AM.
 * 
 * Safe: Only READS from existing CSV, no modification to deals netflix shortcode.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_AUTO_BANNER_SYNC_INTERVAL = DAY_IN_SECONDS;
const IMPACTSHOP_AUTO_BANNER_SYNC_LIMIT = 1000; // Max banners to keep active
/**
 * Parse Hungarian price string like "13 990 Ft" to float 13990.00
 */
function impactshop_parse_price_string($price_str): float
{
    if (empty($price_str) || !is_string($price_str)) {
        return 0.0;
    }
    
    // Remove currency suffix (Ft), spaces, and non-breaking spaces
    $cleaned = preg_replace('/[^0-9,.]/', '', $price_str);
    
    // Handle Hungarian format: comma as decimal separator
    // If there's a comma followed by exactly 2 digits at the end, it's decimal
    if (preg_match('/,\d{2}$/', $cleaned)) {
        $cleaned = str_replace(',', '.', $cleaned);
    } else {
        // Remove commas used as thousand separators
        $cleaned = str_replace(',', '', $cleaned);
    }
    
    return (float) $cleaned;
}

add_action('muplugins_loaded', 'impactshop_auto_banner_sync_boot');

function impactshop_auto_banner_sync_boot(): void
{
    // Register custom cron interval
    add_filter('cron_schedules', 'impactshop_auto_banner_sync_schedules');
    
    // Schedule cron if not already scheduled - runs daily at 6:00 AM
    if (!wp_next_scheduled('impactshop_auto_banner_sync_cron')) {
        // Calculate next 6:00 AM in site timezone
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        $next_run = new DateTime('today 06:00', $timezone);
        
        // If already past 6:00 today, schedule for tomorrow
        if ($now > $next_run) {
            $next_run->modify('+1 day');
        }
        
        wp_schedule_event($next_run->getTimestamp(), 'daily', 'impactshop_auto_banner_sync_cron');
    }
    
    // Hook the sync function
    add_action('impactshop_auto_banner_sync_cron', 'impactshop_auto_banner_sync_run');
    
    // WP-CLI command
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::add_command('impactshop auto-banner sync', 'impactshop_auto_banner_sync_cli');
    }
}

function impactshop_auto_banner_sync_schedules(array $schedules): array
{
    // Using built-in 'daily' schedule, no custom interval needed
    return $schedules;
}

/**
 * Main sync function - fetches CSV and updates auto_banners table
 */
function impactshop_auto_banner_sync_run(): array
{
    $start = microtime(true);
    $result = [
        'success' => false,
        'fetched' => 0,
        'inserted' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    // Get CSV URL from existing settings
    if (!function_exists('impactshop_settings')) {
        $result['errors'][] = 'impactshop_settings() not available';
        return $result;
    }

    $settings = impactshop_settings();
    $csv_url = $settings['banners_csv_url'] ?? '';
    
    if ($csv_url === '') {
        $result['errors'][] = 'banners_csv_url is empty';
        return $result;
    }

    // Fetch CSV
    $response = wp_remote_get($csv_url, [
        'timeout' => 30,
        'sslverify' => false,
    ]);

    if (is_wp_error($response)) {
        $result['errors'][] = 'CSV fetch failed: ' . $response->get_error_message();
        return $result;
    }

    $body = wp_remote_retrieve_body($response);
    if ($body === '') {
        $result['errors'][] = 'CSV body is empty';
        return $result;
    }

    // Parse CSV
    $lines = explode("\n", $body);
    if (count($lines) < 2) {
        $result['errors'][] = 'CSV has no data rows';
        return $result;
    }

    // Get headers from first line
    $headers = str_getcsv(array_shift($lines));
    $headers = array_map('trim', $headers);
    $headers = array_map('strtolower', $headers);

    // Find column indices
    $col_slug = array_search('slug', $headers);
    $col_img = array_search('img', $headers);
    $col_href = array_search('href', $headers);
    $col_label = array_search('label', $headers);
    $col_category = array_search('category', $headers);

    if ($col_slug === false || $col_img === false || $col_href === false || $col_label === false) {
        $result['errors'][] = 'Missing required CSV columns';
        return $result;
    }

    // Parse rows and filter by discount
    $offers = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $cols = str_getcsv($line);
        $result['fetched']++;

        $slug = sanitize_title(trim($cols[$col_slug] ?? ''));
        $img = trim($cols[$col_img] ?? '');
        $href = trim($cols[$col_href] ?? '');
        $label_json = trim($cols[$col_label] ?? '');
        $category = trim($cols[$col_category] ?? '');

        // Banner tab rows are canonical offers; only slug and href are required.
        if ($slug === '' || $href === '') {
            $result['skipped']++;
            continue;
        }

        if (function_exists('impactshop_auto_banner_is_valid_banner_url')) {
            if (!impactshop_auto_banner_is_valid_banner_url($href, $slug)) {
                $result['skipped']++;
                continue;
            }
        } elseif (function_exists('impactshop_is_valid_product_url')) {
            if (!impactshop_is_valid_product_url($href)) {
                $result['skipped']++;
                continue;
            }
        }

        if (function_exists('impactshop_is_whitelisted_partner')) {
            if (!impactshop_is_whitelisted_partner($slug)) {
                $result['skipped']++;
                continue;
            }
        }

        // Parse label JSON
        $label = json_decode($label_json, true);
        if (!is_array($label)) {
            $label = [];
        }

        $title = (string) ($label['title'] ?? '');
        if ($title === '' && $category !== '') {
            $title = $category;
        }
        if ($title === '') {
            $title = ucwords(str_replace(['-', '_'], ' ', $slug));
        }
        
        // Parse price fields - CSV uses 'price'/'old_price' strings like "13 990 Ft"
        // Also support legacy 'price_num'/'old_price_num' float fields
        $price_new = impactshop_parse_price_string($label['price'] ?? '') 
                     ?: (float) ($label['price_num'] ?? 0);
        $price_old = impactshop_parse_price_string($label['old_price'] ?? '') 
                     ?: (float) ($label['old_price_num'] ?? 0);
        
        // CSV uses 'pct', also support legacy 'discount_pct'
        $discount_pct = (int) ($label['pct'] ?? $label['discount_pct'] ?? 0);

        // Calculate discount if not provided
        if ($discount_pct === 0 && $price_old > 0 && $price_new > 0 && $price_new < $price_old) {
            $discount_pct = (int) round((1 - ($price_new / $price_old)) * 100);
        }

        $offers[] = [
            'title' => $title,
            'image_url' => $img,
            'shop_slug' => $slug,
            'url' => $href,
            'price_old' => $price_old,
            'price_new' => $price_new,
            'discount_percent' => $discount_pct,
            'category' => $category,
            'priority' => impactshop_auto_banner_sync_calculate_priority($discount_pct, $price_new),
        ];
    }

    // Sort by priority (highest first) and limit
    usort($offers, function ($a, $b) {
        return $b['priority'] <=> $a['priority'];
    });
    $offers = array_slice($offers, 0, IMPACTSHOP_AUTO_BANNER_SYNC_LIMIT);

    if (empty($offers)) {
        $result['errors'][] = 'No valid offers found after filtering';
        return $result;
    }

    // Update database
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_auto_banners';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    if (!$table_exists) {
        $result['errors'][] = 'auto_banners table does not exist';
        return $result;
    }

    // Deactivate old sync'd banners (keep manually added ones)
    $wpdb->query(
        "UPDATE {$table} SET status = 'disabled' 
         WHERE status = 'active' 
         AND shop_slug LIKE 'sync:%'"
    );

    // Insert new offers
    $now = current_time('mysql');
    $ends_at = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
    foreach ($offers as $offer) {
        // Use sync: prefix to identify auto-synced banners
        $sync_slug = 'sync:' . $offer['shop_slug'];
        
        // Check for existing (by title + shop to avoid duplicates)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE shop_slug = %s AND (title = %s OR banner_url = %s) LIMIT 1",
            $sync_slug,
            $offer['title'],
            $offer['url']
        ));

        if ($existing) {
            // Reactivate existing
            $wpdb->update(
                $table,
                [
                    'status' => 'active',
                    'image_url' => $offer['image_url'],
                    'banner_url' => $offer['url'],
                    'price_old' => $offer['price_old'],
                    'price_new' => $offer['price_new'],
                    'discount_percent' => $offer['discount_percent'],
                    'priority' => $offer['priority'],
                    'starts_at' => $now,
                    'ends_at' => $ends_at,
                ],
                ['id' => $existing],
                ['%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s'],
                ['%d']
            );
            $result['inserted']++;
        } else {
            // Insert new
            $wpdb->insert(
                $table,
                [
                    'status' => 'active',
                    'title' => $offer['title'],
                    'image_url' => $offer['image_url'],
                    'shop_slug' => $sync_slug,
                    'banner_url' => $offer['url'],
                    'price_old' => $offer['price_old'],
                    'price_new' => $offer['price_new'],
                    'discount_percent' => $offer['discount_percent'],
                    'priority' => $offer['priority'],
                    'starts_at' => $now,
                    'ends_at' => $ends_at,
                    'created_at' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s', '%s']
            );
            $result['inserted']++;
        }
    }

    // Cleanup old disabled sync banners (older than 24h)
    $wpdb->query(
        "DELETE FROM {$table} 
         WHERE status = 'disabled' 
         AND shop_slug LIKE 'sync:%'
         AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );

    $result['success'] = true;
    $result['duration_ms'] = round((microtime(true) - $start) * 1000);

    // Log result
    if (function_exists('impactshop_ads_watch_log')) {
        impactshop_ads_watch_log('info', 'auto_banner_sync_complete', $result);
    }

    return $result;
}

/**
 * Calculate priority for sorting offers
 */
function impactshop_auto_banner_sync_calculate_priority(int $discount_pct, float $price): int
{
    // Higher discount = higher priority
    $priority = $discount_pct * 2;
    
    // Bonus for mid-range prices (not too cheap, not too expensive)
    if ($price >= 5000 && $price <= 50000) {
        $priority += 10;
    }
    
    return max(0, $priority);
}

/**
 * WP-CLI command
 */
function impactshop_auto_banner_sync_cli(array $args, array $assoc_args): void
{
    WP_CLI::log('Starting auto-banner sync from Google Sheets CSV...');
    
    $result = impactshop_auto_banner_sync_run();
    
    if ($result['success']) {
        WP_CLI::success(sprintf(
            'Sync complete: %d fetched, %d inserted/updated, %d skipped (%.0fms)',
            $result['fetched'],
            $result['inserted'],
            $result['skipped'],
            $result['duration_ms'] ?? 0
        ));
    } else {
        WP_CLI::error('Sync failed: ' . implode(', ', $result['errors']));
    }
}

/**
 * REST endpoint to trigger manual sync (admin only)
 */
add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/auto-banner/sync', [
        'methods' => 'POST',
        'callback' => function () {
            $result = impactshop_auto_banner_sync_run();
            return new WP_REST_Response($result, $result['success'] ? 200 : 500);
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ]);
});
