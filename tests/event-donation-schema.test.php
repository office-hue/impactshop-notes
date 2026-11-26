<?php
declare(strict_types=1);

// WordPress loads this file before calling dbDelta(). Keep the fixture private to this run.
$fixture = sys_get_temp_dir() . '/event-schema-' . bin2hex(random_bytes(8));
mkdir($fixture . '/wp-admin/includes', 0700, true);
touch($fixture . '/wp-admin/includes/upgrade.php');
register_shutdown_function(static function () use ($fixture): void {
    unlink($fixture . '/wp-admin/includes/upgrade.php');
    rmdir($fixture . '/wp-admin/includes');
    rmdir($fixture . '/wp-admin');
    rmdir($fixture);
});
define('ABSPATH', $fixture . '/');
function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function get_option($name, $default) { return $GLOBALS['version']; }
function update_option($name, $value, $autoload): void { $GLOBALS['version'] = $value; }
function dbDelta(string $sql): void { $GLOBALS['ddl'][] = $sql; }

require __DIR__ . '/../wp-content/mu-plugins/impactshop-event-donation-widget.php';
const COLUMNS = ['ticket_count', 'regular_ticket_count', 'supporter_ticket_count', 'ticket_serials', 'selected_package',
    'cert_manual_confirmed', 'cert_manual_confirmed_by', 'cert_manual_confirmed_at',
    'payment_method', 'transfer_confirmed_by', 'transfer_confirmed_at'];

class DonationSchemaDb {
    public $prefix = 'custom_';
    public $last_error = '';
    public $columns = COLUMNS;
    public $failColumn = '';
    public $readError = false;
    public $reads = [];
    public $added = [];
    public function get_col(string $sql): array {
        $this->reads[] = $sql;
        $this->last_error = $this->readError ? 'read failed' : '';
        return $this->columns;
    }
    public function get_charset_collate(): string { return ''; }
    public function query(string $sql) {
        if (!preg_match('/^ALTER TABLE custom_impactshop_event_donations ADD COLUMN ([a-z_]+) .+ AFTER [a-z_]+$/D', $sql, $m)) {
            throw new RuntimeException('Unexpected DDL');
        }
        $this->added[] = $m[1];
        if ($m[1] === $this->failColumn) { return false; }
        $this->columns[] = $m[1];
        return 0; // wpdb can report zero affected rows for successful DDL.
    }
}
function expect(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
function runSchema(): void {
    global $wpdb;
    $wpdb->reads = $wpdb->added = [];
    impactshop_event_donation_ensure_schema();
    expect($wpdb->reads === ['SHOW COLUMNS FROM custom_impactshop_event_donations'], 'Expected one prefixed metadata read');
}
$wpdb = new DonationSchemaDb();
$version = IMPACTSHOP_EVENT_DONATION_SCHEMA_VERSION;
$ddl = [];
runSchema();
expect($wpdb->added === [] && $ddl === [], 'Healthy schema must not write');
$wpdb->columns = ['id', 'status', 'gdpr_email_consent', 'donation_cert_sent_at'];
runSchema();
expect($wpdb->added === COLUMNS, 'Missing columns must follow dependency order');
runSchema();
expect($wpdb->added === [], 'Second pass must not repeat DDL');
$version = 'old';
$wpdb->columns = [];
runSchema();
expect($version === 'old' && $wpdb->added === [], 'Empty metadata must not authorize DDL or a version bump');
$wpdb->columns = ['id'];
$wpdb->readError = true;
runSchema();
expect($version === 'old' && $wpdb->added === [], 'Unreadable metadata must not authorize DDL');
$wpdb->readError = false;
$wpdb->failColumn = 'regular_ticket_count';
runSchema();
expect($wpdb->added === ['ticket_count', 'regular_ticket_count'] && $version === 'old', 'Stop on failed DDL');
$wpdb->failColumn = '';
runSchema();
expect($wpdb->added === array_slice(COLUMNS, 1) && $version === IMPACTSHOP_EVENT_DONATION_SCHEMA_VERSION, 'Resume after partial DDL');
expect(count($ddl) === 4, 'The existing version upgrade path must call dbDelta before verification');
echo "7 schema scenarios passed\n";
