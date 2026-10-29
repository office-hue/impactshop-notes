<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
$clickPseudo = 'abc123def456';
$clickTransients = [];
function add_action(...$args): void {}
function impactshop_identity_profile_cookie(): string { return $GLOBALS['clickPseudo']; }
function sanitize_text_field($value): string { return trim((string) $value); }
function esc_url_raw($value): string { return (string) $value; }
function current_time($type): string { return '2026-10-29 12:00:00'; }
function get_transient($key) { return $GLOBALS['clickTransients'][$key] ?? false; }
function set_transient($key, $value, $ttl): bool { $GLOBALS['clickTransients'][$key] = $value; return true; }
// A stale frontend hint or development-mode flag must never reach these writers.
function impactshop_ads_watch_is_always_reward_enabled(): bool { return true; }
function impactshop_ads_watch_add_votes(...$args): void { throw new RuntimeException('click awarded votes'); }
final class Sharity_Points_Manager {
    public function award_points_for_pseudo(...$args): void { throw new RuntimeException('click awarded points'); }
}
final class WP_REST_Request {
    public function __construct(private $json, private array $form = []) {}
    public function get_json_params() { return $this->json; }
    public function get_body_params(): array { return $this->form; }
}
final class WP_REST_Response {
    public function __construct(private array $data, private int $status) {}
    public function get_data(): array { return $this->data; }
    public function get_status(): int { return $this->status; }
}
final class ClickTestWpdb {
    public string $prefix = 'wp_';
    public array $inserts = [];
    public function insert($table, $data, $formats): int {
        $this->inserts[] = ['table' => $table, 'data' => $data];
        return 1;
    }
}
$wpdb = new ClickTestWpdb();
require_once __DIR__ . '/../wp-content/mu-plugins/impactshop-click-tracking.php';
function check($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$payload = [
    'content_type' => 'auto_banner', 'content_id' => 'banner-1',
    'cta_url' => 'https://merchant.example/deal?affiliate=kept',
    'shop_slug' => 'merchant', 'category' => 'books', 'price_range' => '1000-2000',
    'points' => 999, 'dedupe_key' => 'stale-client-hint',
];
foreach ([new WP_REST_Request($payload), new WP_REST_Request(null, $payload)] as $request) {
    $response = impactshop_click_tracking_handle($request);
    check($response->get_status() === 200, 'analytics compatibility status');
    $data = $response->get_data();
    check($data['awarded_points'] === 0 && $data['awarded_votes'] === 0, 'click must award zero');
    check($data['reward_enabled'] === false, 'click reward remains disabled');
    check($data['new_total'] === null && $data['available_votes'] === null, 'click cannot synthesize totals');
}
check(count($wpdb->inserts) === 2, 'both requests retain analytics');
check($wpdb->inserts[0]['data']['cta_url'] === $payload['cta_url'], 'tracking preserves existing destination');
check($clickTransients['user_prefs_abc123def456']['shop_clicks']['merchant'] === 2, 'existing preference capture retained');
$clickPseudo = '';
$response = impactshop_click_tracking_handle(new WP_REST_Request($payload));
check($response->get_data()['status'] === 'missing_identity', 'missing identity remains a no-op');
check(count($wpdb->inserts) === 2, 'missing identity has no insert');
echo "impactshop click analytics without rewards: PASS\n";
