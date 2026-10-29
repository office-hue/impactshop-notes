<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
$testFilters = [];
$testActions = [];
function apply_filters($name, $default, ...$args) { return $GLOBALS['testFilters'][$name] ?? $default; }
function add_filter(...$args): void {}
function do_action($name, ...$args): void { $GLOBALS['testActions'][] = [$name, $args]; }
function sanitize_text_field($value): string { return trim((string) $value); }
function wp_json_encode($value) { return json_encode($value); }
function current_time($type) {
    $now = strtotime('2026-10-29 12:00:00 UTC');
    return match ($type) { 'timestamp' => $now, 'Y-m-d' => '2026-10-29', default => '2026-10-29 12:00:00' };
}
final class WriterTestWpdb {
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $reconnect_retries = 5;
    public array $queries = [];
    public array $transactions = [];
    public array $logs = [];
    public object $row;
    public ?object $snapshot = null;
    public int $transactionCount = 0;
    public int $logCount = 0;
    public string $fail = '';
    public int $connection = 7;
    public ?int $lockedBalance = null;
    public function __construct() {
        $this->row = (object) ['points_total' => 1000, 'points_decayed' => 0,
            'last_activity_at' => '2026-09-01 00:00:00', 'last_decay_check_at' => null,
            'level_locked_until' => null, 'freeze_until' => null, 'freeze_count_yearly' => 0];
    }
    public function prepare($sql, ...$args): string {
        foreach ($args as $arg) { $sql = preg_replace('/%[ds]/', is_int($arg) ? (string) $arg : "'" . $arg . "'", $sql, 1); }
        return $sql;
    }
    public function get_var($sql) {
        $this->queries[] = $sql;
        return $sql === 'SELECT CONNECTION_ID()' ? $this->connection : $this->row->freeze_until;
    }
    public function get_row($sql) {
        $this->queries[] = $sql;
        if (!str_contains($sql, 'FOR UPDATE') || $this->snapshot === null) { throw new RuntimeException('balance read without transaction lock'); }
        if ($this->lockedBalance !== null) { $this->row->points_total = $this->lockedBalance; $this->snapshot->points_total = $this->lockedBalance; }
        return clone $this->row;
    }
    public function query($sql) {
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION') { $this->snapshot = clone $this->row; $this->transactionCount = count($this->transactions); $this->logCount = count($this->logs); return 1; }
        if ($sql === 'ROLLBACK') {
            if ($this->snapshot !== null) { $this->row = $this->snapshot; $this->transactions = array_slice($this->transactions, 0, $this->transactionCount); $this->logs = array_slice($this->logs, 0, $this->logCount); }
            $this->snapshot = null; return 1;
        }
        if ($sql === 'COMMIT') {
            if ($this->fail === 'commit') { return false; }
            $this->snapshot = null;
            if ($this->fail === 'connection') { $this->connection++; }
            return 1;
        }
        if (str_starts_with($sql, 'UPDATE ')) {
            if ($this->fail === 'update') { return false; }
            preg_match('/points_total = points_total - (\d+)/', $sql, $m);
            $this->row->points_total -= (int) $m[1];
            if (str_contains($sql, 'points_decayed')) {
                $this->row->points_decayed += (int) $m[1];
                $this->row->last_decay_check_at = current_time('mysql');
            } else {
                $this->row->freeze_until = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
                $this->row->freeze_count_yearly++;
            }
            return 1;
        }
        if (str_starts_with($sql, 'INSERT IGNORE')) {
            if ($this->fail === 'history') { return false; }
            $this->transactions[] = $sql; return 1;
        }
        return 1;
    }
    public function insert($table, $data) {
        if ($this->fail === 'history' && str_ends_with($table, 'point_transactions')) { return false; }
        if (str_ends_with($table, 'point_transactions')) { $this->transactions[] = $data; }
        else { $this->logs[] = $data; }
        return 1;
    }
}
require_once __DIR__ . '/../wp-content/mu-plugins/sharity-vacation-manager.php';
function check($condition, $message): void { if (!$condition) { throw new RuntimeException($message); } }
$vacation = new Sharity_Vacation_Manager();
foreach (['user', 'pseudo'] as $subject) {
    $wpdb = new WriterTestWpdb();
    $wpdb->lockedBalance = 900;
    $result = $subject === 'user' ? $vacation->activate_vacation(12, 7) : $vacation->activate_vacation_for_pseudo('abc123def456', 7);
    check($result['success'] === true && $wpdb->row->points_total === 400, 'vacation deducts unchanged cost from locked balance');
    check($wpdb->row->freeze_count_yearly === 1 && count($wpdb->transactions) === 1, 'freeze and history committed together');
    check($wpdb->reconnect_retries === 5, 'vacation restores reconnect setting');
    $wpdb->lockedBalance = null;
    $again = $subject === 'user' ? $vacation->activate_vacation(12, 7) : $vacation->activate_vacation_for_pseudo('abc123def456', 7);
    check($again['success'] === false && count($wpdb->transactions) === 1, 'concurrent or repeated activation cannot double charge');
}
foreach (['update', 'history', 'commit'] as $failure) {
    $wpdb = new WriterTestWpdb(); $wpdb->fail = $failure;
    check($vacation->activate_vacation(12, 7)['success'] === false, 'failed vacation is not success');
    check($wpdb->row->points_total === 1000 && $wpdb->row->freeze_until === null, 'failed vacation restores balance and freeze');
}
foreach (['vacation'] as $writer) {
    $wpdb = new WriterTestWpdb(); $wpdb->fail = 'connection'; $testActions = [];
    $result = $vacation->activate_vacation(12, 7)['success'];
    check($result === false, 'changed connection cannot report commit success');
    check(count(array_filter($testActions, fn($a) => in_array($a[0], ['sharity_points_decayed', 'sharity_vacation_activated'], true))) === 0, 'unknown commit emits no success action');
    check($wpdb->reconnect_retries === 5, 'unknown commit restores setting');
}
$wpdb = new WriterTestWpdb(); $wpdb->row->points_total = 499;
check($vacation->activate_vacation(12, 7)['success'] === false && $wpdb->row->points_total === 499, 'insufficient vacation balance unchanged');
$wpdb = new WriterTestWpdb();
check($vacation->activate_vacation(12, 99)['success'] === false, 'duration bound retained');
$wpdb->row->freeze_until = gmdate('Y-m-d H:i:s', time() + 86400);
check(sharity_points_block_when_frozen(true, 12, 'purchase') === false, 'existing freeze behavior is not broadened');
echo "sharity vacation writer transactions: PASS (native decay is covered by impactshop-points-mysql.integration.php)\n";
