<?php
/** Runs the actual canonical managers against a deterministic SQL failure fixture. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$actions = [];
$checks = 0;
function check($condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException('FAIL ' . $label); }
}
function apply_filters($name, $value, ...$rest) { return $value; }
function wp_json_encode($value) { return json_encode($value); }
function sanitize_text_field($value) { return strip_tags($value); }
function current_time($format) { return '2026-11-15 12:00:00'; }
function do_action($name, ...$args): void
{
    global $actions, $wpdb;
    check(!$wpdb->inTransaction, 'action only after transaction');
    check($wpdb->committed, 'action only after confirmed commit');
    $actions[] = $name;
}
class PointDb
{
    public string $prefix = 'wp_';
    public string $last_error = '';
    public int $insert_id = 19;
    protected int $reconnect_retries = 5;
    public bool $inTransaction = false;
    public bool $committed = false;
    public bool $newUser = false;
    public int $total = 1995;
    public string $level = 'basic';
    public array $ledger = [];
    public int $history = 0;
    public string $fail = '';
    public bool $duplicate = false;
    public bool $duplicateMissing = false;
    public bool $commitButFalse = false;
    public int $connection = 31;
    public bool $switchBeforeCommit = false;
    private ?array $snapshot = null;
    private array $prepared = [];
    public function __get($name) { return $this->$name; }
    public function __set($name, $value) { $this->$name = $value; }
    public function prepare($query, ...$args) { $key = 'prepared:' . count($this->prepared); $this->prepared[$key] = [$query, $args]; return $key; }
    private function record(string $query): array
    {
        [$sql, $args] = $this->prepared[$query] ?? [$query, []];
        $this->last_error = '';
        if ($this->fail !== '' && str_contains($sql, $this->fail)) { $this->last_error = 'injected'; }
        return [$sql, $args];
    }
    public function query($query)
    {
        [$sql, $args] = $this->record($query);
        check($this->reconnect_retries === 0, 'reconnect disabled throughout SQL transaction');
        if ($this->last_error !== '') { return false; }
        if ($sql === 'START TRANSACTION') {
            $this->snapshot = [$this->total, $this->level, $this->ledger, $this->history, $this->newUser];
            $this->inTransaction = true;
        } elseif ($sql === 'COMMIT') {
            $this->inTransaction = false; $this->committed = true; $this->snapshot = null;
            if ($this->commitButFalse) { $this->last_error = 'ambiguous'; return false; }
        } elseif ($sql === 'ROLLBACK') {
            if ($this->snapshot !== null) {
                [$this->total, $this->level, $this->ledger, $this->history, $this->newUser] = $this->snapshot;
            }
            $this->inTransaction = false;
        } elseif (str_contains($sql, 'INSERT IGNORE INTO wp_point_transactions')) {
            if ($this->duplicate) { return 0; }
            $this->ledger[] = $args;
        } elseif (str_contains($sql, 'UPDATE wp_user_points')) {
            $this->total += $args[0];
        }
        return 1;
    }
    public function get_var($query)
    {
        [$sql] = $this->record($query);
        if ($this->last_error !== '') { return null; }
        if (str_contains($sql, 'CONNECTION_ID')) {
            if ($this->switchBeforeCommit && $this->history > 0) { return $this->connection + 1; }
            return $this->connection;
        }
        if (str_contains($sql, 'COUNT(*)')) { return $this->newUser ? 0 : 1; }
        if (str_contains($sql, 'SELECT points_total')) { return $this->total; }
        if (str_contains($sql, 'SELECT current_level')) { return $this->level; }
        if (str_contains($sql, 'SELECT id FROM wp_point_transactions')) { return $this->duplicateMissing ? null : 19; }
        return null;
    }
    public function insert($table, $data)
    {
        $this->last_error = '';
        if ($this->fail === 'INSERT ' . $table) { $this->last_error = 'injected'; return false; }
        check($this->inTransaction, 'user/history insert in same transaction');
        if ($table === 'wp_user_points') { $this->newUser = false; }
        if ($table === 'wp_level_history') { $this->history++; }
        return 1;
    }
    public function update($table, $data, $where)
    {
        $this->last_error = '';
        if ($this->fail === 'UPDATE level') { $this->last_error = 'injected'; return false; }
        $this->level = $data['current_level'];
        return 1;
    }
}
require __DIR__ . '/../wp-content/mu-plugins/sharity-level-manager.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-manager.php';
function fresh(): PointDb { global $wpdb, $actions; $wpdb = new PointDb(); $actions = []; return $wpdb; }
function award(): array { return (new Sharity_Points_Manager())->award_points_for_pseudo('abcdef123456', 5, 'bonus', 'fixture', ['source_type' => 'jysk_campaign'], 'jysk:fixture'); }
$db = fresh();
check(award()['success'] === true, 'normal award succeeds');
check($db->total === 2000 && count($db->ledger) === 1 && $db->level === 'bronze' && $db->history === 1, 'normal ledger totals level history');
check($actions === ['sharity_level_changed', 'sharity_points_earned'], 'success actions after commit');
check($db->reconnect_retries === 5, 'retry count restored after success');
foreach (['SET TRANSACTION', 'START TRANSACTION', 'COUNT(*)', 'INSERT wp_user_points', 'INSERT IGNORE INTO wp_point_transactions', 'UPDATE wp_user_points', 'SELECT points_total', 'SELECT current_level', 'UPDATE level', 'INSERT wp_level_history', 'COMMIT'] as $failure) {
    $db = fresh(); $db->fail = $failure;
    if ($failure === 'INSERT wp_user_points') { $db->newUser = true; }
    $result = award();
    check($result['success'] === false, 'SQL failure not success ' . $failure);
    check($db->total === 1995 && $db->ledger === [] && $db->history === 0 && $db->level === 'basic', 'rollback keeps ledger and totals consistent ' . $failure);
    check($actions === [] && $db->reconnect_retries === 5, 'no premature action and retries restored ' . $failure);
}
$db = fresh(); $db->duplicate = true;
check(award()['duplicate'] === true && $db->total === 1995 && $actions === [], 'duplicate rolls back without side effects');
$db = fresh(); $db->duplicate = true; $db->duplicateMissing = true;
check(award()['success'] === false && $actions === [], 'ignored insert without actual dedupe row is not a duplicate success');
$db = fresh(); $db->switchBeforeCommit = true;
check(award()['success'] === false && $db->total === 1995 && $actions === [], 'connection identity change prevents commit');
$db = fresh(); $db->commitButFalse = true;
check(award()['success'] === false && $db->total === 2000 && count($db->ledger) === 1, 'ambiguous durable commit never reports success');
check($actions === [] && $db->reconnect_retries === 5, 'ambiguous commit emits no action and restores retry config');
$db = fresh(); $db->newUser = true;
check(award()['success'] === true && !$db->newUser, 'new pseudo creation is inside successful transaction');
echo "PASS {$checks} canonical manager assertions (fixture only; no real database)\n";
