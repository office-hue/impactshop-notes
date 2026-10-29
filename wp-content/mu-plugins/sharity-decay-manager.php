<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canonical decay manager, imported from the live/Notes-identical source
 * SHA256 9aee5b159b0878a243e522ec3f7fb49d9f41906eb767c9261d25554418b53442.
 * Policy arithmetic is preserved; all balance/ledger writes now share the
 * latest locked snapshot on the existing connection, without wpdb replay.
 */
class Sharity_Decay_Manager
{
    public const GRACE_PERIOD_DAYS = 5;
    public const DECAY_RATES = [
        6 => 0.005,
        15 => 0.010,
        31 => 0.015,
        61 => 0.020,
    ];

    public function calculate_and_apply_decay(int $user_id): int
    {
        return $this->apply_checked('user_id', $user_id);
    }

    public function calculate_and_apply_decay_for_pseudo(string $pseudo_id): int
    {
        return $this->apply_checked('pseudo_id', $pseudo_id);
    }

    /** @param int|string $identity */
    private function apply_checked(string $field, $identity): int
    {
        global $wpdb;
        $db = $wpdb->dbh;
        $started = false;
        $committing = false;
        try {
            if (!($db instanceof mysqli) || !preg_match('/\A[A-Za-z0-9_]+\z/D', $wpdb->prefix)) {
                throw new RuntimeException('decay_storage_unavailable');
            }
            // wpdb::query may reconnect/replay a failed statement; transactions
            // deliberately use this already-open mysqli connection directly.
            $this->checked_query($db, 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $this->checked_query($db, 'START TRANSACTION');
            $started = true;
            $where = $field === 'user_id' ? 'user_id = %d' : 'pseudo_id = %s';
            $result = $this->checked_query($db, $wpdb->prepare(
                "SELECT points_total, last_activity_at, level_locked_until, freeze_until, last_decay_check_at
                 FROM {$wpdb->prefix}user_points WHERE {$where} FOR UPDATE",
                $identity
            ));
            if (!($result instanceof mysqli_result)) {
                throw new RuntimeException('decay_snapshot_unavailable');
            }
            $row = $result->fetch_object();
            $result->free();
            $calculation = $row ? $this->calculate($row) : null;
            if ($calculation === null) {
                $started = false;
                if (!$this->rollback_or_close($db)) {
                    throw new RuntimeException('decay_transaction_failed');
                }
                return 0;
            }
            [$points_before, $points_after, $decay_amount, $days_inactive, $rate] = $calculation;
            $now = current_time('mysql');
            $this->checked_query($db, $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_points
                 SET points_total = %d,
                     points_decayed = points_decayed + %d,
                     last_decay_check_at = %s
                 WHERE {$where}",
                $points_after, $decay_amount, $now, $identity
            ));
            if ($db->affected_rows !== 1) {
                throw new RuntimeException('decay_balance_write_failed');
            }

            $log = [
                'user_id' => $field === 'user_id' ? $identity : null,
                'points_before' => $points_before,
                'points_after' => $points_after,
                'decay_amount' => $decay_amount,
                'decay_percentage' => $rate * 100,
                'days_inactive' => $days_inactive,
                'last_activity_date' => $row->last_activity_at,
                'applied' => 1,
                'created_at' => $now,
            ];
            $ledger = [
                'user_id' => $field === 'user_id' ? $identity : null,
                'points' => -$decay_amount,
                'type' => 'decay',
                'dedupe_key' => 'decay:' . gmdate('Y-m-d'),
                'metadata' => wp_json_encode(['days_inactive' => $days_inactive, 'rate' => $rate]),
                'created_at' => $now,
            ];
            if ($field === 'pseudo_id') {
                $log['pseudo_id'] = $identity;
                $ledger['pseudo_id'] = $identity;
            }
            $this->checked_insert($db, $wpdb->prefix . 'decay_logs', $log);
            $this->checked_insert($db, $wpdb->prefix . 'point_transactions', $ledger);
            $committing = true;
            $this->checked_query($db, 'COMMIT');
        } catch (Throwable $error) {
            if ($started && $db instanceof mysqli) {
                $this->rollback_or_close($db);
            }
            // No success hook on SQL errors or a lost COMMIT response. A retry
            // locks and reads last_decay_check_at before any further deduction.
            do_action('sharity_points_error', 'decay_failed', [
                $field => $identity,
                'error' => $committing ? 'decay_commit_unconfirmed_readback_required' : 'decay_transaction_failed',
            ]);
            return 0;
        }

        do_action('sharity_points_decayed', $identity, $decay_amount, $days_inactive);
        return $decay_amount;
    }

