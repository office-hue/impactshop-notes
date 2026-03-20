<?php
/**
 * Plugin Name: ImpactShop – Cégjelző NGO Registry
 * Description: Cégjelző API-ból NGO adatok gazdagítása, tiltás logika és AI Agent export.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ImpactShop_Cegjelzo
{
    private const TABLE = 'impactshop_ngo_registry';
    private const CRON_HOOK = 'impactshop_cegjelzo_weekly_sync';
    private const STATS_OPTION = 'impactshop_cegjelzo_daily_stats';
    private const CURSOR_OPTION = 'impactshop_cegjelzo_sync_cursor';
    private const LAST_SYNC_OPTION = 'impactshop_cegjelzo_last_sync';
    private const EXPORT_PATH = 'impactshop/ngo-registry.json';
    private const MAX_BATCH = 20;

    public static function boot(): void
    {
        add_action('muplugins_loaded', [__CLASS__, 'maybe_install']);
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action(self::CRON_HOOK, [__CLASS__, 'sync_weekly']);
        add_filter('impactshop_ngo_card_allow_slug', [__CLASS__, 'filter_blocked_slug'], 10, 2);
        add_filter('impactshop_ngo_card_display_name', [__CLASS__, 'filter_display_name'], 10, 3);

        if (defined('WP_CLI') && WP_CLI) {
            self::register_cli();
        }
    }

    public static function maybe_install(): void
    {
        $version = get_option('impactshop_cegjelzo_schema_version', '');
        if ($version === '1.0.0') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(190) NOT NULL,
            cegjelzo_id VARCHAR(50) DEFAULT NULL,
            official_name VARCHAR(500) DEFAULT NULL,
            short_name VARCHAR(255) DEFAULT NULL,
            display_name VARCHAR(255) DEFAULT NULL,
            org_type VARCHAR(100) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            tax_number VARCHAR(20) DEFAULT NULL,
            registration_number VARCHAR(50) DEFAULT NULL,
            registration_date DATE DEFAULT NULL,
            status_code TINYINT(1) DEFAULT NULL,
            status_label VARCHAR(80) DEFAULT NULL,
            level_of_charity VARCHAR(100) DEFAULT NULL,
            activity TEXT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            representatives JSON DEFAULT NULL,
            proceedings JSON DEFAULT NULL,
            has_proceedings TINYINT(1) DEFAULT 0,
            blocked_reason VARCHAR(255) DEFAULT NULL,
            blocked_at DATETIME DEFAULT NULL,
            enrichment_source VARCHAR(40) DEFAULT 'manual',
            cegjelzo_raw_response JSON DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug_unique (slug),
            KEY idx_cegjelzo_id (cegjelzo_id),
            KEY idx_status_code (status_code),
            KEY idx_blocked_at (blocked_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('impactshop_cegjelzo_schema_version', '1.0.0');
    }

    public static function schedule_cron(): void
    {
        add_filter('cron_schedules', static function (array $schedules): array {
            if (!isset($schedules['weekly'])) {
                $schedules['weekly'] = [
                    'interval' => 7 * DAY_IN_SECONDS,
                    'display'  => __('Hetente', 'impactshop'),
                ];
            }
            return $schedules;
        });

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'weekly', self::CRON_HOOK);
        }
    }

    public static function sync_weekly(): void
    {
        self::sync_batch(self::MAX_BATCH);
    }

    public static function sync_batch(int $limit = 20, bool $force = false): array
    {
        $slugs = self::collect_slugs();
        $total = count($slugs);
        if ($total === 0) {
            return ['processed' => 0, 'total' => 0, 'updated' => 0];
        }

        $cursor = (int) get_option(self::CURSOR_OPTION, 0);
        $batch = array_slice($slugs, $cursor, $limit);
        if (empty($batch)) {
            $cursor = 0;
            $batch = array_slice($slugs, 0, $limit);
        }

        $client = new ImpactShop_Cegjelzo_Client();
        $processed = 0;
        $updated = 0;

        foreach ($batch as $slug => $name) {
            $processed++;
            $result = self::enrich_slug($client, $slug, $name, $force);
            if (!is_wp_error($result) && $result) {
                $updated++;
            }
        }

        $cursor = ($cursor + $processed) % max(1, $total);
        update_option(self::CURSOR_OPTION, $cursor, false);
        update_option(self::LAST_SYNC_OPTION, current_time('mysql'), false);
        self::write_export();
        do_action('impactshop_ngo_card_purge_cache');

        update_option(self::STATS_OPTION, [
            'processed' => $processed,
            'updated'   => $updated,
            'total'     => $total,
            'at'        => current_time('mysql'),
        ], false);

        return ['processed' => $processed, 'updated' => $updated, 'total' => $total];
    }

    private static function collect_slugs(): array
    {
        $items = [];
        if (class_exists('ImpactShop_NGO_Card_API') && method_exists('ImpactShop_NGO_Card_API', 'get_dataset_items')) {
            $dataset = ImpactShop_NGO_Card_API::get_dataset_items(true);
            foreach ($dataset as $slug => $item) {
                $slug = sanitize_title($slug);
                $name = $item['name'] ?? '';
                if ($slug !== '') {
                    $items[$slug] = (string) $name;
                }
            }
        }

        return $items;
    }

    private static function enrich_slug(ImpactShop_Cegjelzo_Client $client, string $slug, string $displayName, bool $force): bool|WP_Error
    {
        $existing = self::get_registry($slug);
        if (!$force && $existing && !self::should_refresh($existing)) {
            return false;
        }

        $searchName = self::resolve_search_name($slug, $displayName);
        if (mb_strlen($searchName) < 3) {
            return new WP_Error('cegjelzo_short_name', 'Túl rövid keresési név.');
        }

        $results = $client->search_civil_org($searchName, 'name', null, 5);
        if (is_wp_error($results)) {
            return $results;
        }

        $best = self::find_best_match($results, $slug, $searchName);
        if (!$best) {
            $auto = $client->autocomplete_civil_org($searchName, 5);
            if (!is_wp_error($auto) && !empty($auto['items'][0]['id'])) {
                $id = (string) $auto['items'][0]['id'];
                $detail = $client->search_civil_org($id, 'reg_number', null, 5);
                if (!is_wp_error($detail)) {
                    $best = self::find_best_match($detail, $slug, $searchName);
                }
            }
        }

        if (!$best) {
            return new WP_Error('cegjelzo_no_match', "Nem található NGO: {$slug}");
        }

        $payload = self::normalize_record($best, $slug, $displayName);
        self::upsert_registry($payload);
        return true;
    }

    private static function should_refresh(array $row): bool
    {
        $updated = $row['updated_at'] ?? '';
        if (!$updated) {
            return true;
        }
        $ts = strtotime($updated);
        if (!$ts) {
            return true;
        }
        return (time() - $ts) > WEEK_IN_SECONDS;
    }

    private static function resolve_search_name(string $slug, string $displayName): string
    {
        if ($displayName !== '' && sanitize_title($displayName) !== $slug) {
            return $displayName;
        }
        if (function_exists('impactshop_resolve_ngo_name')) {
            $name = impactshop_resolve_ngo_name($slug);
            if ($name !== '') {
                return $name;
            }
        }
        return str_replace('-', ' ', $slug);
    }

    private static function find_best_match(array $results, string $slug, string $searchName): ?array
    {
        $items = $results['items'] ?? $results['data'] ?? $results;
        if (!is_array($items)) {
            return null;
        }

        $searchNameLower = mb_strtolower($searchName);
        $best = null;
        $bestScore = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $longName = mb_strtolower((string) ($item['long_name'] ?? ''));
            $shortName = mb_strtolower((string) ($item['short_name'] ?? ''));
            $score = 0;
            if ($longName !== '') {
                similar_text($searchNameLower, $longName, $longPct);
                $score += $longPct;
            }
            if ($shortName !== '') {
                similar_text($searchNameLower, $shortName, $shortPct);
                $score = max($score, $shortPct);
            }
            if (isset($item['registration_number'])) {
                $score += 5;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        return ($bestScore >= 60) ? $best : $best;
    }

    private static function normalize_record(array $raw, string $slug, string $displayName): array
    {
        $statusLabel = (string) ($raw['status'] ?? '');
        $statusCode = isset($raw['status_code']) ? (int) $raw['status_code'] : null;
        $proceedings = $raw['proceedings'] ?? null;
        $hasProceedings = self::has_risky_proceedings($proceedings, $statusLabel);
        $blockedReason = $hasProceedings ? 'proceedings' : '';
        if ($statusCode === 0) {
            $blockedReason = 'inactive';
        }

        return [
            'slug'                => $slug,
            'cegjelzo_id'          => self::first_value($raw['registration_number'] ?? $raw['id'] ?? null),
            'official_name'        => self::first_value($raw['long_name'] ?? ''),
            'short_name'           => self::first_value($raw['short_name'] ?? ''),
            'display_name'         => $displayName,
            'org_type'             => self::first_value($raw['type'] ?? ''),
            'address'              => self::first_value($raw['address'] ?? ''),
            'tax_number'           => self::first_value($raw['tax_number'] ?? ''),
            'registration_number'  => self::first_value($raw['registration_number'] ?? ''),
            'registration_date'    => self::first_value($raw['insertion'] ?? ''),
            'status_code'          => $statusCode,
            'status_label'         => $statusLabel,
            'level_of_charity'     => self::first_value($raw['level_of_charity'] ?? ''),
            'activity'             => self::first_value($raw['activity'] ?? ''),
            'description'          => self::first_value($raw['description'] ?? ''),
            'representatives'      => wp_json_encode($raw['representatives'] ?? []),
            'proceedings'          => wp_json_encode($proceedings ?? []),
            'has_proceedings'      => $hasProceedings ? 1 : 0,
            'blocked_reason'       => $blockedReason ?: null,
            'blocked_at'           => $blockedReason ? current_time('mysql') : null,
            'enrichment_source'    => 'cegjelzo',
            'cegjelzo_raw_response'=> wp_json_encode($raw),
            'updated_at'           => current_time('mysql'),
            'created_at'           => current_time('mysql'),
        ];
    }

    private static function first_value($value): ?string
    {
        if (is_array($value)) {
            $value = $value[0]['value'] ?? $value[0] ?? '';
        }
        $value = is_scalar($value) ? (string) $value : '';
        return $value !== '' ? $value : null;
    }

    private static function has_risky_proceedings($proceedings, string $statusLabel): bool
    {
        $text = $statusLabel . ' ' . wp_json_encode($proceedings);
        $normalized = self::normalize_text($text);
        $keywords = [
            'kenyszertorles',
            'felszamolas',
            'vegelzamolas',
            'vegrehajtas',
            'adotartozas',
            'csodeljaras',
            'torles',
        ];
        foreach ($keywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function normalize_text(string $text): string
    {
        $text = mb_strtolower($text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        return trim($text);
    }

    private static function upsert_registry(array $payload): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $slug = $payload['slug'];
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($existing) {
            unset($payload['created_at']);
            $wpdb->update($table, $payload, ['slug' => $slug]);
        } else {
            $wpdb->insert($table, $payload);
        }
    }

    private static function get_registry(string $slug): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug), ARRAY_A);
        return $row ?: null;
    }

    public static function filter_blocked_slug(bool $allow, string $slug): bool
    {
        if (!$allow) {
            return false;
        }
        $row = self::get_registry($slug);
        if (!$row) {
            return true;
        }
        if (!empty($row['blocked_reason'])) {
            return false;
        }
        if (!empty($row['status_code']) && (int) $row['status_code'] === 0) {
            return false;
        }
        if (!empty($row['has_proceedings'])) {
            return false;
        }
        return true;
    }

    public static function filter_display_name(string $name, string $slug, string $rawName): string
    {
        $row = self::get_registry($slug);
        if (!$row) {
            return $name;
        }
        $official = $row['official_name'] ?? '';
        $short = $row['short_name'] ?? '';
        if ($official) {
            return $official;
        }
        if ($short) {
            return $short;
        }
        return $name ?: $rawName;
    }

    public static function register_routes(): void
    {
        register_rest_route('impact/v1', '/cegjelzo/ngo-registry', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_registry'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function rest_registry(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows = $wpdb->get_results("SELECT slug, official_name, short_name, status_code, status_label, level_of_charity, activity, description, updated_at, blocked_reason, blocked_at FROM {$table}", ARRAY_A);
        return new WP_REST_Response(['items' => $rows], 200);
    }

    private static function write_export(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows = $wpdb->get_results("SELECT slug, official_name, short_name, status_code, status_label, level_of_charity, activity, description, updated_at, blocked_reason, blocked_at FROM {$table}", ARRAY_A);
        $payload = [
            'generated_at' => gmdate('c'),
            'items' => $rows,
        ];
        $upload_dir = wp_upload_dir(null, false);
        if (!empty($upload_dir['basedir'])) {
            $path = trailingslashit($upload_dir['basedir']) . self::EXPORT_PATH;
            wp_mkdir_p(dirname($path));
            file_put_contents($path, wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    private static function register_cli(): void
    {
        WP_CLI::add_command('impactshop cegjelzo sync', function ($args, $assoc) {
            $limit = isset($assoc['limit']) ? (int) $assoc['limit'] : self::MAX_BATCH;
            $force = !empty($assoc['force']);
            $result = self::sync_batch($limit, $force);
            WP_CLI::success(sprintf('Processed %d/%d (updated %d).', $result['processed'], $result['total'], $result['updated']));
        });

        WP_CLI::add_command('impactshop cegjelzo test-connection', function () {
            $client = new ImpactShop_Cegjelzo_Client();
            $result = $client->autocomplete_civil_org('alap', 1);
            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }
            WP_CLI::success('Cégjelző API elérhető.');
        });
    }
}

final class ImpactShop_Cegjelzo_Client
{
    private const PROD_BASE = 'https://api.cegjelzo.com/api/v2';
    private const TEST_BASE = 'https://dev.api.cegjelzo.com/api/v2';
    private const DEFAULT_TIMEOUT = 12;
    private const MAX_RETRIES = 2;
    private const RETRY_DELAY_MS = 250;
    private const CIVIL_ORG_FIELDS = [
        'status',
        'status_code',
        'long_name',
        'short_name',
        'address',
        'nav_address',
        'tax_number',
        'activity',
        'bank_accounts',
        'constituent_document_date',
        'description',
        'insertion',
        'leading_orgs',
        'level_of_charity',
        'proceedings',
        'registration_number',
        'representatives',
        'type',
        'updated_at',
    ];
    private const NAV_FIELDS = [
        'nav_address',
    ];

    private string $apiKey;
    private string $clientId;
    private bool $useTestEndpoint;

    public function __construct(?string $apiKey = null, ?string $clientId = null, bool $test = false)
    {
        $this->apiKey = $apiKey ?? (defined('IMPACTSHOP_CEGJELZO_API_KEY') ? IMPACTSHOP_CEGJELZO_API_KEY : (string) get_option('impactshop_cegjelzo_api_key', ''));
        $this->clientId = $clientId ?? (defined('IMPACTSHOP_CEGJELZO_CLIENT_ID') ? IMPACTSHOP_CEGJELZO_CLIENT_ID : (string) get_option('impactshop_cegjelzo_client_id', ''));
        $this->useTestEndpoint = $test || (bool) get_option('impactshop_cegjelzo_test_mode', false);
    }

    public function autocomplete_civil_org(string $name, int $limit = 10): array|WP_Error
    {
        if (mb_strlen($name) < 3) {
            return new WP_Error('cegjelzo_short_query', 'Minimum 3 karakter szükséges.', ['status' => 400]);
        }
        return $this->get('/autocomplete', [
            'search'      => $name,
            'type'        => 'civil_orgs',
            'limit'       => $limit,
            'only-active' => 1,
        ]);
    }

    public function search_civil_org(string $value, string $searchType = 'name', ?array $fields = null, int $limit = 5): array|WP_Error
    {
        $params = [
            'value' => $value,
            'type'  => $searchType,
            'limit' => $limit,
        ];
        $requestFields = $fields ?? array_merge(self::CIVIL_ORG_FIELDS, self::NAV_FIELDS);
        return $this->get('/search', $params, [
            'X-Fields' => implode(',', $requestFields),
        ]);
    }

    private function get(string $endpoint, array $params = [], array $extraHeaders = []): array|WP_Error
    {
        if ($this->apiKey === '' || $this->clientId === '') {
            return new WP_Error('cegjelzo_not_configured', 'Cégjelző API kulcs vagy Client ID nincs beállítva.');
        }

        $base = $this->useTestEndpoint ? self::TEST_BASE : self::PROD_BASE;
        $url  = $base . $endpoint . '?' . http_build_query($params);

        $headers = array_merge([
            'X-Api-Key'   => $this->apiKey,
            'X-Client-Id' => $this->clientId,
            'Accept'      => 'application/json',
        ], $extraHeaders);

        $attempt = 0;
        while ($attempt <= self::MAX_RETRIES) {
            $response = wp_remote_get($url, [
                'headers' => $headers,
                'timeout' => self::DEFAULT_TIMEOUT,
            ]);
            if (is_wp_error($response)) {
                $attempt++;
                if ($attempt <= self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
                continue;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code === 429) {
                $attempt++;
                if ($attempt <= self::MAX_RETRIES) {
                    usleep(1000 * 1000);
                }
                continue;
            }
            if ($code === 401) {
                return new WP_Error('cegjelzo_unauthorized', 'Érvénytelen API kulcs vagy Client ID.', ['status' => 401]);
            }
            if ($code === 403) {
                return new WP_Error('cegjelzo_forbidden', 'Lejárt előfizetés vagy hozzáférés megtagadva.', ['status' => 403]);
            }
            if ($code >= 400) {
                $decoded = json_decode($body, true);
                $message = $decoded['message'] ?? "HTTP {$code} hiba a Cégjelző API-tól.";
                return new WP_Error('cegjelzo_api_error', $message, ['status' => $code, 'body' => $body]);
            }

            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                return new WP_Error('cegjelzo_invalid_response', 'Nem értelmezhető API válasz.', ['body' => $body]);
            }
            return $decoded;
        }

        return new WP_Error('cegjelzo_unknown_error', 'Ismeretlen hiba a Cégjelző API hívásnál.');
    }
}

ImpactShop_Cegjelzo::boot();
