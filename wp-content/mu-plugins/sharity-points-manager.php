<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Points_Manager
{
    public function award_points_for_pseudo(
        string $pseudo_id,
        int $points,
        string $type,
        ?string $source_id = null,
        array $metadata = [],
        ?string $dedupe_key = null
    ): array {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '' || $points === 0) {
            return ['success' => false, 'error' => 'invalid_input'];
        }

        if (!apply_filters('sharity_can_earn_points', true, $pseudo_id, $type)) {
            return ['success' => false, 'error' => 'earning_blocked'];
        }

        global $wpdb;

        $dedupe = $dedupe_key ?? $this->build_dedupe_key($type, $source_id, $metadata);
        $metadata_json = !empty($metadata) ? wp_json_encode($metadata) : null;
        // wpdb's documented magic accessors expose reconnect_retries. A drop-in
        // that cannot disable reconnect must fail closed before any point write.
        if (!property_exists($wpdb, 'reconnect_retries')) {
            return ['success' => false, 'error' => 'transaction_unavailable'];
        }
        $previous_retries = $wpdb->reconnect_retries;
        $checked = static function ($result) use ($wpdb) {
            if ($result === false || $wpdb->last_error !== '') {
                throw new RuntimeException('point_storage_failure');
            }
            return $result;
        };
        $started = false;
        try {
            $wpdb->reconnect_retries = 0;
            if ($wpdb->reconnect_retries !== 0) {
                throw new RuntimeException('point_connection_guard_unavailable');
            }
            $connection = (string) $checked($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection === '') {
                throw new RuntimeException('point_connection_unavailable');
            }
            $checked($wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'));
            $checked($wpdb->query('START TRANSACTION'));
            $started = true;
            $this->ensure_pseudo_row($pseudo_id);

            $inserted = $checked($wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}point_transactions
                (user_id, pseudo_id, points, type, source_id, source_type, dedupe_key, metadata, created_at)
             VALUES (NULL, %s, %d, %s, %s, %s, %s, %s, %s)",
            $pseudo_id,
            $points,
            $type,
            $source_id,
            $metadata['source_type'] ?? null,
            $dedupe,
            $metadata_json,
            current_time('mysql')
            )));

            if ($inserted === 0) {
                $existing_id = $checked($wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}point_transactions WHERE pseudo_id = %s AND dedupe_key = %s LIMIT 1",
                    $pseudo_id,
                    $dedupe
                )));
                if ($existing_id === null || (int) $existing_id <= 0) {
                    throw new RuntimeException('point_duplicate_unconfirmed');
                }
                $checked($wpdb->query('ROLLBACK'));
                $started = false;
                return ['success' => true, 'duplicate' => true, 'transaction_id' => (int) $existing_id];
            }
            if ($inserted !== 1) {
                throw new RuntimeException('point_insert_unconfirmed');
            }

            $transaction_id = (int) $wpdb->insert_id;

            $updated = $checked($wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_points
                 SET points_total = GREATEST(points_total + %d, 0),
                     points_lifetime = points_lifetime + %d,
                     points_decayed = points_decayed + %d,
                     last_activity_at = %s
                 WHERE pseudo_id = %s",
                $points,
                $points > 0 ? $points : 0,
                $type === 'decay' && $points < 0 ? abs($points) : 0,
                current_time('mysql'),
                $pseudo_id
            )
            ));
            if (!in_array($updated, [0, 1], true)) {
                throw new RuntimeException('point_total_unconfirmed');
            }

            $new_total_result = $checked($wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
            )));
            if ($new_total_result === null) {
                throw new RuntimeException('point_total_missing');
            }
            $new_total = (int) $new_total_result;

            $level_manager = new Sharity_Level_Manager();
            $level_change = $level_manager->check_and_update_level_for_pseudo($pseudo_id, $new_total, false);

            if ((string) $checked($wpdb->get_var('SELECT CONNECTION_ID()')) !== $connection) {
                throw new RuntimeException('point_connection_changed');
            }
            $checked($wpdb->query('COMMIT'));
            $started = false;
            if ((string) $checked($wpdb->get_var('SELECT CONNECTION_ID()')) !== $connection) {
                throw new RuntimeException('point_commit_unconfirmed');
            }
        } catch (Throwable $error) {
            if ($started) {
                try {
                    $wpdb->query('ROLLBACK');
                } catch (Throwable $ignored) {
                    // The outcome remains unknown; never reconnect or claim success.
                }
            }
            return ['success' => false, 'error' => 'transaction_unconfirmed'];
        } finally {
            $wpdb->reconnect_retries = $previous_retries;
        }

        if ($level_change['changed']) {
            do_action('sharity_level_changed', $pseudo_id, $level_change['old_level'], $level_change['new_level'], $new_total);
        }

        do_action(
            'sharity_points_earned',
            null,
            $points,
            $type,
            array_merge($metadata, [
                'pseudo_id' => $pseudo_id,
                'transaction_id' => $transaction_id,
                'new_total' => $new_total,
            ])
        );

        return [
            'success' => true,
            'new_total' => $new_total,
            'level_changed' => $level_change['changed'],
            'level_up_to' => $level_change['new_level'],
            'transaction_id' => $transaction_id,
        ];
    }

    public function award_points(
        int $user_id,
        int $points,
        string $type,
        ?string $source_id = null,
        array $metadata = [],
        ?string $dedupe_key = null
    ): array {
        if ($user_id <= 0 || $points === 0) {
            return ['success' => false, 'error' => 'invalid_input'];
        }

        if (!apply_filters('sharity_can_earn_points', true, $user_id, $type)) {
            return ['success' => false, 'error' => 'earning_blocked'];
        }

        global $wpdb;

        $this->ensure_user_row($user_id);

        $dedupe = $dedupe_key ?? $this->build_dedupe_key($type, $source_id, $metadata);
        $metadata_json = !empty($metadata) ? wp_json_encode($metadata) : null;

        $wpdb->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $wpdb->query('START TRANSACTION');

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->prefix}point_transactions
                (user_id, points, type, source_id, source_type, dedupe_key, metadata, created_at)
             VALUES (%d, %d, %s, %s, %s, %s, %s, %s)",
            $user_id,
            $points,
            $type,
            $source_id,
            $metadata['source_type'] ?? null,
            $dedupe,
            $metadata_json,
            current_time('mysql')
        ));

        if ($inserted === 0) {
            $wpdb->query('ROLLBACK');
            return ['success' => true, 'duplicate' => true, 'transaction_id' => null];
        }

        $transaction_id = (int) $wpdb->insert_id;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}user_points
                 SET points_total = GREATEST(points_total + %d, 0),
                     points_lifetime = points_lifetime + %d,
                     points_decayed = points_decayed + %d,
                     last_activity_at = %s
                 WHERE user_id = %d",
                $points,
                $points > 0 ? $points : 0,
                $type === 'decay' && $points < 0 ? abs($points) : 0,
                current_time('mysql'),
                $user_id
            )
        );

        $new_total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        $level_manager = new Sharity_Level_Manager();
        $level_change = $level_manager->check_and_update_level($user_id, $new_total);

        $wpdb->query('COMMIT');

        do_action(
            'sharity_points_earned',
            $user_id,
            $points,
            $type,
            array_merge($metadata, [
                'transaction_id' => $transaction_id,
                'new_total' => $new_total,
            ])
        );

        return [
            'success' => true,
            'new_total' => $new_total,
            'level_changed' => $level_change['changed'],
            'level_up_to' => $level_change['new_level'],
            'transaction_id' => $transaction_id,
        ];
    }

    public function get_points_snapshot(int $user_id): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$row) {
            return [];
        }

        return $row;
    }

    public function get_points_snapshot_for_pseudo(string $pseudo_id): array
    {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '') {
            return [];
        }

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ), ARRAY_A);

        if (!$row) {
            return [];
        }

        return $row;
    }

    private function ensure_user_row(int $user_id): void
    {
        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        if ($exists > 0) {
            return;
        }

        $wpdb->insert("{$wpdb->prefix}user_points", [
            'user_id' => $user_id,
            'pseudo_id' => null,
            'points_total' => 0,
            'points_lifetime' => 0,
            'points_decayed' => 0,
            'current_level' => 'basic',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
    }

    private function ensure_pseudo_row(string $pseudo_id): void
    {
        global $wpdb;
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('point_user_read_failed');
        }

        if ($exists > 0) {
            return;
        }

        $inserted = $wpdb->insert("{$wpdb->prefix}user_points", [
            'user_id' => null,
            'pseudo_id' => $pseudo_id,
            'points_total' => 0,
            'points_lifetime' => 0,
            'points_decayed' => 0,
            'current_level' => 'basic',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        if ($inserted === false || $wpdb->last_error !== '') {
            throw new RuntimeException('point_user_insert_failed');
        }
    }

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

    private function build_dedupe_key(string $type, ?string $source_id, array $metadata): string
    {
        if (!empty($metadata['dedupe_key'])) {
            return (string) $metadata['dedupe_key'];
        }

        $parts = [$type];
        if ($source_id) {
            $parts[] = $source_id;
        }
        if (!empty($metadata['date'])) {
            $parts[] = (string) $metadata['date'];
        }

        return implode(':', $parts);
    }

    private function normalize_pseudo_id(string $pseudo_id): string
    {
        if (function_exists('sharity_normalize_pseudo_id')) {
            return sharity_normalize_pseudo_id($pseudo_id);
        }

        $pseudo_id = strtolower(sanitize_text_field($pseudo_id));
        if ($pseudo_id === '') {
            return '';
        }

        if (function_exists('impactshop_identity_profile_valid_pseudo')) {
            return impactshop_identity_profile_valid_pseudo($pseudo_id) ? $pseudo_id : '';
        }

        return preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id) ? $pseudo_id : '';
    }
}
