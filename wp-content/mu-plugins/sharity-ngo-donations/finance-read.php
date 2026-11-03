<?php
/** Trusted local WP-CLI evidence inspection and explicit observation storage. No settlement or activation. */
declare(strict_types=1);

function sharity_donation_cli_payment_inspect(array $args, array $assoc): void
{
    try {
        $keys = array_keys($assoc); sort($keys);
        $adjustments = array_key_exists('adjustments', $assoc);
        if (!defined('WP_CLI') || !WP_CLI || $args !== []
            || $keys !== ($adjustments ? ['adjustments', 'order'] : ['order'])
            || ($adjustments && $assoc['adjustments'] !== true)
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])) { throw new RuntimeException(); }
        foreach (['sharity_donation_order_autocommit', 'sharity_donation_webhook_order', 'sharity_donation_order_status', 'sharity_donation_runtime_adapter'] as $function) {
            if (!function_exists($function)) { throw new RuntimeException(); }
        }
        $row = sharity_donation_webhook_order($assoc['order']);
        $status = sharity_donation_order_status($row['subject_ref'], $assoc['order'], time());
        if ($status['state'] !== 'session_recorded' || $row['state'] !== 'session_recorded') { throw new RuntimeException(); }
        $envelope = sharity_donation_order_decode($row);
        sharity_donation_order_autocommit();
        $adapter = sharity_donation_runtime_adapter();
        $result = $adjustments ? $adapter->retrieveAdjustmentEvidence($row['provider_session_id'], $envelope)
            : $adapter->retrievePaymentEvidence($row['provider_session_id'], $envelope);
        WP_CLI::line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) { WP_CLI::error('DONATION_EVIDENCE_UNAVAILABLE'); }
}

function sharity_donation_cli_finance_schema(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $assoc !== ['apply' => true]
            || !function_exists('sharity_donation_finance_schema_apply') || !sharity_donation_finance_schema_apply()
            || !function_exists('sharity_donation_movement_schema_apply') || !sharity_donation_movement_schema_apply()
            || !function_exists('sharity_donation_backing_schema_apply') || !sharity_donation_backing_schema_apply()
            || !function_exists('sharity_donation_target_schema_apply') || !sharity_donation_target_schema_apply()) { throw new RuntimeException(); }
        WP_CLI::line('DONATION_FINANCE_SCHEMA_READY');
    } catch (Throwable $error) { WP_CLI::error('DONATION_FINANCE_SCHEMA_UNAVAILABLE'); }
}

function sharity_donation_cli_payment_record(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || array_keys($assoc) !== ['order']
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])
            || !function_exists('sharity_donation_finance_record') || !function_exists('sharity_donation_runtime_adapter')) { throw new RuntimeException(); }
        $receipt = sharity_donation_finance_record($assoc['order'], sharity_donation_runtime_adapter(), time());
        WP_CLI::line(json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        $code = in_array($error->getMessage(), ['DONATION_FINANCE_EVIDENCE_TOO_LARGE', 'DONATION_FINANCE_PACKET_TOO_SMALL'], true)
            ? $error->getMessage() : 'DONATION_FINANCE_UNAVAILABLE';
        WP_CLI::error($code);
    }
}

function sharity_donation_cli_payment_reconcile(array $args, array $assoc): void
{
    try {
        $record = array_key_exists('record-movements', $assoc); $keys = array_keys($assoc); sort($keys);
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $keys !== ($record ? ['order', 'record-movements'] : ['order'])
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])
            || !function_exists('sharity_donation_finance_reconcile')
            || ($record && (!is_string($assoc['record-movements']) || !preg_match('/^[a-f0-9]{64}$/D', $assoc['record-movements'])
                || !function_exists('sharity_donation_finance_record_movements')))) { throw new RuntimeException(); }
        $result = $record ? sharity_donation_finance_record_movements($assoc['order'], $assoc['record-movements'], time())
            : sharity_donation_finance_reconcile($assoc['order'], time());
        WP_CLI::line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        WP_CLI::error(in_array($error->getMessage(), ['DONATION_RECONCILE_LIMIT', 'DONATION_RECONCILE_CONFLICT',
            'DONATION_MOVEMENT_STALE', 'DONATION_MOVEMENT_EMPTY', 'DONATION_MOVEMENT_CONFLICT',
            'DONATION_MOVEMENT_UNAVAILABLE', 'DONATION_FINANCE_PACKET_TOO_SMALL'], true) ? $error->getMessage() : 'DONATION_RECONCILE_UNAVAILABLE');
    }
}

