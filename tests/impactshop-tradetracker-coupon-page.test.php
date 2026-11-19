<?php
/** Offline real-class proof: php tests/impactshop-tradetracker-coupon-page.test.php */
define('ABSPATH', __DIR__ . '/');
$now = 1000.0; $requests = []; $responses = []; $checks = 0;
function impactshop_tradetracker_clock() { return $GLOBALS['now']; }
function add_action(...$a) { throw new LogicException('Unexpected hook'); }
function update_option(...$a) { throw new LogicException('Unexpected writer'); }
function add_option(...$a) { throw new LogicException('Unexpected writer'); }
class WP_Error {}
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_remote_post($url, $args) {
    $GLOBALS['requests'][] = [$url, $args];
    if (!$GLOBALS['responses']) { throw new LogicException('Unexpected HTTP'); }
    $r = array_shift($GLOBALS['responses']);
    if (is_array($r)) { $GLOBALS['now'] += $r['advance'] ?? 0; }
    return $r;
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
require __DIR__ . '/../wp-content/mu-plugins/impactshop-tradetracker.php';
function ck($v, $label) { $GLOBALS['checks']++; if (!$v) { throw new LogicException($label); } }
function reset_fixture() { $GLOBALS['now'] = 1000.0; $GLOBALS['requests'] = []; $GLOBALS['responses'] = []; }
function client() { return new ImpactShop_TradeTracker('123456', 'fixture-only'); }
function envelope($method, $body = '') {
    return '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><'
        . $method . 'Response xmlns="https://ws.tradetracker.com/soap-literal-wsi/affiliate">' . $body
        . '</' . $method . 'Response></soap:Body></soap:Envelope>';
}
function http($body, $cookies = [], $status = 200, $advance = 0) {
    return ['response' => ['code' => $status], 'body' => $body, 'cookies' => $cookies, 'advance' => $advance];
}
function auth($body = null) {
    return http($body ?? envelope('authenticate'), [(object) ['name' => 'PHPSESSID', 'value' => 'synthetic-session', 'domain' => '.tradetracker.com']]);
}
function site() {
    return envelope('getAffiliateSites', '<affiliateSites><affiliateSite><ID>481183</ID><name>Synthetic site</name>'
        . '<URL>https://www.sharity.hu/</URL><info><status>accepted</status></info><logoURL xsi:nil="true"/></affiliateSite></affiliateSites>');
}
function campaign() {
    return envelope('getCampaigns', '<campaigns><campaign><ID>700001</ID><name>Synthetic shop</name><URL>https://merchant.invalid/</URL>'
        . '<info><assignmentStatus>accepted</assignmentStatus><policyCashbackStatus>allowed</policyCashbackStatus>'
        . '<policyDiscountCodeStatus>allowed</policyDiscountCodeStatus><timeZone>Europe/Budapest</timeZone>'
        . '<referencesSupported>true</referencesSupported></info><logoURL xsi:nil="true"/></campaign></campaigns>');
}
function item($id = '800001', $changes = []) {
    $fields = array_replace(['ID' => $id, 'name' => 'Synthetic discount', 'description' => 'Selected items.',
        'conditions' => 'Minimum basket applies.', 'voucherCode' => 'FIXTURE10', 'validFromDate' => '2026-01-01',
        'validToDate' => '2026-12-31', 'code' => '<a href="https://tracking.invalid/private">Creative</a>', 'referenceSupported' => 'true'], $changes);
    $xml = '<materialItem><campaign><ID>700001</ID></campaign>';
    foreach ($fields as $name => $value) {
        if ($value === null) { $xml .= '<' . $name . ' xsi:nil="true"/>'; }
        else { $xml .= '<' . $name . '>' . htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</' . $name . '>'; }
    }
    return $xml . '</materialItem>';
}
function page($items = '', $resource = 'voucher') {
    return envelope($resource === 'voucher' ? 'getMaterialIncentiveVoucherItems' : 'getMaterialIncentiveOfferItems', '<materialItems>' . $items . '</materialItems>');
}
function ready($material, $site = null, $campaign = null) {
    $GLOBALS['responses'] = [auth(), http($site ?? site()), http($campaign ?? campaign()), http($material)];
}
function reject($queue, $code, $count, $args = ['voucher', '700001', 0, 100]) {
    reset_fixture(); $GLOBALS['responses'] = $queue; $c = client();
    try { $c->get_coupon_page(...$args); throw new LogicException('Rejected response accepted'); }
    catch (RuntimeException $e) { ck($e->getMessage() === $code, 'fixed expected error ' . $code); ck(strpos($e->getMessage(), 'fixture-only') === false, 'no secret in error'); }
    ck(count($GLOBALS['requests']) === $count, 'exact failure request count');
    try { $c->get_coupon_page(...$args); throw new LogicException('Failed client retried'); }
    catch (RuntimeException $e) { ck($e->getMessage() === $code, 'failed instance suspended'); }
    ck(count($GLOBALS['requests']) === $count, 'no retry after failure');
}

// Known byte vectors protect the wire contract independently of a codec roundtrip.
$referenceVectors = [
    ['sat1_' . str_repeat('A', 43), 'tt1-' . str_repeat('0', 64)],
    ['sat1_' . str_repeat('_', 42) . '8', 'tt1-' . str_repeat('f', 64)],
    ['sat1_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8',
        'tt1-000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f'],
    ['sat1_' . rtrim(strtr(base64_encode(str_repeat("\xfb", 32)), '+/', '-_'), '='),
        'tt1-' . str_repeat('fb', 32)],
];
reset_fixture();
foreach ($referenceVectors as [$token, $reference]) {
    ck(ImpactShop_TradeTracker::reference_for_token($token) === $reference, 'known TT reference vector');
    ck(ImpactShop_TradeTracker::token_for_reference($reference) === $token, 'known original SAT1 vector');
    ck(strlen($reference) === 68 && preg_match('/^[a-z0-9-]+$/D', $reference), 'TT alphabet and length');
    ck(ImpactShop_TradeTracker::token_for_reference(ImpactShop_TradeTracker::reference_for_token($token)) === $token,
        'original token and correlation hash remain recoverable');
}
$validToken = $referenceVectors[0][0]; $validReference = $referenceVectors[0][1];
foreach ([null, false, 123, [], new stdClass(), '', substr($validToken, 1), $validToken . 'A',
    $validToken . "\n", str_replace('_', '-', $validToken), 'sat1_' . str_repeat('A', 42) . 'B',
    substr($validToken, 0, -1) . '=', 'sat1_' . str_repeat('A', 42) . '/', "sat1_\0" . str_repeat('A', 42)] as $invalid) {
    try { ImpactShop_TradeTracker::reference_for_token($invalid); throw new LogicException('Invalid token accepted'); }
    catch (InvalidArgumentException $e) { ck($e->getMessage() === 'tt_reference', 'fixed token error without input'); }
}
foreach ([null, false, 123, [], new stdClass(), '', $validToken, substr($validReference, 1),
    $validReference . '0', $validReference . "\n", 'tt1-' . str_repeat('F', 64),
    'tt2-' . str_repeat('0', 64), 'tt1-' . str_repeat('0', 63) . '_', " tt1-" . str_repeat('0', 64)] as $invalid) {
    try { ImpactShop_TradeTracker::token_for_reference($invalid); throw new LogicException('Invalid reference accepted'); }
    catch (InvalidArgumentException $e) { ck($e->getMessage() === 'tt_reference', 'fixed reference error without input'); }
}
ck($requests === [] && $responses === [], 'codec requires no constructor, credential, HTTP or queued response');

reset_fixture(); ready(page(item())); $c = client(); $r = $c->get_coupon_page('voucher', '700001', 0, 1);
ck(count($requests) === 4, 'auth site campaign page');
ck($r['siteId'] === '481183' && $r['campaign']['campaignId'] === '700001' && $r['returned'] === 1, 'exact scope');
ck($r['campaign']['policyCashbackStatus'] === 'allowed' && $r['campaign']['timeZone'] === 'Europe/Budapest', 'qualified flags');
ck($r['rows'][0]['voucherCode'] === 'FIXTURE10' && strpos($r['rows'][0]['code'], 'tracking.invalid') !== false, 'voucher and private creative distinct');
ck($r['responseBytes'] === strlen(page(item())) && $r['totalResponseBytes'] === array_sum(array_map(fn($x) => strlen($x['body']), [auth(), http(site()), http(campaign()), http(page(item()))])), 'actual wire bytes');
$wireBefore = 0; $wireBodies = [auth()['body'], site(), campaign(), page(item())];
foreach ($requests as $i => [$url, $args]) {
    ck($url === 'https://ws.tradetracker.com/soap-literal-wsi/affiliate', 'endpoint, never WSDL');
    ck($args['timeout'] === 8 && $args['redirection'] === 0 && $args['sslverify'] === true && $args['limit_response_size'] === 1048577 - $wireBefore, 'remaining download/time/TLS bounds');
    $wireBefore += strlen($wireBodies[$i]);
    ck(count($args['cookies']) === ($i === 0 ? 0 : 1), 'in-memory auth cookie');
    $d = new DOMDocument(); ck($d->loadXML($args['body'], LIBXML_NONET), 'well-formed request');
    $xp = new DOMXPath($d); $xp->registerNamespace('tt', 'https://ws.tradetracker.com/soap-literal-wsi/affiliate');
    $method = ['authenticate', 'getAffiliateSites', 'getCampaigns', 'getMaterialIncentiveVoucherItems'][$i];
    ck(strpos($args['headers']['Content-Type'], '/affiliate/' . $method . '"') !== false, 'SOAP1.2 action');
    foreach ($d->getElementsByTagName('*') as $e) { ck(in_array($e->namespaceURI, ['http://www.w3.org/2003/05/soap-envelope', 'https://ws.tradetracker.com/soap-literal-wsi/affiliate'], true), 'qualified request elements'); }
    if ($i === 0) { ck($xp->evaluate('string(//tt:passphrase)') === 'fixture-only', 'actual supplied credential'); }
    if ($i === 1) { ck($xp->evaluate('string(//tt:options/tt:ID)') === '481183', 'site-filter ID'); }
    if ($i === 2) { ck($xp->evaluate('string(//tt:options/tt:ID)') === '700001' && $xp->evaluate('string(//tt:assignmentStatus)') === 'accepted', 'exact accepted campaign query'); }
    if ($i === 3) { ck($xp->evaluate('string(//tt:includeUnsubscribedCampaigns)') === 'false' && $xp->evaluate('string(//tt:limit)') === '1' && $xp->evaluate('string(//tt:sort)') === 'ID', 'material scope and paging'); }
}
$responses = [http(page(item('800002', ['voucherCode' => null, 'validFromDate' => null, 'validToDate' => null]), 'offer'))];
$r = $c->get_coupon_page('offer', 700001, 100, 100);
ck(count($requests) === 5 && $r['rows'][0]['voucherCode'] === null && $r['rows'][0]['validToDate'] === null, 'reuse session/site/campaign; offer nil preserved');
$responses = [http(page())]; ck($c->get_coupon_page('voucher', '700001')['returned'] === 0, 'actual empty page');

foreach ([['bad', 'fixture-only'], [0, 'fixture-only'], [1.5, 'fixture-only'], [123, null], [123, "bad\nsecret"], [123, "\xffinvalid"]] as [$a, $b]) {
    reset_fixture(); try { new ImpactShop_TradeTracker($a, $b); throw new LogicException('Invalid constructor accepted'); }
    catch (RuntimeException $e) { ck($e->getMessage() === 'tt_input' && count($requests) === 0, 'constructor0network'); }
}
foreach ([['banner', '700001', 0, 100], ['voucher', '0', 0, 100], ['voucher', '700001', -1, 100], ['voucher', '700001', 0, 0], ['voucher', '700001', 0, 101], ['voucher', '700001', 0.5, 10]] as $args) { reject([], 'tt_input', 0, $args); }
foreach ([401 => 'tt_auth', 403 => 'tt_auth', 429 => 'tt_rate_limit', 302 => 'tt_transport', 503 => 'tt_transport'] as $status => $error) { reject([http('fixture-only echoed', [], $status)], $error, 1); }
reject([new WP_Error()], 'tt_transport', 1);
reject([http(envelope('authenticate'))], 'tt_auth', 1);
foreach (['', '<bad/>', str_replace('2003/05', '2001/05', envelope('authenticate')), str_replace('affiliate', 'foreign', envelope('authenticate')),
    '<!DOCTYPE x [<!ENTITY secret "fixture-only">]>' . envelope('authenticate'), envelope('authenticate') . "\0", "\xff" . envelope('authenticate'),
    '<?xml version="1.0" encoding="ISO-8859-1"?>' . envelope('authenticate'),
    iconv('UTF-8', 'UTF-16LE', '<?xml version="1.0" encoding="UTF-16"?>' . envelope('authenticate'))] as $xml) { reject([auth($xml)], 'tt_schema', 1); }
reject([auth(str_repeat('x', 1048577))], 'tt_limits', 1);
$fault = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"><soap:Body><soap:Fault><soap:Reason>fixture-only echoed</soap:Reason></soap:Fault></soap:Body></soap:Envelope>';
reject([http($fault, [], 500)], 'tt_auth', 1);
reject([auth(), http($fault, [], 500)], 'tt_fault', 2);
foreach ([str_replace('481183', '999999', site()), str_replace('accepted', 'pending', site()), str_replace('www.sharity.hu', 'other.invalid', site())] as $xml) { reject([auth(), http($xml)], 'tt_scope', 2); }
foreach ([str_replace('700001', '700002', campaign()), str_replace('accepted', 'pending', campaign())] as $xml) { reject([auth(), http(site()), http($xml)], 'tt_scope', 3); }
reject([auth(), http(site()), http(str_replace('<assignmentStatus>', '<assignmentStatus xsi:nil="true">', campaign()))], 'tt_schema', 3);
foreach ([str_replace('affiliate', 'foreign', page(item())), page(item() . item()),
    str_replace('<name>Synthetic discount</name>', '<name>A</name><name>B</name>', page(item())),
    str_replace('<name>Synthetic discount</name>', '<name><nested>A</nested></name>', page(item())),
    str_replace('<name>Synthetic discount</name>', '<name xmlns="urn:foreign">A</name>', page(item())),
    str_replace('<conditions>Minimum basket applies.</conditions>', '<conditions xsi:nil="true">conflict</conditions>', page(item())),
    str_replace('<referenceSupported>true</referenceSupported>', '<referenceSupported>maybe</referenceSupported>', page(item())),
    str_replace('</materialItems>', '</materialItems><materialItems/>', page(item())),
    str_replace('<materialItems>', '<materialItems xsi:nil="true">', page(item()))] as $xml) { reject([auth(), http(site()), http(campaign()), http($xml)], 'tt_schema', 4); }
reject([auth(), http(site()), http(campaign()), http(page(str_replace('<campaign><ID>700001', '<campaign><ID>700002', item())))], 'tt_scope', 4);
reject([auth(), http(site()), http(campaign()), http(page(item() . item('800002')))], 'tt_schema', 4, ['voucher', '700001', 0, 1]);
reset_fixture(); ready(page(item())); $responses[0]['advance'] = 59.5; $c = client(); $c->get_coupon_page('voucher', '700001');
ck($requests[1][1]['timeout'] === 0.5 && $requests[3][1]['timeout'] === 0.5, 'remaining deadline shortens every request');
reset_fixture(); ready(page()); $responses[3]['advance'] = 60; $queue = $responses; reject($queue, 'tt_limits', 4);
reset_fixture(); $large = envelope('authenticate'); $large .= str_repeat(' ', 1048576 - strlen($large)); reject([auth($large), http(site())], 'tt_limits', 1);
$nearlyFull = substr($large, 0, -100); reject([auth($nearlyFull), http(site())], 'tt_limits', 2);
ck($requests[1][1]['limit_response_size'] === 101, 'partial byte budget caps next download at remaining plus sentinel');
reset_fixture(); ready(page()); $c = client(); $c->get_coupon_page('voucher', '700001');
for ($i = 0; $i < 16; $i++) { $responses[] = http(page()); $c->get_coupon_page('voucher', '700001', ($i + 1) * 100); }
ck(count($requests) === 20, 'one shared20request budget');
try { $c->get_coupon_page('voucher', '700001'); throw new LogicException('21st request allowed'); }
catch (RuntimeException $e) { ck($e->getMessage() === 'tt_limits' && count($requests) === 20, '21st request blocked before HTTP'); }
echo 'TradeTracker page client: ' . $checks . " assertions PASS\n";