    /** Unchanged eligibility, rates, rounding, caps and floor from the canonical source. */
    private function calculate(object $row): ?array
    {
        if (!$row->last_activity_at) {
            return null;
        }
        if ($row->level_locked_until && strtotime($row->level_locked_until) > time()) {
            return null;
        }
        if ($row->freeze_until && strtotime($row->freeze_until) > time()) {
            return null;
        }
        $now = (int) current_time('timestamp');
        $days_inactive = (int) floor(($now - strtotime($row->last_activity_at)) / 86400);
        if ($days_inactive <= self::GRACE_PERIOD_DAYS) {
            return null;
        }
        if (!empty($row->last_decay_check_at)) {
            $last_decay_day = date('Y-m-d', strtotime((string) $row->last_decay_check_at));
            if ($last_decay_day === current_time('Y-m-d')) {
                return null;
            }
        }
        $rates = $this->get_decay_rates();
        $rate = 0.0;
        foreach ($rates as $threshold => $threshold_rate) {
            if ($days_inactive >= $threshold) {
                $rate = $threshold_rate;
            }
        }
        if ($rate <= 0) {
            return null;
        }
        $points_before = (int) $row->points_total;
        $decay_amount = (int) round($points_before * $rate);
        $max_decay_abs = (int) apply_filters('sharity_decay_max_amount', 10000);
        $max_decay_percent = (float) apply_filters('sharity_decay_max_percent', 0.10);
        $max_decay = $max_decay_abs > 0 ? $max_decay_abs : PHP_INT_MAX;
        if ($max_decay_percent > 0) {
            $max_decay = min($max_decay, (int) floor($points_before * $max_decay_percent));
        }
        if ($max_decay > 0) {
            $decay_amount = min($decay_amount, $max_decay);
        }
        if ($decay_amount <= 0) {
            return null;
        }
        $floor_points = (int) apply_filters('sharity_decay_floor_points', 100);
        if ($floor_points > 0 && $points_before > $floor_points) {
            $points_after = max($floor_points, $points_before - $decay_amount);
        } else {
            $points_after = max(0, $points_before - $decay_amount);
        }
        $decay_amount = max(0, $points_before - $points_after);
        return $decay_amount > 0 ? [$points_before, $points_after, $decay_amount, $days_inactive, $rate] : null;
    }

    /** @return mysqli_result|bool */
    private function checked_query(mysqli $db, string $sql)
    {
        $result = $db->query($sql);
        if ($result === false) {
            throw new RuntimeException('decay_query_failed');
        }
        return $result;
    }

    private function rollback_or_close(mysqli $db): bool
    {
        try {
            $this->checked_query($db, 'ROLLBACK');
            return true;
        } catch (Throwable $error) {
            // Prevent a future START on this shared handle from implicitly
            // committing unfinished balance/log/ledger writes.
            try { $db->close(); } catch (Throwable $ignored) {}
            return false;
        }
    }

    /** @param array<string, mixed> $data */
    private function checked_insert(mysqli $db, string $table, array $data): void
    {
        global $wpdb;
        $columns = implode(',', array_keys($data));
        $values = [];
        foreach ($data as $value) {
            $values[] = $value === null ? 'NULL' : $wpdb->prepare(is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s'), $value);
        }
        $this->checked_query($db, 'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . implode(',', $values) . ')');
        if ($db->affected_rows !== 1) {
            throw new RuntimeException('decay_ledger_write_failed');
        }
    }

    private function get_decay_rates(): array
    {
        $rates = apply_filters('sharity_points_decay_rates', self::DECAY_RATES);
        if (!is_array($rates) || $rates === []) {
            return self::DECAY_RATES;
        }
        $clean = [];
        foreach ($rates as $threshold => $rate) {
            $threshold = (int) $threshold;
            $rate = (float) $rate;
            if ($threshold <= 0 || $rate <= 0) {
                continue;
            }
            $clean[$threshold] = $rate;
        }
        if ($clean === []) {
            return self::DECAY_RATES;
        }
        ksort($clean);
        return $clean;
    }
}
