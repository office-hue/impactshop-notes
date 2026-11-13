<?php

declare(strict_types=1);

// Runs selected actual producer functions in isolated PHP processes, without WP/DB.
// Golden arguments remain usable in shallow CI. Where available, additionally
// compare responses/side effects against the pinned exact live-matching preimage.
const LIVE_PREIMAGE = '366df47e3572b27f71eb1efbfe52fcef0a00a955';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function run_process(array $command): string {
    $process = proc_open($command, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, dirname(__DIR__));
    check(is_resource($process), 'process unavailable'); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    check(proc_close($process) === 0, 'fixture failed: ' . $error); return $out;
}
if (($argv[1] ?? '') !== 'fixture') {
    $probe = proc_open(['git','cat-file','-e', LIVE_PREIMAGE . '^{commit}'], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $probePipes, dirname(__DIR__));
    check(is_resource($probe), 'git unavailable');
    foreach ($probePipes as $pipe) fclose($pipe);
    $hasPreimage = proc_close($probe) === 0;
    $cases = 0;
    foreach (['success', 'earning_blocked', 'ledger_write_failed', 'commit_unconfirmed'] as $result) {
        $baseline = $hasPreimage ? json_decode(run_process([PHP_BINARY, __FILE__, 'fixture', 'baseline', 'missing', $result]), true, 512, JSON_THROW_ON_ERROR) : null;
        foreach (['missing', 'off', 'on', 'failed'] as $mode) {
            $current = json_decode(run_process([PHP_BINARY, __FILE__, 'fixture', 'current', $mode, $result]), true, 512, JSON_THROW_ON_ERROR);
            if ($baseline !== null) check($current['business'] === $baseline['business'], 'changed manager arguments/response/side effects: ' . $mode . '/' . $result);
            check(count($current['business']['calls']) === 18, 'expected 17 earning calls plus unchanged admin adjustment');
            if ($mode === 'on') {
                check(count($current['observations']) === 34, 'all 17 earning calls have before and after');
                $calls = array_slice($current['business']['calls'], 0, 17);
                foreach ($calls as $i => $call) {
                    $before = $current['observations'][$i * 2]; $after = $current['observations'][$i * 2 + 1];
                    check(array_slice($before['args'], 2) === $call['args'], 'observation arguments diverged');
                    check($before['args'][1] === ($call['method'] === 'award_points' ? 'user' : 'pseudo'), 'wrong subject kind');
                    check($after['result'] === $current['business']['manager_result'], 'result observation changed');
                }
            } else check($current['observations'] === [], 'disabled/unavailable recording performed work');
            $cases++;
        }
    }
    $bonusCases = 0;
    foreach (['success', 'earning_blocked', 'ledger_write_failed', 'commit_unconfirmed'] as $result) {
        $baseline = $hasPreimage ? json_decode(run_process([PHP_BINARY, __FILE__, 'fixture', 'baseline', 'missing', $result, 'bonus']), true, 512, JSON_THROW_ON_ERROR) : null;
        foreach (['missing', 'off', 'on', 'failed'] as $mode) {
            $current = json_decode(run_process([PHP_BINARY, __FILE__, 'fixture', 'current', $mode, $result, 'bonus']), true, 512, JSON_THROW_ON_ERROR);
            if ($baseline !== null) check($current['business'] === $baseline['business'], 'changed purchase bonus business contract: ' . $mode . '/' . $result);
            check(count($current['business']['calls']) === 6, 'expected six actual manager calls across pseudo/user bonus branches');
            if ($mode === 'on') {
                check(count($current['observations']) === 12, 'six purchase bonus calls observed; existing entitlement skipped');
                foreach ($current['business']['calls'] as $i => $call) {
                    $before = $current['observations'][$i * 2]; $after = $current['observations'][$i * 2 + 1];
                    $kind = $call['method'] === 'award_points' ? 'user' : 'pseudo';
                    check($before['args'][0] === 'cron.purchase_bonus.' . $kind && $before['args'][1] === $kind, 'wrong bonus observation lane');
                    check(array_slice($before['args'], 2) === $call['args'], 'purchase bonus observation arguments diverged');
                    check($after['result'] === $current['business']['manager_result'], 'bonus result observation changed');
                }
            } else check($current['observations'] === [], 'disabled bonus recorder performed work');
            $bonusCases++;
        }
    }
    echo "PASS purchase bonus golden arguments ({$bonusCases} isolated cases; first/shop/referral pseudo+user; existing entitlement skipped)\n";
    echo "PASS reward producer golden arguments ({$cases} isolated cases; 17 earning calls, admin excluded; no WP/DB bootstrap)\n";
    echo $hasPreimage ? "PASS response/side-effect parity against pinned live preimages\n" : "NOT RUN original response/side-effect parity: pinned Git object unavailable\n";
    exit;
}

