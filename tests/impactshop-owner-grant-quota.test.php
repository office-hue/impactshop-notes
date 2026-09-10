<?php

declare(strict_types=1);

define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);

$GLOBALS['test_now'] = 1760000000;
$GLOBALS['impactshop_safe_disabled'] = false;

function is_ssl(): bool { return true; }
function wp_salt(string $scheme = ''): string { return 'quota-test-salt-' . $scheme; }
function impactshop_identity_grant_storage_ready(): bool { return true; }
function impactshop_identity_owner_grant_table(): string { return 'wp_impactshop_identity_device_grants'; }
function impactshop_identity_utc_now(): int { return (int) $GLOBALS['test_now']; }
function impactshop_identity_utc_sql(?int $timestamp = null): string { return gmdate('Y-m-d H:i:s', $timestamp ?? impactshop_identity_utc_now()); }
function impactshop_identity_mark_safe_disabled(): void { $GLOBALS['impactshop_safe_disabled'] = true; }
function impactshop_identity_owner_hash(string $token): string { return hash_hmac('sha256', $token, wp_salt('impactshop_identity_owner')); }
function impactshop_identity_owner_pseudo_hash(string $pseudo_id): string { return hash_hmac('sha256', strtolower($pseudo_id), wp_salt('impactshop_identity_owner_pseudo')); }

final class QuotaTestWpdb
{
    public array $rows = [];
    public string $last_error = '';
    public int $commits = 0;
    public int $rollbacks = 0;
    public bool $fail_prune = false;

    public function prepare(string $query, ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sd]/', $replacement, $query, 1);
        }
        return $query;
    }

    public function query(string $query)
    {
        $this->last_error = '';
        if ($query === 'START TRANSACTION') {
            return true;
        }
        if ($query === 'COMMIT') {
            $this->commits++;
            return true;
        }
        if ($query === 'ROLLBACK') {
            $this->rollbacks++;
            return true;
        }
        if (str_starts_with($query, 'DELETE FROM')) {
            if ($this->fail_prune) {
                $this->last_error = 'prune failed';
                return false;
            }
            $cutoff = strtotime((string) preg_replace("/.*expires_at <= '([^']+)'.*/s", '$1', $query));
            $deleted = 0;
            foreach ($this->rows as $key => $row) {
                if ($deleted >= 64) {
                    break;
                }
                if (($row['state'] ?? '') === 'pending'
                    && strtotime((string) ($row['expires_at'] ?? '')) <= $cutoff) {
                    unset($this->rows[$key]);
                    $deleted++;
                }
            }
            $this->rows = array_values($this->rows);
            return $deleted;
        }
        throw new RuntimeException('unexpected SQL query: ' . $query);
    }

    public function get_results(string $query, $output): array
    {
        $this->last_error = '';
        $now = impactshop_identity_utc_now();
        $rows = array_values(array_filter($this->rows, static function (array $row) use ($now): bool {
            return ($row['state'] ?? '') === 'pending'
                && empty($row['revoked_at'])
                && strtotime((string) ($row['expires_at'] ?? '')) > $now;
        }));
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) $left['expires_at'], (string) $right['expires_at']);
        });
        return array_map(static fn(array $row): array => ['grant_hash' => $row['grant_hash']], array_slice($rows, 0, 257));
    }

    public function insert(string $table, array $row, array $formats): int
    {
        $this->last_error = '';
        $this->rows[] = $row;
        return 1;
    }

    public function get_var(string $query)
    {
        $this->last_error = '';
        preg_match("/grant_hash = '([a-f0-9]{64})'/", $query, $match);
        $hash = (string) ($match[1] ?? '');
        $count = 0;
        foreach ($this->rows as $row) {
            if (($row['grant_hash'] ?? '') === $hash
                && ($row['state'] ?? '') === 'pending') {
                $count++;
            }
        }
        return $count;
    }
}

$wpdb = new QuotaTestWpdb();
$GLOBALS['wpdb'] = $wpdb;

