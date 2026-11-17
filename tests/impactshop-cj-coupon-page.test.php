<?php
/** Offline real-seam tests: php tests/impactshop-cj-coupon-page.test.php */
define('ABSPATH', __DIR__ . '/'); define('HOUR_IN_SECONDS', 3600);
putenv('CJ_PUBLISHER_PAT=fixture-only'); putenv('CJ_PUBLISHER_ID=700000'); putenv('CJ_WEBSITE_ID=900000');
function get_option($name, $default = false) { return $name === 'impactshop_cj_shops' ? [] : ($name === 'impactshop_cj_enabled' ? false : $default); }
function update_option(...$args) { throw new LogicException('Unexpected option write'); }
function add_option(...$args) { throw new LogicException('Unexpected option write'); }
class WP_Error { function __construct(...$args) {} }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = [$url, $args];
    if (!$GLOBALS['responses']) { throw new LogicException('Unexpected extra request'); }
    return array_shift($GLOBALS['responses']);
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
require __DIR__ . '/../wp-content/mu-plugins/impactshop-cj.php';
$assertions = 0;
function ck($v, $message) { global $assertions; $assertions++; if (!$v) { throw new LogicException($message); } }
function xml_link($id = '800001', $overrides = []) {
    $fields = array_merge(['advertiser-id' => '700001', 'advertiser-name' => 'Fixture Merchant', 'link-id' => $id,
        'link-name' => '10 percent off selected items', 'description' => 'Minimum basket applies.', 'link-type' => 'Text Link',
        'relationship-status' => 'joined', 'promotion-type' => 'Coupon', 'coupon-code' => 'FIXTURE10',
        'promotion-start-date' => '2026-01-01 00:00:00.0', 'promotion-end-date' => '2026-12-31 23:59:59.0',
        'clickUrl' => 'https://tracking.invalid/click-fixture', 'destination' => 'https://merchant.invalid/'], $overrides);
    $xml = '<link>';
    foreach ($fields as $key => $value) { if ($value !== null) { $xml .= '<' . $key . '>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $key . '>'; } }
    return $xml . '</link>';
}
function xml_page($rows = [], $total = null, $page = 1) {
    return '<cj-api><links total-matched="' . ($total ?? count($rows)) . '" records-returned="' . count($rows) . '" page-number="' . $page . '">' . implode('', $rows) . '</links></cj-api>';
}
function read_xml($xml, $type = 'coupon', $page = 1) {
    $GLOBALS['requests'] = []; $GLOBALS['responses'] = [['response' => ['code' => 200], 'body' => $xml]];
    $r = ImpactShop_CJ::read_coupon_page($type, $page, 4.5);
    ck(count($GLOBALS['requests']) === 1, 'one read'); return $r;
}
function reject_xml($xml, $reason = 'coupon_schema', $type = 'coupon', $page = 1) {
    $GLOBALS['requests'] = []; $GLOBALS['responses'] = [['response' => ['code' => 200], 'body' => $xml]];
    try { ImpactShop_CJ::read_coupon_page($type, $page, 4); throw new LogicException('Invalid XML accepted'); }
    catch (RuntimeException $e) { ck($e->getMessage() === $reason, 'fixed expected error: ' . $reason); }
    ck(count($GLOBALS['requests']) === 1, 'invalid response no retry');
}
$r = read_xml(xml_page([xml_link()]));
ck($r['websiteId'] === '900000' && $r['page'] === 1 && $r['total'] === 1 && $r['returned'] === 1, 'scope and counters');
ck($r['responseBytes'] === strlen(xml_page([xml_link()])), 'caller receives byte count');
ck($r['rows'][0]['relationship_status'] === 'joined' && $r['rows'][0]['link_type'] === 'Text Link', 'scope fields');
ck($r['rows'][0]['coupon_code'] === 'FIXTURE10' && $r['rows'][0]['promotion_start'] === '2026-01-01 00:00:00.0', 'existing normalizer');
[$url, $args] = $GLOBALS['requests'][0]; $u = parse_url($url); parse_str($u['query'], $q);
ck($u['scheme'] === 'https' && $u['host'] === 'link-search.api.cj.com' && $u['path'] === '/v2/link-search', 'fixed endpoint');
ck($q === ['website-id'=>'900000', 'advertiser-ids'=>'joined', 'promotion-type'=>'coupon', 'records-per-page'=>'100', 'page-number'=>'1'], 'exact query');
ck($args['headers'] === ['Authorization'=>'Bearer fixture-only', 'Accept'=>'application/xml'], 'existing auth contract');
ck($args['timeout'] === 4.5 && $args['sslverify'] === true && $args['redirection'] === 0 && $args['limit_response_size'] === 1048577, 'bounds');
$r = read_xml(str_replace('clickUrl', 'clickURL', xml_page([xml_link()])));
ck($r['rows'][0]['click_url'] === 'https://tracking.invalid/click-fixture', 'documented clickURL alias');
$r = read_xml(xml_page([str_replace('</link>', '<link-code-html><a href="https://tracking.invalid/">Private creative</a></link-code-html></link>', xml_link())]));
ck(!isset($r['rows'][0]['link-code-html']), 'creative not returned');
ck(read_xml(xml_page())['rows'] === [], 'complete zero');
foreach (['sale/discount'=>'Sale/Discount', 'free shipping'=>'Free Shipping'] as $type=>$label) {
    ck(read_xml(xml_page([xml_link('800001', ['promotion-type'=>$label, 'coupon-code'=>''])]), $type)['rows'][0]['promotion_type'] === $label, 'promotion types');
}
$hundred = array_map(fn($i) => xml_link((string) (800000 + $i)), range(1, 100));
ck(read_xml(xml_page($hundred, 101))['returned'] === 100, 'full intermediate page');
ck(read_xml(xml_page([xml_link()], 101, 2), 'coupon', 2)['returned'] === 1, 'final remainder');
$good = xml_page([xml_link()]);
foreach (['not XML', '<other/>', '<cj-api/>', str_replace('cj-api', 'other', $good),
    str_replace('<cj-api>', '<cj-api xmlns="urn:foreign">', $good),
    str_replace('<link-id>', '<link-id xmlns="urn:foreign">', $good),
    str_replace('</cj-api>', '<links total-matched="0" records-returned="0" page-number="1"/></cj-api>', $good),
    str_replace('<link-id>800001</link-id>', '<link-id><nested>800001</nested></link-id>', $good),
    str_replace('<link-id>800001</link-id>', '<link-id>800001</link-id><link-id>800002</link-id>', $good),
    str_replace('records-returned="1"', 'records-returned="0"', $good),
    str_replace('total-matched="1"', 'total-matched="2001"', $good),
    str_replace('page-number="1"', 'page-number="2"', $good),
    xml_page([xml_link()], 101), xml_page([xml_link(), xml_link()]),
    xml_page([xml_link('0')]), xml_page([xml_link('999999999999999999999999999999')]),
    xml_page([xml_link('800001', ['relationship-status'=>'not-joined'])]),
    xml_page([xml_link('800001', ['promotion-type'=>'Sale/Discount'])]),
    xml_page([xml_link('800001', ['link-type'=>'Product Link'])]),
    xml_page([xml_link('800001', ['promotion-end-date'=>null])]),
    xml_page([xml_link('800001', ['clickURL'=>'https://different.invalid/'])]),
    str_replace('<description>', '<description attribute="ambiguous">', $good),
    "\xff" . $good] as $xml) { reject_xml($xml); }
foreach (['<!DOCTYPE cj-api>', '<!DOCTYPE cj-api [<!ENTITY x "x">]>', '<!DOCTYPE cj-api SYSTEM "https://external.invalid/xxe">'] as $dtd) {
    reject_xml($dtd . xml_page());
    reject_xml(iconv('UTF-8', 'UTF-16LE', '<?xml version="1.0" encoding="UTF-16"?>' . $dtd . xml_page()));
}
reject_xml('<?xml version="1.0" encoding="ISO-8859-1"?>' . xml_page());
reject_xml(str_repeat('x', 1048577), 'coupon_limits');
foreach ([401=>'coupon_auth',403=>'coupon_auth',429=>'coupon_rate_limit',500=>'coupon_transport'] as $status=>$reason) {
    $GLOBALS['requests']=[]; $GLOBALS['responses']=[['response'=>['code'=>$status],'body'=>'Not Authenticated: fixture-only']];
    try { ImpactShop_CJ::read_coupon_page('coupon',1,4); throw new LogicException('HTTP error accepted'); }
    catch (RuntimeException $e) { ck($e->getMessage() === $reason && strpos($e->getMessage(),'fixture-only')===false,'HTTP secret redaction'); }
    ck(count($GLOBALS['requests'])===1,'HTTP failure no retry');
}
$GLOBALS['requests']=[]; $GLOBALS['responses']=[new WP_Error('fixture-only')];
try { ImpactShop_CJ::read_coupon_page('coupon',1,4); throw new LogicException('Transport accepted'); }
catch (RuntimeException $e) { ck($e->getMessage()==='coupon_transport','transport fixed error'); }
foreach ([['invalid',1,4],['coupon',0,4],['coupon',21,4],['coupon',1,0],['coupon',1,9],['coupon',1,NAN],['coupon',1,INF]] as $input) {
    $GLOBALS['requests']=[];
    try { ImpactShop_CJ::read_coupon_page(...$input); throw new LogicException('Invalid input accepted'); }
    catch (RuntimeException $e) { ck($e->getMessage()==='coupon_schema','invalid input'); }
    ck($GLOBALS['requests']===[],'invalid input zero network');
}
foreach ([['CJ_WEBSITE_ID','invalid'],['CJ_WEBSITE_ID',"90\n0000"],['CJ_PUBLISHER_PAT',"fixture\r\ninvalid"]] as [$key,$value]) {
    putenv($key.'='.$value); $GLOBALS['requests']=[];
    try { ImpactShop_CJ::read_coupon_page('coupon',1,4); throw new LogicException('Bad credential accepted'); }
    catch (RuntimeException $e) { ck($e->getMessage()==='coupon_auth','invalid credential'); }
    ck($GLOBALS['requests']===[],'invalid credential zero network');
    putenv('CJ_WEBSITE_ID=900000'); putenv('CJ_PUBLISHER_PAT=fixture-only');
}
$old = libxml_use_internal_errors(false); read_xml(xml_page()); ck(libxml_use_internal_errors()===false,'libxml mode restored'); libxml_use_internal_errors($old);
echo "CJ coupon page: {$assertions} assertions PASS (offline)\n";
