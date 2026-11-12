<?php
/** Real catalog projection/query functions; synthetic read-only DB boundary. No WP runtime claims. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php';

function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
class CatalogReadFixture {
    public string $prefix = 'fixture_';
    public array $queries = [];
    public function prepare(string $sql, ...$params): array { return ['sql' => $sql, 'params' => $params]; }
    public function get_var(array $query): int { $this->queries[] = $query; return 254; }
    public function get_results(array $query, $format): array {
        $this->queries[] = $query;
        // Fail if producer stops selecting the eligibility fields needed by its projection.
        check(str_contains($query['sql'], 'cf.campaign_key') && str_contains($query['sql'], 'cf.allow_user_selection'), 'query must select campaign and permission');
        check(str_contains($query['sql'], 'cf.allow_public_listing = 1') && str_contains($query['sql'], "cf.campaign_state = 'active'"), 'public listing and active campaign constraints remain');
        return [
            ['sharity_ngo_id' => 7, 'slug' => 'civil-%c3%a1rviz', 'name' => 'Synthetic civil NGO', 'campaign_key' => 'regional_fixture', 'allow_user_selection' => '1', 'is_active' => 1],
            ['sharity_ngo_id' => 8, 'slug' => 'restricted-ngo', 'name' => 'Restricted NGO', 'campaign_key' => 'regional_fixture', 'allow_user_selection' => '0', 'is_active' => 1],
        ];
    }
}
$wpdb = new CatalogReadFixture();
$filters = ['campaign' => 'regional_fixture', 'active_only' => true, 'featured_only' => false, 'q' => '', 'category' => '', 'legal_status' => '', 'city' => ''];
$result = impactshop_vb2026_catalog_query($filters, 2, 48);
check($result['pagination'] === ['page' => 2, 'per_page' => 48, 'total_items' => 254, 'total_pages' => 6], 'full catalog pagination remains intact');
check($result['items'][0]['campaign'] === 'regional_fixture' && $result['items'][0]['allow_user_selection'] === true, 'catalog explicitly exposes correct campaign and permission');
check($result['items'][0]['slug'] === 'civil-%c3%a1rviz', 'projection preserves opaque slug');
check($result['items'][1]['allow_user_selection'] === false, 'denied eligibility remains false, not omitted');
check($wpdb->queries[1]['params'] === ['regional_fixture', 48, 48], 'campaign binding and page offset remain intact');
$featured = impactshop_vb2026_get_featured_items('regional_fixture', 10);
check($featured[0]['campaign'] === 'regional_fixture' && $featured[0]['allow_user_selection'] === true, 'featured projection carries same campaign permission');
check($featured[1]['allow_user_selection'] === false, 'featured does not imply selectable');
$missing = impactshop_vb2026_catalog_row_to_public_item(['sharity_ngo_id' => 9, 'slug' => 'unknown-ngo', 'name' => 'Unknown']);
check($missing['campaign'] === '' && $missing['allow_user_selection'] === false, 'missing eligibility fails closed');
echo "VB2026 catalog campaign/eligibility projection: PASS\n";
