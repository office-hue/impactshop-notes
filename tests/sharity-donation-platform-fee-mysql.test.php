<?php
/** Included within the existing drain harness: real MariaDB, offline pinned SDK. */
declare(strict_types=1);
$platformStart=$checks;$platformPrior=$options;$platformSnapshot=$snapshot;
define('SHARITY_DONATIONS_STRIPE_PLATFORM_WEBHOOK_SECRET','whsec_PLATFORMLOCALTESTONLY');
define('SHARITY_DONATIONS_STRIPE_PLATFORM_WEBHOOK_LIVEMODE',false);
define('SHARITY_DONATIONS_STRIPE_PLATFORM_ACCOUNT_ID','acct_PLATFORM');
class PlatformFeeLookupHttp implements Stripe\HttpClient\ClientInterface {
    public array $responses=[];public int $gets=0;public bool $drop=false;public string $account='';
    public function request($m,$u,$h,$p,$f,$a='v1',$r=null){
        global $wpdb;$this->gets++;$path=parse_url($u,PHP_URL_PATH);
        check($m==='get'&&$p===[]&&!$f&&$r===0,'platform lookup GET only, no retry');
        check($wpdb->db->query('SELECT @@in_transaction AS tx')->fetch_assoc()['tx']==='0','platform lookup outside transaction');
        $connected=str_starts_with($path,'/v1/charges/');
        $headers=array_values(array_filter($h,static fn($v)=>str_starts_with($v,'Stripe-Account:')));
        check($headers===($connected?['Stripe-Account: '.$this->account]:[]),'platform versus connected SDK scope');
        if($this->drop||!isset($this->responses[$path]))throw new RuntimeException('PRIVATE_PROVIDER_FAILURE');
        return [json_encode($this->responses[$path]),200,[]];
    }
}
function platformEvent(string $type,array $resource):array {
    return ['id'=>'evt_'.bin2hex(random_bytes(10)),'object'=>'event','type'=>$type,'created'=>time(),
        'api_version'=>Sharity_Stripe_Checkout_Adapter::API_VERSION,'livemode'=>false,'data'=>['object'=>$resource]];
}
function platformDeliver(array $event,?object $http=null,?string $sig=null,?int $stamp=null):WP_REST_Response {
    $raw=json_encode($event);$q=new WP_REST_Request([],'');$q->raw=$raw;
    $q->signature=$sig??Stripe\WebhookSignature::generateSignatureHeader($raw,SHARITY_DONATIONS_STRIPE_PLATFORM_WEBHOOK_SECRET,$stamp);
    return sharity_donation_platform_webhook_handle($q,$http);
}
function platformClear():void {global $wpdb,$inbox;$wpdb->db->query("DELETE FROM `$inbox`");}
try {
    drainReset();$snapshot=array_replace($snapshot,['donation_mode'=>'amplifier','votes'=>500,'package_id'=>'starter',
        'price_book_version'=>'amplifier-net80-v1:HUF','policy_version'=>'amplifier-net80-v1']);
    [$o,$checkout]=drainOrder('platform-fee');$snapshot=$platformSnapshot;
    $e=sharity_donation_order_decode($o);$suffix=substr($o['order_id'],3);$chargeId='ch_'.$suffix;$feeId='fee_'.$suffix;
    $cp='/v1/charges/'.$chargeId;$sp='/v1/checkout/sessions/'.$o['provider_session_id'];$bp='/v1/balance_transactions/txn_'.$suffix;
    $fp='/v1/application_fees/'.$feeId;$fbp='/v1/balance_transactions/txn_FEE'.$suffix;
    $drainHttp->responses[$sp]['payment_intent']['application_fee_amount']=10000;
    $drainHttp->responses[$cp]['application_fee']=$feeId;$drainHttp->responses[$cp]['application_fee_amount']=10000;
    $drainHttp->responses[$bp]['fee']=12000;$drainHttp->responses[$bp]['net']=38000;
    $drainHttp->responses[$bp]['fee_details'][]=['amount'=>10000,'currency'=>'huf','type'=>'application_fee'];
    $fee=['object'=>'application_fee','id'=>$feeId,'account'=>$e['snapshot']['account_id'],'charge'=>$chargeId,
        'livemode'=>false,'amount'=>10000,'currency'=>'huf','amount_refunded'=>0,'refunded'=>false,'created'=>time()-10,
        'originating_transaction'=>null,'balance_transaction'=>'txn_FEE'.$suffix,'refunds'=>['object'=>'list','data'=>[],'has_more'=>false]];
    $drainHttp->responses[$fp]=$fee;$drainHttp->responses['/v1/account']=['object'=>'account','id'=>'acct_PLATFORM'];
    $drainHttp->responses[$fp.'/refunds']=['object'=>'list','data'=>[],'has_more'=>false];
    $drainHttp->responses[$fbp]=['object'=>'balance_transaction','id'=>'txn_FEE'.$suffix,'source'=>$feeId,'type'=>'application_fee',
        'amount'=>10000,'currency'=>'huf','fee'=>0,'net'=>10000,'fee_details'=>[],'status'=>'available','created'=>time()-10,
        'available_on'=>time()-5,'exchange_rate'=>null];
    $lookup=new PlatformFeeLookupHttp();$lookup->account=$fee['account'];$lookup->responses=$drainHttp->responses;
    platformClear();$event=platformEvent('application_fee.created',$fee);$posts=calls();$gets=$lookup->gets;
    foreach(['livemode'=>true,'api_version'=>'2020-08-27','created'=>time()+400,'account'=>'acct_FOREIGN']as$key=>$value){
        $bad=$event;$bad[$key]=$value;check(platformDeliver($bad,$lookup)->status===400,'platform context '.$key);
    }
    $badSig=Stripe\WebhookSignature::generateSignatureHeader(json_encode($event),SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET);
    check(platformDeliver($event,$lookup,$badSig)->status===400,'connected secret cannot authorize platform');
    check(platformDeliver($event,$lookup,null,time()-600)->status===400,'old platform signature rejected');
    check(deliver($event)->status===400,'platform event cannot be silently ACKed by connected route');
    check($lookup->gets===$gets&&inboxCount()===0,'bad signature/scope rejected before SDK or DB write');
    check(platformDeliver($event,$lookup)->status===200&&$lookup->gets-$gets===3,'created uses three scoped GETs');
    $saved=sharity_donation_webhook_read($event['id']);$notice=json_decode($saved['identity_json'],true);
    check($notice['source_scope']==='platform'&&$notice['platform_account_id']==='acct_PLATFORM'&&!str_contains($saved['identity_json'],'PRIVATE'),'minimal platform identity');
    check(platformDeliver($event,$lookup)->status===200&&inboxCount()===1,'exact duplicate receipt');
    $conflict=$event;$conflict['data']['object']['amount_refunded']=1;
    check(platformDeliver($conflict,$lookup)->status===409,'event id conflicting signed floor');
    $wpdb->failOn='INSERT INTO `'.$inbox.'`';$wpdb->failure='lost_ack';$lost=$event;$lost['id']='evt_'.bin2hex(random_bytes(10));
    check(platformDeliver($lost,$lookup)->status===200,'lost INSERT ACK read back');$wpdb->failOn='';$wpdb->failure='';
    $r=lifecycleDrain();check($r['observed']===2&&$r['financial_readiness']===false,'fee-created notices traverse actual drain');
    check(sharity_donation_webhook_read($event['id'])['observation_digest']!==null&&targetCount($o['order_id'])===1,'actual observation/target stored once');
    // Signed refund progress precedes a newer provider read: do not close stale evidence.
    platformClear();$reported=$fee;$reported['amount_refunded']=1000;
    $refunded=platformEvent('application_fee.refunded',$reported);
    check(platformDeliver($refunded,$lookup)->status===200,'refunded carries application_fee, not fee_refund');
    $count=targetCount($o['order_id']);$r=lifecycleDrain();
    check($r['pending']===1&&$r['observed']===0&&targetCount($o['order_id'])===$count,'stale aggregate cannot project or complete notice');
    $fr=['object'=>'fee_refund','id'=>'fr_'.$suffix,'fee'=>$feeId,'amount'=>1000,'currency'=>'huf','created'=>time()-5,'balance_transaction'=>'txn_FR'.$suffix];
    $drainHttp->responses[$fp]['amount_refunded']=1000;$drainHttp->responses[$fp]['refunds']['data']=[$fr];
    $drainHttp->responses[$fp.'/refunds']['data']=[$fr];
    $debit=array_replace($drainHttp->responses[$fbp],['id'=>'txn_FR'.$suffix,'source'=>$fr['id'],'type'=>'application_fee_refund','amount'=>-1000,'net'=>-1000]);
    $credit=array_replace($debit,['id'=>'txn_CR'.$suffix,'type'=>'adjustment','amount'=>1000,'net'=>1000]);
    $drainHttp->responses['/v1/balance_transactions/'.$debit['id']]=$debit;
    $drainHttp->responses['/v1/balance_transactions/'.$credit['id']]=$credit;
    $drainHttp->responses['/v1/balance_transactions']=['object'=>'list','data'=>[$credit],'has_more'=>false];
    $lookup->responses=$drainHttp->responses;
    $r=lifecycleDrain();check($r['observed']===1&&sharity_donation_webhook_read($refunded['id'])['observation_digest']!==null,'later actual refund evidence resolves pending fee notice');
    platformClear();$updated=platformEvent('application_fee.refund.updated',$fr);
    check(platformDeliver($updated,$lookup)->status===200,'fee_refund needs no livemode/status of its own');
    $bad=$updated;$bad['data']['object']=$reported;
    check(platformDeliver($bad,$lookup)->status===400,'refund.updated rejects application_fee object');
    $options[SHARITY_DONATION_ORDERS_ENABLED]='0';$options['sharity_donation_accounts_v1']=[];
    $r=lifecycleDrain();
    check($r['pending']===1&&$r['observed']===0,'unexpanded refund balance remains pending');
    // The list GET requests expansion; an ID alone never proves the debit.
    $drainHttp->responses[$fp.'/refunds']['data'][0]['balance_transaction']=$debit;
    $r=lifecycleDrain();
    check($r['observed']===1&&$r['financial_readiness']===false,'old fee refund processed with new checkout disabled');
    $row=sharity_donation_webhook_read($updated['id']);
    $evidence=json_decode(sharity_donation_finance_read($o['order_id'],$row['observation_digest'])['canonical_json'],true);
    check(sharity_donation_lifecycle_matches($row,$evidence),'native persisted evidence matches exact fee refund');
    foreach(['missing','amount','currency','balance','fee','platform','charge']as$case){
        $bad=$evidence;
        if($case==='missing')$bad['fee_refunds']=[];
        if($case==='amount')$bad['fee_refunds'][0]['amount_minor']='999';
        if($case==='currency')$bad['fee_refunds'][0]['currency']='EUR';
        if($case==='balance')$bad['fee_refunds'][0]['balance']['id']='txn_OTHER';
        if($case==='fee')$bad['original']['application_fee']['id']='fee_OTHER';
        if($case==='platform')$bad['original']['platform_account_id']='acct_OTHER';
        if($case==='charge')$bad['original']['charge_id']='ch_OTHER';
        check(!sharity_donation_lifecycle_matches($row,$bad),'wrong/stale evidence remains pending '.$case);
    }
    // Native fee and refund currency can differ from donor payment currency.
    $fx=$evidence;$fx['original']['application_fee']['currency']='EUR';$fx['fee_refunds'][0]['currency']='EUR';
    $i=json_decode($row['identity_json'],true);$i['fee_currency']='EUR';$fxrow=$row;$fxrow['identity_json']=json_encode($i);
    check(sharity_donation_lifecycle_matches($fxrow,$fx),'native fee currency compared only to fee, never relabelled HUF payment');
    $lookupOriginal=$lookup->responses;
    foreach(['platform','account','fee','charge','mode','metadata']as$case){
        platformClear();$lookup->responses=$lookupOriginal;$badEvent=platformEvent('application_fee.created',$fee);
        if($case==='platform')$lookup->responses['/v1/account']['id']='acct_WRONG';
        if($case==='account')$lookup->responses[$fp]['account']='acct_WRONG';
        if($case==='fee')$lookup->responses[$cp]['application_fee']='fee_WRONG';
        if($case==='charge')$lookup->responses[$cp]['amount']=49999;
        if($case==='mode')$lookup->responses[$cp]['metadata']['donation_mode']='direct';
        if($case==='metadata')$lookup->responses[$cp]['metadata']['quote_id']='dq_'.str_repeat('f',32);
        $r=platformDeliver($badEvent,$lookup);check(in_array($r->status,[400,503],true)&&inboxCount()===0,'wrong original relation rejected '.$case);
    }
    $lookup->responses=$lookupOriginal;$lookup->responses[$cp]['metadata']=[];
    check(platformDeliver(platformEvent('application_fee.created',$fee),$lookup)->body['ignored']===true&&inboxCount()===0,'genuine non-Sharity fee ignored');
    $lookup->responses=$lookupOriginal;$lookup->drop=true;
    check(platformDeliver(platformEvent('application_fee.created',$fee),$lookup)->status===503&&inboxCount()===0,'lookup failure unacknowledged');$lookup->drop=false;
    check(calls()===$posts,'platform receipt/drain/refund adds no provider POST');
    echo 'Platform fee MariaDB: '.($checks-$platformStart)." checks; separate signing, original relation, real drain/refund, zero POST\n";
} finally {$snapshot=$platformSnapshot;$options=$platformPrior;$wpdb->failOn='';$wpdb->failure='';Stripe\ApiRequestor::setHttpClient($transport);}