$identity_source = file_get_contents(dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-panel.php');
$start = strpos($identity_source, 'function impactshop_identity_owner_issue');
$end = strpos($identity_source, 'function impactshop_identity_owner_set_pending_cookie', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('owner issue function boundaries not found');
}
eval(substr($identity_source, $start, $end - $start));

function quota_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

quota_assert(str_contains($identity_source, "state = 'pending' AND expires_at <= %s ORDER BY expires_at ASC LIMIT 64"), 'prune must use the indexed bounded predicate');
quota_assert(str_contains($identity_source, 'LIMIT 257 FOR UPDATE'), 'quota must lock a bounded candidate set for concurrency');
quota_assert(str_contains($identity_source, 'impactshop_identity_owner_issue($pseudo_id, $supersedes_grant_hash, false)'), 'recovery must explicitly bypass only the public quota');

function quota_row(string $state, int $expires_at, ?string $revoked_at = null): array
{
    return [
        'grant_hash' => hash('sha256', $state . ':' . $expires_at . ':' . random_int(1, PHP_INT_MAX)),
        'pseudo_hash' => 'pseudo',
        'created_at' => gmdate('Y-m-d H:i:s', $expires_at - 60),
        'last_seen_at' => gmdate('Y-m-d H:i:s', $expires_at - 60),
        'expires_at' => gmdate('Y-m-d H:i:s', $expires_at),
        'revoked_at' => $revoked_at,
        'state' => $state,
    ];
}

$now = impactshop_identity_utc_now();
$wpdb->rows = [];
for ($i = 0; $i < 70; $i++) {
    $wpdb->rows[] = quota_row('pending', $now - 100 - $i);
}
$wpdb->rows[] = quota_row('active', $now - 100);
$wpdb->rows[] = quota_row('pending', $now + 600);
quota_assert(impactshop_identity_owner_issue('abcdefghij12', '', true) === true, 'normal public issuance should pass below quota');
$expired_pending = 0;
$active_rows = 0;
$future_pending = 0;
foreach ($wpdb->rows as $row) {
    if (($row['state'] ?? '') === 'pending' && strtotime((string) $row['expires_at']) <= $now) {
        $expired_pending++;
    }
    if (($row['state'] ?? '') === 'active') {
        $active_rows++;
    }
    if (($row['state'] ?? '') === 'pending' && strtotime((string) $row['expires_at']) > $now) {
        $future_pending++;
    }
}
quota_assert($expired_pending === 6, 'prune must be bounded to 64 expired pending rows');
quota_assert($active_rows === 1, 'active rows must be preserved by prune');
quota_assert($future_pending === 2, 'nonexpired pending rows plus the new row must be preserved');

$wpdb->rows = [quota_row('pending', $now - 100)];
for ($i = 0; $i < 256; $i++) {
    $wpdb->rows[] = quota_row('pending', $now + 600 + $i);
}
unset($GLOBALS['impactshop_pending_owner_token'], $GLOBALS['impactshop_owner_issued_this_request']);
$commits_before_quota = $wpdb->commits;
quota_assert(impactshop_identity_owner_issue('abcdefghij12', '', true) === false, 'public quota must refuse at 256');
quota_assert(count($wpdb->rows) === 256, 'quota refusal must prune expired rows but insert no new row');
quota_assert($wpdb->commits === $commits_before_quota + 1, 'successful quota pruning must commit');
quota_assert(!isset($GLOBALS['impactshop_pending_owner_token']), 'quota refusal must queue no pending token');
quota_assert(!isset($GLOBALS['impactshop_owner_issued_this_request']), 'quota refusal must set no issued marker');

// Verified recovery bypasses the public quota but still uses the same prune
// transaction and retains supersession binding.
$wpdb->rows = [];
for ($i = 0; $i < 256; $i++) {
    $wpdb->rows[] = quota_row('pending', $now + 600 + $i);
}
quota_assert(impactshop_identity_owner_issue('abcdefghij12', str_repeat('a', 64), false) === true, 'verified recovery may bypass public quota');
quota_assert(count($wpdb->rows) === 257, 'recovery bypass should insert one superseding pending row');

$wpdb->rows = [quota_row('pending', $now - 100)];
$wpdb->fail_prune = true;
unset($GLOBALS['impactshop_pending_owner_token'], $GLOBALS['impactshop_owner_issued_this_request']);
quota_assert(impactshop_identity_owner_issue('abcdefghij12', '', true) === false, 'prune DB failure must fail closed');
quota_assert($wpdb->rollbacks > 0, 'prune DB failure must rollback');
quota_assert($GLOBALS['impactshop_safe_disabled'] === true, 'prune DB failure must safe-disable');
quota_assert(!isset($GLOBALS['impactshop_pending_owner_token']), 'prune DB failure must queue no token');

echo "impactshop owner-grant quota/prune behavior: PASS\n";
