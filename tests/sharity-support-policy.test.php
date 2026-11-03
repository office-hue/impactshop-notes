<?php
/** Weak-typing caller deliberately tests that floats/ints never coerce into money. */
use sharity_support_policy\PolicyViolation;
use function sharity_support_policy\{make_snapshot, assert_snapshot_valid, allocate};
require __DIR__ . '/../wp-content/mu-plugins/sharity-support-policy.php';

$checks = 0;
function check($ok, string $message): void {
    $GLOBALS['checks']++;
    if (!$ok) { throw new RuntimeException($message); }
}
function rejects(callable $f, string $message, ?string $error = null): void {
    try { $f(); } catch (PolicyViolation $e) {
        check($error === null || $e->getMessage() === $error, $message . ': wrong domain error'); return;
    }
    throw new RuntimeException($message . ': not rejected');
}
function input(string $source = 'affiliate', string $tier = 'base', string $donor = 'basic'): array {
    return ['source' => $source, 'ngo_slug' => 'synthetic-ngo', 'tier' => $tier, 'donor_level' => $donor,
        'captured_at_utc' => '2026-11-03T16:00:00Z', 'ngo_quarter_key' => '2026Q4',
        'ngo_authority' => ['ref' => 'quarter-snapshot:example', 'sha256' => str_repeat('a', 64), 'observed_at_utc' => '2026-11-03T15:59:00Z'],
        'donor_authority' => ['ref' => 'level-snapshot:example', 'sha256' => str_repeat('b', 64), 'observed_at_utc' => '2026-11-03T15:59:30Z']];
}
// Fixed expected minor targets at R=100000, independently stated business rates.
$targets = [
    'affiliate' => ['base' => [50000,52500,55000,57500,60000,62500], 'rising' => [55000,57750,60500,63250,66000,68750], 'legend' => [60000,63000,66000,69000,72000,75000]],
    'offerwall' => ['base' => [60000,63000,66000,69000,72000,75000], 'rising' => [65000,68250,71500,74750,78000,81250], 'legend' => [70000,73500,77000,80500,84000,87500]],
];
$levels = ['basic','bronze','silver','gold','platinum','legend'];
foreach ($targets as $source => $tiers) foreach ($tiers as $tier => $expected) foreach ($levels as $i => $level) {
    $s = make_snapshot(input($source, $tier, $level));
    foreach (['HUF','EUR','USD','GBP'] as $currency) {
        $a = allocate($s, $currency, '100000', '0', '0');
        check($a['ngo_cumulative_target_minor'] === (string)$expected[$i], "rates $source/$tier/$level/$currency");
        check($a['sharity_cumulative_target_minor'] === (string)(100000-$expected[$i]), 'remaining distributable net preserved');
        check($a['currency'] === $currency && $a['currency_exponent'] === 2, 'explicit minor currency retained');
        check($a['snapshot_digest'] === $s['snapshot_digest'] && $a['ngo_slug'] === 'synthetic-ngo', 'frozen root policy reference');
    }
}
$s = make_snapshot(input());
check(allocate($s, 'HUF', '1000', '200', '300')['ngo_cumulative_target_minor'] === '250', 'extra cost and reserve each deducted once');
check(allocate($s, 'HUF', '800', '0', '0')['ngo_cumulative_target_minor'] === '400', 'already net cash not charged a fee again');
check(allocate($s, 'HUF', '0', '0', '0')['ngo_cumulative_target_minor'] === '0', 'confirmed zero cash valid');
check(allocate($s, 'HUF', '1000', '200', '800')['sharity_cumulative_target_minor'] === '0', 'fully reserved money not distributed');
rejects(fn() => allocate($s, 'HUF', '1000', '1001', '0'), 'cost exceeds cash', 'SUPPORT_POLICY_UNFUNDED');
rejects(fn() => allocate($s, 'HUF', '1000', '200', '801'), 'liability exceeds available money', 'SUPPORT_POLICY_UNFUNDED');
rejects(fn() => allocate($s, 'HUF', '9223372036854775807', '9223372036854775807', '9223372036854775807'), 'overflow-sized C+Q fails safely', 'SUPPORT_POLICY_UNFUNDED');

// Independently computed with Python unbounded integer arithmetic; no product helpers.
$large = [
 ['affiliate','base','basic','0','0','4611686018427387903','4611686018427387904'],
 ['affiliate','rising','gold','0','0','5833782813310645697','3389589223544130110'],
 ['affiliate','legend','legend','0','0','6917529027641081855','2305843009213693952'],
 ['offerwall','legend','legend','0','0','8070450532247928831','1152921504606846976'],
 ['offerwall','legend','legend','9223372036854775806','0','0','1'],
 ['offerwall','rising','platinum','1','1','7194230188746725127','2029141848108050678'],
];
foreach ($large as [$source,$tier,$donor,$c,$q,$ngo,$sharity]) {
    $a = allocate(make_snapshot(input($source,$tier,$donor)), 'USD', '9223372036854775807', $c, $q);
    check($a['ngo_cumulative_target_minor'] === $ngo, 'BIGINT exact NGO target');
    check($a['sharity_cumulative_target_minor'] === $sharity, 'BIGINT exact residual');
}
foreach ([['10007'], array_fill(0,10007,1), [3001,2003,2003,1000,2000], [2000,1000,2003,3001,2003]] as $parts) {
    $sum=0; $last=0; $journal=0;
    foreach ($parts as $part) {
        $sum+=(int)$part;
        $a=allocate(make_snapshot(input('offerwall','legend','legend')), 'HUF', (string)$sum, '0', '0');
        $next=(int)$a['ngo_cumulative_target_minor']; $journal+=$next-$last; $last=$next;
    }
    check($sum===10007 && $journal===8756, 'receipt partition/order independent cumulative target');
}
$a=allocate(make_snapshot(input('offerwall','legend','legend')), 'EUR', '100', '0', '0');
check($a['ngo_cumulative_target_minor']==='87' && $a['sharity_cumulative_target_minor']==='13', 'single floor preserves one-minor remainder');
check(allocate($s,'HUF','100','0','100')['ngo_cumulative_target_minor']==='0'
    && allocate($s,'HUF','100','0','0')['ngo_cumulative_target_minor']==='50', 'release changes cumulative target, not received cash');

