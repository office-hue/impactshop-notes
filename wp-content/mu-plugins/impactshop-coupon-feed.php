<?php
/**
 * Plugin Name: ImpactShop private coupon feed
 * Description: Default-off, cache-only shopping projection; explicit CLI/cron refresh only.
 *
 * Own non-autoload options: impactshop_coupon_{config,policy}_v1 and
 * impactshop_coupon_{snapshot,refresh_state,refresh_lock}_v1_<provider>.
 * Config: {schemaVersion:1, revision:string, enabledProviders:string[]}.
 * Policy: {schemaVersion:1, revision:string, bindings:[{partnerKey,providerKey,
 * programRef,providerProgramId,siteOrChannelId,publicationAllowed:boolean,
 * attributionCompatibility:compatible|incompatible|unknown,checkedAt,expiresAt,
 * qualification:{acknowledged:true,evidenceRef:string}}], providers:{dognet:{
 * qualification:{acknowledged:true,evidenceRef:string}, dateTimezone:string,
 * nullStartMeansUnbounded:boolean, nullEndMeansOngoing:boolean,
 * kindMap:{"<type>:<form>":{kind:coupon|sale|free_shipping,
 * redeemMode:code|automatic, applicability:conditions_apply|merchant_wide}}}}}.
 * providers.cj: {qualification, dateTimezone:"UTC", emptyStartMeansUnbounded:boolean,
 * emptyEndMeansOngoing:boolean, ongoingEndMeansOngoing:boolean}. Blank/ongoing
 * semantics are independent; a missing decision never becomes an active offer.
 * Qualification acknowledges actual account/program/site and public traffic rights,
 * plus provider field/date semantics; policy insertion alone is not qualification.
 * providers.tradetracker: {qualification,siteId,dateTimezone,nullStartMeansUnbounded,
 * nullEndMeansOngoing,endDateInclusive,resourceMap:{voucher|offer:{kind,redeemMode,applicability}}}.
 * Every semantic field is explicit and qualified. Server credential inputs are
 * IMPACTSHOP_TRADETRACKER_CUSTOMER_ID and IMPACTSHOP_TRADETRACKER_PASSPHRASE
 * (environment, or existing constant when environment is absent); no storage here.
 * No credentials, raw provider responses or financial state are written here.
 */
if (!defined('ABSPATH')) { exit; }

if (!function_exists('impactshop_coupon_now')) { function impactshop_coupon_now() { return time(); } }
if (!function_exists('impactshop_coupon_clock')) { function impactshop_coupon_clock() { return microtime(true); } }
function impactshop_coupon_iso($time) { return gmdate('Y-m-d\TH:i:s\Z', $time); }
function impactshop_coupon_providers() { return ['dognet', 'cj', 'admitad', 'tradetracker']; }
function impactshop_coupon_error($code) { return new WP_Error('coupon_' . $code, 'Coupon source unavailable.'); }
function impactshop_coupon_list($value) { return is_array($value) && array_keys($value) === range(0, count($value) - 1) || $value === []; }
function impactshop_coupon_stamp($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value)) { return false; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));
    return $date && $date->format('Y-m-d\TH:i:s\Z') === $value ? $date->getTimestamp() : false;
}
function impactshop_coupon_ack($value) {
    return is_array($value) && ($value['acknowledged'] ?? null) === true
        && is_string($value['evidenceRef'] ?? null) && strlen(trim($value['evidenceRef'])) > 0
        && strlen($value['evidenceRef']) <= 256;
}
function impactshop_coupon_binding_key($binding) {
    return json_encode([$binding['partnerKey'], $binding['providerKey'], $binding['programRef']], JSON_UNESCAPED_SLASHES);
}
function impactshop_coupon_config() {
    $config = get_option('impactshop_coupon_config_v1', null);
    if ($config === null || $config === false) { return ['schemaVersion' => 1, 'revision' => 'initial-off', 'enabledProviders' => []]; }
    if (!is_array($config) || ($config['schemaVersion'] ?? null) !== 1 || !is_string($config['revision'] ?? null)
        || $config['revision'] === '' || !impactshop_coupon_list($config['enabledProviders'] ?? null)
        ) { return impactshop_coupon_error('config_invalid'); }
    foreach ($config['enabledProviders'] as $provider) {
        if (!is_string($provider) || !in_array($provider, impactshop_coupon_providers(), true)) { return impactshop_coupon_error('config_invalid'); }
    }
    if (count(array_unique($config['enabledProviders'])) !== count($config['enabledProviders'])) { return impactshop_coupon_error('config_invalid'); }
    return $config;
}
function impactshop_coupon_validate_policy($policy, $now = null) {
    $now = $now === null ? impactshop_coupon_now() : $now;
    if (!is_array($policy) || ($policy['schemaVersion'] ?? null) !== 1 || !is_string($policy['revision'] ?? null)
        || $policy['revision'] === '' || !impactshop_coupon_list($policy['bindings'] ?? null)
        || count($policy['bindings']) > 2000 || !is_array($policy['providers'] ?? null)) { return impactshop_coupon_error('policy_invalid'); }
    $seen = [];
    foreach ($policy['bindings'] as $binding) {
        if (!is_array($binding)) { return impactshop_coupon_error('policy_invalid'); }
        foreach (['partnerKey' => 128, 'programRef' => 160, 'providerProgramId' => 160, 'siteOrChannelId' => 160] as $field => $limit) {
            if (!is_string($binding[$field] ?? null) || $binding[$field] === '' || strlen($binding[$field]) > $limit
                || preg_match('/[\x00-\x20\x7f]/', $binding[$field])) { return impactshop_coupon_error('policy_invalid'); }
        }
        if (!in_array($binding['providerKey'] ?? null, impactshop_coupon_providers(), true)
            || !is_bool($binding['publicationAllowed'] ?? null)
            || !in_array($binding['attributionCompatibility'] ?? null, ['compatible', 'incompatible', 'unknown'], true)
            || impactshop_coupon_stamp($binding['checkedAt'] ?? null) === false
            || impactshop_coupon_stamp($binding['expiresAt'] ?? null) === false
            || impactshop_coupon_stamp($binding['checkedAt']) >= impactshop_coupon_stamp($binding['expiresAt'])) { return impactshop_coupon_error('policy_invalid'); }
        $key = impactshop_coupon_binding_key($binding);
        if (isset($seen[$key])) { return impactshop_coupon_error('policy_invalid'); }
        $seen[$key] = true;
    }
    return $policy;
}
function impactshop_coupon_policy_binding($policy, $binding) {
    foreach ($policy['bindings'] as $candidate) {
        if (impactshop_coupon_binding_key($candidate) === impactshop_coupon_binding_key($binding)) { return $candidate; }
    }
    return null;
}
function impactshop_coupon_binding_allowed($binding, $now) {
    return $binding && $binding['publicationAllowed'] === true && impactshop_coupon_ack($binding['qualification'] ?? null)
        && $binding['attributionCompatibility'] === 'compatible'
        && impactshop_coupon_stamp($binding['checkedAt']) <= $now && impactshop_coupon_stamp($binding['expiresAt']) > $now;
}
function impactshop_coupon_implemented($provider) {
    return $provider === 'dognet' || ($provider === 'cj' && is_callable(['ImpactShop_CJ', 'read_coupon_page']))
        || ($provider === 'tradetracker' && class_exists('ImpactShop_TradeTracker') && method_exists('ImpactShop_TradeTracker', 'get_coupon_page'));
}
function impactshop_coupon_mapping($policy, $provider = 'dognet') {
    $mapping = $policy['providers'][$provider] ?? null;
    if ($provider === 'tradetracker') { return impactshop_coupon_tt_mapping($mapping); }
    if ($provider === 'cj') {
        if (!is_array($mapping) || !impactshop_coupon_ack($mapping['qualification'] ?? null)
            || ($mapping['dateTimezone'] ?? null) !== 'UTC') { return impactshop_coupon_error('qualification_missing'); }
        foreach (['emptyStartMeansUnbounded', 'emptyEndMeansOngoing', 'ongoingEndMeansOngoing'] as $field) {
            if (!is_bool($mapping[$field] ?? null)) { return impactshop_coupon_error('qualification_missing'); }
        }
        return $mapping;
    }
    if (!is_array($mapping) || !impactshop_coupon_ack($mapping['qualification'] ?? null)
        || !is_string($mapping['dateTimezone'] ?? null) || !is_bool($mapping['nullStartMeansUnbounded'] ?? null)
        || !is_bool($mapping['nullEndMeansOngoing'] ?? null) || !is_array($mapping['kindMap'] ?? null)
        || !$mapping['kindMap']) { return impactshop_coupon_error('qualification_missing'); }
    try { new DateTimeZone($mapping['dateTimezone']); } catch (Exception $e) { return impactshop_coupon_error('qualification_missing'); }
    foreach ($mapping['kindMap'] as $key => $shape) {
        if (!preg_match('/^\d+:\d+$/D', (string) $key) || !is_array($shape)
            || !in_array($shape['kind'] ?? null, ['coupon', 'sale', 'free_shipping'], true)
            || !in_array($shape['redeemMode'] ?? null, ['code', 'automatic'], true)
            || !in_array($shape['applicability'] ?? null, ['conditions_apply', 'merchant_wide'], true)
            || ($shape['kind'] === 'coupon' && $shape['redeemMode'] !== 'code')) { return impactshop_coupon_error('qualification_missing'); }
    }
    return $mapping;
}
function impactshop_coupon_fingerprint($value) { return hash('sha256', serialize($value)); }

