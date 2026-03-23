<?php
/**
 * Plugin Name: ImpactShop Email Proxy
 * Description: HMAC-protected REST endpoint to send internal executive summary emails via wp_mail.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ImpactShop_Email_Proxy {
    private const OPTION_KEY = 'impactshop_email_proxy_key';
    private const RATE_LIMIT_KEY = 'impactshop_email_proxy_rl';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('impact/v1', '/send-email', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_send'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_send(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $secret = self::get_shared_secret();
        if ($secret === '') {
            return new WP_Error('impact_email_not_configured', 'Email proxy secret missing', ['status' => 501]);
        }

        if (!self::allow_on_host()) {
            return new WP_Error('impact_email_not_allowed', 'Not allowed on host', ['status' => 403]);
        }

        $params = $request->get_json_params() ?: $request->get_body_params();
        $to_raw = $params['to'] ?? '';
        $subject_raw = (string) ($params['subject'] ?? '');
        $html = (string) ($params['html'] ?? '');
        $text = (string) ($params['text'] ?? '');

        $recipients = self::normalize_recipients($to_raw);
        if (!$recipients || $subject_raw === '' || ($html === '' && $text === '')) {
            return new WP_Error('impact_email_invalid', 'Missing to/subject/body', ['status' => 400]);
        }
        foreach ($recipients as $email) {
            if (!self::is_allowed_recipient($email)) {
                return new WP_Error('impact_email_forbidden', 'Recipient not allowed', ['status' => 403]);
            }
        }

        $body_hash = hash('sha256', $html . "\n" . $text);
        $sig_error = self::verify_signature($request, $secret, implode(',', $recipients), $subject_raw, $body_hash);
        if ($sig_error) {
            return $sig_error;
        }
        if (!self::rate_limit($request)) {
            return new WP_Error('impact_email_rate_limited', 'Rate limited', ['status' => 429]);
        }

        $subject = sanitize_text_field($subject_raw);
        $reply_to = sanitize_email((string) ($params['reply_to'] ?? $params['replyTo'] ?? ''));
        $from_name = sanitize_text_field((string) ($params['from_name'] ?? $params['fromName'] ?? ''));
        $from_email = sanitize_email((string) ($params['from_email'] ?? $params['fromEmail'] ?? ''));

        $headers = [];
        if ($html !== '') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        if ($from_email !== '') {
            $from_label = $from_name !== '' ? sprintf('%s <%s>', $from_name, $from_email) : $from_email;
            $headers[] = 'From: ' . $from_label;
        }
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . $reply_to;
        }

        $sent = wp_mail($recipients, $subject, $html !== '' ? $html : $text, $headers);
        return new WP_REST_Response([
            'sent' => (bool) $sent,
            'count' => count($recipients),
        ], $sent ? 200 : 500);
    }

    private static function normalize_recipients($value): array {
        if (is_array($value)) {
            $list = $value;
        } else {
            $list = preg_split('/[,\s]+/', (string) $value) ?: [];
        }
        $out = [];
        foreach ($list as $email) {
            $email = sanitize_email((string) $email);
            if ($email !== '') {
                $out[] = $email;
            }
        }
        return array_values(array_unique($out));
    }

    private static function is_allowed_recipient(string $email): bool {
        $allow = getenv('IMPACTSHOP_EMAIL_PROXY_ALLOWED_DOMAINS');
        if ($allow === false || $allow === '') {
            $allow = (string) get_option('impactshop_email_proxy_allowed_domains', '');
        }
        if ($allow === '' || $allow === false) {
            $allow = 'sharity.hu';
        }
        $domains = array_filter(array_map('trim', explode(',', (string) $allow)));
        if (!$domains) {
            return true;
        }
        $email = strtolower($email);
        foreach ($domains as $domain) {
            $domain = strtolower($domain);
            if ($domain !== '' && self::ends_with($email, '@' . $domain)) {
                return true;
            }
        }
        return false;
    }

    private static function ends_with(string $haystack, string $needle): bool {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }

    private static function verify_signature(WP_REST_Request $request, string $secret, string $to, string $subject, string $body_hash): ?WP_Error {
        $ts_header = $request->get_header('x-impact-ts');
        $sig_header = $request->get_header('x-impact-signature');
        $ts_param = $request->get_param('impact_ts');
        $sig_param = $request->get_param('impact_sig');
        $ts_fallback = $_GET['impact_ts'] ?? $_GET['x-impact-ts'] ?? null;
        $sig_fallback = $_GET['impact_sig'] ?? $_GET['x-impact-signature'] ?? null;

        $ts_header = $ts_header ?: $ts_param ?: $ts_fallback;
        $sig_header = $sig_header ?: $sig_param ?: $sig_fallback;
        if (!$ts_header || !$sig_header) {
            return new WP_Error('impact_email_unauthorized', 'Missing signature headers', ['status' => 403]);
        }

        $ts_value = (int) $ts_header;
        if (!$ts_value || abs(time() - $ts_value) > 300) {
            return new WP_Error('impact_email_unauthorized', 'Signature timestamp out of range', ['status' => 403]);
        }

        $body_param = (string) ($request->get_param('impact_body_hash') ?? $_GET['impact_body_hash'] ?? '');
        if ($body_param !== '' && !hash_equals($body_param, $body_hash)) {
            return new WP_Error('impact_email_unauthorized', 'Body hash mismatch', ['status' => 403]);
        }

        $payload = $ts_value . '|' . $to . '|' . $subject . '|' . $body_hash;
        $expected_sig = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected_sig, (string) $sig_header)) {
            return new WP_Error('impact_email_unauthorized', 'Invalid signature', ['status' => 403]);
        }
        return null;
    }

    private static function allow_on_host(): bool {
        $home = (string) home_url();
        if (stripos($home, 'staging') !== false) {
            return true;
        }
        $opt_allow = (string) get_option('impactshop_allow_prod_analytics', '');
        if ($opt_allow !== '' && $opt_allow !== '0' && strtolower($opt_allow) !== 'false') {
            return true;
        }
        $env_allow = getenv('IMPACTSHOP_ALLOW_PROD_EMAIL_PROXY');
        return $env_allow === '1' || strtolower((string) $env_allow) === 'true';
    }

    private static function get_shared_secret(): string {
        $secret = (string) get_option(self::OPTION_KEY, '');
        if ($secret === '') {
            $secret = (string) getenv('IMPACTSHOP_EMAIL_PROXY_KEY');
        }
        if ($secret === '') {
            $secret = (string) getenv('IMPACT_EMAIL_PROXY_KEY');
        }
        if ($secret === '') {
            $secret = (string) getenv('IMPACT_ANALYTICS_API_KEY');
        }
        return $secret;
    }

    private static function rate_limit(WP_REST_Request $request): bool {
        $ip = (string) ($request->get_header('x-forwarded-for') ?: $request->get_header('x-real-ip') ?: $_SERVER['REMOTE_ADDR'] ?? '');
        $key = self::RATE_LIMIT_KEY . '_' . md5($ip);
        $bucket = get_transient($key);
        $limit = 10;
        $window = 60;
        if (!is_array($bucket)) {
            set_transient($key, ['count' => 1], $window);
            return true;
        }
        $count = (int) ($bucket['count'] ?? 0);
        if ($count >= $limit) {
            return false;
        }
        $bucket['count'] = $count + 1;
        set_transient($key, $bucket, $window);
        return true;
    }
}

ImpactShop_Email_Proxy::init();
