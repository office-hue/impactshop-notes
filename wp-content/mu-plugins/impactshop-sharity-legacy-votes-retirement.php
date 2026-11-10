<?php
declare(strict_types=1);
/** Plugin Name: Sharity legacy votes retirement */
if (!defined('ABSPATH')) exit;

/** Based on the bounded Hermes draft; native SQL, fail-closed and race handling reviewed here. */
final class Sharity_Legacy_Votes_Retirement
{
    public const OPTION = 'sharity_legacy_votes_retirement_v1';
    private const FACTS = ['pseudo_id', 'order_id', 'transaction_id', 'original_transaction_id', 'amount', 'currency', 'payout_usd', 'currency_amount', 'offer_id', 'status'];

    private static function connection(): mysqli
    {
        global $wpdb;
        if (!isset($wpdb->dbh) || !$wpdb->dbh instanceof mysqli || !preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->prefix)) throw new RuntimeException('retirement_database_unavailable');
        return $wpdb->dbh;
    }

    private static function read(mysqli $db, string $key): ?array
    {
        global $wpdb;
        $result = $db->query("SELECT option_value,autoload FROM `{$wpdb->prefix}options` WHERE option_name='" . $db->real_escape_string($key) . "' LIMIT 1");
        if (!$result instanceof mysqli_result) throw new RuntimeException('retirement_read_failed');
        $row = $result->fetch_assoc();
        if ($row === null) return null;
        if ($row['autoload'] !== 'no') throw new RuntimeException('retirement_option_invalid');
        $value = json_decode($row['option_value'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('retirement_option_invalid');
        return $value;
    }

    public static function sealed(): bool
    {
        $value = self::read(self::connection(), self::OPTION);
        if ($value === null) return false;
        if (($value['schema_version'] ?? null) !== 1 || ($value['sealed'] ?? null) !== true) throw new RuntimeException('retirement_option_invalid');
        return true;
    }

    public static function blocked(): bool
    {
        // Installing this module permanently closes the old product, even if its option disappears.
        return true;
    }

    public static function response(): WP_REST_Response
    {
        return self::reply(['code' => 'legacy_votes_retired', 'message' => 'A régi Impact Challenge végleg lezárult.', 'destination' => 'https://sharity.hu/sajat-sharity'], 410);
    }

    private static function reply(array $value, int $status): WP_REST_Response
    {
        $response = new WP_REST_Response($value, $status);
        $response->header('Cache-Control', 'private, no-store');
        return $response;
    }

    /** Caller authenticates the exact supplied fields BEFORE entering; this service never awards anything. */
    public static function capture(string $provider, string $eventId, string $eventType, array $facts, string $verification): WP_REST_Response
    {
        try {
            if (!self::sealed()) return self::reply(['code' => 'retirement_not_sealed'], 503);
            $content = self::content($provider, $eventId, $eventType, $facts, $verification);
            $hash = hash('sha256', self::json($content));
            $name = 'sharity_legacy_pending_' . hash('sha256', self::json([$provider, $eventId, $eventType]));
            $db = self::connection();
            // MariaDB exposes transaction state in SQL; mysqli has no server_status property.
            $mode = $db->query('SELECT @@autocommit AS enabled,@@in_transaction AS active');
            if (!$mode instanceof mysqli_result) throw new RuntimeException('transaction_state_unavailable');
            $state = $mode->fetch_assoc();
            if ((string) ($state['active'] ?? '') !== '0' || (string) ($state['enabled'] ?? '') !== '1') return self::reply(['code' => 'active_transaction_forbidden'], 503);
            $existing = self::read($db, $name);
            if ($existing !== null) return self::receipt($existing, $content, $hash, true);
            $record = ['schema_version' => 1] + $content + ['payload_hash' => $hash, 'state' => 'pending_manual', 'received_at_utc' => gmdate('Y-m-d H:i:s')];
            global $wpdb;
            $sql = "INSERT INTO `{$wpdb->prefix}options` (option_name,option_value,autoload) VALUES ('" . $db->real_escape_string($name) . "','" . $db->real_escape_string(self::json($record)) . "','no')";
            $duplicate = false;
            try {
                if ($db->query($sql) === false) {
                    if ($db->errno !== 1062) throw new RuntimeException('pending_insert_failed');
                    $duplicate = true;
                }
            } catch (mysqli_sql_exception $error) {
                if ($error->getCode() !== 1062) throw $error;
                $duplicate = true;
            }
            $readback = self::read($db, $name);
            if ($readback === null) throw new RuntimeException('pending_readback_failed');
            return self::receipt($readback, $content, $hash, $duplicate);
        } catch (InvalidArgumentException $ignored) { return self::reply(['code' => 'invalid_pending_event'], 400); }
        catch (Throwable $ignored) { return self::reply(['code' => 'pending_storage_unavailable'], 503); }
    }

    private static function content(string $provider, string $eventId, string $type, array $facts, string $verification): array
    {
        if (!preg_match('/^[a-z0-9_-]{1,40}$/D', $provider) || $eventId === '' || strlen($eventId) > 128 || preg_match('/[\x00-\x1f\x7f]/', $eventId)
            || !in_array($type, ['conversion', 'reversal', 'checkout_completed'], true) || !in_array($verification, ['signed_payload', 'transaction_reference_only'], true)) throw new InvalidArgumentException('invalid_event');
        foreach ($facts as $key => $value) {
            if (!in_array($key, self::FACTS, true) || !(is_string($value) || is_int($value) || is_bool($value) || $value === null) || (is_string($value) && strlen($value) > 512)) throw new InvalidArgumentException('invalid_fact');
        }
        ksort($facts, SORT_STRING);
        $content = ['provider' => $provider, 'event_id' => $eventId, 'event_type' => $type, 'verification' => $verification, 'facts' => $facts];
        try { $json = self::json($content); } catch (Throwable $ignored) { throw new InvalidArgumentException('invalid_encoding'); }
        if (strlen($json) > 16384) throw new InvalidArgumentException('event_too_large');
        return $content;
    }

    private static function json(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }

    private static function receipt(array $row, array $content, string $hash, bool $duplicate): WP_REST_Response
    {
        if (($row['schema_version'] ?? null) !== 1 || !isset($row['payload_hash']) || !preg_match('/^[a-f0-9]{64}$/D', (string) $row['payload_hash'])) throw new RuntimeException('invalid_pending_record');
        try { $stored = self::content($row['provider'] ?? '', $row['event_id'] ?? '', $row['event_type'] ?? '', $row['facts'] ?? [], $row['verification'] ?? ''); }
        catch (Throwable $ignored) { throw new RuntimeException('invalid_pending_record'); }
        if (!hash_equals($row['payload_hash'], hash('sha256', self::json($stored)))) throw new RuntimeException('invalid_pending_hash');
        if (!hash_equals($row['payload_hash'], $hash) || $stored !== $content) return self::reply(['code' => 'pending_content_conflict'], 409);
        return self::reply(['code' => 'pending_manual', 'duplicate' => $duplicate], 200);
    }

    public static function bootstrap(): void
    {
        add_action('template_redirect', static function (): void {
            $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if (is_string($path) && preg_match('~^/impact-challenge/offerwall/ow_[a-f0-9]{40}/?$~D', $path)) {
                nocache_headers();
                wp_die('A régi Impact Challenge végleg lezárult. <a href="https://sharity.hu/sajat-sharity">Szívügyeim megnyitása</a>', 'Lezárt játék', ['response' => 410]);
            }
        }, -1000);
        // ACF's priority-10 filter can reset an earlier REST short-circuit to null.
        add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
            if ($result !== null) return $result;
            $route = $request->get_route();
            if (($route === '/sharity/v1/offerwall/read-model' || preg_match('~^/impact-challenge/offerwall/ow_[a-f0-9]{40}$~D', $route)) && self::blocked()) return self::response();
            return $result;
        }, 20, 3);
    }
}
Sharity_Legacy_Votes_Retirement::bootstrap();