/** Compare-and-swap only this module's options; avoids deleting another refresh owner's lock. */
function impactshop_coupon_cas($name, $old, $new, $delete = false) {
    global $wpdb;
    if ($delete) {
        $sql = $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, maybe_serialize($old));
    } else {
        $sql = $wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND option_value = %s", maybe_serialize($new), $name, maybe_serialize($old));
    }
    $changed = $wpdb->query($sql) === 1;
    if ($changed) { wp_cache_delete($name, 'options'); }
    return $changed;
}
function impactshop_coupon_lock($provider, $now) {
    $name = 'impactshop_coupon_refresh_lock_v1_' . $provider;
    $lock = ['owner' => bin2hex(random_bytes(16)), 'expiresAt' => $now + 90];
    if (add_option($name, $lock, '', false)) { return $lock; }
    $old = get_option($name);
    if (!is_array($old) || !is_int($old['expiresAt'] ?? null) || $old['expiresAt'] > $now) { return false; }
    return impactshop_coupon_cas($name, $old, $lock) ? $lock : false;
}
function impactshop_coupon_fence($provider, $lock, $configHash, $policyHash) {
    return get_option('impactshop_coupon_refresh_lock_v1_' . $provider) === $lock
        && $lock['expiresAt'] > impactshop_coupon_now()
        && impactshop_coupon_fingerprint(impactshop_coupon_config()) === $configHash
        && impactshop_coupon_fingerprint(get_option('impactshop_coupon_policy_v1', null)) === $policyHash;
}
function impactshop_coupon_text($raw, $limit, $empty = false) {
    if (!is_string($raw) || !preg_match('//u', $raw) || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $raw)) { return false; }
    $text = html_entity_decode(wp_strip_all_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Keep every condition while emitting the single-line text required by the reader.
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ((!$empty && $text === '') || strlen($text) > $limit || preg_match('/[\x00-\x08\x0b-\x1f\x7f]|https?:\/\/|www\./i', $text)
        || strpos($text, '<') !== false || strpos($text, '>') !== false) { return false; }
    return $text;
}
function impactshop_coupon_dognet_date($raw, $timezone) {
    if (!is_string($raw)) { return false; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $raw)) {
        $format = 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!' . $format, substr($raw, -1) === 'Z' ? substr($raw, 0, -1) . '+00:00' : $raw);
        $errors = DateTimeImmutable::getLastErrors();
        return $date && (!$errors || !$errors['warning_count'] && !$errors['error_count']) ? $date->getTimestamp() : false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $raw)) { return false; }
    $tz = new DateTimeZone($timezone);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw, $tz);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d H:i:s') !== $raw) { return false; }
    // Ambiguous local DST times have two interpretations; neither is guessed.
    $offsets = [];
    foreach ($tz->getTransitions($date->getTimestamp() - 86400, $date->getTimestamp() + 86400) ?: [] as $transition) { $offsets[$transition['offset']] = true; }
    $matches = 0;
    $wall = strtotime($raw . ' UTC');
    foreach (array_keys($offsets) as $offset) {
        if ((new DateTimeImmutable('@' . ($wall - $offset)))->setTimezone($tz)->format('Y-m-d H:i:s') === $raw) { $matches++; }
    }
    return $matches > 1 ? false : $date->getTimestamp();
}
function impactshop_coupon_normalize_dognet($row, $binding, $mapping) {
    if (!is_array($row) || !is_int($row['id'] ?? null) || $row['id'] <= 0 || !is_int($row['type'] ?? null)
        || !is_int($row['form'] ?? null)) { return false; }
    $shape = $mapping['kindMap'][$row['type'] . ':' . $row['form']] ?? null;
    if (!$shape) { return false; }
    $code = impactshop_coupon_text($row['code'] ?? '', 128, true);
    $benefit = impactshop_coupon_text($row['description'] ?? null, 160);
    $conditions = impactshop_coupon_text($row['detailed_description'] ?? null, 1000, true);
    if ($code === false || $benefit === false || $conditions === false
        || ($shape['redeemMode'] === 'code' && $code === '') || ($shape['redeemMode'] === 'automatic' && $code !== '')
        || !array_key_exists('valid_from', $row) || !array_key_exists('valid_to', $row)) { return false; }
    $start = $row['valid_from'] === null ? ($mapping['nullStartMeansUnbounded'] ? null : false) : impactshop_coupon_dognet_date($row['valid_from'], $mapping['dateTimezone']);
    $end = $row['valid_to'] === null ? ($mapping['nullEndMeansOngoing'] ? null : false) : impactshop_coupon_dognet_date($row['valid_to'], $mapping['dateTimezone']);
    if ($start === false || $end === false || ($start !== null && $end !== null && $start >= $end)) { return false; }
    return ['id' => 'coupon_' . substr(hash('sha256', impactshop_coupon_binding_key($binding) . ':' . $row['id']), 0, 32),
        'kind' => $shape['kind'], 'code' => $code === '' ? null : $code, 'benefitText' => $benefit, 'conditionsText' => $conditions,
        'startsAt' => $start === null ? null : impactshop_coupon_iso($start), 'endsAt' => $end === null ? null : impactshop_coupon_iso($end),
        'expiryKind' => $end === null ? 'ongoing' : 'dated', 'networkLabel' => 'Dognet', 'applicability' => $shape['applicability']];
}
/** Fixed-host read-only POST. No authentication refresh, redirects, retry or provider-supplied next URL. */
function impactshop_coupon_read_dognet($policy, $mapping, $started) {
    $token = get_transient('dognet_api_token_cache_v1');
    if (!is_string($token) || trim($token) === '' || preg_match('/[\r\n]/', $token)) { return impactshop_coupon_error('auth_unavailable'); }
    $channels = [];
    foreach ($policy['bindings'] as $binding) {
        if ($binding['providerKey'] === 'dognet' && impactshop_coupon_binding_allowed($binding, impactshop_coupon_now())) {
            if (!preg_match('/^[1-9]\d{0,15}$/D', $binding['siteOrChannelId']) || !preg_match('/^[1-9]\d{0,15}$/D', $binding['providerProgramId'])) { return impactshop_coupon_error('policy_invalid'); }
            $channels[$binding['siteOrChannelId']] = true;
        }
    }
    if (!$channels) { return impactshop_coupon_error('qualification_missing'); }
    $records = []; $withheld = []; $seen = []; $requests = 0; $bytes = 0;
    foreach (array_keys($channels) as $channel) {
        $last = null; $total = null; $received = 0;
        for ($page = 1; $last === null || $page <= $last; $page++) {
            $remaining = 60 - (impactshop_coupon_clock() - $started);
            if ($remaining <= 0 || ++$requests > 20) { return impactshop_coupon_error('limits'); }
            $response = wp_remote_post('https://api.app.dognet.com/api/v1/coupons/filter?page=' . $page, [
                'timeout' => min(8, $remaining), 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 1048577,
                'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body' => json_encode(['per-page' => 100, 'ad_channel_id' => (int) $channel, 'filter' => [['validity' => ['eq' => 'present']]]]),
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return impactshop_coupon_error('transport'); }
            $body = wp_remote_retrieve_body($response); $bytes += strlen($body);
            if (strlen($body) > 1048576 || $bytes > 1048576 || impactshop_coupon_clock() - $started >= 60) { return impactshop_coupon_error('limits'); }
            $data = json_decode($body, true, 32);
            if (!is_array($data) || !impactshop_coupon_list($data['data'] ?? null) || count($data['data']) > 100 || !is_array($data['meta'] ?? null)) { return impactshop_coupon_error('schema_invalid'); }
            $meta = $data['meta'];
            foreach (['current_page', 'last_page', 'per_page', 'total'] as $field) {
                if (!is_int($meta[$field] ?? null)) { return impactshop_coupon_error('schema_invalid'); }
            }
            if ($meta['current_page'] !== $page || $meta['last_page'] < 1 || $meta['last_page'] > 20 || $meta['per_page'] !== 100
                || $meta['total'] < 0 || $meta['total'] > 2000 || ($last !== null && ($last !== $meta['last_page'] || $total !== $meta['total']))
                || ($page < $meta['last_page'] && count($data['data']) !== 100)) { return impactshop_coupon_error('partial'); }
            $last = $meta['last_page']; $total = $meta['total']; $received += count($data['data']);
            foreach ($data['data'] as $row) {
                if (!is_array($row) || !is_int($row['campaign_id'] ?? null) || $row['campaign_id'] <= 0 || !is_int($row['id'] ?? null)
                    || isset($seen[$channel . ':' . ($row['id'] ?? '')])) { return impactshop_coupon_error('schema_invalid'); }
                $seen[$channel . ':' . $row['id']] = true;
                foreach ($policy['bindings'] as $binding) {
                    if ($binding['providerKey'] !== 'dognet' || $binding['siteOrChannelId'] !== (string) $channel || $binding['providerProgramId'] !== (string) $row['campaign_id']
                        || !impactshop_coupon_binding_allowed($binding, impactshop_coupon_now())) { continue; }
                    $key = impactshop_coupon_binding_key($binding);
                    $offer = impactshop_coupon_normalize_dognet($row, $binding, $mapping);
                    if ($offer === false) { $withheld[$key] = true; } else { $records[$key][] = $offer; }
                }
            }
        }
        if ($received !== $total || $last !== max(1, (int) ceil($total / 100))) { return impactshop_coupon_error('partial'); }
    }
    return ['records' => $records, 'withheld' => $withheld];
}
/** CJ promotion timestamps are UTC; blank dates and ongoing require separate qualified meanings. */
function impactshop_coupon_cj_date($raw, $emptyAllowed, $ongoingAllowed = false) {
    if (!is_string($raw)) { return false; }
    if ($raw === '') { return $emptyAllowed ? null : false; }
    if (strtolower($raw) === 'ongoing') { return $ongoingAllowed ? null : false; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.0{1,3})?$/D', $raw)) { return false; }
    $wall = substr($raw, 0, 19);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    return $date && (!$errors || !$errors['warning_count'] && !$errors['error_count']) && $date->format('Y-m-d H:i:s') === $wall
        ? $date->getTimestamp() : false;
}
function impactshop_coupon_normalize_cj($row, $binding, $mapping) {
    $kinds = ['coupon' => 'coupon', 'sale/discount' => 'sale', 'free shipping' => 'free_shipping'];
    if (!is_array($row) || ($row['relationship_status'] ?? null) !== 'joined'
        || !is_string($row['link_id'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $row['link_id'])
        || ($row['advertiser_id'] ?? null) !== $binding['providerProgramId']
        || !is_string($row['promotion_type'] ?? null) || !isset($kinds[strtolower($row['promotion_type'])])) { return false; }
    $kind = $kinds[strtolower($row['promotion_type'])];
    $code = impactshop_coupon_text($row['coupon_code'] ?? null, 128, true);
    $benefit = impactshop_coupon_text($row['link_name'] ?? null, 160);
    $conditions = impactshop_coupon_text($row['description'] ?? null, 1000, true);
    $start = impactshop_coupon_cj_date($row['promotion_start'] ?? null, $mapping['emptyStartMeansUnbounded']);
    $end = impactshop_coupon_cj_date($row['promotion_end'] ?? null, $mapping['emptyEndMeansOngoing'], $mapping['ongoingEndMeansOngoing']);
    if ($code === false || $benefit === false || $conditions === false || ($kind === 'coupon' && $code === '')
        || $start === false || $end === false || ($start !== null && $end !== null && $start >= $end)) { return false; }
    return ['id' => 'coupon_' . substr(hash('sha256', impactshop_coupon_binding_key($binding) . ':' . $row['link_id']), 0, 32),
        'kind' => $kind, 'code' => $code === '' ? null : $code, 'benefitText' => $benefit, 'conditionsText' => $conditions,
        'startsAt' => $start === null ? null : impactshop_coupon_iso($start), 'endsAt' => $end === null ? null : impactshop_coupon_iso($end),
        'expiryKind' => $end === null ? 'ongoing' : 'dated', 'networkLabel' => 'CJ', 'applicability' => 'conditions_apply'];
}
/** Coupon-only caller of the existing CJ credential/normalizer seam; no legacy sync or ledger call. */
function impactshop_coupon_read_cj($policy, $mapping, $started) {
    $bindings = [];
    foreach ($policy['bindings'] as $binding) {
        if ($binding['providerKey'] !== 'cj' || !impactshop_coupon_binding_allowed($binding, impactshop_coupon_now())) { continue; }
        if (!preg_match('/^[1-9]\d{0,15}$/D', $binding['siteOrChannelId'])
            || !preg_match('/^[1-9]\d{0,15}$/D', $binding['providerProgramId'])) { return impactshop_coupon_error('policy_invalid'); }
        $bindings[] = $binding;
    }
    if (!$bindings) { return impactshop_coupon_error('qualification_missing'); }
    $records = []; $withheld = []; $seen = []; $requests = 0; $bytes = 0;
    foreach (['coupon', 'sale/discount', 'free shipping'] as $type) {
        $last = 1; $total = null;
        for ($page = 1; $page <= $last; $page++) {
            $remaining = 60 - (impactshop_coupon_clock() - $started);
            if ($remaining <= 0 || ++$requests > 20) { return impactshop_coupon_error('limits'); }
            try { $data = ImpactShop_CJ::read_coupon_page($type, $page, min(8, $remaining)); }
            catch (RuntimeException $e) {
                $known = ['coupon_auth', 'coupon_rate_limit', 'coupon_transport', 'coupon_limits', 'coupon_schema'];
                return new WP_Error(in_array($e->getMessage(), $known, true) ? $e->getMessage() : 'coupon_transport', 'Coupon source unavailable.');
            }
            if (impactshop_coupon_clock() - $started >= 60) { return impactshop_coupon_error('limits'); }
            if (!is_array($data) || !is_int($data['responseBytes'] ?? null) || $data['responseBytes'] < 0
                || !is_int($data['total'] ?? null) || $data['total'] < 0 || $data['total'] > 2000
                || ($data['page'] ?? null) !== $page || !impactshop_coupon_list($data['rows'] ?? null)
                || ($data['returned'] ?? null) !== count($data['rows'])
                || $data['returned'] !== min(100, max(0, $data['total'] - ($page - 1) * 100))) { return impactshop_coupon_error('schema_invalid'); }
            $bytes += $data['responseBytes'];
            if ($bytes > 1048576) { return impactshop_coupon_error('limits'); }
            foreach ($bindings as $binding) {
                if (($data['websiteId'] ?? null) !== $binding['siteOrChannelId']) { return impactshop_coupon_error('scope_mismatch'); }
            }
            if ($total !== null && $total !== $data['total']) { return impactshop_coupon_error('partial'); }
            $total = $data['total']; $last = max(1, (int) ceil($total / 100));
            foreach ($data['rows'] as $row) {
                if (!is_array($row) || ($row['relationship_status'] ?? null) !== 'joined'
                    || !is_string($row['link_id'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $row['link_id'])
                    || !is_string($row['advertiser_id'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $row['advertiser_id'])
                    || !is_string($row['promotion_type'] ?? null) || strtolower($row['promotion_type']) !== $type
                    || isset($seen[$row['link_id']])) { return impactshop_coupon_error('schema_invalid'); }
                $seen[$row['link_id']] = true;
                foreach ($bindings as $binding) {
                    if ($binding['providerProgramId'] !== $row['advertiser_id']) { continue; }
                    $key = impactshop_coupon_binding_key($binding);
                    $offer = impactshop_coupon_normalize_cj($row, $binding, $mapping);
                    if ($offer === false) { $withheld[$key] = true; } else { $records[$key][] = $offer; }
                }
            }
        }
    }
    return ['records' => $records, 'withheld' => $withheld];
}
/** Explicitly qualified TT semantics; no live meaning is inferred from a missing field. */
function impactshop_coupon_tt_mapping($mapping) {
    if (!is_array($mapping) || !impactshop_coupon_ack($mapping['qualification'] ?? null)
        || !is_string($mapping['siteId'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $mapping['siteId'])
        || !is_string($mapping['dateTimezone'] ?? null)) { return impactshop_coupon_error('qualification_missing'); }
    try { new DateTimeZone($mapping['dateTimezone']); } catch (Throwable $e) { return impactshop_coupon_error('qualification_missing'); }
    foreach (['nullStartMeansUnbounded', 'nullEndMeansOngoing', 'endDateInclusive'] as $field) {
        if (!is_bool($mapping[$field] ?? null)) { return impactshop_coupon_error('qualification_missing'); }
    }
    foreach (['voucher', 'offer'] as $resource) {
        $shape = $mapping['resourceMap'][$resource] ?? null;
        if (!is_array($shape) || !in_array($shape['kind'] ?? null, ['coupon', 'sale', 'free_shipping'], true)
            || !in_array($shape['redeemMode'] ?? null, ['code', 'automatic'], true)
            || !in_array($shape['applicability'] ?? null, ['conditions_apply', 'merchant_wide'], true)
            || ($shape['kind'] === 'coupon' && $shape['redeemMode'] !== 'code')) { return impactshop_coupon_error('qualification_missing'); }
    }
    return $mapping;
}
/** Calendar-day boundaries use the existing strict local-time/DST validator. */
function impactshop_coupon_tt_date($raw, $mapping, $end = false) {
    if ($raw === null) { return $mapping[$end ? 'nullEndMeansOngoing' : 'nullStartMeansUnbounded'] ? null : false; }
    if (!is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $raw)
        || !checkdate((int) substr($raw, 5, 2), (int) substr($raw, 8, 2), (int) substr($raw, 0, 4))) { return false; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d') !== $raw) { return false; }
    if ($end && $mapping['endDateInclusive']) { $raw = $date->modify('+1 day')->format('Y-m-d'); }
    return impactshop_coupon_dognet_date($raw . ' 00:00:00', $mapping['dateTimezone']);
}
function impactshop_coupon_tt_normalize($row, $binding, $mapping) {
    if (!is_array($row) || !is_string($row['ID'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $row['ID'])
        || ($row['campaignID'] ?? null) !== $binding['providerProgramId']
        || !in_array($row['resource'] ?? null, ['voucher', 'offer'], true)
        || ($row['referenceSupported'] ?? null) !== true) { return false; }
    foreach (['description', 'conditions', 'voucherCode', 'validFromDate', 'validToDate'] as $field) {
        if (!array_key_exists($field, $row) || ($row[$field] !== null && !is_string($row[$field]))) { return false; }
    }
    $shape = $mapping['resourceMap'][$row['resource']];
    $code = impactshop_coupon_text($row['voucherCode'] ?? '', 128, true);
    $benefit = impactshop_coupon_text($row['name'] ?? null, 160);
    // Description can contain extra restrictions, so preserve it alongside conditions.
    $conditions = impactshop_coupon_text(($row['description'] ?? '') . "\n" . ($row['conditions'] ?? ''), 1000, true);
    $start = impactshop_coupon_tt_date($row['validFromDate'], $mapping);
    $end = impactshop_coupon_tt_date($row['validToDate'], $mapping, true);
    if ($code === false || $benefit === false || $conditions === false
        || ($shape['redeemMode'] === 'code' && $code === '') || ($shape['redeemMode'] === 'automatic' && $code !== '')
        || $start === false || $end === false || ($start !== null && $end !== null && $start >= $end)) { return false; }
    $identity = [$binding['siteOrChannelId'], $binding['providerProgramId'], $row['resource'], $row['ID']];
    return ['id' => 'coupon_' . substr(hash('sha256', impactshop_coupon_binding_key($binding) . ':' . json_encode($identity)), 0, 32),
        'kind' => $shape['kind'], 'code' => $code === '' ? null : $code, 'benefitText' => $benefit, 'conditionsText' => $conditions,
        'startsAt' => $start === null ? null : impactshop_coupon_iso($start), 'endsAt' => $end === null ? null : impactshop_coupon_iso($end),
        'expiryKind' => $end === null ? 'ongoing' : 'dated', 'networkLabel' => 'TradeTracker', 'applicability' => $shape['applicability']];
}
/** Private full scan; one bounded client, both resources, no writes or partial publication. */
function impactshop_coupon_read_tradetracker($policy, $mapping, $started, $client) {
    $mapping = impactshop_coupon_tt_mapping($mapping);
    if (is_wp_error($mapping)) { return $mapping; }
    $inTime = static function () use ($started) {
        if (!is_int($started) && !is_float($started)) { return false; }
        $elapsed = impactshop_coupon_clock() - $started;
        return is_finite($elapsed) && $elapsed >= 0 && $elapsed < 60;
    };
    $campaignValid = static function ($campaign, $id) use ($mapping) {
        if (!is_array($campaign) || ($campaign['siteId'] ?? null) !== $mapping['siteId']
            || ($campaign['campaignId'] ?? null) !== $id || ($campaign['assignmentStatus'] ?? null) !== 'accepted'
            || !is_bool($campaign['referencesSupported'] ?? null)) { return false; }
        foreach (['policyCashbackStatus', 'policyDiscountCodeStatus'] as $field) {
            if (!array_key_exists($field, $campaign) || !in_array($campaign[$field], [null, 'allowed', 'limited', 'disallowed'], true)) { return false; }
        }
        return array_key_exists('timeZone', $campaign) && ($campaign['timeZone'] === null || is_string($campaign['timeZone']));
    };
    $failure = static function ($e) {
        $codes = ['tt_auth' => 'auth', 'tt_rate_limit' => 'rate_limit', 'tt_limits' => 'limits',
            'tt_scope' => 'scope_mismatch', 'tt_schema' => 'schema_invalid', 'tt_input' => 'schema_invalid'];
        return impactshop_coupon_error($codes[$e instanceof RuntimeException ? $e->getMessage() : ''] ?? 'transport');
    };
    $groups = []; $keys = []; $records = []; $withheld = [];
    if (!is_array($policy) || !impactshop_coupon_list($policy['bindings'] ?? null)) { return impactshop_coupon_error('policy_invalid'); }
    foreach ($policy['bindings'] as $binding) {
        if (!is_array($binding)) { return impactshop_coupon_error('policy_invalid'); }
        if (($binding['providerKey'] ?? null) !== 'tradetracker' || !impactshop_coupon_binding_allowed($binding, impactshop_coupon_now())) { continue; }
        if (($binding['siteOrChannelId'] ?? null) !== $mapping['siteId']
            || !is_string($binding['providerProgramId'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $binding['providerProgramId'])) { return impactshop_coupon_error('scope_mismatch'); }
        $key = impactshop_coupon_binding_key($binding);
        if (isset($keys[$key])) { return impactshop_coupon_error('policy_invalid'); }
        $keys[$key] = true; $groups[$binding['providerProgramId']][$key] = $binding;
    }
    if (!$groups) { return impactshop_coupon_error('qualification_missing'); }
    $requests = 0; $bytes = 0; $pages = 0; $qualified = 0;
    foreach ($groups as $id => $bindings) {
        $id = (string) $id;
        if ($requests >= 20 || $bytes >= 1048576 || !$inTime()) { return impactshop_coupon_error('limits'); }
        try { $campaign = $client->qualify_campaign($id); } catch (Throwable $e) { return $failure($e); }
        $qualified++;
        if (!$inTime()) { return impactshop_coupon_error('limits'); }
        if (!$campaignValid($campaign, $id)) { return impactshop_coupon_error('scope_mismatch'); }
        if ($campaign['policyCashbackStatus'] !== 'allowed' || $campaign['policyDiscountCodeStatus'] !== 'allowed'
            || $campaign['timeZone'] !== $mapping['dateTimezone'] || !$campaign['referencesSupported']) {
            foreach ($bindings as $key => $binding) { $withheld[$key] = true; }
            continue;
        }
        foreach (['voucher', 'offer'] as $resource) {
            $offset = 0; $previousID = null;
            do {
                if (!$inTime() || $requests >= 20 || $bytes >= 1048576 || $pages >= 20) { return impactshop_coupon_error('limits'); }
                try { $page = $client->get_coupon_page($resource, $id, $offset, 100); } catch (Throwable $e) { return $failure($e); }
                $pages++;
                if (!$inTime()) { return impactshop_coupon_error('limits'); }
                if (!is_array($page) || ($page['siteId'] ?? null) !== $mapping['siteId'] || ($page['campaign'] ?? null) !== $campaign
                    || ($page['resource'] ?? null) !== $resource || ($page['offset'] ?? null) !== $offset || ($page['limit'] ?? null) !== 100
                    || !impactshop_coupon_list($page['rows'] ?? null) || count($page['rows']) > 100
                    || ($page['returned'] ?? null) !== count($page['rows'])) { return impactshop_coupon_error('schema_invalid'); }
                foreach (['requests', 'responseBytes', 'totalResponseBytes'] as $field) {
                    if (!is_int($page[$field] ?? null) || $page[$field] < 1) { return impactshop_coupon_error('schema_invalid'); }
                }
                if ($page['requests'] < $pages + $qualified + 2 || $page['requests'] <= $requests || $page['totalResponseBytes'] <= $bytes
                    || $page['responseBytes'] > $page['totalResponseBytes'] - $bytes) { return impactshop_coupon_error('schema_invalid'); }
                $requests = $page['requests']; $bytes = $page['totalResponseBytes'];
                if ($requests > 20 || $bytes > 1048576) { return impactshop_coupon_error('limits'); }
                foreach ($page['rows'] as $row) {
                    if (!is_array($row) || !is_string($row['ID'] ?? null) || !preg_match('/^[1-9]\d{0,15}$/D', $row['ID'])
                        || ($row['campaignID'] ?? null) !== $id || ($row['resource'] ?? null) !== $resource
                        || ($previousID !== null && (int) $row['ID'] <= (int) $previousID)) { return impactshop_coupon_error('schema_invalid'); }
                    $previousID = $row['ID'];
                    foreach ($bindings as $key => $binding) {
                        // The VidaXL pilot authorizes this voucher, not the campaign's other materials.
                        if ($binding['partnerKey'] === 'vidaxl'
                            && ($row['resource'] !== 'voucher' || $row['ID'] !== '2597886')) {
                            continue;
                        }
                        $offer = impactshop_coupon_tt_normalize($row, $binding, $mapping);
                        if ($offer === false) { $withheld[$key] = true; } else { $records[$key][] = $offer; }
                    }
                }
                $offset += 100;
            } while ($page['returned'] === 100);
        }
    }
    return $inTime() ? ['records' => $records, 'withheld' => $withheld] : impactshop_coupon_error('limits');
}

function impactshop_coupon_refresh($provider) {
    if (!impactshop_coupon_implemented($provider)) { return impactshop_coupon_error('adapter_unavailable'); }
    $config = impactshop_coupon_config(); $rawPolicy = get_option('impactshop_coupon_policy_v1', null);
    $policy = impactshop_coupon_validate_policy($rawPolicy);
    if (is_wp_error($config)) { return $config; }
    if (is_wp_error($policy)) { return $policy; }
    $mapping = impactshop_coupon_mapping($policy, $provider);
    if (is_wp_error($mapping)) { return $mapping; }
    $now = impactshop_coupon_now(); $lock = impactshop_coupon_lock($provider, $now);
    if (!$lock) { return impactshop_coupon_error('locked'); }
    $configHash = impactshop_coupon_fingerprint($config); $policyHash = impactshop_coupon_fingerprint($rawPolicy);
    $stateName = 'impactshop_coupon_refresh_state_v1_' . $provider;
    $state = ['status' => 'refreshing', 'owner' => $lock['owner'], 'attemptedAt' => impactshop_coupon_iso($now), 'configHash' => $configHash, 'policyHash' => $policyHash];
    try {
        // A scan lasts at most60s.120s between starts leaves a full quiet minute
        // after its last request, so separate CJ scans cannot exceed25/min.
        $previous = get_option($stateName, []);
        // An auth failure stays suspended under the same config/policy until operator repair.
        if (in_array($provider, ['cj', 'tradetracker'], true) && ($previous['error'] ?? null) === 'coupon_auth'
            && ($previous['configHash'] ?? null) === $configHash && ($previous['policyHash'] ?? null) === $policyHash) { return impactshop_coupon_error('auth'); }
        $attempted = impactshop_coupon_stamp($previous['attemptedAt'] ?? null);
        if (in_array($provider, ['cj', 'tradetracker'], true) && $attempted !== false && $attempted + 120 > $now) { return impactshop_coupon_error('rate_limit'); }
        if (!impactshop_coupon_fence($provider, $lock, $configHash, $policyHash)) { return impactshop_coupon_error('publication_fenced'); }
        if (!update_option($stateName, $state, false)) { return impactshop_coupon_error('storage_failed'); }
        if ($provider === 'tradetracker') {
            // Explicit server inputs only; never persist or refresh credentials here.
            $customer = getenv('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID');
            $secret = getenv('IMPACTSHOP_TRADETRACKER_PASSPHRASE');
            if ($customer === false && defined('IMPACTSHOP_TRADETRACKER_CUSTOMER_ID')) { $customer = IMPACTSHOP_TRADETRACKER_CUSTOMER_ID; }
            if ($secret === false && defined('IMPACTSHOP_TRADETRACKER_PASSPHRASE')) { $secret = IMPACTSHOP_TRADETRACKER_PASSPHRASE; }
            if (!is_string($customer) || $customer === '' || !is_string($secret) || $secret === '') { $result = impactshop_coupon_error('auth_unavailable'); }
            else {
                $started = impactshop_coupon_clock();
                try { $client = new ImpactShop_TradeTracker($customer, $secret); }
                catch (Throwable $e) { $client = null; }
                $result = $client ? impactshop_coupon_read_tradetracker($policy, $mapping, $started, $client) : impactshop_coupon_error('auth_unavailable');
                unset($client, $customer, $secret);
            }
        } else {
            $result = $provider === 'cj'
                ? impactshop_coupon_read_cj($policy, $mapping, impactshop_coupon_clock())
                : impactshop_coupon_read_dognet($policy, $mapping, impactshop_coupon_clock());
        }
        if (!impactshop_coupon_fence($provider, $lock, $configHash, $policyHash)) { return impactshop_coupon_error('publication_fenced'); }
        if (is_wp_error($result)) {
            impactshop_coupon_cas($stateName, $state, array_merge($state, ['status' => 'failed', 'error' => $result->get_error_code()]));
            return $result;
        }
        $finished = impactshop_coupon_now();
        $snapshot = ['schemaVersion' => 1, 'owner' => $lock['owner'], 'configHash' => $configHash, 'policyHash' => $policyHash,
            'checkedAt' => impactshop_coupon_iso($finished), 'validUntil' => impactshop_coupon_iso($finished + 7200),
            'records' => $result['records'], 'withheld' => $result['withheld']];
        if (strlen(serialize($snapshot)) > 1048576) {
            impactshop_coupon_cas($stateName, $state, array_merge($state, ['status' => 'failed', 'error' => 'coupon_limits']));
            return impactshop_coupon_error('limits');
        }
        if (!update_option('impactshop_coupon_snapshot_v1_' . $provider, $snapshot, false)) {
            impactshop_coupon_cas($stateName, $state, array_merge($state, ['status' => 'failed', 'error' => 'coupon_storage_failed']));
            return impactshop_coupon_error('storage_failed');
        }
        if (!impactshop_coupon_fence($provider, $lock, $configHash, $policyHash)
            || !impactshop_coupon_cas($stateName, $state, array_merge($state, ['status' => 'complete', 'checkedAt' => $snapshot['checkedAt']]))) { return impactshop_coupon_error('publication_fenced'); }
        return ['provider' => $provider, 'status' => 'complete', 'checkedAt' => $snapshot['checkedAt'], 'withheldBindings' => count($snapshot['withheld'])];
    } finally {
        impactshop_coupon_cas('impactshop_coupon_refresh_lock_v1_' . $provider, $lock, null, true);
    }
}
/** Recheck stored normalized data too: a malformed cache must not escape publicly. */
function impactshop_coupon_offer_valid($offer, $provider = 'dognet') {
    if (!is_array($offer) || !is_string($offer['id'] ?? null) || !preg_match('/^coupon_[a-f0-9]{32}$/D', $offer['id'])
        || !in_array($offer['kind'] ?? null, ['coupon', 'sale', 'free_shipping'], true)
        || !in_array($offer['applicability'] ?? null, ['conditions_apply', 'merchant_wide'], true)
        || !in_array($offer['expiryKind'] ?? null, ['dated', 'ongoing'], true)
        || ($offer['networkLabel'] ?? null) !== ($provider === 'tradetracker' ? 'TradeTracker' : ($provider === 'cj' ? 'CJ' : 'Dognet')) || !array_key_exists('code', $offer)
        || !array_key_exists('startsAt', $offer) || !array_key_exists('endsAt', $offer)) { return false; }
    foreach (['benefitText' => 160, 'conditionsText' => 1000] as $key => $limit) {
        if (impactshop_coupon_text($offer[$key] ?? null, $limit, $key === 'conditionsText') !== ($offer[$key] ?? null)) { return false; }
    }
    if ($offer['code'] !== null && impactshop_coupon_text($offer['code'], 128) !== $offer['code']) { return false; }
    if ($offer['kind'] === 'coupon' && $offer['code'] === null) { return false; }
    $start = $offer['startsAt'] === null ? null : impactshop_coupon_stamp($offer['startsAt']);
    $end = $offer['endsAt'] === null ? null : impactshop_coupon_stamp($offer['endsAt']);
    return $start !== false && $end !== false && !($start !== null && $end !== null && $start >= $end)
        && (($offer['expiryKind'] === 'ongoing' && $end === null) || ($offer['expiryKind'] === 'dated' && $end !== null));
}
function impactshop_coupon_empty($status, $reason = null, $checkedAt = null) {
    $value = ['status' => $status, 'coverage' => 'partial', 'checkedAt' => $checkedAt, 'validUntil' => null, 'freshForMs' => 0, 'offers' => [], 'moreAvailable' => false];
    if ($reason !== null) { $value['reason'] = $reason; }
    return $value;
}
/** Cache-only projection: never initiates provider I/O. */
function impactshop_coupon_project($binding, $config, $policy, $now = null) {
    $now = $now === null ? impactshop_coupon_now() : $now;
    $provider = $binding['providerKey'];
    if (!impactshop_coupon_implemented($provider) || !in_array($provider, $config['enabledProviders'], true)) { return impactshop_coupon_empty('not_checked'); }
    $matched = !is_wp_error($policy) ? impactshop_coupon_policy_binding($policy, $binding) : null;
    if (!$matched || !$matched['publicationAllowed'] || !impactshop_coupon_ack($matched['qualification'] ?? null)) { return impactshop_coupon_empty('unavailable', 'binding_unavailable'); }
    if ($matched['attributionCompatibility'] !== 'compatible') { return impactshop_coupon_empty('unavailable', 'compatibility_unverified'); }
    if (!impactshop_coupon_binding_allowed($matched, $now) || is_wp_error(impactshop_coupon_mapping($policy, $provider))) { return impactshop_coupon_empty('unavailable', 'binding_unavailable'); }
    $snapshot = get_option('impactshop_coupon_snapshot_v1_' . $provider, null);
    $state = get_option('impactshop_coupon_refresh_state_v1_' . $provider, null);
    $checked = is_array($snapshot) && impactshop_coupon_stamp($snapshot['checkedAt'] ?? null) !== false ? $snapshot['checkedAt'] : null;
    if (!is_array($snapshot) || ($snapshot['schemaVersion'] ?? null) !== 1 || !is_array($state) || ($state['status'] ?? null) !== 'complete'
        || ($state['owner'] ?? null) !== ($snapshot['owner'] ?? null)
        || ($snapshot['configHash'] ?? null) !== impactshop_coupon_fingerprint($config)
        || ($snapshot['policyHash'] ?? null) !== impactshop_coupon_fingerprint($policy)
        || !is_array($snapshot['records'] ?? null) || !is_array($snapshot['withheld'] ?? null)) { return impactshop_coupon_empty('unavailable', 'source_unavailable', $checked); }
    $expires = impactshop_coupon_stamp($snapshot['validUntil'] ?? null);
    if ($expires === false || $checked === null || impactshop_coupon_stamp($checked) > $now || $expires <= $now
        || impactshop_coupon_stamp($checked) + 7200 <= $now) { return impactshop_coupon_empty('unavailable', 'stale', $checked); }
    $key = impactshop_coupon_binding_key($binding);
    if (!empty($snapshot['withheld'][$key])) { return impactshop_coupon_empty('unavailable', 'source_unavailable', $checked); }
    if (!impactshop_coupon_list($snapshot['records'][$key] ?? [])) { return impactshop_coupon_empty('unavailable', 'source_unavailable', $checked); }
    $deadline = min($now + 60, $expires, impactshop_coupon_stamp($matched['expiresAt'])); $offers = [];
    $correctVisionExpress = $provider === 'dognet'
        && $matched['partnerKey'] === 'visionexpress' && $matched['programRef'] === 'dognet:visionexpress'
        && $matched['providerProgramId'] === '223' && $matched['siteOrChannelId'] === '26081';
    $visionExpressCouponAdded = false;
    foreach ($snapshot['records'][$key] ?? [] as $offer) {
        if (!impactshop_coupon_offer_valid($offer, $provider)) { return impactshop_coupon_empty('unavailable', 'source_unavailable', $checked); }
        $start = $offer['startsAt'] === null ? null : impactshop_coupon_stamp($offer['startsAt']);
        $end = $offer['endsAt'] === null ? null : impactshop_coupon_stamp($offer['endsAt']);
        if ($start !== null && $start > $now) { $deadline = min($deadline, $start); continue; }
        if ($end !== null && $end <= $now) { continue; }
        if ($end !== null) { $deadline = min($deadline, $end); }
        // Dognet confirmed the corrected code for this campaign on 2026-11-24.
        if ($correctVisionExpress && $offer['kind'] === 'coupon' && in_array($offer['code'], ['WS2025', 'WS2026'], true)) {
            if ($visionExpressCouponAdded) { continue; }
            $visionExpressCouponAdded = true;
            $offer['id'] = 'coupon_' . substr(hash('sha256', $key . ':WS2026'), 0, 32);
            $offer['code'] = 'WS2026';
            $offer['benefitText'] = '20% kedvezmény új kollekciós napszemüvegekre';
            $offer['conditionsText'] = 'Csak a promócióban részt vevő, teljes árú, új kollekciós napszemüvegekre, a webshopban. '
                . 'Akciós termékekre nem érvényes, más kedvezménnyel nem összevonható. Kizárt márkák: Miu Miu, Prada és Persol.';
            $offer['applicability'] = 'conditions_apply';
        }
        // Explicit public field construction: private snapshot metadata never escapes.
        $offers[] = ['id' => $offer['id'], 'kind' => $offer['kind'], 'code' => $offer['code'], 'benefitText' => $offer['benefitText'],
            'conditionsText' => $offer['conditionsText'], 'startsAt' => $offer['startsAt'], 'endsAt' => $offer['endsAt'],
            'expiryKind' => $offer['expiryKind'], 'networkLabel' => $provider === 'tradetracker' ? 'TradeTracker' : ($provider === 'cj' ? 'CJ' : 'Dognet'), 'applicability' => $offer['applicability']];
    }
    usort($offers, function ($a, $b) { return strcmp($a['endsAt'] ?? '9999', $b['endsAt'] ?? '9999') ?: strcmp($a['id'], $b['id']); });
    return ['status' => $offers ? 'available' : 'none', 'coverage' => 'complete', 'checkedAt' => $checked,
        'validUntil' => impactshop_coupon_iso($deadline), 'freshForMs' => max(0, min(60000, ($deadline - $now) * 1000)),
        'offers' => array_slice($offers, 0, 3), 'moreAvailable' => count($offers) > 3];
}
function impactshop_coupon_permission($request) {
    if (!function_exists('impactshop_sharity_affiliate_service_authorized')) { return new WP_Error('coupon_unavailable', 'Coupon service unavailable.', ['status' => 503]); }
    return impactshop_sharity_affiliate_service_authorized($request) ? true : new WP_Error('coupon_unauthorized', 'Unauthorized.', ['status' => 401]);
}
function impactshop_coupon_rest($request) {
    $permission = impactshop_coupon_permission($request);
    if (is_wp_error($permission)) { return $permission; }
    $params = $request->get_query_params();
    // WordPress adds its routing parameter even for pretty /wp-json/ URLs.
    $route = '/sharity/v1/shopping/coupon-offers';
    if (array_key_exists('rest_route', $params)) {
        if ($params['rest_route'] !== $route) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
        unset($params['rest_route']);
    }
    // The actual encoded query catches repeated keys that PHP's parsed params collapse.
    $raw = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    if (strlen($raw) > 8192 || array_keys($params) !== ['bindings'] || !is_string($params['bindings'])) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
    $queryKeys = [];
    foreach (explode('&', $raw) as $part) {
        $pair = explode('=', $part, 2); $key = $pair[0];
        if (count($pair) !== 2 || isset($queryKeys[$key]) || !in_array($key, ['bindings', 'rest_route'], true)
            || ($key === 'rest_route' && urldecode($pair[1]) !== $route)) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
        $queryKeys[$key] = true;
    }
    if (!isset($queryKeys['bindings'])) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
    $bindings = json_decode($params['bindings'], true, 8);
    if (!impactshop_coupon_list($bindings) || count($bindings) < 1 || count($bindings) > 10) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
    // The decoded shape alone would silently accept duplicate JSON object keys.
    preg_match_all('~"(?:[^"\\\\]|\\\\.)*"\s*:~s', $params['bindings'], $jsonKeys);
    if (count($jsonKeys[0]) !== count($bindings) * 3) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
    $seen = [];
    foreach ($bindings as $binding) {
        if (!is_array($binding)) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
        $keys = array_keys($binding); sort($keys);
        if ($keys !== ['partnerKey', 'programRef', 'providerKey'] || !is_string($binding['partnerKey']) || !is_string($binding['programRef'])
            || $binding['partnerKey'] === '' || strlen($binding['partnerKey']) > 128 || $binding['programRef'] === '' || strlen($binding['programRef']) > 160
            || preg_match('/[\x00-\x20\x7f]/', $binding['partnerKey'] . $binding['programRef'])
            || !in_array($binding['providerKey'], impactshop_coupon_providers(), true)) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
        $key = impactshop_coupon_binding_key($binding);
        if (isset($seen[$key])) { return new WP_Error('coupon_invalid_request', 'Invalid bindings.', ['status' => 400]); }
        $seen[$key] = true;
    }
    $now = impactshop_coupon_now(); $config = impactshop_coupon_config();
    if (is_wp_error($config)) { return new WP_Error('coupon_unavailable', 'Coupon service unavailable.', ['status' => 503]); }
    $policy = impactshop_coupon_validate_policy(get_option('impactshop_coupon_policy_v1', null), $now);
    $items = [];
    foreach ($bindings as $binding) { $items[] = ['binding' => $binding, 'benefits' => impactshop_coupon_project($binding, $config, $policy, $now)]; }
    $response = new WP_REST_Response(['schemaVersion' => 1, 'items' => $items], 200);
    $response->header('Cache-Control', 'no-store');
    return $response;
}
function impactshop_coupon_set_enabled($provider, $enabled) {
    if (!in_array($provider, impactshop_coupon_providers(), true)) { return impactshop_coupon_error('invalid_provider'); }
    $config = impactshop_coupon_config();
    if (is_wp_error($config)) { return $config; }
    if ($enabled) {
        if (!impactshop_coupon_implemented($provider)) { return impactshop_coupon_error('adapter_unavailable'); }
        $policy = impactshop_coupon_validate_policy(get_option('impactshop_coupon_policy_v1', null));
        if (is_wp_error($policy)) { return $policy; }
        $mapping = impactshop_coupon_mapping($policy, $provider);
        if (is_wp_error($mapping)) { return $mapping; }
        $allowed = false;
        foreach ($policy['bindings'] as $binding) { if ($binding['providerKey'] === $provider && impactshop_coupon_binding_allowed($binding, impactshop_coupon_now())) { $allowed = true; } }
        if (!$allowed) { return impactshop_coupon_error('qualification_missing'); }
        // Activation consumes a real fresh pilot read under this exact policy/config.
        $snapshot = get_option('impactshop_coupon_snapshot_v1_' . $provider, null);
        $state = get_option('impactshop_coupon_refresh_state_v1_' . $provider, null);
        $now = impactshop_coupon_now();
        if (!is_array($snapshot) || ($snapshot['schemaVersion'] ?? null) !== 1
            || !is_array($state) || ($state['status'] ?? null) !== 'complete'
            || ($state['owner'] ?? null) !== ($snapshot['owner'] ?? null)
            || ($snapshot['configHash'] ?? null) !== impactshop_coupon_fingerprint($config)
            || ($snapshot['policyHash'] ?? null) !== impactshop_coupon_fingerprint($policy)
            || impactshop_coupon_stamp($snapshot['checkedAt'] ?? null) === false
            || impactshop_coupon_stamp($snapshot['checkedAt']) > $now
            || impactshop_coupon_stamp($snapshot['checkedAt']) + 7200 <= $now
            || impactshop_coupon_stamp($snapshot['validUntil'] ?? null) <= $now
            || !is_array($snapshot['withheld'] ?? null) || $snapshot['withheld']) { return impactshop_coupon_error('fresh_pilot_required'); }
    }
    $providers = array_values(array_diff($config['enabledProviders'], [$provider]));
    if ($enabled) { $providers[] = $provider; }
    sort($providers);
    $config = ['schemaVersion' => 1, 'revision' => bin2hex(random_bytes(16)), 'enabledProviders' => $providers];
    if (!update_option('impactshop_coupon_config_v1', $config, false)) { return impactshop_coupon_error('storage_failed'); }
    if ($providers) {
        if (!wp_next_scheduled('impactshop_coupon_refresh_v1')) { wp_schedule_event(impactshop_coupon_now() + 3600, 'hourly', 'impactshop_coupon_refresh_v1'); }
    } else { wp_clear_scheduled_hook('impactshop_coupon_refresh_v1'); }
    return ['provider' => $provider, 'enabled' => $enabled, 'revision' => $config['revision'],
        'ready' => false, 'refreshRequired' => $enabled];
}
function impactshop_coupon_status() {
    $config = impactshop_coupon_config(); $providers = [];
    foreach (impactshop_coupon_providers() as $provider) {
        $state = get_option('impactshop_coupon_refresh_state_v1_' . $provider, []);
        $snapshot = get_option('impactshop_coupon_snapshot_v1_' . $provider, []);
        $lock = get_option('impactshop_coupon_refresh_lock_v1_' . $provider, []);
        $providers[$provider] = ['implemented' => impactshop_coupon_implemented($provider), 'enabled' => !is_wp_error($config) && in_array($provider, $config['enabledProviders'], true),
            'refreshStatus' => $state['status'] ?? 'not_checked', 'error' => $state['error'] ?? null, 'checkedAt' => $snapshot['checkedAt'] ?? null,
            'validUntil' => $snapshot['validUntil'] ?? null, 'lockActive' => ($lock['expiresAt'] ?? 0) > impactshop_coupon_now()];
    }
    return ['schemaVersion' => 1, 'configValid' => !is_wp_error($config), 'providers' => $providers, 'hourlyScheduled' => (bool) wp_next_scheduled('impactshop_coupon_refresh_v1')];
}
add_action('rest_api_init', function () {
    register_rest_route('sharity/v1', '/shopping/coupon-offers', ['methods' => 'GET', 'callback' => 'impactshop_coupon_rest', 'permission_callback' => 'impactshop_coupon_permission']);
});
add_action('impactshop_coupon_refresh_v1', function () {
    $config = impactshop_coupon_config();
    if (is_wp_error($config)) { return; }
    foreach ($config['enabledProviders'] as $provider) {
        if (impactshop_coupon_implemented($provider)) { impactshop_coupon_refresh($provider); }
    }
});
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('impactshop coupons', function ($args, $assoc) {
        $command = $args[0] ?? 'status'; $provider = $assoc['provider'] ?? '';
        if ($command === 'status') { $result = impactshop_coupon_status(); }
        elseif ($command === 'refresh') { $result = impactshop_coupon_refresh($provider); }
        elseif ($command === 'enable' || $command === 'disable') { $result = impactshop_coupon_set_enabled($provider, $command === 'enable'); }
        else { $result = impactshop_coupon_error('invalid_command'); }
        if (is_wp_error($result)) { WP_CLI::line(json_encode(['status' => 'failed', 'error' => $result->get_error_code()])); WP_CLI::halt(1); return; }
        WP_CLI::line(json_encode($result, JSON_UNESCAPED_SLASHES));
    });
}