// Weak-mode numeric arguments must not be silently coerced, even for C and Q.
foreach ([0,1,1.0,1.5,true,false,null,[],new stdClass(),'','00','01','-1','+1','1.0','1e3',' 1','1 ','1\n',"1\n",'9223372036854775808',str_repeat('9',200)] as $bad) {
    rejects(fn()=>allocate($s,'HUF',$bad,'0','0'), 'invalid R');
    rejects(fn()=>allocate($s,'HUF','100',$bad,'0'), 'invalid C');
    rejects(fn()=>allocate($s,'HUF','100','0',$bad), 'invalid Q');
}
foreach (['JPY','huf','',null,1,['HUF','EUR']] as $bad) rejects(fn()=>allocate($s,$bad,'100','0','0'),'unsupported/mixed currency');
try { allocate($s,'HUF','100','0'); throw new RuntimeException('missing liability accepted'); }
catch (ArgumentCountError $e) { check(true, 'liability input mandatory'); }

foreach (['stripe','direct','amplifier','offerwall_legacy','',1] as $bad) {
    $x=input();$x['source']=$bad;rejects(fn()=>make_snapshot($x),'unsupported source/no legacy reclassification');
}
foreach (['basee','basic','Legend',null] as $bad) {$x=input();$x['tier']=$bad;rejects(fn()=>make_snapshot($x),'no fallback NGO tier');}
foreach (['unknown','base','Legend',null] as $bad) {$x=input();$x['donor_level']=$bad;rejects(fn()=>make_snapshot($x),'no fallback donor level');}
foreach (['','ngo space','NGO','ngo--name',str_repeat('a',101)] as $bad) {$x=input();$x['ngo_slug']=$bad;rejects(fn()=>make_snapshot($x),'NGO binding shape');}
foreach (['2026Q0','2026Q5','2026-Q4','2026Q4\n',20264] as $bad) {$x=input();$x['ngo_quarter_key']=$bad;rejects(fn()=>make_snapshot($x),'quarter key shape');}
foreach (['2026-02-30T00:00:00Z','2026-11-03T25:00:00Z','2026-11-03T16:00:00+00:00','2026-11-03',"2026-11-03T16:00:00Z\n",null] as $bad) {
    $x=input();$x['captured_at_utc']=$bad;rejects(fn()=>make_snapshot($x),'strict real UTC capture');
}
foreach (['ngo_authority','donor_authority'] as $key) {
    $x=input();unset($x[$key]);rejects(fn()=>make_snapshot($x),'missing authority');
    $x=input();$x[$key]['observed_at_utc']='2026-11-03T16:00:01Z';rejects(fn()=>make_snapshot($x),'authority cannot be observed after capture');
    foreach (['',str_repeat('g',64),str_repeat('a',63),null] as $bad) {$x=input();$x[$key]['sha256']=$bad;rejects(fn()=>make_snapshot($x),'real canonical SHA shape');}
    foreach (['','email@example.invalid',str_repeat('x',161),null] as $bad) {$x=input();$x[$key]['ref']=$bad;rejects(fn()=>make_snapshot($x),'bounded opaque ref');}
    $x=input();$x[$key]['owner']='not-allowed';rejects(fn()=>make_snapshot($x),'extra identifying authority field');
}
foreach (['user_id','pseudo_id','points_total','rate_bps'] as $key) {$x=input();$x[$key]=123;rejects(fn()=>make_snapshot($x),'strict constructor shape');}
foreach (['rate_bps'=>9999,'donor_multiplier_bps'=>13000,'schema_version'=>2,'policy_id'=>'unknown','snapshot_digest'=>str_repeat('c',64)] as $key=>$bad) {
    $x=$s;$x[$key]=$bad;rejects(fn()=>allocate($x,'HUF','100','0','0'),'frozen contract tampering');
}
$x=$s; $x['owner']='not-allowed'; rejects(fn()=>assert_snapshot_valid($x),'extra snapshot identity');
$x=$s; unset($x['snapshot_digest']); rejects(fn()=>assert_snapshot_valid($x),'missing snapshot digest');
$x=input();$x=array_reverse($x,true);$x['ngo_authority']=array_reverse($x['ngo_authority'],true);
check(make_snapshot($x)===$s,'canonical construction independent of input key order');
$x=array_reverse($s,true);$x['donor_authority']=array_reverse($x['donor_authority'],true);
check(assert_snapshot_valid($x)===$s,'stored object key order irrelevant');
$new=make_snapshot(input('offerwall','legend','legend'));
check(allocate($s,'HUF','100','0','0')['ngo_cumulative_target_minor']==='50'
    && allocate($new,'HUF','100','0','0')['ngo_cumulative_target_minor']==='87','later capture cannot rewrite frozen earlier contract');
echo "Support policy: {$checks} checks passed; pure PHP, no DB/network/activation\n";