$version = $argv[2]; $mode = $argv[3]; $resultMode = $argv[4]; $fixtureType = $argv[5] ?? 'main';
define('HOUR_IN_SECONDS', 3600); define('ARRAY_A', 'ARRAY_A');
$GLOBALS['business'] = ['calls' => [], 'side_effects' => [], 'responses' => []];
$GLOBALS['observations'] = [];
$GLOBALS['manager_result'] = $resultMode === 'success'
    ? ['success' => true, 'transaction_id' => 173, 'duplicate' => true]
    : ['success' => false, 'error' => $resultMode];
class Sharity_Points_Manager {
    public function award_points(...$args): array { return $this->record(__FUNCTION__, $args); }
    public function award_points_for_pseudo(...$args): array { return $this->record(__FUNCTION__, $args); }
    private function record(string $method, array $args): array {
        $GLOBALS['business']['calls'][] = compact('method','args'); return $GLOBALS['manager_result'];
    }
}
if ($mode !== 'missing') {
    class Sharity_Reward_Observation {
        public static function beforeAward(...$args): ?array {
            if ($GLOBALS['mode'] !== 'on') return null;
            $GLOBALS['observations'][] = ['args' => $args]; return ['fixture' => true];
        }
        public static function after($token, $result): bool {
            $GLOBALS['observations'][] = ['result' => $result]; return true;
        }
    }
}
class WP_User { public int $ID = 17; }
class WP_REST_Request {
    public function __construct(private array $payload = [], private string $route = '') {}
    public function get_json_params(): array { return $this->payload; }
    public function get_route(): string { return $this->route; }
}
class WP_REST_Response {
    public function __construct(public $data, public int $status = 200) {}
    public function get_status(): int { return $this->status; }
}
class ProducerDbStub {
    public string $prefix = 'synthetic_';
    public string $last_error = '';
    public function get_row(string $sql, $format): ?array {
        return str_contains($sql, 'user_referrals') ? ($GLOBALS['referral_row'] ?? null) : ($GLOBALS['existing_bonus'] ?? null);
    }
    public function update(...$args): int { $GLOBALS['business']['side_effects'][] = ['update', $args]; return 1; }
    public function prepare(string $sql, ...$args): string { return $sql; }
    public function get_var(string $sql): int { return 0; }
}
$GLOBALS['wpdb'] = new ProducerDbStub();
function sharity_points_get_pseudo_cookie(): string { return 'abcdefghij'; }
function sharity_points_get_pseudo_from_cookie(): string { return 'abcdefghij'; }
function get_transient($key) { return false; }
function set_transient(...$args): void { $GLOBALS['business']['side_effects'][] = $args; }
function get_query_var($key) { return $key === 'ngo' ? 'example-ngo' : 1; }
function sanitize_title($value): string { return strtolower($value); }
function sanitize_text_field($value): string { return trim($value); }
function sanitize_key($value): string { return strtolower($value); }
function wp_unslash($value) { return $value; }
function apply_filters($name, $value, ...$args) { return $value; }
function is_wp_error($value): bool { return false; }
function rest_ensure_response($value) { return $value; }
function sharity_points_require_signature($request) { return null; }
function sharity_points_manage_permission(): bool { return false; }
function get_current_user_id(): int { return 17; }
function current_time($format) { return $format === 'timestamp' ? 1794484800 : '2026-11-12 12:00:00'; }
function date_i18n($format, $time): string { return gmdate($format, $time); }
function wp_date($format, $time): string { return gmdate($format, $time); }
function sharity_collect_purchase_weeks(...$args): array { return ['202645','202644','202643','202642']; }
function sharity_collect_purchase_months(...$args): array { return ['2026-11','2026-10']; }
function sharity_has_consecutive_weeks(...$args): bool { return true; }
function sharity_has_consecutive_months(...$args): bool { return true; }
if ($fixtureType !== 'bonus') {
    function sharity_points_handle_referral(...$args): void { $GLOBALS['business']['side_effects'][] = ['referral', $args]; }
}

