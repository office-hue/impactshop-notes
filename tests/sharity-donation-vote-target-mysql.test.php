<?php
/** Included by existing real MariaDB harness; synthetic observations, no live money. */
declare(strict_types=1);
(static function (): void {
    global $checks, $wpdb, $snapshot, $options, $now, $financeHttp, $d2iData, $runtimeFactoryCalls;
    $start = $checks; $oldSnapshot = $snapshot; $oldOptions = $options;
    $packages = ['starter'=>[50000,500], 'supporter'=>[100000,1100], 'champion'=>[250000,3000], 'hero'=>[500000,6500], 'legend'=>[1000000,15000]];
    $fixture = static function (string $tag, string $mode='amplifier', string $package='starter', string $currency='HUF') use ($packages, $oldSnapshot): array {
        global $snapshot, $options, $d2iData;
        [$gross,$votes] = $packages[$package];
        $options['sharity_donation_accounts_v1']['test-ngo']['currencies'][$currency] = ['min_minor'=>'100','max_minor'=>'1000000'];
        $snapshot = array_replace($oldSnapshot, ['donation_mode'=>$mode, 'currency'=>$currency, 'amount_minor'=>(string)$gross,
            'votes'=>$mode==='direct'?0:$votes, 'package_id'=>$mode==='direct'?null:$package,
            'price_book_version'=>$mode==='direct'?null:'amplifier-net80-v1:'.$currency,
            'policy_version'=>$mode==='direct'?'direct-net100-v1':'amplifier-net80-v1']);
        try { $order = financeOrder('vote-'.$tag); } finally { $snapshot = $oldSnapshot; }
        $data = $d2iData;
        $data['original']['order_id'] = $order['order_id']; $data['original']['session_id'] = $order['provider_session_id'];
        $data['original']['donation_mode'] = $mode; $data['original']['payment'] = ['amount_minor'=>(string)$gross,'currency'=>$currency];
        $balance = reconcileBalance('txn_VOTECHARGE','ch_FINANCE','charge',(string)$gross);
        unset($balance['scope'],$balance['type'],$balance['source_id']); $balance['currency']=$currency;
        $data['original']['initial_charge_balance']=$balance;
        $data['original']['application_fee']=null;
        if ($mode==='amplifier') {
            $platform=$balance; $platform['id']='txn_VOTEFEE'; $platform['gross_minor']=(string)intdiv($gross,5);$platform['net_minor']=$platform['gross_minor'];
            $data['original']['application_fee']=['id'=>'fee_VOTE','amount_minor'=>$platform['gross_minor'],'currency'=>$currency,
                'refund_state'=>['amount_refunded_minor'=>'0'],'initial_balance'=>$platform];
        }
        return [$order,$data];
    };
    $refund = static fn(string $id, string $amount, string $status='succeeded', string $currency='HUF'):array =>
        ['id'=>$id,'amount_minor'=>$amount,'currency'=>$currency,'created'=>1800000010,'status'=>$status,'balance'=>null,'failure_balance'=>null];
    $observe = static function (array $o, array $data, array $refunds=[], ?string $aggregate=null): array {
        $data['refunds']=$refunds;
        $sum=0;foreach($refunds as $r){if(($r['status']??null)==='succeeded'){$sum+=(int)$r['amount_minor'];}}
        $data['original']['charge_refund_state']['amount_refunded_minor']=$aggregate??(string)$sum;
        return reconcileFixture($o,$data);
    };
    $read = static fn(array $o):array => sharity_donation_vote_target($o['order_id'],$GLOBALS['now']);
    try {
        foreach (['HUF','EUR','USD','GBP'] as $currency) {
            foreach ($packages as $package=>[$gross,$votes]) {
                [$o,$data]=$fixture($currency.$package,'amplifier',$package,$currency);
                check($read($o)['remaining_vote_target']===null,'no observation is unavailable');
                foreach ([0,1,intdiv($gross,5),$gross-1,$gross] as $returned) {
                    $r=$observe($o,$data,$returned===0?[]:[$refund('re_VOTE',(string)$returned,'succeeded',$currency)]);
                    $target=$read($o);
                    $expected=match($returned){0=>$votes,1=>$votes-1,$gross-1,$gross=>0,default=>intdiv($votes*4,5)};
                    check($target['remaining_vote_target']===$expected,'five package/four currency nominal rounding');
                    check($target['confirmed_refund_gross_minor']===(string)$returned&&$target['payment_currency']===$currency,'exact original currency refund');
                    check($target['target_digest']===hash('sha256',sharity_donation_finance_json(array_diff_key($target,['target_digest'=>true]))),'complete deterministic target digest');
                    removeReconcileFixture($r);
                }
            }
        }
        [$o,$base]=$fixture('states');
        // Settlement amount/currency/fee/FX never enters the original gross vote ratio.
        $fx=$base;$b=&$fx['original']['initial_charge_balance'];$b['currency']='EUR';$b['gross_minor']='123';$b['net_minor']='120';$b['total_fee_minor']='3';$b['processing_cost_minor']='3';
        $b['fee_details']=[['amount_minor'=>'3','currency'=>'EUR','type'=>'stripe_fee']];$b['exchange_rate']=0.00246;unset($b);
        $r=$observe($o,$fx,[$refund('re_FX','10000')]);
        check($read($o)['remaining_vote_target']===400&&$read($o)['original_gross_minor']==='50000','cross-currency settlement cannot change nominal refund ratio');removeReconcileFixture($r);
        foreach (['succeeded','failed','canceled'] as $terminal) {
            $pending=$observe($o,$base,[$refund('re_STATE','10000','pending')],'10000');
            check($read($o)['remaining_vote_target']===null&&in_array('refund_pending',$read($o)['reasons'],true),'unresolved refund not final');
            $final=$observe($o,$base,[$refund('re_STATE','10000',$terminal)]);
            $expected=$read($o);check($expected['remaining_vote_target']===($terminal==='succeeded'?400:500),'compatible terminal resolves pending');
            removeReconcileFixture($pending);removeReconcileFixture($final);
            $final=$observe($o,$base,[$refund('re_STATE','10000',$terminal)]);
            $pending=$observe($o,$base,[$refund('re_STATE','10000','pending')],'10000');
            check($read($o)===$expected,'reverse insertion identical target/digest');removeReconcileFixture($pending);removeReconcileFixture($final);
        }
        $one=$observe($o,$base,[$refund('re_ONE','10000')]);
        $changed=$base;$changed['original']['initial_charge_balance']['status']='pending';
        $two=$observe($o,$changed,[$refund('re_ONE','10000')]);
        check($read($o)['remaining_vote_target']===400&&$read($o)['confirmed_refund_ids']===['re_ONE'],'dedupe identical refund in distinct observations');
        $three=$observe($o,$base,[$refund('re_ONE','10000'),$refund('re_TWO','5000')]);
        check($read($o)['remaining_vote_target']===350&&$read($o)['confirmed_refund_ids']===['re_ONE','re_TWO'],'distinct refunds accumulate once');
        removeReconcileFixture($three);removeReconcileFixture($two);
        foreach (['failed','canceled'] as $state) {$bad=$observe($o,$base,[$refund('re_ONE','10000',$state)]);fails(fn()=>$read($o),'DONATION_VOTE_TARGET_CONFLICT');removeReconcileFixture($bad);}
        foreach (['amount_minor'=>'9000','currency'=>'USD','created'=>1800000011] as $key=>$value) {
            $badRefund=$refund('re_ONE','10000');$badRefund[$key]=$value;$bad=$observe($o,$base,[$badRefund]);
            fails(fn()=>$read($o),'DONATION_VOTE_TARGET_CONFLICT');removeReconcileFixture($bad);
        }
        removeReconcileFixture($one);
        $bad=$observe($o,$base,[$refund('re_OVER1','30000'),$refund('re_OVER2','30000')]);fails(fn()=>$read($o),'DONATION_VOTE_TARGET_CONFLICT');removeReconcileFixture($bad);
        foreach (['amount_minor','currency','status','created'] as $key) {
            $r=$refund('re_OLD','1000');unset($r[$key]);$old=$observe($o,$base,[$r],'1000');
            check($read($o)['remaining_vote_target']===null&&in_array('refund_nominal_missing',$read($o)['reasons'],true),'legacy missing nominal not guessed');removeReconcileFixture($old);
        }
        foreach (['0','20000'] as $aggregate) {
            $bad=$observe($o,$base,[$refund('re_MISMATCH','10000')],$aggregate);
            check($read($o)['remaining_vote_target']===null&&in_array('refund_aggregate_mismatch',$read($o)['reasons'],true),'non-atomic aggregate mismatch held');removeReconcileFixture($bad);
        }
        $missing=$base;unset($missing['original']['charge_refund_state']['amount_refunded_minor']);$r=reconcileFixture($o,$missing);
        check($read($o)['remaining_vote_target']===null,'missing refund aggregate held');removeReconcileFixture($r);
        $incomplete=$base;$incomplete['resources_complete']=false;$r=reconcileFixture($o,$incomplete);
        fails(fn()=>$read($o),'DONATION_VOTE_TARGET_UNAVAILABLE');removeReconcileFixture($r);
        // Disputes hold spending separately; they never become a nominal refund or final vote zero.
        $dispute=static function(string $state):array {
            return ['id'=>'dp_VOTE','amount_minor'=>'50000','currency'=>'HUF','created'=>1800000011,'status'=>$state,
                'balances'=>[reconcileBalance('txn_VOTEDISPUTE','dp_VOTE','adjustment','-50000'),reconcileBalance('txn_VOTEREVERSE','dp_VOTE','adjustment','50000')]];
        };
        foreach (['under_review'=>true,'lost'=>true,'won'=>false,'warning_closed'=>false,'prevented'=>false] as $state=>$hold) {
            $data=$base;$data['disputes']=[$dispute($state)];$r=$observe($o,$data,[$refund('re_SAME','10000')]);
            $t=$read($o);check($t['dispute_hold']===$hold&&$t['remaining_vote_target']===400&&$t['confirmed_refund_gross_minor']==='10000','dispute does not double deduct refund');removeReconcileFixture($r);
        }
        $open=$base;$open['disputes']=[$dispute('under_review')];$closed=$base;$closed['disputes']=[$dispute('won')];
        $a=$observe($o,$open);$b=$observe($o,$closed);$expected=$read($o);
        check($expected['dispute_hold']&&in_array('dispute_history_ambiguous',$expected['reasons'],true),'closed/open without resource sequence cannot prove not reopened');removeReconcileFixture($a);removeReconcileFixture($b);
        $b=$observe($o,$closed);$a=$observe($o,$open);check($read($o)===$expected,'dispute arrival order has no authority');removeReconcileFixture($a);removeReconcileFixture($b);
        foreach (['missing_balance','missing_status','missing_history','immutable'] as $case) {
            $data=$closed;
            if($case==='missing_balance'){$data['disputes'][0]['balances']=[];}
            if($case==='missing_status'){unset($data['disputes'][0]['status']);}
            if($case==='missing_history'){$data['disputes']=[];$data['original']['disputed']=true;}
            if($case==='immutable'){$a=$observe($o,$closed);$data['disputes'][0]['amount_minor']='40000';}
            $r=$observe($o,$data);
            if($case==='immutable'){fails(fn()=>$read($o),'DONATION_VOTE_TARGET_CONFLICT');removeReconcileFixture($a);}
            else{check($read($o)['dispute_hold'],'missing dispute evidence holds');}
            removeReconcileFixture($r);
        }
        [$direct,$directData]=$fixture('direct','direct');$r=$observe($direct,$directData,[$refund('re_DIRECT','50000')]);check($read($direct)['remaining_vote_target']===0&&$read($direct)['original_votes']===0,'direct has no votes');removeReconcileFixture($r);
        // Existing row identity, content and strict CLI are rechecked, with no external or SQL writes.
        $r=$observe($o,$base);$expected=$read($o);$gets=$financeHttp->gets;$posts=calls();$count=financeCount();$ddls=$wpdb->ddls;$opts=$options;$factory=$runtimeFactoryCalls;
        $financeHttp->drop=true;$wpdb->db->query('START TRANSACTION READ ONLY');
        try {check($read($o)===$expected,'read-only transaction succeeds');sharity_donation_cli_vote_target([],['order'=>$o['order_id']]);
            check(json_decode(end(WP_CLI::$lines),true)===json_decode(sharity_donation_finance_json($expected),true),'real CLI result');}
        finally{$wpdb->db->rollback();$financeHttp->drop=false;}
        check($gets===$financeHttp->gets&&$posts===calls()&&$count===financeCount()&&$ddls===$wpdb->ddls&&$options===$opts&&$factory===$runtimeFactoryCalls,'no provider/DB/option/DDL writes');
        foreach (['grant_supported','activation_allowed','execute_supported','allocation_eligible','payment_verified','settlement_reconciled','financial_acceptance','atomic_snapshot'] as $key){check($expected[$key]===false,'no readiness '.$key);}
        check(!str_contains(json_encode($expected),'subject_ref')&&!str_contains(json_encode($expected),'PRIVATE_'),'no owner/provider PII');
        foreach ([[[],[]],[['x'],['order'=>$o['order_id']]],[[],['order'=>'bad']],[[],['order'=>$o['order_id'],'apply'=>true]],[[],['order'=>$o['order_id'],'votes'=>99999]]] as [$args,$assoc]){fails(fn()=>sharity_donation_cli_vote_target($args,$assoc),'DONATION_VOTE_TARGET_UNAVAILABLE');}
        check((WP_CLI::$commands['sharity donations vote-target']??null)==='sharity_donation_cli_vote_target','actual registered CLI');
        $wpdb->failOn='AND digest =';$wpdb->failure='before';fails(fn()=>$read($o),'DONATION_VOTE_TARGET_UNAVAILABLE');$wpdb->failOn='';$wpdb->failure='';
        foreach (['account_id'=>'acct_FOREIGN','digest'=>str_repeat('0',64),'canonical_json'=>'{}'] as $key=>$bad) {
            $wpdb->query($wpdb->prepare('UPDATE `'.sharity_donation_finance_table().'` SET `'.$key.'`=%s WHERE id=%s',$bad,$r['id']));
            fails(fn()=>$read($o),'DONATION_VOTE_TARGET_UNAVAILABLE');
            $wpdb->query($wpdb->prepare('UPDATE `'.sharity_donation_finance_table().'` SET `'.$key.'`=%s WHERE id=%s',$r[$key],$r['id']));
        }
        removeReconcileFixture($r);
        echo 'Donation vote target MariaDB: '.($checks-$start)." checks; observation-only, no grant/provider writes\n";
    } finally {$snapshot=$oldSnapshot;$options=$oldOptions;$wpdb->afterRead=null;$wpdb->failOn='';$wpdb->failure='';$financeHttp->drop=false;}
})();
