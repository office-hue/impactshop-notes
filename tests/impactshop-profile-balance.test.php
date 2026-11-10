<?php
declare(strict_types=1);

// Full source loaded with inert WordPress hooks and synthetic, SELECT-only storage.
// Optional SHARITY_WEB_SESSION_SOURCE exercises the real reader across both repos.
define('ABSPATH', __DIR__ . '/fixtures/wordpress/');
define('ARRAY_A', 'ARRAY_A');
function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function is_admin(): bool { return false; }

final class BalanceDatabase
{
    public string $prefix = 'fixture_';
    public string $last_error = '';
    public array $args = [];
    public array $reads = [];
    public ?array $row = null;
    public bool $fail = false;
    public function prepare(string $sql, ...$args): string { $this->args = $args; return $sql; }
    public function get_row(string $sql, $format): ?array
    {
        if (!str_contains($sql, 'impact_contest_vote_balances') || $this->args !== ['abcdef123456', 'sharity_shared']) {
            throw new RuntimeException('Unexpected balance source or profile');
        }
        $this->reads[] = $this->args;
        $this->last_error = $this->fail ? 'synthetic_read_failure' : '';
        return $this->fail ? null : $this->row;
    }
    public function __call(string $name, array $args) { throw new RuntimeException('Unexpected storage operation: ' . $name); }
}
$GLOBALS['wpdb'] = new BalanceDatabase();
$missing = ($argv[1] ?? '') === '--missing-reader';
$realSource = getenv('SHARITY_WEB_SESSION_SOURCE');
if (!$missing) {
    if ($realSource) {
        require $realSource;
    } else {
        final class ImpactShop_Sharity_Web_Session
        {
            public static ?array $wallet = null;
            public static array $subjects = [];
            public static function read_shared_wallet_for_pseudo(string $pseudo): ?array
            {
                self::$subjects[] = $pseudo;
                return self::$wallet;
            }
        }
    }
}
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-panel.php';
function same($actual, $expected, string $name): void
{
    if ($actual !== $expected) throw new RuntimeException($name . ': ' . json_encode([$actual, $expected]));
}
if ($missing) {
    same(impactshop_identity_profile_votes_available('abcdef123456'), null, 'missing reader');
    same($GLOBALS['wpdb']->reads, [], 'missing reader must not fall back to old SQL');
    echo "profile balance without reader: PASS\n";
    exit;
}
foreach (['', 'bad-id'] as $invalid) same(impactshop_identity_profile_votes_available($invalid), null, 'invalid profile');
if ($realSource) {
    $db = $GLOBALS['wpdb'];
    $db->row = ['votes_total' => '60', 'votes_spent' => '7', 'votes_available' => '53', 'updated_at' => null];
    same(impactshop_identity_profile_votes_available('abcdef123456'), 53, 'real shared reader');
    $db->row = null;
    same(impactshop_identity_profile_votes_available('abcdef123456'), 0, 'missing shared row');
    $db->fail = true;
    same(impactshop_identity_profile_votes_available('abcdef123456'), null, 'real DB failure');
    $db->fail = false;
    $db->row = ['votes_total' => 60, 'votes_spent' => 7, 'votes_available' => 54];
    same(impactshop_identity_profile_votes_available('abcdef123456'), null, 'real invariant failure');
    echo "profile balance with actual shared reader: PASS\n";
} else {
    foreach ([0, 53, 9007199254740991] as $value) {
        ImpactShop_Sharity_Web_Session::$wallet = ['scope' => 'sharity_shared', 'source_status' => 'shared', 'available' => $value];
        same(impactshop_identity_profile_votes_available('abcdef123456'), $value, 'shared available');
    }
    foreach ([null, true, '53', -1, 1.5, 9007199254740992] as $value) {
        ImpactShop_Sharity_Web_Session::$wallet = ['scope' => 'sharity_shared', 'source_status' => 'shared', 'available' => $value];
        same(impactshop_identity_profile_votes_available('abcdef123456'), null, 'invalid common result');
    }
    foreach (['scope' => 'vb2026', 'source_status' => 'legacy_compatibility'] as $key => $value) {
        ImpactShop_Sharity_Web_Session::$wallet = array_merge(['scope' => 'sharity_shared', 'source_status' => 'shared', 'available' => 163], [$key => $value]);
        same(impactshop_identity_profile_votes_available('abcdef123456'), null, 'wrong wallet authority');
    }
    ImpactShop_Sharity_Web_Session::$wallet = null;
    same(impactshop_identity_profile_votes_available('abcdef123456'), null, 'unavailable common reader');
    same(array_unique(ImpactShop_Sharity_Web_Session::$subjects), ['abcdef123456'], 'wrong profile sent to common reader');
    $p = proc_open([PHP_BINARY, __FILE__, '--missing-reader'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    same(proc_close($p), 0, 'missing-reader process: ' . $err);
    same($out, "profile balance without reader: PASS\n", 'missing-reader result');
    echo "profile balance adapter: PASS (real-reader integration requires SHARITY_WEB_SESSION_SOURCE)\n";
}
