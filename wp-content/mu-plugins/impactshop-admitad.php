<?php
/**
 * Plugin Name: Impactshop Admitad
 * Description: Impact Shop ownership marker and default-off, read-only Admitad shadow adapter.
 */

if (!defined('ABSPATH')) {
    return;
}

function impactshop_admitad_ownership_meta(): void
{
    if (wp_parse_url(home_url('/'), PHP_URL_HOST) !== 'app.sharity.hu') {
        return;
    }
    $path = wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path !== '/impactshop/' && $path !== '/impactshop') {
        return;
    }
    echo '<meta name="mitgo-verification" content="348a9888-0449-4a2e-8dbb-122bd395fe0a" />' . "\n";
}
add_action('wp_head', 'impactshop_admitad_ownership_meta');


/**
 * Publisher API evidence only. No catalog publication, outbound or economic writes.
 * Official contracts: developers.mitgo.com Publisher ad spaces / Affiliate programs /
 * Publisher reports (read 2026-11-16). Credentials are supplied by the server caller.
 */
final class ImpactShop_Admitad
{
    private const WEBSITE_ID = '2888572';
    private const WEBSITE_URL = 'https://app.sharity.hu/impactshop/';
    private const BODY_LIMIT = 1048576;

    /**
     * $config: enabled (strict bool), access_token, approved_campaign_ids (string IDs).
     * $input: limit (1..100), offset; actions also campaign, from/until
     * (provider status-update wall time, d.m.Y H:i:s, without invented timezone).
     * Returns one page plus explicit continuation; nothing is persisted.
     */
    public static function shadow(string $resource, array $input = [], array $config = []): array
    {
        if (($config['enabled'] ?? false) !== true) {
            throw new RuntimeException('admitad_shadow_disabled');
        }
        $token = $config['access_token'] ?? '';
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9._~+\/-]{8,4096}=*$/D', $token)) {
            throw new RuntimeException('admitad_token_missing_or_invalid');
        }
        if (!in_array($resource, ['sites', 'programs', 'actions'], true)) {
            throw new RuntimeException('admitad_resource_invalid');
        }
        $allowed = $resource === 'sites' ? [] : ['limit', 'offset'];
        if ($resource === 'actions') { $allowed = array_merge($allowed, ['campaign', 'from', 'until']); }
        if (array_diff(array_keys($input), $allowed)) { throw new RuntimeException('admitad_input_invalid'); }
        $limit = self::integer($input['limit'] ?? 100, 1, 100);
        $offset = self::integer($input['offset'] ?? 0, 0, 1000000000);
        $query = ['limit' => $limit, 'offset' => $offset];
        $campaign = '';
        if ($resource === 'actions') {
            $campaign = self::id($input['campaign'] ?? null);
            $approved = $config['approved_campaign_ids'] ?? [];
            if (!is_array($approved) || !in_array($campaign, $approved, true)) {
                throw new RuntimeException('admitad_campaign_not_approved');
            }
            $from = self::date($input['from'] ?? null);
            $until = self::date($input['until'] ?? null);
            if ($from > $until) { throw new RuntimeException('admitad_date_range_invalid'); }
            $query += ['website' => self::WEBSITE_ID, 'campaign' => $campaign, 'total' => 0,
                'status_updated_start' => $from->format('d.m.Y H:i:s'),
                'status_updated_end' => $until->format('d.m.Y H:i:s')];
        }
        $started = microtime(true);
        $requests = 0;
        $sites = self::request('/websites/v2/', [], $token, $started, $requests);
        if (!array_is_list($sites) || count($sites) > 100) { throw new RuntimeException('admitad_sites_invalid'); }
        $site = null;
        foreach ($sites as $row) {
            if (!is_array($row)) { throw new RuntimeException('admitad_sites_invalid'); }
            if (self::id($row['id'] ?? null) !== self::WEBSITE_ID) { continue; }
            if ($site !== null || ($row['site_url'] ?? null) !== self::WEBSITE_URL) {
                throw new RuntimeException('admitad_site_binding_mismatch');
            }
            $site = ['id' => self::WEBSITE_ID, 'site_url' => self::WEBSITE_URL,
                'status' => self::text($row['status'] ?? null, 64),
                'validation_passed' => is_bool($row['validation_passed'] ?? null) ? $row['validation_passed'] : null];
        }
        if ($site === null) { throw new RuntimeException('admitad_site_not_found'); }
        $result = ['provider' => 'admitad', 'mode' => 'read_only_shadow', 'outbound_enabled' => false,
            'economic_writes_enabled' => false, 'site' => $site, 'resource' => $resource, 'rows' => [],
            'complete' => true, 'next_offset' => null];
        if ($resource !== 'sites') {
            $path = $resource === 'programs' ? '/advcampaigns/website/' . self::WEBSITE_ID . '/' : '/statistics/actions/';
            $page = self::request($path, $query, $token, $started, $requests);
            $rows = $page['results'] ?? null;
            $meta = $page['_meta'] ?? null;
            if (!is_array($rows) || !array_is_list($rows) || !is_array($meta) || count($rows) > $limit
                || self::integer($meta['limit'] ?? null, 1, 100) !== $limit
                || self::integer($meta['offset'] ?? null, 0, 1000000000) !== $offset) {
                throw new RuntimeException('admitad_page_invalid');
            }
            $count = self::integer($meta['count'] ?? null, 0, 1000000000);
            if (count($rows) !== min($limit, max(0, $count - $offset))) {
                throw new RuntimeException('admitad_page_incomplete');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) { throw new RuntimeException('admitad_row_invalid'); }
                $result['rows'][] = $resource === 'programs' ? self::program($row) : self::action($row, $campaign);
            }
            $result['total'] = $count;
            $result['complete'] = $offset + count($rows) >= $count;
            $result['next_offset'] = $result['complete'] ? null : $offset + count($rows);
        }
        // Even an unexpected upstream echo of the bearer must not reach operator output.
        array_walk_recursive($result, static function ($value) use ($token): void {
            if (is_string($value) && strpos($value, $token) !== false) {
                throw new RuntimeException('admitad_response_contains_secret');
            }
        });
        if (microtime(true) - $started > 20) { throw new RuntimeException('admitad_budget_exceeded'); }
        return $result;
    }

    private static function request(string $path, array $query, string $token, float $started, int &$requests): array
    {
        if (++$requests > 2 || microtime(true) - $started >= 20) { throw new RuntimeException('admitad_budget_exceeded'); }
        $url = 'https://api.admitad.com' . $path;
        if ($query) { $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986); }
        try {
            $response = wp_remote_get($url, ['timeout' => 8, 'redirection' => 0, 'sslverify' => true,
                'limit_response_size' => self::BODY_LIMIT + 1, 'headers' => ['Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json']]);
        } catch (Throwable $e) {
            throw new RuntimeException('admitad_transport_failed');
        }
        // Provider/transport text and response bodies are never included in exceptions.
        if (is_wp_error($response)) { throw new RuntimeException('admitad_transport_failed'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) { throw new RuntimeException('admitad_http_' . $code); }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || strlen($body) > self::BODY_LIMIT) { throw new RuntimeException('admitad_response_too_large'); }
        if (microtime(true) - $started > 20) { throw new RuntimeException('admitad_budget_exceeded'); }
        try {
            // Validate original JSON, then use the existing Dognet numeric-lexeme convention.
            json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            $exact = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/s',
                static fn(array $m): string => '"' . $m[0] . '"', $body);
            $value = json_decode($exact ?? '', true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('admitad_json_invalid');
        }
        if (!is_array($value)) { throw new RuntimeException('admitad_response_invalid'); }
        return $value;
    }

    private static function program(array $row): array
    {
        return ['campaign_id' => self::id($row['id'] ?? null),
            'status' => self::text($row['status'] ?? null, 64),
            'connection_status' => self::text($row['connection_status'] ?? null, 64),
            'site_url' => self::text($row['site_url'] ?? null, 2048),
            'outbound_enabled' => false];
    }

    private static function action(array $row, string $campaign): array
    {
        if (self::id($row['advcampaign_id'] ?? null) !== $campaign
            || (isset($row['website_id']) && self::id($row['website_id']) !== self::WEBSITE_ID)) {
            throw new RuntimeException('admitad_action_binding_mismatch');
        }
        $currency = $row['currency'] ?? null;
        if (!is_string($currency) || !preg_match('/^[A-Z]{3}$/D', $currency)) { throw new RuntimeException('admitad_currency_invalid'); }
        $subid = $row['subid'] ?? null;
        // Shadow has no attribution authority. Only an opaque SAT1 value may leave this parser.
        if (!is_string($subid) || !preg_match('/^sat1_[A-Za-z0-9_-]{43}$/D', $subid)) { $subid = null; }
        return ['website_id' => self::WEBSITE_ID, 'campaign_id' => $campaign,
            'action_id' => self::id($row['action_id'] ?? null),
            'order_id' => isset($row['order_id']) ? self::text($row['order_id'], 256) : null,
            'commission' => self::decimal($row['payment'] ?? null),
            'order_amount' => self::decimal($row['cart'] ?? null, true), 'currency' => $currency,
            'status' => self::text($row['status'] ?? null, 64),
            'paid' => self::flag($row['paid'] ?? null), 'processed' => self::flag($row['processed'] ?? null),
            'action_date' => self::optional_text($row, 'action_date'),
            'status_updated' => self::optional_text($row, 'status_updated'),
            'closing_date' => self::optional_text($row, 'closing_date'), 'subid' => $subid];
    }

    private static function decimal($value, bool $nullable = false): ?string
    {
        if ($nullable && $value === null) { return null; }
        if (!is_string($value) || strlen($value) > 64 || !preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $value)) {
            throw new RuntimeException('admitad_decimal_invalid');
        }
        return $value;
    }

    private static function flag($value): ?bool
    {
        if ($value === null) { return null; }
        if ($value !== '0' && $value !== '1') { throw new RuntimeException('admitad_flag_invalid'); }
        return $value === '1';
    }

    private static function integer($value, int $min, int $max): int
    {
        if (is_int($value)) { $value = (string) $value; }
        if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]{0,9})$/D', $value)
            || (int) $value < $min || (int) $value > $max) { throw new RuntimeException('admitad_integer_invalid'); }
        return (int) $value;
    }

    private static function id($value): string
    {
        if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,19}$/D', $value)) { throw new RuntimeException('admitad_id_invalid'); }
        return $value;
    }

    private static function text($value, int $max): string
    {
        if (!is_string($value) || $value === '' || strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new RuntimeException('admitad_field_invalid');
        }
        return $value;
    }

    private static function optional_text(array $row, string $key): ?string
    {
        return isset($row[$key]) ? self::text($row[$key], 32) : null;
    }

    private static function date($value): DateTimeImmutable
    {
        if (!is_string($value)) { throw new RuntimeException('admitad_date_invalid'); }
        $date = DateTimeImmutable::createFromFormat('!d.m.Y H:i:s', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('d.m.Y H:i:s') !== $value) { throw new RuntimeException('admitad_date_invalid'); }
        return $date;
    }

    /** `wp impactshop admitad shadow sites|programs|actions [--campaign=ID --from=... --until=...]` */
    public static function cli_shadow(array $args, array $assoc): void
    {
        try {
            if (count($args) !== 1) { throw new RuntimeException('admitad_resource_invalid'); }
            $campaigns = getenv('IMPACTSHOP_ADMITAD_APPROVED_CAMPAIGN_IDS');
            $result = self::shadow($args[0], $assoc, [
                'enabled' => getenv('IMPACTSHOP_ADMITAD_SHADOW_ENABLED') === '1',
                'access_token' => getenv('IMPACTSHOP_ADMITAD_ACCESS_TOKEN') ?: '',
                'approved_campaign_ids' => $campaigns === false || $campaigns === '' ? [] : explode(',', $campaigns),
            ]);
            \WP_CLI::line(wp_json_encode($result, JSON_UNESCAPED_SLASHES));
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $secret = getenv('IMPACTSHOP_ADMITAD_ACCESS_TOKEN') ?: '';
            if (!preg_match('/^admitad_[a-z_]+(?:[0-9]{3})?$/D', $message)
                || ($secret !== '' && strpos($message, $secret) !== false)) {
                $message = 'admitad_shadow_failed';
            }
            \WP_CLI::error($message);
        }
    }
}

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('impactshop admitad shadow', [ImpactShop_Admitad::class, 'cli_shadow']);
}