$functions = $fixtureType === 'bonus' ? ['sharity-points-ledger-sync.php' => ['sharity_points_impactshop_sql','sharity_points_impactshop_bonus_once','sharity_points_handle_referral']] : [
    'sharity-points-events.php' => ['sharity_points_award_daily_login','sharity_points_award_user_login','sharity_points_track_wallet_download','sharity_points_track_share_page','sharity_points_rest_event_bridge','sharity_points_video_ad'],
    'sharity-points-api.php' => ['sharity_points_pseudo_earn','sharity_points_earn','sharity_points_webhook_purchase','sharity_points_user_feedback','sharity_points_pseudo_feedback','sharity_points_admin_adjust'],
    'sharity-points-cron.php' => ['sharity_award_streaks_for_user','sharity_award_streaks_for_pseudo'],
];
foreach ($functions as $file => $names) {
    $path = 'wp-content/mu-plugins/' . $file;
    $source = $version === 'baseline' ? run_process(['git','show', LIVE_PREIMAGE . ':' . $path]) : file_get_contents(dirname(__DIR__) . '/' . $path);
    $tokens = token_get_all($source); $found = [];
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $start = $i; $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || !in_array($tokens[$j][1], $names, true)) continue;
        $name = $tokens[$j][1]; $body = ''; $depth = 0; $opened = false;
        for (; $i < count($tokens); $i++) {
            $t = $tokens[$i]; $body .= is_array($t) ? $t[1] : $t;
            // Interpolated SQL variables use T_CURLY_OPEN and a matching literal }.
            if ($t === '{' || is_array($t) && in_array($t[0], [T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES], true)) { $depth++; $opened = true; }
            if ($t === '}' && --$depth === 0 && $opened) break;
        }
        eval($body); $found[] = $name;
    }
    check(count($found) === count($names), 'function extraction incomplete');
}
function response($value): void { $GLOBALS['business']['responses'][] = $value; }
if ($fixtureType === 'bonus') {
    $root = str_repeat('a', 64); $metadata = ['source_type' => 'impactshop_bonus', 'root_id' => $root];
    response(sharity_points_impactshop_bonus_once('abcdefghij', 100, 'first_purchase', $root, $metadata, 'first_purchase:abcdefghij'));
    response(sharity_points_impactshop_bonus_once('abcdefghij', 25, 'shop_discovery', $root, $metadata, 'shop_discovery:abcdefghij:fixture-shop'));
    $GLOBALS['referral_row'] = ['id' => 5, 'referrer_pseudo_id' => 'referrer12', 'referrer_user_id' => 0];
    response(sharity_points_handle_referral('abcdefghij', 0, $metadata, true));
    $GLOBALS['referral_row'] = ['id' => 6, 'referrer_pseudo_id' => '', 'referrer_user_id' => 29];
    response(sharity_points_handle_referral('abcdefghij', 0, $metadata, true));
    $GLOBALS['existing_bonus'] = ['id' => 99, 'pseudo_id' => 'abcdefghij', 'user_id' => null, 'points' => 100, 'type' => 'first_purchase'];
    response(sharity_points_impactshop_bonus_once('abcdefghij', 100, 'first_purchase', 'another-root', ['source_type' => 'impactshop_bonus'], 'first_purchase:abcdefghij'));
    $GLOBALS['existing_bonus']['points'] = 101;
    response(sharity_points_impactshop_bonus_once('abcdefghij', 100, 'first_purchase', $root, $metadata, 'first_purchase:abcdefghij'));
    $GLOBALS['referral_row'] = null;
    response(sharity_points_handle_referral('abcdefghij', 0, $metadata, true));
    $expected = [
        ['abcdefghij',100,'first_purchase',$root,$metadata,'first_purchase:abcdefghij'],
        ['abcdefghij',25,'shop_discovery',$root,$metadata,'shop_discovery:abcdefghij:fixture-shop'],
        ['referrer12',200,'referral','0',$metadata,'referral:abcdefghij'],
        ['abcdefghij',50,'referral_bonus','0',$metadata,'referral_bonus:abcdefghij'],
        [29,200,'referral','0',$metadata,'referral:abcdefghij'],
        ['abcdefghij',50,'referral_bonus','0',$metadata,'referral_bonus:abcdefghij'],
    ];
    check(array_column($GLOBALS['business']['calls'], 'args') === $expected, 'golden original purchase bonus arguments changed');
    check(array_slice($GLOBALS['business']['responses'], -3) === [['success'=>true,'transaction_id'=>99],['success'=>false,'transaction_id'=>99],true], 'existing bonus entitlement return changed');
    $GLOBALS['business']['manager_result'] = $GLOBALS['manager_result'];
    echo json_encode(['business'=>$GLOBALS['business'],'observations'=>$GLOBALS['observations']], JSON_THROW_ON_ERROR);
    exit;
}

