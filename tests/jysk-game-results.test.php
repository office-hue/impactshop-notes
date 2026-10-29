<?php
/** Pure protocol/raffle checks; no host database or HTTP. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/fixtures/no-wordpress-dropin');
define('WP_CLI', true);
define('JYSK_GAME_POLICY_HASH', str_repeat('b', 64));
define('JYSK_GAME_PRODUCER_SHAS', [str_repeat('c', 40)]);
define('JYSK_GAME_NGO_IDS', ['ngo-A', 'ngo-a']);
function add_action(...$args): void {}
function add_filter(...$args): void {}
$GLOBALS['clock'] = strtotime('2026-11-30T12:00:00Z');
$GLOBALS['forceRejection'] = false;
$source = str_replace('declare(strict_types=1);', '', substr(file_get_contents(__DIR__ . '/../wp-content/mu-plugins/impactshop-jysk-game.php'), 5));
eval('namespace JyskResultsFixture; use \\RuntimeException; use \\Throwable; function time() { return $GLOBALS["clock"]; } function hash_hmac($algo,$data,$key,$binary=false) { if ($GLOBALS["forceRejection"]) { $GLOBALS["forceRejection"]=false; return str_repeat(chr(255),32); } return \\hash_hmac($algo,$data,$key,$binary); } ' . $source);
use JyskResultsFixture\Impactshop_Jysk_Game as Bridge;
$checks = 0;
function check(bool $ok, string $name): void { global $checks; $checks++; if (!$ok) { throw new RuntimeException('FAIL: ' . $name); } }
function rejects(callable $call, string $name): void { try { $call(); } catch (Throwable $e) { check(true, $name); return; } check(false, $name); }
function manifest(int $people = 4): array {
    $members = [];
    foreach (range(1, max(1, $people)) as $i) {
        if ($people === 0) { break; }
        $subject = hash('sha256', 'subject-' . $i);
        $members[] = ['personKey' => hash('sha256', 'person-' . $i), 'subject' => $subject, 'subjects' => [$subject], 'weight' => $i * 5];
    }
    return ['kind' => 'jysk-game-results-v1', 'campaign' => 'jysk_2026', 'sourceCommit' => str_repeat('c', 40), 'sourceTree' => str_repeat('d', 40), 'target' => str_repeat('a', 64),
        'configVersion' => 8, 'rosterVersion' => 1, 'policyHash' => str_repeat('b', 64), 'termsVersion' => 'terms-v1', 'privacyVersion' => 'privacy-v1',
        'endsAt' => '2026-11-29T23:00:00.000Z', 'generatedAt' => '2026-11-30T00:00:00.000Z', 'datasetHash' => str_repeat('1', 64),
        'decisionsHash' => str_repeat('2', 64), 'rosterHash' => str_repeat('3', 64), 'reviewReference' => str_repeat('4', 64),
        'eventCount' => 10, 'validEventCount' => 10, 'globalPoints' => ['queued' => 0,'sending' => 0,'unknown' => 1,'delivered' => 9,'rejected' => 0],
        'ngos' => [['ngoId' => 'ngo-A','displayName' => 'Civil A','votes' => 25], ['ngoId' => 'ngo-a','displayName' => 'Civil B','votes' => 25]], 'members' => $members];
}
$m = manifest(); check(Bridge::validateResults($m) === $m, 'exact envelope accepted');
$seed = str_repeat('5', 64); $digest = hash('sha256', json_encode($m));
$draw = Bridge::drawResults($m, $digest, $seed);
$vector = ['members' => [], 'ngos' => $m['ngos']];
foreach (['a','b','c','d'] as $i => $id) { $vector['members'][] = ['personKey' => str_repeat($id,64),'weight' => ($i+1)*5]; }
$actualVector = Bridge::drawResults($vector,str_repeat('6',64),$seed);
check(array_column($actualVector['winners'],'personKey') === [str_repeat('b',64),str_repeat('c',64),str_repeat('a',64)] && $actualVector['ngoWinner']['ngoId'] === 'ngo-a','TypeScript/PHP v1 vector and bytewise NGO tie order');
check($draw === Bridge::drawResults($m, $digest, $seed), 'same seed resumes same draw');
check(count($draw['winners']) === 3 && count(array_unique(array_column($draw['winners'], 'personKey'))) === 3, 'three distinct weighted people');
check(in_array($draw['ngoWinner']['ngoId'], ['ngo-A','ngo-a'], true), 'separate NGO tie draw');
foreach ([0,1,2] as $n) { check(count(Bridge::drawResults(manifest($n), $digest, $seed)['winners']) === $n, 'small pool has no replacement ' . $n); }
$excluded = array_column($draw['winners'], 'personKey');
$replacement = Bridge::drawResults($m, $digest, $seed, 1, $excluded, 1);
check(count($replacement['winners']) === 1 && !in_array($replacement['winners'][0]['personKey'], $excluded, true), 'replacement excludes every previous winner');
$GLOBALS['forceRejection'] = true;
$rejected = Bridge::drawResults($m, $digest, $seed);
check(!$GLOBALS['forceRejection'] && count($rejected['winners']) === 3, 'all-ones sample rejected, then progresses');
foreach (['private' => 'secret', 'members' => [array_merge($m['members'][0], ['weight' => 1000])], 'ngos' => [array_merge($m['ngos'][0], ['votes' => -5])], 'sourceCommit' => str_repeat('e',40), 'endsAt' => '2026-12-01T00:00:00.000Z'] as $field => $value) {
    $bad = $m; $bad[$field] = $value; rejects(fn () => Bridge::validateResults($bad), 'reject invalid ' . $field);
}
$bad = $m; $bad['members'][] = $m['members'][0]; rejects(fn () => Bridge::validateResults($bad), 'duplicate person rejected');
$bad = $m; $bad['members'][1]['subjects'][] = $m['members'][0]['subject']; rejects(fn () => Bridge::validateResults($bad), 'cross-person subject rejected');
$GLOBALS['clock'] = strtotime('2026-11-20T00:00:00Z'); rejects(fn () => Bridge::validateResults($m), 'premature result rejected');
check(!str_contains(json_encode($draw['ngoWinner']), 'personKey'), 'public NGO result has no person');
echo json_encode(['result' => 'PASS','checks' => $checks,'scope' => 'pure results protocol and deterministic draw; no database integration claim']), "\n";
