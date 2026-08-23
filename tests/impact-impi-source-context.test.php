<?php
declare(strict_types=1);

$plugin = __DIR__ . '/../wp-content/mu-plugins/impact-community-impi-source.php';
$source = file_get_contents($plugin);
if ($source === false) {
    throw new RuntimeException('source plugin missing');
}

assert(str_contains($source, "GET /impact/v1/internal/impi/circles" ) === false);
assert(str_contains($source, "'/internal/impi/circles/(?P<circle_id>\\d+)/context'") === true);
assert(str_contains($source, "'permission_callback' => 'ic_impi_source_permission'") === true);
assert(str_contains($source, 'hash_equals') === true);
assert(str_contains($source, 'IMPACT_IMPI_COMMUNITY_SOURCE_TOKEN') === true);
assert(str_contains($source, 'IC_IMPI_SOURCE_MAX_ACTIVITIES = 24') === true);
assert(str_contains($source, 'IC_IMPI_SOURCE_MAX_RETENTION_DAYS = 30') === true);
assert(str_contains($source, "SELECT id, circle_id, post_type, body, created_at") === true);
assert(str_contains($source, 'author_hash') === false);
assert(str_contains($source, 'pid_hash') === false);
assert(str_contains($source, 'wp_remote_') === false);
assert(str_contains($source, '$wpdb->insert') === false);
assert(str_contains($source, '$wpdb->update') === false);
assert(str_contains($source, '$wpdb->delete') === false);
assert(str_contains($source, 'register_rest_route') === true);
assert(str_contains($source, "'methods' => WP_REST_Server::READABLE") === true);

$policy = json_decode((string) file_get_contents(__DIR__ . '/../config/impact-impi-source-authority.json'), true, 512, JSON_THROW_ON_ERROR);
assert($policy['enabled_by_default'] === false);
assert($policy['publication'] === false);
assert($policy['writers'] === []);
assert($policy['cron'] === false);
assert($policy['raw_context_retention_days'] === 30);
assert($policy['pilot_circles'] === ['Tamási', 'Győztesek Egyesülete']);

// Hermetic pure-function fixture: no WordPress runtime or database is loaded.
if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}
define('IMPACT_IMPI_COMMUNITY_SOURCE_ENABLED', true);
define('IMPACT_IMPI_COMMUNITY_SOURCE_TOKEN', str_repeat('t', 64));
define('IMPACT_IMPI_COMMUNITY_SOURCE_CIRCLES', '17');
if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code, public string $message, public array $data = []) {}
    }
}
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function get_header(string $name): string { return ''; }
        public function get_param(string $name): string { return ''; }
    }
}
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server { public const READABLE = 'GET'; }
}
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function add_action($hook, $callback) {}
function register_rest_route($namespace, $route, $args) {}
require_once $plugin;

$redacted = ic_impi_source_redact_text('Írj az anna@example.com címre, IBAN HU42117730161111111111111111, token=abc123456789.');
assert(str_contains($redacted, 'anna@example.com') === false);
assert(str_contains($redacted, 'HU42117730161111111111111111111') === false);
assert(str_contains($redacted, 'token=abc123456789') === false);
assert(strlen($redacted) > 0);

$wpdb = new class {
    public string $prefix = 'wp_';
    public function prepare(string $query, ...$args): string { return $query; }
    public function get_row(string $query): object {
        return (object) ['id' => 17, 'name' => 'Minta Kör', 'description' => 'Közös zöld cél', 'is_active' => 1];
    }
    public function get_results(string $query): array {
        return [(object) ['id' => 9, 'circle_id' => 17, 'post_type' => 'text', 'body' => 'Találkozó: anna@example.com, token=secret123456789', 'created_at' => '2026-08-23 08:00:00']];
    }
};
$context = ic_impi_source_context(17);
assert(is_array($context));
assert(array_keys($context) === ['circle_id', 'circle_name', 'mission', 'public_rules', 'mode', 'topic_allowlist', 'activities', 'summary', 'as_of_utc']);
assert($context['circle_id'] === 17);
assert(count($context['activities']) === 1);
assert(str_contains($context['activities'][0]['body'], 'anna@example.com') === false);
assert(str_contains($context['activities'][0]['body'], 'secret123456789') === false);
assert(str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'author_hash') === false);

fwrite(STDOUT, "PASS impact-impi-source-context\n");