sharity_points_award_daily_login();
sharity_points_award_user_login('fixture', new WP_User());
sharity_points_track_wallet_download();
$_SERVER['REQUEST_URI'] = '/ngo/example-ngo/share/'; sharity_points_track_share_page();
response(sharity_points_rest_event_bridge(new WP_REST_Response(['unchanged' => true]), null, new WP_REST_Request(['pseudo_id'=>'ABCDEFGHIJ','nickname'=>'fixture'], '/impactshop/v1/profile/update')));
response(sharity_points_rest_event_bridge(new WP_REST_Response(['unchanged' => true]), null, new WP_REST_Request(['campaign_id'=>'campaign-1'], '/impact/v1/vote/view')));
response(sharity_points_video_ad(new WP_REST_Request()));
response(sharity_points_pseudo_earn(new WP_REST_Request(['points'=>3,'type'=>'bonus','source_id'=>'fixture','metadata'=>['date'=>'2026-11-12']])));
response(sharity_points_earn(new WP_REST_Request(['user_id'=>17,'points'=>4,'type'=>'bonus','dedupe_key'=>'signed:fixture','metadata'=>['source_type'=>'signed']])));
response(sharity_points_webhook_purchase(new WP_REST_Request(['pseudo_id'=>'ABCDEFGHIJ','amount_huf'=>9,'source_id'=>'123','dedupe_key'=>'signed:purchase'])));
response(sharity_points_user_feedback(new WP_REST_Request(['rating'=>5,'comment'=>'PRIVATE fixture comment','channel'=>'fixture'])));
response(sharity_points_pseudo_feedback(new WP_REST_Request(['rating'=>5,'comment'=>'PRIVATE fixture comment','channel'=>'fixture'])));
sharity_award_streaks_for_user(17, new Sharity_Points_Manager());
sharity_award_streaks_for_pseudo('abcdefghij', new Sharity_Points_Manager());
response(sharity_points_admin_adjust(new WP_REST_Request(['user_id'=>17,'points'=>2,'reason'=>'fixture'])));
// Rejected source gates must not create a candidate or alter their response.
response(sharity_points_pseudo_earn(new WP_REST_Request(['points'=>0,'type'=>'bonus'])));
response(sharity_points_earn(new WP_REST_Request(['user_id'=>17,'points'=>4])));
response(sharity_points_webhook_purchase(new WP_REST_Request(['pseudo_id'=>'abcdefghij','points'=>4,'dedupe_key'=>'impactshop:blocked'])));
response(sharity_points_webhook_purchase(new WP_REST_Request(['pseudo_id'=>'abcdefghij','points'=>4,'dedupe_key'=>'signed:pending','metadata'=>['status'=>'pending']])));
$day = gmdate('Y-m-d');
$feedback = ['source_type'=>'feedback','rating'=>5,'comment'=>'PRIVATE fixture comment','channel'=>'fixture','submitted_at'=>'2026-11-12 12:00:00'];
$expectedArgs = [
    ['abcdefghij',2,'login_daily',$day,['source_type'=>'session'],'login_daily:abcdefghij:'.$day],
    [17,2,'login_daily',$day,['source_type'=>'wp_login'],'login_daily:17:'.$day],
    ['abcdefghij',25,'wallet_download','example-ngo',['source_type'=>'wallet','ngo_slug'=>'example-ngo'],'wallet_download:abcdefghij:example-ngo'],
    ['abcdefghij',10,'share','example-ngo',['source_type'=>'share_page','ngo_slug'=>'example-ngo'],'share:abcdefghij:example-ngo:'.$day],
    ['abcdefghij',10,'profile_complete',null,['source_type'=>'identity_panel'],'profile_complete:abcdefghij'],
    ['abcdefghij',15,'nickname',null,['source_type'=>'nickname'],'nickname:abcdefghij'],
    ['abcdefghij',5,'video_sponsor','campaign-1',['source_type'=>'impact_vote','campaign_id'=>'campaign-1'],'video_sponsor:abcdefghij:'.$day],
    ['abcdefghij',1,'video_ad',null,['source_type'=>'ad_view'],'video_ad:abcdefghij:'.$day.':1'],
    ['abcdefghij',3,'bonus','fixture',['date'=>'2026-11-12'],null],
    [17,4,'bonus',null,['source_type'=>'signed'],'signed:fixture'],
    ['ABCDEFGHIJ',9,'purchase','123',['source_type'=>'purchase_webhook'],'signed:purchase'],
    [17,20,'feedback','2026-11',$feedback,'feedback:user:17:2026-11:1'],
    ['abcdefghij',20,'feedback','2026-11',$feedback,'feedback:pseudo:abcdefghij:2026-11:1'],
    [17,30,'streak_bonus','2026-11',['source_type'=>'streak','period'=>'weekly','weeks'=>['202645','202644','202643','202642']],'streak_weekly:2026-11'],
    [17,50,'streak_bonus','2026-11',['source_type'=>'streak','period'=>'monthly','months'=>['2026-11','2026-10']],'streak_monthly:2026-11'],
    ['abcdefghij',30,'streak_bonus','2026-11',['source_type'=>'streak','period'=>'weekly','weeks'=>['202645','202644','202643','202642']],'streak_weekly:2026-11'],
    ['abcdefghij',50,'streak_bonus','2026-11',['source_type'=>'streak','period'=>'monthly','months'=>['2026-11','2026-10']],'streak_monthly:2026-11'],
    [17,2,'admin_adjustment','fixture',['source_type'=>'admin_adjustment','reason'=>'fixture'],null],
];
check(array_column($GLOBALS['business']['calls'], 'args') === $expectedArgs, 'golden original manager argument contract changed');
$GLOBALS['business']['manager_result'] = $GLOBALS['manager_result'];
echo json_encode(['business'=>$GLOBALS['business'],'observations'=>$GLOBALS['observations']], JSON_THROW_ON_ERROR);