/** A proof file never implies approval: writes require its exact dry-run digest. */
function sharity_donation_cli_backing(array $args, array $assoc, bool $reserve): void
{
    try {
        $apply=array_key_exists('apply',$assoc);$keys=array_keys($assoc);sort($keys);
        $expected=$reserve?['input','order']:['input'];if($apply){$expected[]='apply';}sort($expected);
        if (!defined('WP_CLI') || !WP_CLI || $args!==[] || $keys!==$expected || !is_string($assoc['input']??null)
            || ($apply && (!is_string($assoc['apply']) || !preg_match('/^[a-f0-9]{64}$/D',$assoc['apply'])))
            || ($reserve && (!is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D',$assoc['order'])))
            || !function_exists('sharity_donation_backing_read_proof') || !function_exists('sharity_donation_backing_import')
            || !function_exists('sharity_donation_backing_reserve')) { throw new RuntimeException(); }
        $proof=sharity_donation_backing_read_proof($assoc['input']);$digest=$apply?$assoc['apply']:'';
        $result=$reserve?sharity_donation_backing_reserve($assoc['order'],$proof,$digest,time()):sharity_donation_backing_import($proof,$digest,time());
        WP_CLI::line(json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        $allowed=['STALE','SOURCE_UNPROVEN','SOURCE_CONFLICT','CLAIM_CONFLICT','INSUFFICIENT','EXPIRED','PROOF_MALFORMED','PROOF_PERMISSION','LIMIT'];
        WP_CLI::error(in_array(str_replace('DONATION_BACKING_','',$error->getMessage()),$allowed,true)?$error->getMessage():'DONATION_BACKING_UNAVAILABLE');
    }
}
function sharity_donation_cli_backing_import(array $args,array $assoc): void { sharity_donation_cli_backing($args,$assoc,false); }
function sharity_donation_cli_backing_reserve(array $args,array $assoc): void { sharity_donation_cli_backing($args,$assoc,true); }

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations backing-import','sharity_donation_cli_backing_import');
    WP_CLI::add_command('sharity donations backing-reserve','sharity_donation_cli_backing_reserve');
    WP_CLI::add_command('sharity donations payment-reconcile', 'sharity_donation_cli_payment_reconcile');
    WP_CLI::add_command('sharity donations finance-target','sharity_donation_cli_finance_target');
    WP_CLI::add_command('sharity donations finance-schema', 'sharity_donation_cli_finance_schema');
    WP_CLI::add_command('sharity donations payment-record', 'sharity_donation_cli_payment_record');
    WP_CLI::add_command('sharity donations payment-inspect', 'sharity_donation_cli_payment_inspect');
}

function sharity_donation_cli_finance_target(array $args, array $assoc): void
{
    try {
        $apply = array_key_exists('apply', $assoc); $keys = array_keys($assoc); sort($keys);
        $expected = $apply ? ['apply', 'order'] : ['order'];
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $keys !== $expected
            || !is_string($assoc['order']) || !preg_match('/^do_[a-f0-9]{32}$/D', $assoc['order'])
            || ($apply && (!is_string($assoc['apply']) || !preg_match('/^[a-f0-9]{64}$/D', $assoc['apply'])))
            || !function_exists('sharity_donation_finance_target')) { throw new RuntimeException(); }
        $result = sharity_donation_finance_target($assoc['order'], time(), $apply ? $assoc['apply'] : '');
        WP_CLI::line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        $allowed = ['DONATION_TARGET_STALE', 'DONATION_TARGET_CONFLICT', 'DONATION_TARGET_MISSING_CHARGE',
            'DONATION_TARGET_LIMIT', 'DONATION_TARGET_REGISTRY_MISMATCH', 'DONATION_FINANCE_PACKET_TOO_SMALL', 'DONATION_TARGET_UNAVAILABLE', 'DONATION_RECONCILE_LIMIT', 'DONATION_RECONCILE_CONFLICT'];
        WP_CLI::error(in_array($error->getMessage(), $allowed, true) ? $error->getMessage() : 'DONATION_TARGET_UNAVAILABLE');
    }
}

/** CLI account binding reuses the existing option map and private proof reader.
 * Evidence references attest operator review, never independently prove cash or legal identity. */
function sharity_donation_account_proof(array $d, string $ngo, int $now): void
{
    sharity_donation_backing_keys($d, ['version','ngo_slug','account_id','platform_account_id','livemode','verified_at_utc',
        'verified_until_utc','settlement_currency','payout_currency','fee_payer','losses_payments',
        'platform_costs_minor','platform_liability_minor','currencies','mode_currencies','evidence','confirmed']);
    if ($d['version'] !== 1 || $d['ngo_slug'] !== $ngo || !is_bool($d['livemode']) || $d['confirmed'] !== true
        || $d['fee_payer'] !== 'account' || $d['losses_payments'] !== 'stripe'
        || $d['platform_costs_minor'] !== '0' || $d['platform_liability_minor'] !== '0') { throw new RuntimeException('DONATION_ACCOUNT_MODEL_UNPROVEN'); }
    foreach (['account_id','platform_account_id'] as $field) { sharity_donation_backing_string($d[$field], '/^acct_[A-Za-z0-9]{1,123}$/D'); }
    if ($d['account_id'] === $d['platform_account_id']) { throw new RuntimeException('DONATION_ACCOUNT_MODEL_UNPROVEN'); }
    $at = sharity_donation_backing_time($d['verified_at_utc']); $until = sharity_donation_backing_time($d['verified_until_utc']);
    if ($at > $now || $until <= $now || $at > $until) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_EXPIRED'); }
    foreach (['settlement_currency','payout_currency'] as $field) {
        if (!in_array($d[$field], ['HUF','EUR','USD','GBP'], true)) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_INVALID'); }
    }
    sharity_donation_backing_keys($d['evidence'], ['representation','fee_terms','currency_support','platform_costs','retention','readiness']);
    foreach ($d['evidence'] as $reference) { sharity_donation_backing_reference($reference); }
    if (!is_array($d['currencies']) || !$d['currencies'] || count($d['currencies']) > 4) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_INVALID'); }
    foreach ($d['currencies'] as $c => $bounds) {
        sharity_donation_backing_keys($bounds, ['min_minor','max_minor']);
        if (!in_array($c, ['HUF','EUR','USD','GBP'], true)
            || !sharity_donation_quote_minor($bounds['min_minor'], $c) || !sharity_donation_quote_minor($bounds['max_minor'], $c)
            || (int)$bounds['min_minor'] > (int)$bounds['max_minor'] || (int)$bounds['max_minor'] > 99999999) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_INVALID'); }
    }
    sharity_donation_backing_keys($d['mode_currencies'], ['direct','amplifier']); $enabled = false;
    foreach ($d['mode_currencies'] as $mode => $cells) {
        if (!is_array($cells) || count($cells) > 4) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_INVALID'); }
        foreach ($cells as $c => $v) {
            if (!in_array($c, ['HUF','EUR','USD','GBP'], true) || !is_bool($v) || ($v && !isset($d['currencies'][$c]))) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_INVALID'); }
            // Paid votes/fund execution are not ready. This release must not activate them.
            if ($mode === 'amplifier' && $v) { throw new RuntimeException('DONATION_ACCOUNT_AMPLIFIER_UNAVAILABLE'); }
            if ($mode === 'direct' && $v) { $enabled = true; }
        }
    }
    if (!$enabled) { throw new RuntimeException('DONATION_ACCOUNT_PROOF_INVALID'); }
}

function sharity_donation_account_binding_payload(array $d, array $p, int $now): array
{
    if (($p['schema_version'] ?? null) !== 'sharity-donation-account-inspect-v1'
        || ($p['account_id'] ?? null) !== $d['account_id'] || ($p['platform_account_id'] ?? null) !== $d['platform_account_id']
        || ($p['livemode'] ?? null) !== $d['livemode'] || ($p['default_currency'] ?? null) !== $d['settlement_currency']
        || ($p['controller']['fees_payer'] ?? null) !== 'account' || ($p['controller']['losses_payments'] ?? null) !== 'stripe'
        || ($p['capabilities']['card_payments'] ?? null) !== 'active'
        || ($p['flags'] ?? null) !== ['charges_enabled'=>true,'payouts_enabled'=>true,'details_submitted'=>true]
        || ($p['requirements'] ?? null) !== ['currently_due'=>0,'past_due'=>0,'pending_verification'=>0,'errors'=>0,'disabled'=>false]
        || ($at = sharity_donation_quote_timestamp($p['observed_at_utc'] ?? null)) === null || $at > $now || $now - $at > 30) {
        throw new RuntimeException('DONATION_ACCOUNT_PROVIDER_UNPROVEN');
    }
    return ['account_id'=>$d['account_id'], 'livemode'=>$d['livemode'], 'verified_until'=>$d['verified_until_utc'],
        'modes'=>['direct'=>true,'amplifier'=>false], 'currencies'=>$d['currencies'], 'mode_currencies'=>$d['mode_currencies'],
        'account_evidence'=>$d, 'provider_observation'=>$p];
}

function sharity_donation_account_option_read(callable $query, string $table): array
{
    $rows = $query("SELECT option_value FROM `{$table}` WHERE option_name = ?", ['sharity_donation_accounts_v1']);
    if (count($rows) > 1) { throw new RuntimeException(); }
    if (!$rows) { return [null, []]; }
    $raw = $rows[0]['option_value'];
    if (!is_string($raw) || strlen($raw) > 1048576) { throw new RuntimeException(); }
    $map = @unserialize($raw, ['allowed_classes'=>false]);
    if (!is_array($map)) { throw new RuntimeException(); }
    // Fail on objects/resources/deep or recursive corrupt option data; preserve valid sibling records.
    json_encode($map, JSON_THROW_ON_ERROR, 32);
    $walk = static function ($v) use (&$walk): void {
        if (is_object($v) || is_resource($v)) { throw new RuntimeException(); }
        if (is_array($v)) { foreach ($v as $x) { $walk($x); } }
    }; $walk($map);
    return [$raw, $map];
}

function sharity_donation_account_seal(array $binding, string $request): array
{
    unset($binding['binding_version']); $binding['account_request_digest'] = $request;
    $binding['binding_version'] = 'account-' . hash('sha256', sharity_donation_finance_json($binding));
    return $binding;
}

/** One atomic SQL CAS; no remote calls, reconnect, retry or transaction held here. */
function sharity_donation_account_change(string $ngo, string $expect, array $request, ?array $payload = null): array
{
    global $wpdb;
    try {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $ngo) || strlen($ngo) > 100
            || !preg_match('/^(absent|[A-Za-z0-9._:-]{1,128})$/D', $expect)
            || !in_array($request['kind'] ?? null, ['bind','disable'], true)) { throw new RuntimeException(); }
        [$db,$query] = sharity_donation_backing_db(); $table = $wpdb->options;
        if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D', $table)) { throw new RuntimeException(); }
        $indexes = $query("SHOW INDEX FROM `{$table}`"); $groups = [];
        foreach ($indexes as $index) { $groups[$index['Key_name']][] = $index; }
        $unique = false;
        foreach ($groups as $group) {
            if (count($group) === 1 && $group[0]['Column_name'] === 'option_name' && (int)$group[0]['Non_unique'] === 0 && $group[0]['Sub_part'] === null) { $unique = true; }
        }
        if (!$unique) { throw new RuntimeException(); }
        [$raw,$map] = sharity_donation_account_option_read($query,$table);
        $old = $map[$ngo] ?? null;
        $digest = hash('sha256',sharity_donation_finance_json([$ngo,$expect,$request]));
        if (is_array($old) && ($old['account_request_digest'] ?? null) === $digest) {
            if (($old['binding_version'] ?? null) !== sharity_donation_account_seal($old,$digest)['binding_version']) { throw new RuntimeException(); }
            return $old;
        }
        if ($expect === 'absent' ? $old !== null : (!is_array($old) || ($old['binding_version'] ?? null) !== $expect)) { throw new RuntimeException('DONATION_ACCOUNT_STALE'); }
        if ($request['kind'] === 'bind') {
            if ($payload === null) { throw new RuntimeException(); } $next = $payload;
        } else {
            if (!is_array($old)) { throw new RuntimeException('DONATION_ACCOUNT_STALE'); }
            $mode = $request['mode'] ?? null; $currency = $request['currency'] ?? null;
            if (!in_array($mode,['direct','amplifier'],true) || !in_array($currency,['HUF','EUR','USD','GBP','all'],true)) { throw new RuntimeException(); }
            $next = $old;
            if (!is_array($next['mode_currencies'] ?? null)) { $next['mode_currencies'] = []; }
            if (!is_array($next['mode_currencies'][$mode] ?? null)) { $next['mode_currencies'][$mode] = []; }
            foreach ($currency === 'all' ? ['HUF','EUR','USD','GBP'] : [$currency] as $c) { $next['mode_currencies'][$mode][$c] = false; }
            if (!in_array(true,$next['mode_currencies'][$mode],true)) { $next['modes'][$mode] = false; }
        }
        $next = sharity_donation_account_seal($next,$digest); $map[$ngo] = $next; $encoded = serialize($map);
        if (strlen($encoded) > 1048576) { throw new RuntimeException(); }
        try {
            if ($raw === null) {
                $query("INSERT INTO `{$table}` (option_name,option_value,autoload) VALUES (?,?,?)", ['sharity_donation_accounts_v1',$encoded,'no']);
            } else {
                $query("UPDATE `{$table}` SET option_value = ? WHERE option_name = ? AND BINARY option_value = ?", [$encoded,'sharity_donation_accounts_v1',$raw]);
            }
        } catch (Throwable $uncertain) { /* The native full readback below determines the result, never a second write. */ }
        finally {
            foreach (['sharity_donation_accounts_v1','alloptions','notoptions'] as $key) { wp_cache_delete($key,'options'); }
        }
        [, $actual] = sharity_donation_account_option_read($query,$table);
        if (!isset($actual[$ngo]) || sharity_donation_finance_json($actual[$ngo]) !== sharity_donation_finance_json($next)) { throw new RuntimeException('DONATION_ACCOUNT_WRITE_UNCONFIRMED'); }
        return $actual[$ngo];
    } catch (Throwable $error) {
        $safe = ['DONATION_ACCOUNT_STALE','DONATION_ACCOUNT_WRITE_UNCONFIRMED'];
        throw new RuntimeException(in_array($error->getMessage(),$safe,true) ? $error->getMessage() : 'DONATION_ACCOUNT_STORAGE_UNAVAILABLE');
    }
}

