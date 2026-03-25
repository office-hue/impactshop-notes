<?php
/**
 * Plugin Name: ImpactShop CJ Integration
 * Description: CJ Advertiser + Commission sync helper (shop feed + ledger ingest + CLI)
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ImpactShop_CJ
{
    private const OPTION_ENABLED = 'impactshop_cj_enabled';
    private const OPTION_SHOPS = 'impactshop_cj_shops';
    private const OPTION_SHOPS_SYNCED = 'impactshop_cj_shops_synced_at';
    private const OPTION_FAILED = 'impactshop_cj_failed_queue';
    private const OPTION_CURSOR = 'impactshop_cj_last_cursor';
    private const OPTION_LINKS = 'impactshop_cj_links';
    private const OPTION_PRODUCTS = 'impactshop_cj_products';

    private const API_TIMEOUT = 25;
    private const FX_CACHE_TTL = 12 * HOUR_IN_SECONDS;
    private const GRAPHQL_TIMEOUT = 45;
    private const ADS_ENDPOINT = 'https://ads.api.cj.com/query';
    private const FAILED_QUEUE_LIMIT = 200;
    private static array $donationMultiplierCache = [];
    private const CATEGORY_ALIASES = [
        'party goods'         => 'Szabadidő',
        'entertainment'       => 'Szabadidő',
        'travel'              => 'Szabadidő',
        'tourism'             => 'Szabadidő',
        'trips'               => 'Szabadidő',
        'malls'               => 'Vegyes',
        'shopping services'   => 'Vegyes',
        'shopping & services' => 'Vegyes',
        'marketplace'         => 'Vegyes',
        'miscellaneous'       => 'Vegyes',
        'other'               => 'Vegyes',
        'fashion'             => 'Divat',
        'apparel'             => 'Divat',
        'clothing'            => 'Divat',
        'shoes'               => 'Divat',
        'beauty'              => 'Egészség',
        'health'              => 'Egészség',
        'wellness'            => 'Egészség',
        'home & garden'       => 'Otthon',
        'home'                => 'Otthon',
        'garden'              => 'Otthon',
        'decor'               => 'Otthon',
        'furniture'           => 'Bútor',
        'electronics'         => 'Műszaki',
        'consumer electronics'=> 'Műszaki',
        'software'            => 'Műszaki',
        'technology'          => 'Műszaki',
        'gadgets'             => 'Műszaki',
        'education'           => 'Kultúra',
        'books'               => 'Kultúra',
        'culture'             => 'Kultúra',
        'finance'             => 'Vegyes',
        'insurance'           => 'Vegyes',
        'services'            => 'Vegyes',
        'automotive'          => 'Szabadidő',
        'sports'              => 'Sport',
        'sporting goods'      => 'Sport',
        'fitness'             => 'Sport',
        'pet'                 => 'Állat',
        'pets'                => 'Állat',
        'animal'              => 'Állat',
        'food'                => 'Élelmiszer',
        'grocery'             => 'Élelmiszer',
        'supermarket'         => 'Élelmiszer',
        'jewelry'             => 'Ékszer',
        'gift'                => 'Ajándék',
        'gifts'               => 'Ajándék',
        'toy'                 => 'Játék',
        'toys'                => 'Játék',
        'charity'             => 'Vegyes',
        'utazas'              => 'Szabadidő',
        'utazás'              => 'Szabadidő',
        'szabadidő'           => 'Szabadidő',
        'szabadido'           => 'Szabadidő',
        'sport'               => 'Sport',
        'divat'               => 'Divat',
        'egészség'            => 'Egészség',
        'szépség'             => 'Egészség',
        'otthon & kert'       => 'Otthon',
        'otthon'              => 'Otthon',
        'bútor'               => 'Bútor',
        'műszaki'             => 'Műszaki',
        'kultúra'             => 'Kultúra',
        'állat'               => 'Állat',
        'élelmiszer'          => 'Élelmiszer',
        'ékszer'              => 'Ékszer',
    ];

    public static function bootstrap(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            self::register_cli();
        }

        if (!self::is_enabled()) {
            return;
        }

        add_filter('impactshop_shops_raw', [self::class, 'filter_shops_raw']);
    }

    /**
     * Merge synced CJ shops into the canonical shop list.
     *
     * @param array $rows
     * @return array
     */
    public static function filter_shops_raw(array $rows): array
    {
        $cjShops = get_option(self::OPTION_SHOPS, []);
        if (!is_array($cjShops) || !$cjShops) {
            return $rows;
        }

        $existingSlugs = [];
        foreach ($rows as $row) {
            if (!empty($row['shop_slug'])) {
                $existingSlugs[$row['shop_slug']] = true;
            }
        }

        foreach ($cjShops as $shop) {
            if (($shop['status'] ?? '') !== 'joined') {
                continue;
            }
            $slug = $shop['slug'] ?? '';
            if ($slug === '' || isset($existingSlugs[$slug])) {
                continue;
            }
            $rows[] = [
                'name'           => $shop['name'] ?? '',
                'shop_slug'      => $slug,
                'category'       => $shop['primary_category'] ?? 'Egyéb',
                'logo_url'       => $shop['logo_url'] ?? '',
                'product_url'    => $shop['program_url'] ?? '',
                'homepage'       => $shop['program_url'] ?? '',
                'network_source' => 'cj',
                'cj_advertiser_id' => $shop['advertiser_id'] ?? '',
                'cj_tracking_template' => $shop['tracking_template'] ?? '',
                'added_at'       => $shop['last_seen_at'] ?? '',
            ];
        }

        return $rows;
    }

    private static function register_cli(): void
    {
        \WP_CLI::add_command('impactshop cj sync-shops', [self::class, 'cli_sync_shops']);
        \WP_CLI::add_command('impactshop cj ledger-sync', [self::class, 'cli_sync_ledger']);
        \WP_CLI::add_command('impactshop cj retry-failed', [self::class, 'cli_retry_failed']);
        \WP_CLI::add_command('impactshop cj seed-from-csv', [self::class, 'cli_seed_from_csv']);
        \WP_CLI::add_command('impactshop cj test-connection', [self::class, 'cli_test_connection']);
        \WP_CLI::add_command('impactshop cj fetch-links', [self::class, 'cli_fetch_links']);
        \WP_CLI::add_command('impactshop cj sync-products', [self::class, 'cli_sync_products']);
        \WP_CLI::add_command('impactshop cj toggle', [self::class, 'cli_toggle']);

        // Backwards compatible aliases (colon syntax).
        \WP_CLI::add_command('impactshop cj:sync-shops', [self::class, 'cli_sync_shops']);
        \WP_CLI::add_command('impactshop cj:sync-ledger', [self::class, 'cli_sync_ledger']);
        \WP_CLI::add_command('impactshop cj:retry-failed', [self::class, 'cli_retry_failed']);
        \WP_CLI::add_command('impactshop cj:seed-from-csv', [self::class, 'cli_seed_from_csv']);
        \WP_CLI::add_command('impactshop cj:test-connection', [self::class, 'cli_test_connection']);
        \WP_CLI::add_command('impactshop cj:fetch-links', [self::class, 'cli_fetch_links']);
        \WP_CLI::add_command('impactshop cj:sync-products', [self::class, 'cli_sync_products']);
        \WP_CLI::add_command('impactshop cj:toggle', [self::class, 'cli_toggle']);
    }

    public static function cli_sync_shops(array $args, array $assocArgs): void
    {
        self::assert_enabled_cli();
        try {
            $result = self::sync_shops();
            \WP_CLI::success(sprintf('CJ shops synced: %d entries (%d skipped)', $result['synced'], $result['skipped']));
        } catch (Throwable $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    public static function cli_sync_ledger(array $args, array $assocArgs): void
    {
        self::assert_enabled_cli();
        $window = $assocArgs['window'] ?? 'PT2H';
        $since  = $assocArgs['since'] ?? null;
        $dedupe = isset($assocArgs['dedupe-strategy']) ? strtolower($assocArgs['dedupe-strategy']) : 'replace';
        try {
            $result = self::sync_ledger($window, $since, $dedupe);
            \WP_CLI::success(sprintf('CJ ledger sync: %d inserted, %d updated, %d skipped', $result['inserted'], $result['updated'], $result['skipped']));
            if ($result['failed'] > 0) {
                \WP_CLI::warning(sprintf('%d commissions queued for retry.', $result['failed']));
            }
        } catch (Throwable $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    public static function cli_retry_failed(array $args, array $assocArgs): void
    {
        self::assert_enabled_cli();
        $limit = isset($assocArgs['limit']) ? max(1, absint($assocArgs['limit'])) : 100;
        $queue = get_option(self::OPTION_FAILED, []);
        if (!is_array($queue) || !$queue) {
            \WP_CLI::log('CJ retry queue empty.');
            return;
        }

        $slice = array_slice($queue, 0, $limit);
        $remaining = array_slice($queue, $limit);

        $dedupe = isset($assocArgs['dedupe-strategy']) ? strtolower($assocArgs['dedupe-strategy']) : 'replace';
        $replayed = self::ingest_commissions($slice, $dedupe);
        update_option(self::OPTION_FAILED, array_values($remaining), false);

        \WP_CLI::success(sprintf('Retried %d queued commissions (%d inserted, %d updated, %d still failing).', count($slice), $replayed['inserted'], $replayed['updated'], $replayed['failed']));
    }

    public static function cli_fetch_links(array $args, array $assocArgs): void
    {
        self::assert_enabled_cli();
        $filters = [
            'link-type'      => $assocArgs['link-type'] ?? 'Product Link,Text Link',
            'promotion-type' => $assocArgs['promotion-type'] ?? null,
            'promotion-start-date' => $assocArgs['promotion-start'] ?? null,
            'promotion-end-date'   => $assocArgs['promotion-end'] ?? null,
            'keywords'       => $assocArgs['keywords'] ?? null,
            'relationship-status' => $assocArgs['relationship'] ?? 'joined',
        ];
        if (isset($assocArgs['advertiser-ids'])) {
            $filters['advertiser-ids'] = $assocArgs['advertiser-ids'];
        }
        $limit = isset($assocArgs['limit']) ? max(1, absint($assocArgs['limit'])) : 5000;
        $output = $assocArgs['output'] ?? 'data/cj-links.json';

        try {
            $result = self::sync_links($filters, $limit, $output);
            \WP_CLI::success(sprintf('CJ links fetched: %d rows saved to %s', $result['count'], $result['path']));
        } catch (Throwable $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    public static function cli_sync_products(array $args, array $assocArgs): void
    {
        self::assert_enabled_cli();
        $limit = isset($assocArgs['limit']) ? max(1, absint($assocArgs['limit'])) : 500;
        try {
            $result = self::sync_products($limit);
            \WP_CLI::success(sprintf(
                'CJ products synced: %d advertisers (%d products, next page: %s)',
                $result['advertisers'],
                $result['products'],
                $result['next_page'] ?? 'n/a'
            ));
        } catch (Throwable $e) {
            \WP_CLI::error($e->getMessage());
        }
    }

    public static function cli_toggle(array $args, array $assocArgs): void
    {
        $current = self::is_enabled();
        if (isset($assocArgs['on']) && isset($assocArgs['off'])) {
            \WP_CLI::error('Specify either --on or --off, not both.');
        }
        if (isset($assocArgs['on'])) {
            self::set_enabled(true);
            \WP_CLI::success('CJ integration enabled.');
            return;
        }
        if (isset($assocArgs['off'])) {
            self::set_enabled(false);
            \WP_CLI::success('CJ integration disabled.');
            return;
        }
        \WP_CLI::line(sprintf('CJ integration is currently %s.', $current ? 'ON' : 'OFF'));
    }

    public static function cli_seed_from_csv(array $args, array $assocArgs): void
    {
        self::assert_enabled_cli();
        $csvPath = $assocArgs['csv'] ?? '';
        if ($csvPath === '' || !file_exists($csvPath)) {
            \WP_CLI::error('CSV file not found. Use --csv=path/to/Feeds-Migration-Report.csv');
        }

        $priority = strtoupper($assocArgs['priority'] ?? 'P0');
        $limit = isset($assocArgs['limit']) ? max(1, absint($assocArgs['limit'])) : 50;
        $output = $assocArgs['output'] ?? sprintf('data/cj-shops-%s.csv', $priority);

        $feeds = self::parse_feed_csv($csvPath);
        if (!$feeds) {
            \WP_CLI::error('CSV file contains no rows.');
        }

        $ranked = self::rank_feeds($feeds);
        $filtered = array_values(array_filter($ranked, static fn($row) => $row['priority'] === $priority));
        if (!$filtered) {
            \WP_CLI::warning(sprintf('No feeds match priority %s.', $priority));
            return;
        }
        $subset = array_slice($filtered, 0, $limit);
        self::write_csv($output, $subset);
        \WP_CLI::success(sprintf('Generated %s (%d rows).', $output, count($subset)));
    }

    public static function cli_test_connection(array $args, array $assocArgs): void
    {
        $guardMode = !empty($assocArgs['guard']);
        $creds = self::get_credentials(true);
        $tests = [
            [
                'label' => 'Link Search v2',
                'url'   => 'https://link-search.api.cj.com/v2/link-search?website-id=' . rawurlencode($creds['website_id']) . '&records-per-page=1&advertiser-ids=joined',
                'headers' => ['Authorization' => $creds['developer_key']],
            ],
            [
                'label' => 'Advertiser Lookup v2',
                'url'   => 'https://advertiser-lookup.api.cj.com/v2/advertiser-lookup?advertiser-ids=joined&records-per-page=1&requestor-cid=' . rawurlencode($creds['publisher_id']),
                'headers' => ['Authorization' => $creds['developer_key']],
            ],
        ];

        $publisherPat = $creds['publisher_pat'];
        if ($publisherPat) {
            $tests[] = [
                'label' => 'Commission GraphQL',
                'url'   => 'https://commissions.api.cj.com/query',
                'body'  => wp_json_encode(['query' => '{ publisherCommissions(forPublishers:["' . $creds['publisher_id'] . '"]){count}}']),
                'headers' => ['Authorization' => 'Bearer ' . $publisherPat, 'Content-Type' => 'application/json'],
                'method' => 'POST',
            ];
        } else {
            $msg = 'CJ_PUBLISHER_PAT not set; skipping GraphQL smoke test.';
            if ($guardMode) {
                \WP_CLI::warning($msg . ' (guard continuing without GraphQL coverage)');
            } else {
                \WP_CLI::warning($msg);
            }
        }

        $failures = 0;
        foreach ($tests as $test) {
            $requestArgs = [
                'timeout' => 15,
                'headers' => $test['headers'],
                'method'  => $test['method'] ?? 'GET',
                'body'    => $test['body'] ?? null,
            ];
            $resp = wp_remote_request($test['url'], $requestArgs);
            if (is_wp_error($resp)) {
                \WP_CLI::warning(sprintf('%s: %s', $test['label'], $resp->get_error_message()));
                $failures++;
                continue;
            }
            $code = wp_remote_retrieve_response_code($resp);
            if ($code === 200) {
                \WP_CLI::line(sprintf('✅ %s (%d)', $test['label'], $code));
            } else {
                \WP_CLI::warning(sprintf('⚠️ %s returned HTTP %d', $test['label'], $code));
                $failures++;
            }
        }

        if ($failures === 0) {
            \WP_CLI::success('CJ connectivity looks healthy.');
            return;
        }

        $message = sprintf('%d CJ connectivity check(s) failed.', $failures);
        if ($guardMode) {
            \WP_CLI::error($message);
        }
        \WP_CLI::warning($message);
    }

    /**
     * Fetch and store the advertiser list.
     */
    private static function sync_shops(): array
    {
        $creds = self::get_credentials(true);
        $synced = [];
        $skipped = 0;
        $page = 1;

        do {
            $response = self::request_advertiser_lookup([
                'advertiser-ids'   => 'joined',
                'records-per-page' => 100,
                'page-number'      => $page,
                'requestor-cid'    => $creds['publisher_id'],
            ]);

            $items = self::extract_items($response, 'advertisers', 'advertiser');
            if (!$items) {
                break;
            }

            foreach ($items as $item) {
                $id   = self::field($item, 'advertiser-id');
                $name = self::field($item, 'program-name');
                if ($name === '') {
                    $name = self::field($item, 'advertiser-name');
                }
                if ($id === '' || $name === '') {
                    $skipped++;
                    continue;
                }

                $parentCategory = '';
                $childCategory = '';
                if (!empty($item['categories']['category'])) {
                    $cat = $item['categories']['category'];
                    if (is_array($cat)) {
                        $childCategory = (string) reset($cat);
                    } else {
                        $childCategory = (string) $cat;
                    }
                }
                if (!empty($item['primary-category'])) {
                    $cat = $item['primary-category'];
                    if (is_array($cat)) {
                        $parentCategory = (string) ($cat['parent'] ?? '');
                        if ($childCategory === '') {
                            $childCategory = (string) ($cat['child'] ?? '');
                        }
                    } else {
                        $parentCategory = (string) $cat;
                    }
                }
                $categoryLabel = self::normalize_category($childCategory, $parentCategory);
                $slug = 'cj-' . $id;
                $programUrl = self::field($item, 'program-url');
                $logoUrl = ''; 
                if ($programUrl !== '') {
                    $logoUrl = self::derive_logo_url($programUrl);
                }

                $synced[$id] = [
                    'advertiser_id'     => $id,
                    'name'              => $name,
                    'program_url'       => $programUrl,
                    'primary_category'  => $categoryLabel,
                    'status'            => strtolower(self::field($item, 'relationship-status') ?: 'joined'),
                    'slug'              => $slug,
                    'tracking_template' => '',
                    'last_seen_at'      => current_time('mysql'),
                    'logo_url'          => $logoUrl,
                ];
            }

            $page++;
            $meta = self::extract_meta($response, 'advertisers');
            $totalPages = $meta['total-pages'] ?? $page;
        } while ($page <= $totalPages);

        update_option(self::OPTION_SHOPS, array_values($synced), false);
        update_option(self::OPTION_SHOPS_SYNCED, current_time('mysql'), false);

        return [
            'synced'  => count($synced),
            'skipped' => $skipped,
        ];
    }

    /**
     * Fetch commissions and insert them into the ledger.
     *
     * @param string $window ISO8601 duration (e.g. PT2H)
     */
    private static function sync_ledger(string $window, ?string $since, string $dedupeStrategy): array
    {
        $creds = self::get_credentials(true);
        if (empty($creds['publisher_pat'])) {
            throw new RuntimeException('CJ publisher PAT missing; cannot sync ledger.');
        }

        $now = time();
        $cursorTimestamp = get_option(self::OPTION_CURSOR);
        if ($since) {
            $startTs = strtotime($since) ?: $now - DAY_IN_SECONDS;
        } else {
            $startTs = $cursorTimestamp ? max(strtotime($cursorTimestamp) - MINUTE_IN_SECONDS * 5, $now - DAY_IN_SECONDS) : $now - 2 * HOUR_IN_SECONDS;
        }

        try {
            $interval = new DateInterval($window);
        } catch (Exception $e) {
            $interval = new DateInterval('PT2H');
        }
        $startIso = gmdate('Y-m-d\TH:i:s\Z', $startTs);
        $durationSeconds = (int) $interval->format('%s');
        $endIso = $since ? gmdate('Y-m-d\TH:i:s\Z', $now) : gmdate('Y-m-d\TH:i:s\Z', min($now, $startTs + $durationSeconds));

        $batch = [];
        $cursorId = null;
        do {
            $payload = self::fetch_commissions_graphql($creds, $startIso, $endIso, $cursorId);
            if (!$payload) {
                break;
            }
            $records = $payload['records'] ?? [];
            if ($records) {
                foreach ($records as $record) {
                    $converted = self::map_graphql_commission($record);
                    if ($converted) {
                        $normalized = self::normalize_commission($converted);
                        if ($normalized) {
                            $batch[] = $normalized;
                        }
                    }
                }
            }
            if (!empty($payload['payloadComplete'])) {
                break;
            }
            $cursorId = $payload['maxCommissionId'] ?? null;
        } while ($cursorId);

        $result = self::ingest_commissions($batch, $dedupeStrategy === 'update' ? 'update' : 'replace');

        update_option(self::OPTION_CURSOR, $endIso, false);

        return $result;
    }

    private static function sync_links(array $filters, int $limit, string $output): array
    {
        $creds = self::get_credentials(true);
        $collected = [];
        $pageSize = isset($filters['records-per-page']) ? max(1, min(100, (int) $filters['records-per-page'])) : 100;
        $linkTypes = $filters['link-type'] ?? 'Product Link';
        if (!is_array($linkTypes)) {
            $linkTypes = array_filter(array_map('trim', explode(',', (string) $linkTypes)));
        }
        if (!$linkTypes) {
            $linkTypes = ['Product Link'];
        }
        $linkTypes = array_values(array_unique($linkTypes));

        $advertiserFilters = [];
        if (isset($filters['advertiser-ids'])) {
            $advertiserFilters = is_array($filters['advertiser-ids'])
                ? array_filter(array_map('trim', $filters['advertiser-ids']))
                : array_filter(array_map('trim', explode(',', (string) $filters['advertiser-ids'])));
        }
        if (!$advertiserFilters) {
            $advertiserFilters = [(string) ($filters['relationship-status'] ?? 'joined')];
        }
        $fallbackAdvertisers = $advertiserFilters;
        if (count($advertiserFilters) === 1 && in_array(strtolower($advertiserFilters[0]), ['joined', 'all', ''], true)) {
            $products = get_option(self::OPTION_PRODUCTS, []);
            if (is_array($products) && !empty($products['items']) && is_array($products['items'])) {
                $derived = [];
                foreach ($products['items'] as $item) {
                    $id = isset($item['advertiser_id']) ? trim((string) $item['advertiser_id']) : '';
                    if ($id !== '') {
                        $derived[$id] = true;
                    }
                }
                if ($derived) {
                    $advertiserFilters = array_keys($derived);
                }
            }
        }
        if (!$advertiserFilters) {
            $advertiserFilters = $fallbackAdvertisers;
        }

        foreach ($linkTypes as $type) {
            foreach ($advertiserFilters as $advertiserFilter) {
                if ($advertiserFilter === '') {
                    $advertiserFilter = 'joined';
                }
                $page = 1;
                do {
                    $params = [
                        'website-id'          => $creds['website_id'],
                        'link-type'           => $type,
                    'advertiser-ids'      => $advertiserFilter,
                    'records-per-page'    => $pageSize,
                    'page-number'         => $page,
                    'promotion-type'      => $filters['promotion-type'] ?? null,
                    'promotion-start-date'=> $filters['promotion-start-date'] ?? null,
                    'promotion-end-date'  => $filters['promotion-end-date'] ?? null,
                    'keywords'            => $filters['keywords'] ?? null,
                    ];
                    $response = self::request_link_search($params);
                    $items = self::extract_items($response, 'links', 'link');
                if ($items) {
                    foreach ($items as $item) {
                        $normalized = self::normalize_link($item);
                        if ($normalized) {
                            $key = $normalized['advertiser_id'] . ':' . $normalized['link_id'];
                            $collected[$key] = $normalized;
                            if (count($collected) >= $limit) {
                                break 2;
                            }
                        }
                    }
                }
                $page++;
                $meta = self::extract_meta($response, 'links');
                $totalPages = $meta['total-pages'] ?? $page;
                } while ($page <= $totalPages && count($collected) < $limit);

                if (count($collected) >= $limit) {
                    break 2;
                }
            }
        }

        $payload = array_values($collected);
        update_option(self::OPTION_LINKS, $payload, false);
        self::write_json($output, $payload);

        return [
            'count' => count($payload),
            'path'  => $output,
        ];
    }

    private static function sync_products(int $limit): array
    {
        $creds = self::get_credentials(true);
        $pat = $creds['publisher_pat'];
        if ($pat === '') {
            throw new RuntimeException('CJ PAT is required for Ads API product sync.');
        }
        $companyId = $creds['publisher_id'];
        if ($companyId === '') {
            throw new RuntimeException('CJ companyId missing for Ads API product sync.');
        }

        $targetAdvertisers = max(1, $limit);
        $feedPool = self::collect_product_feeds($companyId, $pat, $targetAdvertisers * 2);
        $feeds = $feedPool['rows'];
        if (!$feeds) {
            throw new RuntimeException('CJ product feed list is empty for this publisher.');
        }

        $best = [];
        $productCount = 0;
        foreach ($feeds as $feed) {
            if (count($best) >= $targetAdvertisers) {
                break;
            }
            $advertiserId = $feed['advertiser_id'] ?? '';
            $adId = $feed['ad_id'] ?? '';
            if ($advertiserId === '' || $adId === '') {
                continue;
            }

            $product = self::fetch_best_product_for_feed($companyId, $pat, $adId, $advertiserId);
            if ($product) {
                $slug = 'cj-' . $advertiserId;
                $best[$slug] = $product;
                $productCount++;
            }
        }

        update_option(self::OPTION_PRODUCTS, [
            'synced_at' => current_time('mysql'),
            'items'     => $best,
        ], false);

        return [
            'advertisers' => count($best),
            'products'    => $productCount,
            'next_page'   => $feedPool['has_more'] ? 'offset:' . $feedPool['next_offset'] : null,
        ];
    }

    private static function collect_product_feeds(string $companyId, string $token, int $desired): array
    {
        $query = <<<'GQL'
query ImpactShopProductFeeds($company: ID!, $limit: Int!, $offset: Int) {
  productFeeds(companyId: $company, limit: $limit, offset: $offset) {
    count
    totalCount
    resultList {
      adId
      advertiserId
      advertiserName
      productCount
      lastUpdated
      feedName
    }
  }
}
GQL;

        $offset = 0;
        $batchSize = 50;
        $unique = [];
        $loops = 0;
        $totalCount = null;

        while ($loops < 20 && count($unique) < $desired) {
            $variables = [
                'company' => $companyId,
                'limit'   => $batchSize,
                'offset'  => $offset,
            ];
            $response = self::request_ads_graphql($query, $variables, $token);
            $data = $response['data']['productFeeds'] ?? null;
            if (!$data) {
                break;
            }
            $totalCount = isset($data['totalCount']) ? (int) $data['totalCount'] : $totalCount;
            $items = isset($data['resultList']) && is_array($data['resultList']) ? $data['resultList'] : [];
            if (!$items) {
                break;
            }

            foreach ($items as $item) {
                $advertiserId = trim((string)($item['advertiserId'] ?? ''));
                $adId = trim((string)($item['adId'] ?? ''));
                if ($advertiserId === '' || $adId === '') {
                    continue;
                }
                $score = self::score_feed_candidate($item);
                if (!isset($unique[$advertiserId]) || $score > $unique[$advertiserId]['score']) {
                    $unique[$advertiserId] = [
                        'advertiser_id'   => $advertiserId,
                        'advertiser_name' => trim((string)($item['advertiserName'] ?? '')),
                        'ad_id'           => $adId,
                        'product_count'   => (int)($item['productCount'] ?? 0),
                        'last_updated'    => $item['lastUpdated'] ?? '',
                        'feed_name'       => trim((string)($item['feedName'] ?? '')),
                        'score'           => $score,
                    ];
                }
            }

            $offset += $batchSize;
            $loops++;
            if ($totalCount !== null && $offset >= $totalCount) {
                break;
            }
        }

        uasort($unique, static fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'rows'        => array_values($unique),
            'next_offset' => $offset,
            'has_more'    => $totalCount !== null ? ($offset < $totalCount) : false,
        ];
    }

    private static function fetch_best_product_for_feed(string $companyId, string $token, string $adId, string $advertiserId): ?array
    {
        $query = <<<'GQL'
query ImpactShopProductsByFeed($company: ID!, $ad: ID!, $limit: Int!, $page: String) {
  products(companyId: $company, adIds: [$ad], limit: $limit, page: $page) {
    nextPage
    resultList {
      advertiserId
      advertiserName
      adId
      title
      description
      link
      imageLink
      price { amount currency }
      salePrice { amount currency }
    }
  }
}
GQL;

        $page = null;
        $attempts = 0;
        $best = null;

        do {
            $variables = [
                'company' => $companyId,
                'ad'      => $adId,
                'limit'   => 40,
                'page'    => $page,
            ];
            $response = self::request_ads_graphql($query, $variables, $token);
            $data = $response['data']['products'] ?? null;
            if (!$data) {
                break;
            }
            $items = isset($data['resultList']) && is_array($data['resultList']) ? $data['resultList'] : [];
            foreach ($items as $item) {
                $itemAdvertiser = trim((string)($item['advertiserId'] ?? ''));
                if ($itemAdvertiser === '' || $itemAdvertiser !== $advertiserId) {
                    continue;
                }
                $score = self::score_product_candidate($item);
                if ($score <= 0) {
                    continue;
                }
                if ($best === null || $score > $best['score']) {
                    $best = [
                        'advertiser_id'   => $advertiserId,
                        'advertiser_name' => trim((string)($item['advertiserName'] ?? '')),
                        'ad_id'           => (string)($item['adId'] ?? $adId),
                        'title'           => self::pick_product_title($item, $advertiserId),
                        'description'     => trim((string)($item['description'] ?? '')),
                        'link'            => trim((string)($item['link'] ?? '')),
                        'image_link'      => trim((string)($item['imageLink'] ?? '')),
                        'price'           => self::normalize_amount($item['price'] ?? null),
                        'sale_price'      => self::normalize_amount($item['salePrice'] ?? null),
                        'score'           => $score,
                    ];
                }
                if (!empty($best['image_link'])) {
                    break 2;
                }
            }

            $page = $data['nextPage'] ?? null;
            $attempts++;
        } while ($page && $attempts < 5);

        if ($best) {
            unset($best['score']);
        }

        return $best;
    }

    private static function score_feed_candidate(array $item): float
    {
        $count = (int)($item['productCount'] ?? 0);
        $timestamp = 0;
        if (!empty($item['lastUpdated'])) {
            $parsed = strtotime($item['lastUpdated']);
            if ($parsed !== false) {
                $timestamp = $parsed;
            }
        }

        return ($count * 1000) + ($timestamp / DAY_IN_SECONDS);
    }

    /**
     * Convert API response into ledger-ready arrays.
     */
    private static function normalize_commission(array $item): ?array
    {
        $actionId = self::field($item, 'action-id');
        $advertiserId = self::field($item, 'advertiser-id');
        $advertiserName = self::field($item, 'advertiser-name');
        $eventDate = self::field($item, 'event-date');
        $statusRaw = strtolower(self::field($item, 'action-status'));
        $sid = self::field($item, 'sid');
        if ($actionId === '' || $advertiserId === '' || $eventDate === '' || $sid === '') {
            self::queue_failure($item, 'missing required fields');
            return null;
        }

        $parts = array_pad(preg_split('~[|~]~', $sid), 2, '');
        $ngoSlug = sanitize_title($parts[0]);
        $pseudoFromSid = self::extract_pseudo_from_sid($sid);
        $pseudoId = $pseudoFromSid !== '' ? $pseudoFromSid : self::pseudo_from_sid($sid, $actionId);
        if ($ngoSlug === '') {
            self::queue_failure($item, 'missing NGO slug in sid');
            return null;
        }

        $amount = (float) self::field($item, 'commission-amount');
        if ($amount <= 0) {
            self::queue_failure($item, 'commission amount <= 0');
            return null;
        }

        $currency = strtoupper(self::field($item, 'commission-currency') ?: 'USD');
        $rate = self::get_exchange_rate($currency);
        if ($rate <= 0) {
            $rate = (float) apply_filters('impactshop_cj_fx_fallback', 390.0, $currency);
        }
        $donationMultiplier = $pseudoFromSid !== '' ? self::resolve_donation_multiplier($pseudoFromSid, $timestamp) : 1.0;
        $amountHuf = (int) round($amount * $rate * $donationMultiplier);

        $status = self::normalize_status($statusRaw);
        if ($status === 'corrected') {
            self::queue_failure($item, 'corrected commissions require manual handling');
            return null;
        }

        $timestamp = strtotime($eventDate) ?: time();

        return [
            'source_ref'   => 'CJ:' . $actionId,
            'pseudo_id'    => $pseudoId,
            'ngo_slug'     => $ngoSlug,
            'ngo_display'  => function_exists('impactshop_resolve_ngo_name') ? impactshop_resolve_ngo_name($ngoSlug) : $ngoSlug,
            'shop_slug'    => 'cj-' . $advertiserId,
            'shop_display' => $advertiserName ?: ('CJ #' . $advertiserId),
            'amount_huf'   => max(0, $amountHuf),
            'channel'      => 'cj',
            'status'       => $status,
            'happened_at'  => gmdate('Y-m-d H:i:s', $timestamp),
        ];
    }

    private static function ingest_commissions(array $batch, string $dedupeStrategy = 'replace'): array
    {
        global $wpdb;

        $inserted = 0;
        $updated = 0;
        $failed = 0;

        if (!$batch) {
            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
                'failed'   => 0,
            ];
        }

        $table = $wpdb->prefix . 'impact_ledger';
        foreach ($batch as $row) {
            $data = [
                'pseudo_id'    => substr($row['pseudo_id'], 0, 12),
                'ngo_slug'     => $row['ngo_slug'],
                'ngo_display'  => $row['ngo_display'],
                'shop_slug'    => $row['shop_slug'],
                'shop_display' => $row['shop_display'],
                'amount_huf'   => $row['amount_huf'],
                'channel'      => $row['channel'],
                'status'       => $row['status'],
                'happened_at'  => $row['happened_at'],
                'source_ref'   => $row['source_ref'],
            ];

            $formats = [
                '%s', '%s', '%s', '%s', '%s',
                '%d', '%s', '%s', '%s', '%s',
            ];

            $result = $dedupeStrategy === 'update'
                ? self::upsert_ledger_row($table, $data)
                : $wpdb->replace($table, $data, $formats);

            if ($result === false) {
                $failed++;
                self::queue_failure($row, 'db_error');
            } else {
                $rowsAffected = $wpdb->rows_affected ?? 0;
                if ($rowsAffected === 1) {
                    $inserted++;
                } elseif ($rowsAffected >= 2) {
                    $updated++;
                }
            }
        }

        return [
            'inserted' => $inserted,
            'updated'  => $updated,
            'skipped'  => 0,
            'failed'   => $failed,
        ];
    }

    private static function request(string $url, array $params): array
    {
        $creds = self::get_credentials(true);
        $url = add_query_arg(array_filter($params, static fn($v) => $v !== null && $v !== ''), $url);
        $response = wp_remote_get($url, [
            'timeout' => self::API_TIMEOUT,
            'headers' => [
                'Authorization' => $creds['developer_key'],
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('CJ API error: ' . $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code === 429) {
            throw new RuntimeException('CJ API rate limit reached');
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                throw new RuntimeException('CJ API response invalid JSON/XML');
            }
            $data = json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true);
        }

        return $data;
    }

    private static function request_link_search(array $params): array
    {
        $url = add_query_arg(array_filter($params, static fn($v) => $v !== null && $v !== ''), 'https://link-search.api.cj.com/v2/link-search');
        $creds = self::get_credentials(true);
        $response = wp_remote_get($url, [
            'timeout' => self::API_TIMEOUT,
            'headers' => [
                'Authorization' => $creds['developer_key'],
                'Accept'        => 'application/xml',
            ],
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException('CJ Link Search error: ' . $response->get_error_message());
        }
        $body = (string) wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('CJ Link Search invalid XML response');
        }
        $data = json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true);
        if (isset($data['cj-api']) && is_array($data['cj-api'])) {
            return $data['cj-api'];
        }
        return $data;
    }

    private static function request_ads_graphql(string $query, array $variables, string $token): array
    {
        $response = wp_remote_post(self::ADS_ENDPOINT, [
            'timeout' => self::GRAPHQL_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'query'     => $query,
                'variables' => $variables,
            ]),
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException('CJ Ads API error: ' . $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('CJ Ads API invalid response (HTTP %d)', $code));
        }
        if (!empty($decoded['errors'])) {
            $messages = [];
            foreach ((array) $decoded['errors'] as $error) {
                if (!empty($error['message'])) {
                    $messages[] = (string) $error['message'];
                }
            }
            throw new RuntimeException('CJ Ads API GraphQL error: ' . implode('; ', $messages));
        }
        return $decoded;
    }

    private static function normalize_amount($node): ?array
    {
        if (!is_array($node)) {
            return null;
        }
        $amount = isset($node['amount']) ? (float) $node['amount'] : null;
        $currency = isset($node['currency']) ? trim((string) $node['currency']) : '';
        if ($amount === null || $currency === '') {
            return null;
        }
        return [
            'amount'   => $amount,
            'currency' => $currency,
        ];
    }

    private static function score_product_candidate(array $item): int
    {
        $score = 0;
        if (!empty($item['salePrice']['amount'])) {
            $score += 5;
        }
        if (!empty($item['price']['amount'])) {
            $score += 3;
        }
        if (!empty($item['imageLink'])) {
            $score += 2;
        }
        if (!empty($item['link'])) {
            $score += 2;
        }
        if (!empty($item['title'])) {
            $score += 1;
        }
        return $score;
    }

    private static function pick_product_title(array $item, string $fallbackId): string
    {
        $title = trim((string) ($item['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }
        $description = trim((string) ($item['description'] ?? ''));
        if ($description !== '') {
            return $description;
        }
        return 'CJ #' . $fallbackId;
    }

    private static function request_advertiser_lookup(array $params): array
    {
        $url = add_query_arg(array_filter($params, static fn($v) => $v !== null && $v !== ''), 'https://advertiser-lookup.api.cj.com/v2/advertiser-lookup');
        $creds = self::get_credentials(true);
        $response = wp_remote_get($url, [
            'timeout' => self::API_TIMEOUT,
            'headers' => [
                'Authorization' => $creds['developer_key'],
                'Accept'        => 'application/xml',
            ],
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException('CJ Advertiser Lookup error: ' . $response->get_error_message());
        }
        $body = (string) wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('CJ Advertiser Lookup invalid XML response');
        }
        return json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true);
    }

    private static function fetch_commissions_graphql(array $creds, string $since, string $before, ?string $cursor): array
    {
        $query = <<<'GRAPHQL'
query ($publisherIds: [String!]!, $since: String, $before: String, $cursor: String, $websiteIds: [String!]) {
  publisherCommissions(
    forPublishers: $publisherIds,
    sincePostingDate: $since,
    beforePostingDate: $before,
    sinceCommissionId: $cursor,
    websiteIds: $websiteIds
  ) {
    payloadComplete
    maxCommissionId
    records {
      commissionId
      actionStatus
      advertiserId
      advertiserName
      postingDate
      eventDate
      shopperId
      pubCommissionAmountPubCurrency
      pubCommissionAmountUsd
    }
  }
}
GRAPHQL;

        $body = [
            'query' => $query,
            'variables' => [
                'publisherIds' => [$creds['publisher_id']],
                'since'        => $since,
                'before'       => $before,
                'cursor'       => $cursor,
                'websiteIds'   => [$creds['website_id']],
            ],
        ];

        $response = wp_remote_post('https://commissions.api.cj.com/query', [
            'timeout' => self::GRAPHQL_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $creds['publisher_pat'],
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException('CJ GraphQL error: ' . $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($rawBody, true);
        if ($code !== 200) {
            $message = is_array($payload) && !empty($payload['errors'])
                ? wp_json_encode($payload['errors'])
                : $rawBody;
            throw new RuntimeException('CJ GraphQL HTTP ' . $code . ': ' . $message);
        }
        if (!is_array($payload)) {
            throw new RuntimeException('CJ GraphQL returned invalid JSON');
        }
        if (!empty($payload['errors'])) {
            throw new RuntimeException('CJ GraphQL error: ' . wp_json_encode($payload['errors']));
        }
        return $payload['data']['publisherCommissions'] ?? [];
    }

    private static function extract_items(array $data, string $root, string $key): array
    {
        if (empty($data[$root][$key])) {
            return [];
        }
        $items = $data[$root][$key];
        if (isset($items[0])) {
            return $items;
        }
        return [$items];
    }

    private static function extract_meta(array $data, string $root): array
    {
        $meta = $data[$root]['@attributes'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        return [
            'total-matched'   => (int) ($meta['total-matched'] ?? 0),
            'records-returned'=> (int) ($meta['records-returned'] ?? 0),
            'page-number'     => (int) ($meta['page-number'] ?? 1),
            'total-pages'     => (int) ($meta['total-pages'] ?? 1),
        ];
    }

    private static function upsert_ledger_row(string $table, array $data)
    {
        global $wpdb;

        $sql = "
            INSERT INTO {$table}
              (pseudo_id, ngo_slug, ngo_display, shop_slug, shop_display, amount_huf, channel, status, happened_at, source_ref)
            VALUES (%s,%s,%s,%s,%s,%d,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
              ngo_display = VALUES(ngo_display),
              shop_display = VALUES(shop_display),
              amount_huf = VALUES(amount_huf),
              status = VALUES(status),
              happened_at = VALUES(happened_at),
              channel = VALUES(channel)
        ";

        $prepared = $wpdb->prepare(
            $sql,
            $data['pseudo_id'],
            $data['ngo_slug'],
            $data['ngo_display'],
            $data['shop_slug'],
            $data['shop_display'],
            $data['amount_huf'],
            $data['channel'],
            $data['status'],
            $data['happened_at'],
            $data['source_ref']
        );

        return $wpdb->query($prepared);
    }

    private static function field(array $item, string $field): string
    {
        if (isset($item[$field])) {
            if (is_array($item[$field]) && isset($item[$field][0])) {
                return (string) $item[$field][0];
            }
            return (string) $item[$field];
        }
        return '';
    }

    private static function parse_feed_csv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return $rows;
        }
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return $rows;
        }
        $header = array_map('trim', $header);
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $data);
        }
        fclose($handle);
        return $rows;
    }

    private static function map_graphql_commission(array $record): array
    {
        $amount = null;
        $currency = '';
        if (isset($record['pubCommissionAmountPubCurrency']) && $record['pubCommissionAmountPubCurrency'] !== null) {
            $amount = (string) $record['pubCommissionAmountPubCurrency'];
            $currency = 'HUF';
        }
        if (($amount === null || (float) $amount <= 0) && isset($record['pubCommissionAmountUsd']) && $record['pubCommissionAmountUsd'] !== null) {
            $amount = (string) $record['pubCommissionAmountUsd'];
            $currency = 'USD';
        }

        return [
            'action-id'          => (string) ($record['commissionId'] ?? ''),
            'advertiser-id'      => (string) ($record['advertiserId'] ?? ''),
            'advertiser-name'    => (string) ($record['advertiserName'] ?? ''),
            'event-date'         => (string) ($record['eventDate'] ?? ($record['postingDate'] ?? '')),
            'action-status'      => (string) ($record['actionStatus'] ?? ''),
            'sid'                => (string) ($record['shopperId'] ?? ($record['sid'] ?? '')),
            'commission-amount'  => $amount ?? '',
            'commission-currency'=> $currency ?: 'HUF',
        ];
    }

    private static function extract_scalar($value): string
    {
        if (is_string($value)) {
            return trim($value === 'Array' ? '' : $value);
        }
        if (is_array($value)) {
            if (isset($value[0]) && is_string($value[0])) {
                return trim($value[0]);
            }
            if (isset($value['@attributes'])) {
                foreach ($value['@attributes'] as $attr) {
                    if (is_string($attr) && $attr !== '') {
                        return trim($attr);
                    }
                }
            }
        }
        return '';
    }

    private static function normalize_category(string ...$values): string
    {
        foreach ($values as $value) {
            $candidate = trim($value);
            if ($candidate === '') {
                continue;
            }
            $key = strtolower($candidate);
            if (isset(self::CATEGORY_ALIASES[$key])) {
                return self::CATEGORY_ALIASES[$key];
            }
            if (isset(self::CATEGORY_ALIASES[str_replace('&', 'and', $key)])) {
                return self::CATEGORY_ALIASES[str_replace('&', 'and', $key)];
            }
            return ucwords($candidate);
        }
        return 'Egyéb';
    }

    private static function derive_logo_url(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }
        return 'https://logo.clearbit.com/' . $host;
    }

    private static function parse_targeted_countries(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $parts = preg_split('~[,\s]+~', strtoupper($value), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($parts ?: []));
    }

    private static function normalize_bool(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'y'], true);
    }

    private static function advertiser_map(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $rows = get_option(self::OPTION_SHOPS, []);
        $map = [];
        foreach ($rows as $row) {
            $id = (string) ($row['advertiser_id'] ?? '');
            if ($id !== '') {
                $map[$id] = $row;
            }
        }
        return $cache = $map;
    }

    private static function rank_feeds(array $rows): array
    {
        $now = time();
        $counts = [];
        foreach ($rows as $row) {
            $id = trim($row['Advertiser Id'] ?? '');
            if ($id !== '') {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }
        arsort($counts);
        $topAdvertisers = array_slice(array_keys($counts), 0, 5);

        $ranked = [];
        foreach ($rows as $row) {
            $lastImportRaw = trim($row['Last Import Date'] ?? '');
            $ts = $lastImportRaw ? strtotime($lastImportRaw) : false;
            $days = $ts ? (int) floor(($now - $ts) / DAY_IN_SECONDS) : 9999;
            if ($days < 30) {
                $priority = 'P0';
            } elseif ($days < 90) {
                $priority = 'P1';
            } else {
                $priority = 'P2';
            }
            if (in_array($row['Advertiser Id'] ?? '', $topAdvertisers, true)) {
                $priority = 'P0';
            }

            $row['priority'] = $priority;
            $row['last_import_days'] = $days < 9999 ? $days : null;
            $row['last_import_iso'] = $ts ? gmdate('Y-m-d', $ts) : '';
            $row['advertiser_feed_count'] = $counts[$row['Advertiser Id'] ?? ''] ?? 1;
            $ranked[] = $row;
        }

        return $ranked;
    }

    private static function write_csv(string $path, array $rows): void
    {
        if (!$rows) {
            return;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $handle = fopen($path, 'wb');
        if (!$handle) {
            throw new RuntimeException(sprintf('Unable to write to %s', $path));
        }
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private static function normalize_link(array $item): ?array
    {
        $linkId = self::field($item, 'link-id');
        $advertiserId = self::field($item, 'advertiser-id');
        if ($linkId === '' || $advertiserId === '') {
            return null;
        }
        $clickUrl = self::field($item, 'clickUrl');
        $shopMap = self::advertiser_map();
        $shop = $shopMap[$advertiserId] ?? null;
        $couponCode = self::extract_scalar($item['coupon-code'] ?? '');
        $promotionType = self::field($item, 'promotion-type');
        $category = $shop['primary_category'] ?? self::normalize_category(self::field($item, 'category'));
        $logoUrl = $shop['logo_url'] ?? '';
        if ($logoUrl === '') {
            $logoUrl = self::derive_logo_url(self::field($item, 'destination'));
        }
        $isCoupon = ($couponCode !== '') || stripos($promotionType, 'coupon') !== false;
        return [
            'link_id'          => $linkId,
            'advertiser_id'    => $advertiserId,
            'advertiser_name'  => self::field($item, 'advertiser-name'),
            'link_name'        => self::field($item, 'link-name'),
            'description'      => self::field($item, 'description'),
            'creative_text'    => self::field($item, 'creative-text'),
            'language'         => self::field($item, 'language'),
            'promotion_type'   => $promotionType,
            'coupon_code'      => $couponCode,
            'click_url'        => $clickUrl,
            'destination'      => self::field($item, 'destination'),
            'promotion_start'  => self::extract_scalar($item['promotion-start-date'] ?? ''),
            'promotion_end'    => self::extract_scalar($item['promotion-end-date'] ?? ''),
            'currency'         => self::extract_scalar($item['currency'] ?? ''),
            'price'            => self::extract_scalar($item['price'] ?? ''),
            'category'         => $category,
            'logo_url'         => $logoUrl,
            'is_coupon'        => $isCoupon,
            'allow_deep_linking' => self::normalize_bool(self::field($item, 'allow-deep-linking')),
            'targeted_countries' => self::parse_targeted_countries(self::field($item, 'targeted-countries')),
        ];
    }

    private static function write_json(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        file_put_contents($path, wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function get_credentials(bool $required = false): array
    {
        $key = getenv('CJ_DEVELOPER_KEY');
        if ($key === false && defined('CJ_DEVELOPER_KEY')) {
            $key = CJ_DEVELOPER_KEY;
        }
        $pid = getenv('CJ_PUBLISHER_ID');
        if ($pid === false && defined('CJ_PUBLISHER_ID')) {
            $pid = CJ_PUBLISHER_ID;
        }
        $pat = getenv('CJ_PUBLISHER_PAT');
        if ($pat === false && defined('CJ_PUBLISHER_PAT')) {
            $pat = CJ_PUBLISHER_PAT;
        }
        $website = getenv('CJ_WEBSITE_ID');
        if ($website === false && defined('CJ_WEBSITE_ID')) {
            $website = CJ_WEBSITE_ID;
        }

        $key = is_string($key) ? trim($key) : '';
        $pid = is_string($pid) ? trim($pid) : '';
        $pat = is_string($pat) ? trim($pat) : '';
        $website = is_string($website) ? trim($website) : '';
        if ($website === '') {
            $website = $pid;
        }

        $authHeader = '';
        if ($pat !== '') {
            $authHeader = 'Bearer ' . $pat;
        } elseif ($key !== '') {
            $authHeader = $key;
        }

        if ($required && ($authHeader === '' || $pid === '')) {
            throw new RuntimeException('CJ credentials missing. Provide CJ_PUBLISHER_PAT (preferred) or CJ_DEVELOPER_KEY and CJ_PUBLISHER_ID.');
        }

        return [
            'developer_key' => $authHeader,
            'publisher_id'  => $pid,
            'publisher_pat' => $pat,
            'website_id'    => $website ?: $pid,
        ];
    }

    private static function is_enabled(): bool
    {
        if (defined('IMPACTSHOP_CJ_ENABLED')) {
            return (bool) IMPACTSHOP_CJ_ENABLED;
        }
        $option = get_option(self::OPTION_ENABLED, '1');
        return $option === '1' || $option === 1 || $option === true;
    }

    private static function set_enabled(bool $enabled): void
    {
        update_option(self::OPTION_ENABLED, $enabled ? '1' : '0', false);
    }

    private static function assert_enabled_cli(): void
    {
        if (!self::is_enabled()) {
            throw new RuntimeException('CJ integration disabled. Run `wp impactshop cj:toggle --on` to re-enable.');
        }
    }

    private static function normalize_status(string $status): string
    {
        return match ($status) {
            'locked', 'approved' => 'approved',
            'pending'           => 'pending',
            'corrected', 'rejected' => 'corrected',
            default             => 'pending',
        };
    }

    private static function pseudo_from_sid(string $sid, string $fallback): string
    {
        $fromSid = self::extract_pseudo_from_sid($sid);
        if ($fromSid !== '') {
            return $fromSid;
        }
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $sid));
        if ($clean !== '') {
            return substr($clean, 0, 12);
        }
        return strtoupper(substr(md5($fallback), 0, 12));
    }

    private static function extract_pseudo_from_sid(string $sid): string
    {
        $parts = preg_split('~[|~]~', $sid);
        if (is_array($parts) && isset($parts[1])) {
            $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $parts[1]));
            if ($clean !== '') {
                return substr($clean, 0, 12);
            }
        }
        return '';
    }

    private static function resolve_donation_multiplier(string $pseudoId, ?int $timestamp = null): float
    {
        $pseudoId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $pseudoId));
        if ($pseudoId === '') {
            return 1.0;
        }
        $cacheKey = $pseudoId . '|' . ($timestamp ? date('Y-m-d H:i:s', $timestamp) : 'now');
        if (isset(self::$donationMultiplierCache[$cacheKey])) {
            return self::$donationMultiplierCache[$cacheKey];
        }

        $multiplier = null;
        if ($timestamp !== null) {
            $multiplier = self::resolve_multiplier_from_history($pseudoId, $timestamp);
        }
        if ($multiplier === null && class_exists('Sharity_Level_Manager')) {
            $levelManager = new Sharity_Level_Manager();
            $level = $levelManager->calculate_level_for_pseudo($pseudoId);
            $config = $levelManager->get_level_config($level);
            $multiplier = isset($config['multiplier']) ? (float) $config['multiplier'] : 1.0;
        } elseif ($multiplier === null) {
            $multiplier = self::resolve_multiplier_from_table($pseudoId);
        }

        $multiplier = max(1.0, (float) $multiplier);
        $multiplier = (float) apply_filters('impactshop_donation_multiplier', $multiplier, $pseudoId, $timestamp);
        $multiplier = (float) apply_filters('impactshop_cj_donation_multiplier', $multiplier, $pseudoId, $timestamp);
        $multiplier = max(1.0, min(1.25, $multiplier));
        self::$donationMultiplierCache[$cacheKey] = $multiplier;
        return $multiplier;
    }

    private static function resolve_multiplier_from_history(string $pseudoId, int $timestamp): ?float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'level_history';
        static $tableExists = null;
        if ($tableExists === null) {
            $tableExists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        }
        if (!$tableExists) {
            return null;
        }

        $level = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT new_level FROM {$table} WHERE LOWER(pseudo_id) = %s AND created_at <= %s ORDER BY created_at DESC LIMIT 1",
            strtolower($pseudoId),
            date('Y-m-d H:i:s', $timestamp)
        ));
        if ($level === '') {
            return null;
        }
        return self::multiplier_from_level($level);
    }

    private static function resolve_multiplier_from_table(string $pseudoId): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'user_points';
        $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($tableExists !== $table) {
            return 1.0;
        }

        $level = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT current_level FROM {$table} WHERE LOWER(pseudo_id) = %s",
            strtolower($pseudoId)
        ));

        return self::multiplier_from_level($level);
    }

    private static function multiplier_from_level(string $level): float
    {
        $map = [
            'legend'   => 1.25,
            'platinum' => 1.20,
            'gold'     => 1.15,
            'silver'   => 1.10,
            'bronze'   => 1.05,
            'basic'    => 1.00,
        ];
        $key = strtolower(trim($level));
        return $map[$key] ?? 1.0;
    }

    private static function queue_failure(array $payload, string $reason): void
    {
        $queue = get_option(self::OPTION_FAILED, []);
        if (!is_array($queue)) {
            $queue = [];
        }
        $queue[] = [
            'payload' => $payload,
            'reason'  => $reason,
            'logged_at' => current_time('mysql'),
        ];
        if (count($queue) > self::FAILED_QUEUE_LIMIT) {
            $queue = array_slice($queue, -self::FAILED_QUEUE_LIMIT);
        }
        update_option(self::OPTION_FAILED, $queue, false);
    }

    private static function get_exchange_rate(string $currency): float
    {
        $currency = strtoupper($currency);
        if ($currency === 'HUF') {
            return 1.0;
        }
        $rate = apply_filters('impactshop_fx_resolve_rate', null, $currency);
        if (is_numeric($rate) && (float) $rate > 0) {
            return (float) $rate;
        }
        $fallback = apply_filters('impactshop_fx_static_fallback', 0.0, $currency);
        return (float) max(0, $fallback);
    }
}

ImpactShop_CJ::bootstrap();
