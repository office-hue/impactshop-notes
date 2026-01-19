<?php
/**
 * WHAT: Token Store helper (encrypt/decrypt) + lejárat ellenőrzés guard.
 * WHY: Az AI Publishing Loop biztonságos token kezeléséhez szükség van egységes, titkosított tárolásra és lejárat monitorra.
 * HOW: AES-256-GCM titkosítás (master key env/KMS-re felkészítve), tokenthealh helper WP-ből/CLI-ből hívható.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rövid kódrészlet példa (használat):
 *
 * // Titkosítás:
 * $cipher = impact_publisher_encrypt('ACCESS_TOKEN_VALUE', 'platform:meta');
 * // Dekódolás:
 * $plain = impact_publisher_decrypt($cipher, 'platform:meta');
 * if (is_wp_error($plain)) { // hiba kezelése }
 */

/**
 * Visszaadja a master key-t (hex, 32 byte) vagy WP SALT-ból derivál, ha nincs beállítva.
 */
function impact_publisher_get_master_key(): string
{
    $key = getenv('IMPACT_TOKEN_MASTER_KEY');
    if ($key && strlen($key) === 64 && ctype_xdigit($key)) {
        return $key;
    }
    // Fallback: WP salts hash-e (nem ideális, de működőképes, amíg nincs KMS/env)
    $fallback = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    return $fallback;
}

/**
 * Titkosít egy plaintextet AES-256-GCM-mel, base64-ként ad vissza (iv|tag|cipher).
 */
function impact_publisher_encrypt(string $plaintext, string $aad = ''): string
{
    $key = hex2bin(impact_publisher_get_master_key());
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
    return base64_encode($iv . $tag . $cipher);
}

/**
 * Visszafejt egy AES-256-GCM titkosítást; hibánál WP_Error-t ad.
 */
function impact_publisher_decrypt(string $encoded, string $aad = '')
{
    $blob = base64_decode($encoded, true);
    if ($blob === false || strlen($blob) < 28) { // 12 iv + 16 tag min
        return new WP_Error('invalid_cipher', 'Cipher blob is invalid.');
    }
    $iv = substr($blob, 0, 12);
    $tag = substr($blob, 12, 16);
    $cipher = substr($blob, 28);
    $key = hex2bin(impact_publisher_get_master_key());
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    if ($plain === false) {
        return new WP_Error('decrypt_failed', 'Failed to decrypt token.');
    }
    return $plain;
}

/**
 * Lekérdezi a hamarosan lejáró tokeneket (alap: 24 óra).
 * @return array{expiring: array<int,array>, expired: array<int,array>}
 */
function impact_publisher_token_health(int $threshold_hours = 24): array
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_tokens';
    $now = current_time('mysql');
    $threshold = gmdate('Y-m-d H:i:s', time() + ($threshold_hours * 3600));

    $expiring = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT platform, account_id, tenant_id, token_type, expires_at 
             FROM {$table} 
             WHERE expires_at IS NOT NULL AND expires_at <= %s AND expires_at > %s",
            $threshold,
            $now
        ),
        ARRAY_A
    );
    $expired = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT platform, account_id, tenant_id, token_type, expires_at 
             FROM {$table} 
             WHERE expires_at IS NOT NULL AND expires_at <= %s",
            $now
        ),
        ARRAY_A
    );

    return [
        'expiring' => $expiring ?: [],
        'expired' => $expired ?: [],
    ];
}

/**
 * Egyszerű guard: error_log-ba ír, ha van lejáró token.
 */
function impact_publisher_token_health_log(int $threshold_hours = 24): void
{
    $report = impact_publisher_token_health($threshold_hours);
    if (!empty($report['expiring']) || !empty($report['expired'])) {
        error_log('[impact-publisher] token health: ' . wp_json_encode($report));
    }
}

// WP-CLI hook: wp impact-publisher token-health
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('impact-publisher token-health', function ($args, $assoc_args) {
        $hours = isset($assoc_args['hours']) ? (int) $assoc_args['hours'] : 24;
        $report = impact_publisher_token_health($hours);
        WP_CLI::print_value($report, ['format' => 'json']);
    });
}

// Cron-friendly hook (pl. hourly): hívható `do_action('impact_publisher_token_health_cron');`
add_action('impact_publisher_token_health_cron', function () {
    impact_publisher_token_health_log(24);
});

// WP-cron ütemezés: heti/daily/heti-havi checkhez. Itt óránként (hourly).
add_action('wp', function () {
    if (!wp_next_scheduled('impact_publisher_token_health_cron')) {
        wp_schedule_event(time() + 300, 'hourly', 'impact_publisher_token_health_cron');
    }
});
