<?php
/** Pure local contract tests; no WordPress/database/provider/network. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$actions = [];
function add_action($hook, $callback, ...$unused): void { $GLOBALS['actions'][] = [$hook, $callback]; }
final class ImpactShop_Sharity_Bank_Payout
{
    public static array $registered = [];
    public static function register_handler($operation, $handler, $role): void
    {
        self::$registered[] = [$operation, $handler, $role];
    }
}
$before = get_defined_vars();
require __DIR__ . '/../wp-content/mu-plugins/impactshop-partner-finance.php';
$checks = 0;
function check($condition, $label): void
{
    $GLOBALS['checks']++;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
}
function rejects(callable $work, string $label): void
{
    try { $work(); } catch (InvalidArgumentException $error) { check(true, $label); return; }
    throw new RuntimeException('FAIL accepted: ' . $label);
}
check(count($actions) === 1 && $actions[0][0] === 'plugins_loaded', 'include registers only delayed hook');
check(ImpactShop_Sharity_Bank_Payout::$registered === [], 'no registration or IO on include');
($actions[0][1])();
check(ImpactShop_Sharity_Bank_Payout::$registered[0][0] === 'partner-statement'
    && ImpactShop_Sharity_Bank_Payout::$registered[0][2] === 'collector', 'signed collector handler');
function statement(): array
{
    return ['partner_id' => 'shop', 'statement_id' => '2026-11', 'revision' => 1, 'previous_revision' => 0,
        'policy_revision' => 'contract1', 'currency' => 'EUR', 'net_minor' => 10001,
        'cash' => ['synthetic' => true], 'part_id' => 'affiliate', 'evidence_ref' => 'fixture:statement',
        'source_digest' => str_repeat('a', 64), 'lines' => [
            ['provider_tx_id' => 'tx-b', 'net_minor' => 6001, 'attribution' => []],
            ['provider_tx_id' => 'tx-a', 'net_minor' => 4000, 'attribution' => []],
        ]];
}
$normalized = ImpactShop_Partner_Finance::normalize_statement(statement());
check(array_column($normalized['lines'], 'provider_tx_id') === ['tx-a', 'tx-b'], 'canonical line ordering');
foreach (['USD', 'huf', '', null, 1] as $currency) {
    rejects(static function () use ($currency): void { $s=statement();$s['currency']=$currency;ImpactShop_Partner_Finance::normalize_statement($s); }, 'unsupported currency');
}
foreach (['10001', 10001.0, -1, true, null] as $amount) {
    rejects(static function () use ($amount): void { $s=statement();$s['net_minor']=$amount;ImpactShop_Partner_Finance::normalize_statement($s); }, 'integer minor units');
}
foreach (['revision' => 0, 'previous_revision' => 1, 'source_digest' => 'bad', 'extra' => 'field'] as $key=>$value) {
    rejects(static function () use ($key,$value): void { $s=statement();$s[$key]=$value;ImpactShop_Partner_Finance::normalize_statement($s); }, 'invalid shape or revision');
}
rejects(static function (): void { $s=statement();$s['lines'][1]['provider_tx_id']='tx-b';ImpactShop_Partner_Finance::normalize_statement($s); }, 'duplicate transaction');
rejects(static function (): void { $s=statement();$s['lines'][1]['net_minor']++;ImpactShop_Partner_Finance::normalize_statement($s); }, 'exact sum');
rejects(static function (): void { $s=statement();$s['lines'][0]['net_minor']=PHP_INT_MAX;ImpactShop_Partner_Finance::normalize_statement($s); }, 'sum overflow');
rejects(static function (): void { $s=statement();$s['lines']=array_fill(0,1001,$s['lines'][0]);ImpactShop_Partner_Finance::normalize_statement($s); }, 'bounded lines');
foreach ([[10001,6000,6000],[199,6000,119],[12345,1234,1523],[PHP_INT_MAX,10000,PHP_INT_MAX],[PHP_INT_MAX,1,intdiv(PHP_INT_MAX,10000)],[0,5000,0]] as [$amount,$rate,$expected]) {
    check(ImpactShop_Partner_Finance::share($amount,$rate)===$expected, 'overflow safe exact share');
}
$policy = ['partners' => ['shop' => ['revision'=>'contract1','provider'=>'dognet','account_id'=>str_repeat('b',64),
    'source_kind'=>'dognet_intent','ngo_rate_bps'=>6000,'rounding'=>'per_transaction_floor_minor','provider_scope_ref'=>'26081']]];
check(ImpactShop_Partner_Finance::partner_policy($policy,statement())['allow_operator_evidence']===false, 'operator mapping defaults off');
foreach (['ngo_rate_bps'=>5000.0,'rounding'=>'aggregate_floor','revision'=>'changed','provider'=>'cj','provider_scope_ref'=>'','allow_operator_evidence'=>1] as $key=>$value) {
    rejects(static function () use ($policy,$key,$value): void { $policy['partners']['shop'][$key]=$value;ImpactShop_Partner_Finance::partner_policy($policy,statement()); }, 'trusted policy contract');
}
rejects(static fn()=>ImpactShop_Partner_Finance::partner_policy([],statement()), 'missing partner isolated');
rejects(static function () use ($policy): void { unset($policy['partners']['shop']['rounding']);ImpactShop_Partner_Finance::partner_policy($policy,statement()); }, 'rounding must be explicitly approved');
rejects(static fn()=>ImpactShop_Partner_Finance::schema_sql('bad;DROP'), 'SQL identifier boundary');
check(count(ImpactShop_Partner_Finance::schema_sql('pfd_'))===2, 'only two additive tables');
echo "Partner finance pure: {$checks} checks\n";