function sharity_donation_account_bind(string $ngo, string $path, string $expect, Sharity_Stripe_Checkout_Adapter $adapter, ?callable $clock = null): array
{
    $clock = $clock ?? static fn(): int => time(); $now = $clock();
    $d = sharity_donation_backing_read_proof($path);
    sharity_donation_account_proof($d,$ngo,$now);
    $p = $adapter->inspectAccount($d['account_id']);
    sharity_donation_account_proof($d,$ngo,$clock());
    $payload = sharity_donation_account_binding_payload($d,$p,$clock());
    return sharity_donation_account_change($ngo,$expect,['kind'=>'bind','proof'=>$d],$payload);
}

function sharity_donation_cli_account(array $args, array $assoc, string $operation): void
{
    try {
        $keys = array_keys($assoc); sort($keys);
        $expected = ['inspect'=>['account'], 'bind'=>['expect','input','ngo'], 'disable'=>['currency','expect','mode','ngo']][$operation] ?? null;
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $keys !== $expected) { throw new RuntimeException('DONATION_ACCOUNT_ARGUMENTS'); }
        foreach ($assoc as $value) { if (!is_string($value) || $value === '') { throw new RuntimeException('DONATION_ACCOUNT_ARGUMENTS'); } }
        if ($operation === 'inspect') { $result = sharity_donation_runtime_adapter()->inspectAccount($assoc['account']); }
        else {
            if (!function_exists('sharity_donation_backing_db')) { throw new RuntimeException('DONATION_ACCOUNT_STORAGE_UNAVAILABLE'); }
            $b = $operation === 'bind'
                ? sharity_donation_account_bind($assoc['ngo'],$assoc['input'],$assoc['expect'],sharity_donation_runtime_adapter())
                : sharity_donation_account_change($assoc['ngo'],$assoc['expect'],['kind'=>'disable','mode'=>$assoc['mode'],'currency'=>$assoc['currency']]);
            $result = ['ok'=>true,'ngo_slug'=>$assoc['ngo'],'binding_version'=>$b['binding_version'],
                'verified_until'=>$b['verified_until'],'mode_currencies'=>$b['mode_currencies'],'financially_accepted'=>false];
        }
        WP_CLI::line(json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        $allowed = ['ARGUMENTS','INSPECT_UNAVAILABLE','MODEL_UNPROVEN','PROOF_EXPIRED','PROOF_INVALID','AMPLIFIER_UNAVAILABLE',
            'PROVIDER_UNPROVEN','STALE','WRITE_UNCONFIRMED','STORAGE_UNAVAILABLE'];
        $code = $error->getMessage();
        WP_CLI::error(in_array(str_replace('DONATION_ACCOUNT_','',$code),$allowed,true) ? $code : 'DONATION_ACCOUNT_UNAVAILABLE');
    }
}

