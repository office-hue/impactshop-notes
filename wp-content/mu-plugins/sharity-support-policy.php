<?php
/**
 * Internal affiliate/Offerwall policy; no routes, hooks, storage or activation.
 * Callers MUST resolve trusted server authority and persist this frozen snapshot
 * with the economic root. Shape validation/digest do not authenticate evidence.
 * Cash, extra cost and full liability evidence must be verified by the journal.
 * Points, votes, Stripe direct/Amplifier and legacy Offerwall policy are separate.
 */
declare(strict_types=1);
namespace sharity_support_policy;

const POLICY_IDS = ['affiliate' => 'affiliate_received_net_tiers_v1', 'offerwall' => 'offerwall_v2_received_net_tiers_v2'];
const RATES = ['affiliate' => ['base' => 5000, 'rising' => 5500, 'legend' => 6000], 'offerwall' => ['base' => 6000, 'rising' => 6500, 'legend' => 7000]];
// Financial multipliers only: Sharity_Level_Manager::LEVELS, never vote/discount.
const MULTIPLIERS = ['basic' => 10000, 'bronze' => 10500, 'silver' => 11000, 'gold' => 11500, 'platinum' => 12000, 'legend' => 12500];
const INPUT_KEYS = ['source', 'ngo_slug', 'tier', 'donor_level', 'captured_at_utc', 'ngo_quarter_key', 'ngo_authority', 'donor_authority'];
const MAX_MINOR = '9223372036854775807';
const NGO_RANK_POLICY_VERSION = 'ngo_current_quarter_votes_ties_v1';
final class PolicyViolation extends \DomainException {}

function exact_keys(array $value, array $keys): void
{
    if (count($value) !== count($keys) || array_diff($keys, array_keys($value))) {
        throw new PolicyViolation('SUPPORT_POLICY_KEYS');
    }
}

function utc($value): string
{
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $value)) {
        throw new PolicyViolation('SUPPORT_POLICY_TIME');
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
    if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $value || $date->getTimestamp() <= 0) {
        throw new PolicyViolation('SUPPORT_POLICY_TIME');
    }
    return $value;
}

