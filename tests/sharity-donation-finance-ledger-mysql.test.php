<?php
/** Included by the existing disposable orders MariaDB harness. Real SDK, offline HTTP. */
declare(strict_types=1);
if (!isset($wpdb, $adapter, $transport, $subject, $now)) { throw new RuntimeException('Run sharity-donation-orders-mysql.test.php'); }
$financeStart = $checks; $wpdb->failOn = ''; $wpdb->failure = '';
$ddls = $wpdb->ddls;
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/finance-ledger.php';
check($wpdb->ddls === $ddls, 'finance module does not run DDL on load');
if (!defined('WP_CLI')) { define('WP_CLI', true); }
class WP_CLI {
    public static array $commands = []; public static array $lines = [];
    public static function add_command($name, $handler): void { self::$commands[$name] = $handler; }
    public static function line($line): void { self::$lines[] = $line; }
    public static function error($message): void { throw new RuntimeException($message); }
}
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations/finance-read.php';

final class FinanceHttp implements Stripe\HttpClient\ClientInterface {
    public array $responses = []; public int $gets = 0; public bool $drop = false;
    public function request($method, $url, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null) {
        global $wpdb;
        if ($method !== 'get' || $maxNetworkRetries !== 0 || !str_starts_with($url, 'https://api.stripe.com/v1/')) { throw new RuntimeException('No financial provider write/retry allowed'); }
        if ($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx'] !== '0') { throw new RuntimeException('Provider inside transaction'); }
        $this->gets++;
        if ($this->drop) { throw new Stripe\Exception\ApiConnectionException('PRIVATE_PROVIDER_ERROR'); }
        $path = parse_url($url, PHP_URL_PATH);
        if (!isset($this->responses[$path])) { throw new RuntimeException('Unexpected GET ' . $path); }
        return [json_encode($this->responses[$path], JSON_THROW_ON_ERROR), 200, []];
    }
    public function forOrder(array $row): void {
        $e = sharity_donation_order_decode($row); $s = $e['snapshot']; $cc = strtolower($s['currency']); $gross = (int) $s['amount_minor'];
        $session = checkoutEvent($row)['data']['object']; $session['id'] = $row['provider_session_id'];
        $session['payment_intent'] = ['object'=>'payment_intent','id'=>'pi_FINANCE','metadata'=>$e['payload']['metadata'],
            'amount'=>$gross,'currency'=>$cc,'livemode'=>false,'amount_received'=>$gross,'status'=>'succeeded',
            'latest_charge'=>'ch_FINANCE','application_fee_amount'=>0,'transfer_data'=>null,'customer'=>'PRIVATE_CUSTOMER'];
        $list = ['object'=>'list','data'=>[],'has_more'=>false];
        $charge = ['object'=>'charge','id'=>'ch_FINANCE','payment_intent'=>'pi_FINANCE','livemode'=>false,'status'=>'succeeded','paid'=>true,'captured'=>true,
            'amount'=>$gross,'amount_captured'=>$gross,'currency'=>$cc,'metadata'=>$e['payload']['metadata'],'balance_transaction'=>'txn_FINANCE',
            'application_fee'=>null,'application_fee_amount'=>0,'amount_refunded'=>0,'refunded'=>false,'disputed'=>false,
            'failure_balance_transaction'=>null,'refunds'=>$list,'billing_details'=>['email'=>'PRIVATE_EMAIL']];
        $balance = ['object'=>'balance_transaction','id'=>'txn_FINANCE','source'=>'ch_FINANCE','type'=>'charge',
            'amount'=>$gross,'currency'=>$cc,'fee'=>2000,'net'=>$gross-2000,
            'fee_details'=>[['amount'=>2000,'currency'=>$cc,'type'=>'stripe_fee','description'=>'PRIVATE_DESCRIPTION']],
            'created'=>1800000010,'available_on'=>1800600000,'status'=>'available','exchange_rate'=>null];
        $this->responses = ['/v1/checkout/sessions/'.$row['provider_session_id']=>$session,'/v1/charges/ch_FINANCE'=>$charge,
            '/v1/balance_transactions/txn_FINANCE'=>$balance,'/v1/refunds'=>$list,'/v1/disputes'=>$list];
    }
}
// Inject only the response to the post-INSERT read; the underlying DB stays real.
final class FinanceReadbackDb extends OrderDb {
    public string $readFault = ''; public bool $armed = false; public int $inserts = 0;
    public function query(string $sql) {
        $result = parent::query($sql);
        if (str_starts_with($sql, 'INSERT INTO `'.sharity_donation_finance_table().'`')) { $this->armed = true; $this->inserts++; }
        return $result;
    }
    public function get_row(string $sql, $mode): ?array {
        $row = parent::get_row($sql,$mode);
        if ($this->armed && str_contains($sql,'AND digest =')) {
            if ($this->readFault === 'missing') { return null; }
            if ($this->readFault === 'corrupt' && $row !== null) { $row['account_id'] = 'acct_OTHER'; }
        }
        return $row;
    }
}
function financeOrder(string $tag): array {
    global $transport, $adapter, $subject, $now, $financeHttp;
    Stripe\ApiRequestor::setHttpClient($transport);
    $o = newOrder('finance-' . $tag); sharity_donation_order_dispatch($subject, $o['order_id'], $adapter, $now);
    $row = sharity_donation_order_read($subject, 'order_id', $o['order_id']);
    $financeHttp->forOrder($row); Stripe\ApiRequestor::setHttpClient($financeHttp); return $row;
}
function financeCount(): int { global $wpdb; $t = sharity_donation_finance_table(); return (int) $wpdb->db->query("SELECT COUNT(*) AS n FROM `{$t}`")->fetch_assoc()['n']; }
$financeHttp = new FinanceHttp(); $financeTable = sharity_donation_finance_table();
try {
    foreach ([[[], []], [[], ['apply'=>false]], [[], ['apply'=>'true']], [['apply'], []], [[], ['apply'=>true,'extra'=>true]]] as [$args,$assoc]) {
        fails(fn()=>sharity_donation_cli_finance_schema($args,$assoc), 'DONATION_FINANCE_SCHEMA_UNAVAILABLE');
        check($wpdb->ddls === $ddls, 'no exact --apply means no DDL');
    }
    sharity_donation_cli_finance_schema([], ['apply'=>true]);
    check(end(WP_CLI::$lines) === 'DONATION_FINANCE_SCHEMA_READY' && get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION) === '1', 'explicit CLI schema ready');
    $row = financeOrder('base'); $beforeOrder = $row; $id = $row['order_id']; $beforePosts = calls();
    $record = sharity_donation_finance_record($id,$adapter,$now);
    check(financeCount() === 1 && is_string($record['id']), 'one observation, exact string local identifier');
    $saved = sharity_donation_finance_read($id,$record['digest']);
    $data = json_decode($saved['canonical_json'],true,64,JSON_THROW_ON_ERROR);
    check($data['original']['initial_charge_balance']['gross_minor'] === '50000' && $data['original']['initial_charge_balance']['processing_cost_minor'] === '2000', 'exact minor amounts');
    check(!str_contains($saved['canonical_json'],'PRIVATE_') && !str_contains($saved['canonical_json'],'subject_ref'), 'no provider PII or owner copy');
    foreach (['payment_verified','allocation_eligible','atomic_snapshot','settlement_reconciled'] as $flag) { check($data[$flag] === false, 'observation is not financial acceptance: '.$flag); }
    check(sharity_donation_finance_record($id,$adapter,$now+60) === $record && financeCount() === 1, 'repeat returns same ID and first time');
    check(sharity_donation_order_read($subject,'order_id',$id) === $beforeOrder && calls() === $beforePosts, 'order unchanged and zero new POST');
    check(sharity_donation_finance_schema_apply() && financeCount() === 1, 'idempotent schema preserves observation');
    sharity_donation_cli_payment_record([],['order'=>$id]);
    check(json_decode(end(WP_CLI::$lines),true) === $record, 'CLI record readback receipt');
    foreach ([[[],[]],[[],['order'=>'bad']],[[$id],[]],[[],['order'=>$id,'apply'=>true]]] as [$args,$assoc]) {
        $before = $financeHttp->gets; fails(fn()=>sharity_donation_cli_payment_record($args,$assoc),'DONATION_FINANCE_UNAVAILABLE');
        check($financeHttp->gets === $before, 'invalid record CLI never calls provider');
    }
    // Changed actual native-currency facts append; earlier evidence is immutable.
    $b = '/v1/balance_transactions/txn_FINANCE';
    $financeHttp->responses[$b]['fee'] = 2100; $financeHttp->responses[$b]['net'] = 47900;
    $financeHttp->responses[$b]['fee_details'][0]['amount'] = 2100;
    $changed = sharity_donation_finance_record($id,$adapter,$now+61);
    check($changed['id'] !== $record['id'] && financeCount() === 2 && sharity_donation_finance_read($id,$record['digest']) === $saved, 'new fee fact never overwrites prior observation');
    // Lists of resource identities and object keys are canonical, without treating money as float.
    $set = ['z'=>'-999999999999','resources'=>[['id'=>'re_Z','amount_minor'=>'123'],['amount_minor'=>'456','id'=>'re_A']],'a'=>0.125];
    $shuffled = ['a'=>0.125,'resources'=>[['id'=>'re_A','amount_minor'=>'456'],['amount_minor'=>'123','id'=>'re_Z']],'z'=>'-999999999999'];
    check(sharity_donation_finance_canonical($set) === sharity_donation_finance_canonical($shuffled), 'keys and ID sets canonical; provider rate preserved');
    fails(fn()=>sharity_donation_finance_canonical([['id'=>'re_A'],['id'=>'re_A']]),'DONATION_FINANCE_UNAVAILABLE');
    // Actual refund set reordering does not create a new observation.
    $financeHttp->forOrder($row);
    $refunds = [];
    foreach (['re_Z','re_A'] as $rid) { $refunds[] = ['object'=>'refund','id'=>$rid,'charge'=>'ch_FINANCE','payment_intent'=>'pi_FINANCE',
        'amount'=>100,'currency'=>'huf','created'=>1800000020,'status'=>'failed','balance_transaction'=>null,'failure_balance_transaction'=>null]; }
    $financeHttp->responses['/v1/refunds']['data'] = $refunds; $financeHttp->responses['/v1/charges/ch_FINANCE']['refunds']['data'] = $refunds;
    $refundRecord = sharity_donation_finance_record($id,$adapter,$now+62);
    $financeHttp->responses['/v1/refunds']['data'] = array_reverse($refunds);
    $financeHttp->responses['/v1/charges/ch_FINANCE']['refunds']['data'] = array_reverse($refunds);
    check(sharity_donation_finance_record($id,$adapter,$now+63) === $refundRecord, 'real SDK reordered refund set has same digest');
    foreach (['before','lost_ack','throw_after'] as $failure) {
        $o = financeOrder($failure); $n = financeCount();
        $wpdb->failOn = 'INSERT INTO `'.$financeTable.'`'; $wpdb->failure = $failure;
        if ($failure === 'before') { fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE'); check(financeCount() === $n,'failed insert not success'); }
        else { $r=sharity_donation_finance_record($o['order_id'],$adapter,$now); check(financeCount() === $n+1 && $r['order_id'] === $o['order_id'], 'uncertain '.$failure.' recovered by readback'); }
        $wpdb->failOn = ''; $wpdb->failure = '';
    }
    foreach (['missing','corrupt'] as $fault) {
        $o = financeOrder('readback-'.$fault); $n = financeCount();
        $wpdb->db->close(); $wpdb = new FinanceReadbackDb((int)$port); $wpdb->readFault = $fault;
        fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE');
        check(financeCount() === $n+1 && $wpdb->inserts === 1,'unreadable/mismatched persisted row is not success and never reinserted');
        $wpdb->readFault = ''; $r = sharity_donation_finance_record($o['order_id'],$adapter,$now+1);
        check($wpdb->inserts === 1 && $r['observed_at_utc'] === gmdate('Y-m-d H:i:s',$now),'later explicit read recovers original fact');
    }
    // Two actual connections both observe no row before the unique INSERT race.
    $o = financeOrder('race'); $n = financeCount();
    $results = race(fn($slot)=>sharity_donation_finance_record($o['order_id'],$adapter,$now+$slot), 'AND digest =');
    check(isset($results[0]['value'],$results[1]['value']) && $results[0]['value'] === $results[1]['value'] && financeCount() === $n+1, 'concurrent identical observations share one complete winner');
    $o = financeOrder('distinct-race'); $n = financeCount();
    $results = race(function($slot) use($o,$adapter,$now,$financeHttp,$b) {
        if ($slot === 1) { $financeHttp->responses[$b]['status'] = 'pending'; }
        return sharity_donation_finance_record($o['order_id'],$adapter,$now);
    }, 'AND digest =');
    check(isset($results[0]['value'],$results[1]['value']) && $results[0]['value']['id'] !== $results[1]['value']['id'] && financeCount() === $n+2, 'different concurrent facts both retained');
    $o = financeOrder('unsafe'); $before = $financeHttp->gets;
    $wpdb->db->begin_transaction(); $beforeDdl = $wpdb->ddls;
    check(!sharity_donation_finance_schema_apply() && $wpdb->ddls === $beforeDdl && $wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx'] === '1', 'schema never commits caller transaction');
    fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE'); $wpdb->db->rollback();
    $wpdb->db->autocommit(false); fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE'); $wpdb->db->autocommit(true);
    check($financeHttp->gets === $before, 'unsafe DB mode never reaches provider');
    $financeHttp->drop = true; $n = financeCount(); fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE'); $financeHttp->drop = false;
    check(financeCount() === $n,'provider failure never writes partial observation');
    // Canonical stored order result must be validated before the provider GET.
    $orderTable = sharity_donation_order_table(); $before = $financeHttp->gets;
    $wpdb->db->query($wpdb->prepare("UPDATE `{$orderTable}` SET result_json = '{}' WHERE order_id = %s", $o['order_id']));
    fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE');
    check($financeHttp->gets === $before, 'corrupt canonical order status never calls provider');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$orderTable}` SET result_json = %s WHERE order_id = %s", $o['result_json'],$o['order_id']));
    // A corrupt existing identity/content must never be accepted as a successful replay.
    $financeHttp->forOrder($row);
    $wpdb->db->query($wpdb->prepare("UPDATE `{$financeTable}` SET canonical_json = '{}' WHERE id = %s",$record['id']));
    fails(fn()=>sharity_donation_finance_record($id,$adapter,$now),'DONATION_FINANCE_UNAVAILABLE');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$financeTable}` SET canonical_json = %s WHERE id = %s",$saved['canonical_json'],$record['id']));
    $wpdb->failOn = 'AND digest ='; $wpdb->failure = 'before';
    fails(fn()=>sharity_donation_finance_record($id,$adapter,$now),'DONATION_FINANCE_UNAVAILABLE'); $wpdb->failOn = ''; $wpdb->failure = '';
    $o = newOrder('finance-prepared'); $before = $financeHttp->gets;
    fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE');
    $wpdb->db->query($wpdb->prepare("UPDATE `{$orderTable}` SET state = 'attempted' WHERE order_id = %s",$o['order_id']));
    fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_UNAVAILABLE');
    check($financeHttp->gets === $before,'prepared/attempted order cannot be inspected as recorded');
    // Resource size cap is exercised through the real adapter, including PII filtering.
    $o = financeOrder('oversize'); $n = financeCount();
    $financeHttp->responses[$b]['fee_details'] = array_merge($financeHttp->responses[$b]['fee_details'],
        array_fill(0,40000,['amount'=>0,'currency'=>'huf','type'=>str_repeat('a',64)]));
    fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_EVIDENCE_TOO_LARGE');
    check(financeCount() === $n,'over 4 MiB has no truncated/partial insert');
    $financeHttp->responses[$b]['fee_details'] = array_slice($financeHttp->responses[$b]['fee_details'],0,20000);
    $large = sharity_donation_finance_record($o['order_id'],$adapter,$now);
    $bytes = strlen(sharity_donation_finance_read($o['order_id'],$large['digest'])['canonical_json']);
    check($bytes > 65536 && $bytes < SHARITY_DONATION_FINANCE_MAX_BYTES && financeCount() === $n+1,'valid large evidence stored intact beyond the discarded 64 KiB draft cap');
    $n = financeCount();
    $financeHttp->forOrder($o);
    $packet = (int) $wpdb->db->query('SELECT @@global.max_allowed_packet AS n')->fetch_assoc()['n'];
    try {
        $wpdb->db->query('SET GLOBAL max_allowed_packet=1024'); $wpdb->db->close(); $wpdb = new OrderDb((int)$port);
        fails(fn()=>sharity_donation_finance_record($o['order_id'],$adapter,$now),'DONATION_FINANCE_PACKET_TOO_SMALL');
        check(financeCount() === $n,'insufficient real server packet limit rejects before INSERT');
    } finally { $wpdb->db->query('SET GLOBAL max_allowed_packet='.$packet); $wpdb->db->close(); $wpdb = new OrderDb((int)$port); }
    // Every schema read must be checked before a later query can clear last_error.
    foreach (['SHOW TABLE STATUS','SHOW FULL COLUMNS','SHOW INDEX'] as $query) {
        $wpdb->failOn = $query; $wpdb->failure = 'before';
        check(!sharity_donation_finance_schema_apply() && get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION) === '0','schema read error rejects: '.$query);
        $wpdb->failOn = ''; $wpdb->failure = ''; check(sharity_donation_finance_schema_apply(),'schema ready after restored read');
    }
    foreach ([['ALTER TABLE `'.$financeTable.'` ENGINE=MyISAM','ALTER TABLE `'.$financeTable.'` ENGINE=InnoDB'],
        ['ALTER TABLE `'.$financeTable.'` MODIFY donation_mode varchar(9) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'direct\'','ALTER TABLE `'.$financeTable.'` ALTER donation_mode DROP DEFAULT'],
        ['ALTER TABLE `'.$financeTable.'` DROP INDEX order_digest, ADD KEY order_digest(order_id,digest)','ALTER TABLE `'.$financeTable.'` DROP INDEX order_digest, ADD UNIQUE KEY order_digest(order_id,digest)']] as [$bad,$restore]) {
        $n = financeCount(); $wpdb->db->query($bad);
        check(!sharity_donation_finance_schema_apply() && get_option(SHARITY_DONATION_FINANCE_SCHEMA_OPTION) === '0' && financeCount() === $n,'incompatible schema rejected preserving rows');
        $wpdb->db->query($restore); check(sharity_donation_finance_schema_apply(),'explicit fixture repair verified');
    }
    require __DIR__ . '/sharity-donation-finance-reconcile-mysql.test.php';
    require __DIR__ . '/sharity-donation-finance-movements-mysql.test.php';
    require __DIR__ . '/sharity-donation-finance-backing-mysql.test.php';
    check(calls() >= $beforePosts && !isset($data['allocation_minor']), 'no settlement claim');
    echo 'Donation finance MariaDB: '.($checks-$financeStart)." checks; parallel connections, uncertain INSERT, no provider POST during record\n";
} finally {
    $wpdb->failOn = ''; $wpdb->failure = ''; $wpdb->db->query('DROP TABLE IF EXISTS `'.$financeTable.'`');
    $wpdb->db->query('DROP TABLE IF EXISTS `'.sharity_donation_movement_table().'`');
    foreach (['claims','sources','frames'] as $kind) { $wpdb->db->query('DROP TABLE IF EXISTS `'.sharity_donation_backing_table($kind).'`'); }
    unset($options[SHARITY_DONATION_BACKING_SCHEMA_OPTION]);
    unset($options[SHARITY_DONATION_MOVEMENT_SCHEMA_OPTION]);
    unset($options[SHARITY_DONATION_FINANCE_SCHEMA_OPTION]); Stripe\ApiRequestor::setHttpClient($transport);
}