if (defined('WP_CLI') && WP_CLI) {
    foreach (['inspect','bind','disable'] as $operation) {
        WP_CLI::add_command('sharity donations account-'.$operation, static function (array $args,array $assoc) use ($operation): void {
            sharity_donation_cli_account($args,$assoc,$operation);
        });
    }
}

function sharity_donation_cli_finance_drain(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $assoc !== []
            || !function_exists('sharity_donation_checkout_drain')) { throw new RuntimeException(); }
        WP_CLI::line(json_encode(sharity_donation_checkout_drain(time()),JSON_THROW_ON_ERROR));
    } catch (Throwable $error) { WP_CLI::error('DONATION_DRAIN_UNAVAILABLE'); }
}
function sharity_donation_cli_webhook_finance_schema(array $args, array $assoc): void
{
    try {
        if (!defined('WP_CLI') || !WP_CLI || $args !== [] || $assoc !== ['apply'=>true]
            || !function_exists('sharity_donation_webhook_finance_schema_apply')
            || !sharity_donation_webhook_finance_schema_apply()) { throw new RuntimeException(); }
        WP_CLI::line('DONATION_WEBHOOK_FINANCE_SCHEMA_READY');
    } catch (Throwable $error) { WP_CLI::error('DONATION_WEBHOOK_FINANCE_SCHEMA_UNAVAILABLE'); }
}
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('sharity donations finance-drain','sharity_donation_cli_finance_drain');
    WP_CLI::add_command('sharity donations webhook-finance-schema','sharity_donation_cli_webhook_finance_schema');
}