/** Opaque, non-identifying evidence pointers only. Never put owner/pseudo IDs in refs. */
function authority($value, string $captured): array
{
    if (!is_array($value)) { throw new PolicyViolation('SUPPORT_POLICY_AUTHORITY'); }
    exact_keys($value, ['ref', 'sha256', 'observed_at_utc']);
    if (!is_string($value['ref']) || !preg_match('/^[A-Za-z0-9._:-]{1,160}$/D', $value['ref'])
        || !is_string($value['sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $value['sha256'])
        || utc($value['observed_at_utc']) > $captured) {
        throw new PolicyViolation('SUPPORT_POLICY_AUTHORITY');
    }
    return ['ref' => $value['ref'], 'sha256' => $value['sha256'], 'observed_at_utc' => $value['observed_at_utc']];
}

/** Pure normalization of ALREADY resolved authority, not a live lookup or HTTP input. */
function make_snapshot(array $input): array
{
    exact_keys($input, INPUT_KEYS);
    foreach (['source', 'ngo_slug', 'tier', 'donor_level', 'ngo_quarter_key'] as $key) {
        if (!is_string($input[$key])) { throw new PolicyViolation('SUPPORT_POLICY_VALUE'); }
    }
    $source = $input['source']; $tier = $input['tier']; $level = $input['donor_level'];
    if (!isset(RATES[$source][$tier], MULTIPLIERS[$level])
        || strlen($input['ngo_slug']) > 100 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $input['ngo_slug'])
        || !preg_match('/^[0-9]{4}Q[1-4]$/D', $input['ngo_quarter_key'])) {
        throw new PolicyViolation('SUPPORT_POLICY_VALUE');
    }
    $captured = utc($input['captured_at_utc']);
    $snapshot = ['schema_version' => 1, 'policy_id' => POLICY_IDS[$source], 'source' => $source,
        'ngo_slug' => $input['ngo_slug'], 'tier' => $tier, 'donor_level' => $level,
        'rate_bps' => RATES[$source][$tier], 'donor_multiplier_bps' => MULTIPLIERS[$level],
        'captured_at_utc' => $captured, 'ngo_quarter_key' => $input['ngo_quarter_key'],
        'ngo_authority' => authority($input['ngo_authority'], $captured),
        'donor_authority' => authority($input['donor_authority'], $captured)];
    // Fixed field order and validated scalar types give a canonical, order-independent digest.
    $snapshot['snapshot_digest'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $snapshot;
}

/** Reconstruct by the frozen version; never consult today's donor/NGO level. */
function assert_snapshot_valid(array $snapshot): array
{
    exact_keys($snapshot, array_merge(INPUT_KEYS, ['schema_version', 'policy_id', 'rate_bps', 'donor_multiplier_bps', 'snapshot_digest']));
    $rebuilt = make_snapshot(array_intersect_key($snapshot, array_fill_keys(INPUT_KEYS, true)));
    foreach (['schema_version', 'policy_id', 'rate_bps', 'donor_multiplier_bps', 'snapshot_digest'] as $key) {
        if ($snapshot[$key] !== $rebuilt[$key]) { throw new PolicyViolation('SUPPORT_POLICY_SNAPSHOT'); }
    }
    return $rebuilt;
}

/** Untyped argument intentionally prevents weak PHP callers coercing a float/int. */
function minor($value): int
{
    if (PHP_INT_SIZE !== 8 || !is_string($value) || strlen($value) > 19
        || !preg_match('/^(0|[1-9][0-9]*)$/D', $value)
        || (strlen($value) === 19 && strcmp($value, MAX_MINOR) > 0)) {
        throw new PolicyViolation('SUPPORT_POLICY_AMOUNT');
    }
    return (int)$value;
}

/** Already canonical UI counts only. This does not resolve or authenticate authority. */
function current_quarter_ranks(array $groups): array
{
    $votes = [];
    foreach ($groups as $row) {
        if (!is_array($row) || !isset($row['slug']) || !is_string($row['slug'])
            || $row['slug'] === '' || strlen($row['slug']) > 190
            || preg_match('/[\x00-\x1f\x7f]/', $row['slug']) || !array_key_exists('votes', $row)) {
            throw new PolicyViolation('SUPPORT_POLICY_VOTES');
        }
        // SQL SUM values are decimal strings. Never coerce float, bool or null.
        $count = is_int($row['votes']) && $row['votes'] >= 0 ? $row['votes'] : minor($row['votes']);
        $slug = $row['slug'];
        $previous = $votes[$slug] ?? 0;
        if ($previous > PHP_INT_MAX - $count) { throw new PolicyViolation('SUPPORT_POLICY_VOTES'); }
        $votes[$slug] = $previous + $count;
    }
    // Competition rank, not dense rank: 100, 90, 90, 80 -> 1, 2, 2, 4.
    $descending = $votes;
    uasort($descending, static fn(int $a, int $b): int => $b <=> $a);
    $map = $tiers = [];
    $active = 0; $rank = 0; $previous = null;
    foreach ($descending as $slug => $count) {
        if ($count === 0) { $map[$slug] = 0; $tiers[$slug] = 'base'; continue; }
        ++$active;
        if ($previous !== $count) { $rank = $active; }
        $previous = $count;
        $map[$slug] = $rank;
        $tiers[$slug] = $rank <= 3 ? 'legend' : ($rank <= 10 ? 'rising' : 'base');
    }
    ksort($votes, SORT_STRING); ksort($map, SORT_STRING); ksort($tiers, SORT_STRING);
    return ['map' => $map, 'tiers' => $tiers, 'votes' => $votes, 'active_total' => $active,
        'policy_version' => NGO_RANK_POLICY_VERSION];
}

/**
 * Return cumulative targets for ONE economic root and ONE currency. The writer
 * posts only their difference from the last target; no receipt-by-receipt floor,
 * donor averaging, double fee deduction, common-fund credit or payout happens.
 * All three values are required: omission of a liability is not evidence of zero.
 * Amounts are already 2-exponent minor units, including HUF; no legacy/FX conversion.
 */
function allocate(array $snapshot, $currency, $received_net_minor, $extra_cost_minor, $reserved_liability_minor): array
{
    $snapshot = assert_snapshot_valid($snapshot);
    if (!is_string($currency) || !in_array($currency, ['HUF', 'EUR', 'USD', 'GBP'], true)) {
        throw new PolicyViolation('SUPPORT_POLICY_CURRENCY');
    }
    $r = minor($received_net_minor); $c = minor($extra_cost_minor); $q = minor($reserved_liability_minor);
    // Subtract in stages so even C+Q greater than BIGINT cannot overflow.
    if ($c > $r || $q > $r - $c) { throw new PolicyViolation('SUPPORT_POLICY_UNFUNDED'); }
    $b = $r - $c - $q;
    $coefficient = $snapshot['rate_bps'] * $snapshot['donor_multiplier_bps'];
    // coefficient <= 87500000. Both products fit int64; only the final ratio
    // is floored. This also avoids a general big-number dependency or helper.
    $ngo = intdiv($b, 100000000) * $coefficient + intdiv(($b % 100000000) * $coefficient, 100000000);
    return ['policy_id' => $snapshot['policy_id'], 'snapshot_digest' => $snapshot['snapshot_digest'],
        'ngo_slug' => $snapshot['ngo_slug'], 'currency' => $currency, 'currency_exponent' => 2,
        'received_net_minor' => $received_net_minor, 'extra_cost_minor' => $extra_cost_minor,
        'reserved_liability_minor' => $reserved_liability_minor, 'net_base_minor' => (string)$b,
        'ngo_cumulative_target_minor' => (string)$ngo, 'sharity_cumulative_target_minor' => (string)($b - $ngo)];
}
