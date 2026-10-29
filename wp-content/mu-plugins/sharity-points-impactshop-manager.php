<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** Impact Shop event operations; the shared Offerwall points port remains unchanged. */
class Sharity_Impactshop_Points_Manager
{
    /** Reuse the current connection guard; never reconnect inside a point transaction. */
    public function impactshop_transaction(callable $operation): array
    {
        global $wpdb;
        if (!property_exists($wpdb, 'reconnect_retries')) {
            return ['success' => false, 'error' => 'transaction_unavailable'];
        }
        $previous = $wpdb->reconnect_retries;
        $started = false;
        try {
            $wpdb->reconnect_retries = 0;
            if ($wpdb->reconnect_retries !== 0) {
                throw new RuntimeException('point_connection_guard_unavailable');
            }
            $connection = (string) sharity_points_impactshop_sql($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection === '') {
                throw new RuntimeException('point_connection_unavailable');
            }
            sharity_points_impactshop_sql($wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'));
            sharity_points_impactshop_sql($wpdb->query('START TRANSACTION'));
            $started = true;
            $result = $operation();
            if ((string) sharity_points_impactshop_sql($wpdb->get_var('SELECT CONNECTION_ID()')) !== $connection) {
                throw new RuntimeException('point_connection_changed');
            }
            sharity_points_impactshop_sql($wpdb->query('COMMIT'));
            $started = false;
            if ((string) sharity_points_impactshop_sql($wpdb->get_var('SELECT CONNECTION_ID()')) !== $connection) {
                throw new RuntimeException('point_commit_unconfirmed');
            }
            return $result;
        } catch (Throwable $error) {
            if ($started) {
                try { $wpdb->query('ROLLBACK'); } catch (Throwable $ignored) {}
            }
            return ['success' => false, 'error' => 'transaction_unconfirmed'];
        } finally {
            $wpdb->reconnect_retries = $previous;
        }
    }

    public function apply_impactshop_event(int $id, string $run_id): array
    {
        return $this->impactshop_transaction(function () use ($id, $run_id): array {
            global $wpdb;
            $events = $wpdb->prefix . 'impactshop_point_changes';
            $roots = $wpdb->prefix . 'impactshop_point_settlements';
            // Resolve root before event lock, same order as capture and manual resolution.
            $key = sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare("SELECT root_id FROM {$events} WHERE id=%d", $id)));
            $root = $key ? sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare("SELECT * FROM {$roots} WHERE root_id=%s FOR UPDATE", $key), ARRAY_A)) : null;
            $row = sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare("SELECT * FROM {$events} WHERE id=%d FOR UPDATE", $id), ARRAY_A));
            if (!$row) {
                throw new RuntimeException('event_missing');
            }
            if (!in_array($row['status'], ['queued', 'retry'], true)) {
                return ['success' => true, 'duplicate' => true, 'status' => $row['status']];
            }
            $event = json_decode($row['event_json'], true, 32, JSON_THROW_ON_ERROR);
            $balance = $root && $root['subject_digest'] !== null ? sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_points WHERE impactshop_subject_digest=%s FOR UPDATE", $root['subject_digest']
            ), ARRAY_A)) : null;
            $conflicts = (int) sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$events} WHERE logical_id=%s AND payload_digest<>%s", $row['logical_id'], $row['payload_digest']
            )));
            $lanes = get_option('sharity_points_impactshop_lanes', []);
            $allowed = is_array($lanes) && in_array($event['namespace'], $lanes, true);
            if (!$root || !$balance || $conflicts > 0 || !$allowed) {
                $decision = ['status' => 'manual', 'state' => 'manual', 'delta' => 0, 'forgiven' => 0,
                    'reason' => $conflicts ? 'event_conflict' : (!$allowed ? 'lane_not_ready' : 'attribution_missing')];
            } else {
                $decision = sharity_points_impactshop_decide($root, $event, (int) $balance['points_total']);
            }
            if ($decision['status'] === 'applied' && $decision['delta'] > 0
                && !apply_filters('sharity_can_earn_points', true, $balance['pseudo_id'], 'purchase')) {
                $decision = ['status' => 'held', 'state' => $root['state'], 'delta' => 0, 'forgiven' => 0, 'reason' => 'earning_blocked'];
            }
            if ($decision['status'] === 'applied' && $decision['delta'] !== 0) {
                $delta = $decision['delta'];
                $run = $row['run_id'] ?: $run_id;
                $used = sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare(
                    "SELECT COALESCE(SUM(GREATEST(actual_delta,0)),0) AS positive,
                            COALESCE(SUM(GREATEST(-actual_delta,0)),0) AS negative FROM {$events} WHERE run_id=%s", $run
                ), ARRAY_A));
                $daily = (int) sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(GREATEST(points,0)),0) FROM {$wpdb->prefix}point_transactions
                     WHERE pseudo_id=%s AND source_type IN ('impactshop','impactshop_manual')
                     AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR)", $balance['pseudo_id']
                )));
                $limit = ($delta > 0 && ($delta > 100000 || $daily + $delta > 1000000 || (int) $used['positive'] + $delta > 1000000))
                    || ($delta < 0 && (int) $used['negative'] - $delta > 1000000)
                    || (int) $balance['points_total'] + $delta > 2147483647
                    || (int) $balance['points_lifetime'] + max(0, $delta) > 2147483647;
                if ($limit) {
                    $decision = ['status' => 'held', 'state' => $root['state'], 'delta' => 0, 'forgiven' => 0, 'reason' => 'budget_or_storage_limit'];
                }
            }
            $result = $this->impactshop_apply_decision($root, $row, $balance, $decision, $run_id);
            return ['success' => true] + $result;
        });
    }

    /** All balances here are locked. Requested and actually deducted amounts remain distinguishable. */
    private function impactshop_apply_decision(?array $root, array $row, ?array $balance, array $decision, string $run_id, ?array $resolution = null): array
    {
        global $wpdb;
        $delta = (int) $decision['delta'];
        $tx = null;
        $level = null;
        $event = json_decode($row['event_json'], true, 32, JSON_THROW_ON_ERROR);
        if ($delta !== 0) {
            if (!$balance || (int) $balance['points_total'] + $delta < 0
                || (int) $balance['points_total'] + $delta > 2147483647
                || (int) $balance['points_lifetime'] + max(0, $delta) > 2147483647) {
                throw new RuntimeException('point_total_out_of_range');
            }
            $metadata = ['root_id' => $row['root_id'], 'review_event_id' => (int) $row['id'],
                'forgiven_points' => $decision['forgiven'], 'admin' => $resolution];
            sharity_points_impactshop_sql($wpdb->insert($wpdb->prefix . 'point_transactions', [
                'pseudo_id' => $balance['pseudo_id'], 'user_id' => null, 'points' => $delta,
                'type' => $resolution ? 'admin_adjustment' : ($delta > 0 ? 'purchase' : 'purchase_reversal'),
                'source_id' => (string) $row['id'], 'source_type' => $resolution ? 'impactshop_manual' : 'impactshop',
                'dedupe_key' => 'impactshop:' . ($resolution ? 'review:' : 'event:') . $row['id'],
                'metadata' => wp_json_encode($metadata), 'created_at' => gmdate('Y-m-d H:i:s'),
            ]));
            $tx = (int) $wpdb->insert_id;
            sharity_points_impactshop_sql($wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_points SET points_total=points_total+%d,
                 points_lifetime=points_lifetime+%d, last_activity_at=%s WHERE id=%d",
                $delta, max(0, $delta), gmdate('Y-m-d H:i:s'), $balance['id']
            )));
            $level = (new Sharity_Level_Manager())->check_and_update_level_for_pseudo($balance['pseudo_id'], (int) $balance['points_total'] + $delta, false);
            if (($level['success'] ?? true) === false) {
                throw new RuntimeException('level_write_failed');
            }
        }
        $outcome = ['status' => $decision['status'], 'actual_delta' => $delta,
            'forgiven_points' => (int) $decision['forgiven'], 'transaction_id' => $tx,
            'new_total' => $balance ? (int) $balance['points_total'] + $delta : null, 'level_change' => $level];
        if ($resolution) { $outcome['resolution'] = $resolution; }
        if ($root) {
            $fields = ['state' => $decision['state'], 'version' => (int) $root['version'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')];
            if ($delta !== 0) { $fields['needs_reconciliation'] = 1; }
            if (!$resolution && $decision['status'] === 'applied' && $event['kind'] === 'award') {
                $fields += ['awarded_points' => $delta, 'purchase_transaction_id' => $tx, 'needs_reconciliation' => 1];
            }
            if (!$resolution && $decision['status'] === 'applied' && $event['kind'] === 'refund') {
                $fields += ['reversed_points' => -$delta, 'forgiven_points' => $decision['forgiven'], 'refund_transaction_id' => $tx];
            }
            sharity_points_impactshop_sql($wpdb->update($wpdb->prefix . 'impactshop_point_settlements', $fields, ['root_id' => $root['root_id']]));
            $outcome['root_version'] = $fields['version'];
        }
        $fields = ['status' => $decision['status'], 'reason' => $decision['reason'], 'actual_delta' => $delta,
            'forgiven_points' => $decision['forgiven'], 'transaction_id' => $tx,
            'run_id' => $row['run_id'] ?: $run_id, 'outcome_json' => wp_json_encode($outcome), 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($resolution) {
            $fields['resolution_digest'] = $resolution['digest'];
        }
        sharity_points_impactshop_sql($wpdb->update($wpdb->prefix . 'impactshop_point_changes', $fields, ['id' => $row['id']]));
        return $outcome;
    }

    public function resolve_impactshop_event(int $id, int $version, int $requested, string $reason, int $admin): array
    {
        if ($id < 1 || $version < 0 || $admin < 1 || trim($reason) === '' || strlen($reason) > 500
            || $requested < -2147483647 || $requested > 2147483647) {
            return ['success' => false, 'error' => 'invalid_resolution'];
        }
        $digest = hash('sha256', wp_json_encode([$id, $version, $requested, $reason, $admin]));
        return $this->impactshop_transaction(function () use ($id, $version, $requested, $reason, $admin, $digest): array {
            global $wpdb;
            $key = sharity_points_impactshop_sql($wpdb->get_var($wpdb->prepare("SELECT root_id FROM {$wpdb->prefix}impactshop_point_changes WHERE id=%d", $id)));
            $root = $key ? sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}impactshop_point_settlements WHERE root_id=%s FOR UPDATE", $key), ARRAY_A)) : null;
            $row = sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}impactshop_point_changes WHERE id=%d FOR UPDATE", $id), ARRAY_A));
            if (!$row) {
                return ['success' => false, 'error' => 'event_missing'];
            }
            if ($row['resolution_digest']) {
                return hash_equals($row['resolution_digest'], $digest)
                    ? ['success' => true, 'duplicate' => true] + json_decode($row['outcome_json'], true, 32, JSON_THROW_ON_ERROR)
                    : ['success' => false, 'error' => 'resolution_conflict'];
            }
            if (!in_array($row['status'], ['manual', 'held'], true) || ($root ? (int) $root['version'] : 0) !== $version) {
                return ['success' => false, 'error' => 'resolution_conflict'];
            }
            $balance = $root && $root['subject_digest'] !== null ? sharity_points_impactshop_sql($wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_points WHERE impactshop_subject_digest=%s FOR UPDATE", $root['subject_digest']
            ), ARRAY_A)) : null;
            if (!$balance && $requested !== 0) {
                return ['success' => false, 'error' => 'unbound_subject'];
            }
            $actual = $requested < 0 ? -min(-$requested, (int) $balance['points_total']) : $requested;
            $decision = ['status' => 'resolved', 'state' => 'manual', 'delta' => $actual,
                'forgiven' => $requested < 0 ? -$requested + $actual : 0, 'reason' => 'admin_resolved'];
            return ['success' => true] + $this->impactshop_apply_decision($root, $row, $balance, $decision, $row['run_id'] ?: str_repeat('0', 32),
                ['admin_id' => $admin, 'requested_delta' => $requested, 'reason' => $reason, 'digest' => $digest]);
        });
    }

}
