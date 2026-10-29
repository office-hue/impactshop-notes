<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Points_Manager
{
    private ?mysqli $port_connection = null;

    public function award_points_for_pseudo(string $pseudo_id, int $points, string $type, ?string $source_id = null, array $metadata = [], ?string $dedupe_key = null): array
    {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        return $this->award_checked($pseudo_id, null, $points, $type, $source_id, $metadata, $dedupe_key);
    }

    public function award_points(int $user_id, int $points, string $type, ?string $source_id = null, array $metadata = [], ?string $dedupe_key = null): array
    {
        return $this->award_checked('', $user_id, $points, $type, $source_id, $metadata, $dedupe_key);
    }

    private function award_checked(string $pseudo_id, ?int $user_id, int $points, string $type, ?string $source_id, array $metadata, ?string $dedupe_key): array
    {
        global $wpdb;
        if (($pseudo_id === '' && (int) $user_id <= 0) || $points === 0) return ['success' => false, 'error' => 'invalid_input'];
        if (!apply_filters('sharity_can_earn_points', true, $pseudo_id !== '' ? $pseudo_id : $user_id, $type)) return ['success' => false, 'error' => 'earning_blocked'];
        $dedupe = $dedupe_key ?? $this->build_dedupe_key($type, $source_id, $metadata);
        $expected = ['pseudo_id' => $pseudo_id ?: null, 'user_id' => $user_id, 'points' => $points, 'type' => $type, 'source_id' => $source_id, 'source_type' => $metadata['source_type'] ?? null, 'metadata' => $metadata];
        if ($this->port_query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED') === false || $this->port_query('START TRANSACTION') === false) return ['success' => false, 'error' => 'transaction_begin_failed'];
        try {
            $where = $pseudo_id !== '' ? $wpdb->prepare('pseudo_id = %s', $pseudo_id) : $wpdb->prepare('user_id = %d', $user_id);
            $identity = $pseudo_id !== '' ? ['user_id' => null, 'pseudo_id' => $pseudo_id] : ['user_id' => $user_id, 'pseudo_id' => null];
            $now = current_time('mysql');
            $values = $pseudo_id !== '' ? $wpdb->prepare('NULL, %s', $pseudo_id) : $wpdb->prepare('%d, NULL', $user_id);
            if ($this->port_query("INSERT INTO {$wpdb->prefix}user_points (user_id,pseudo_id,points_total,points_lifetime,points_decayed,current_level,created_at,updated_at) VALUES ({$values},0,0,0,'basic','{$now}','{$now}') ON DUPLICATE KEY UPDATE points_total=points_total") === false) throw new RuntimeException('balance_write_failed');
            $balance = $this->port_row("SELECT * FROM {$wpdb->prefix}user_points WHERE {$where} FOR UPDATE", ARRAY_A);
            if (!is_array($balance)) throw new RuntimeException('balance_read_failed');
            $existing = $this->read_point_transaction($dedupe, $pseudo_id, $user_id);
            if ($existing !== null) {
                $this->port_query('ROLLBACK');
                return $this->point_transaction_matches($existing, $expected) ? ['success' => true, 'duplicate' => true, 'transaction_id' => (int) $existing['id']] : ['success' => false, 'error' => 'dedupe_conflict'];
            }
            if (!empty($wpdb->last_error)) throw new RuntimeException('ledger_read_failed');
            $insert = array_merge($identity, ['points' => $points, 'type' => $type, 'source_id' => $source_id, 'source_type' => $metadata['source_type'] ?? null, 'dedupe_key' => $dedupe, 'metadata' => $metadata ? wp_json_encode($metadata) : null, 'created_at' => $now]);
            if ($this->port_insert($wpdb->prefix . 'point_transactions', $insert) !== 1) throw new RuntimeException('ledger_write_failed');
            $transaction_id = (int) $wpdb->insert_id;
            $new_total = max(0, (int) $balance['points_total'] + $points);
            if ($this->port_query($wpdb->prepare("UPDATE {$wpdb->prefix}user_points SET points_total=%d, points_lifetime=points_lifetime+%d, points_decayed=points_decayed+%d, last_activity_at=%s WHERE {$where}", $new_total, max(0,$points), $type === 'decay' && $points < 0 ? -$points : 0, $now)) === false) throw new RuntimeException('balance_write_failed');
            $manager = new Sharity_Level_Manager();
            $change = $pseudo_id !== '' ? $manager->check_and_update_level_for_pseudo($pseudo_id, $new_total, false) : $manager->check_and_update_level((int) $user_id, $new_total, false);
            if (!($change['success'] ?? false)) throw new RuntimeException('level_write_failed');
            $committed = $this->port_query('COMMIT') !== false;
            if (!$committed && $this->port_query('ROLLBACK') === false) return ['ok'=>false,'success'=>false,'error'=>'commit_unconfirmed'];
            $row = $this->read_point_transaction($dedupe, $pseudo_id, $user_id);
            if ($row === null || !$this->point_transaction_matches($row,$expected)) return ['success' => false, 'error' => 'commit_unconfirmed'];
            if ($committed) {
                if ($change['changed']) do_action('sharity_level_changed', $pseudo_id ?: $user_id, $change['old_level'], $change['new_level'], $new_total);
                do_action('sharity_points_earned', $user_id, $points, $type, array_merge($metadata, ['pseudo_id' => $pseudo_id ?: null, 'transaction_id' => $transaction_id, 'new_total' => $new_total]));
            }
            return ['success' => true, 'new_total' => $new_total, 'level_changed' => $change['changed'], 'level_up_to' => $change['new_level'], 'transaction_id' => (int) $row['id'], 'recovered_commit' => !$committed];
        } catch (Throwable $error) {
            $this->port_query('ROLLBACK');
            return ['success' => false, 'error' => $error->getMessage()];
        }
    }

    private function read_point_transaction(string $key, string $pseudo_id, ?int $user_id = null): ?array
    {
        global $wpdb;
        $identity = $pseudo_id !== '' ? $wpdb->prepare('pseudo_id=%s',$pseudo_id) : $wpdb->prepare('user_id=%d',$user_id);
        $row = $this->port_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}point_transactions WHERE dedupe_key=%s AND {$identity} LIMIT 1", $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private function point_transaction_matches(array $row, array $expected): bool
    {
        foreach (['pseudo_id','user_id','points','type','source_id','source_type'] as $key) {
            if ((string) ($row[$key] ?? '') !== (string) ($expected[$key] ?? '')) return false;
        }
        return (json_decode((string) ($row['metadata'] ?? ''), true) ?: []) == $expected['metadata'];
    }


    /** Backend-only API. IDs must be opaque; never pass task/provider/NGO identifiers. */
    public function apply_offerwall_for_pseudo(string $pseudo_id, int $units, string $operation_id, ?string $reverses = null): array
    {
        global $wpdb;
        $pseudo_id=$this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '' || $units === 0 || abs($units)>100 || !preg_match('/^[a-zA-Z0-9_-]{16,96}$/D',$operation_id) || ($units>0 && $reverses!==null) || ($units<0 && ($reverses===null || !preg_match('/^[a-zA-Z0-9_-]{16,96}$/D',$reverses)))) return ['ok'=>false,'success'=>false,'error'=>'invalid_port_input'];
        $can_earn=$units<=0 || apply_filters('sharity_can_earn_points',true,$pseudo_id,'offerwall');
        $hash=hash('sha256',wp_json_encode([$pseudo_id,$units,$operation_id,$reverses]));
        if ($this->port_query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED') === false || $this->port_query('START TRANSACTION') === false) return ['ok'=>false,'success'=>false,'error'=>'transaction_begin_failed'];
        $change=null;
        try {
            $now=current_time('mysql',true);
            if ($this->port_query($wpdb->prepare("INSERT INTO {$wpdb->prefix}user_points (user_id,pseudo_id,points_total,points_lifetime,points_decayed,current_level,created_at,updated_at) VALUES(NULL,%s,0,0,0,'basic',%s,%s) ON DUPLICATE KEY UPDATE points_total=points_total",$pseudo_id,$now,$now))===false) throw new RuntimeException('balance_write_failed');
            $balance=$this->port_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}user_points WHERE pseudo_id=%s FOR UPDATE",$pseudo_id),ARRAY_A);
            if (!is_array($balance)) throw new RuntimeException('balance_read_failed');
            $available=(int)$balance['points_total'];
            $existing=$this->port_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sharity_offerwall_v2_port_operations WHERE port=%s AND operation_id=%s LIMIT 1",'points',$operation_id),ARRAY_A);
            if (is_array($existing)) {
                $this->port_query('ROLLBACK');
                if (!hash_equals((string)$existing['content_hash'],$hash)) return ['ok'=>false,'success'=>false,'error'=>'dedupe_conflict'];
                $read=$this->read_offerwall_operation($pseudo_id,$operation_id);
                return $read!==null ? array_merge($read,['duplicate'=>true]) : ['ok'=>false,'success'=>false,'error'=>'ledger_readback_failed'];
            }
            if (!empty($wpdb->last_error)) throw new RuntimeException('operation_read_failed');
            if (!$can_earn) throw new RuntimeException('earning_blocked');
            $latest=$this->port_row($wpdb->prepare("SELECT debt_units FROM {$wpdb->prefix}sharity_offerwall_v2_port_operations WHERE port=%s AND pseudo_id=%s ORDER BY id DESC LIMIT 1",'points',$pseudo_id),ARRAY_A);
            if (!empty($wpdb->last_error)) throw new RuntimeException('debt_read_failed');
            $debt=(int)($latest['debt_units'] ?? 0);
            if ($debt < 0 || $available < 0) throw new RuntimeException('balance_invariant_failed');
            if ($units<0) {
                $original=$this->read_offerwall_operation($pseudo_id,(string)$reverses);
                if ($original===null || (int)$original['requested_units']!==-$units) throw new RuntimeException('invalid_reversal');
                $already=$this->port_row($wpdb->prepare("SELECT operation_id FROM {$wpdb->prefix}sharity_offerwall_v2_port_operations WHERE port=%s AND reverses=%s LIMIT 1",'points',$reverses),ARRAY_A);
                if (!empty($wpdb->last_error)) throw new RuntimeException('reversal_read_failed');
                if (is_array($already)) throw new RuntimeException('already_reversed');
                $applied=-min(-$units,$available);
                $debt+=(-$units)-(-$applied);
            } else {
                $settled=min($units,$debt);
                $applied=$units-$settled;
                $debt-=$settled;
            }
            $operation=['port'=>'points','operation_id'=>$operation_id,'pseudo_id'=>$pseudo_id,'requested_units'=>$units,'applied_units'=>$applied,'debt_units'=>$debt,'content_hash'=>$hash,'reverses'=>$reverses,'created_at'=>current_time('mysql',true)];
            $metadata=['operation_id'=>$operation_id,'requested_units'=>$units,'applied_units'=>$applied,'debt_units'=>$debt];
            $main_delta=$units>0 ? $units : $applied;
            $entries=[['main',$main_delta,'offerwall']];
            if ($units>0 && $applied<$units) $entries[]=['debt',-($units-$applied),'admin_adjustment'];
            foreach ($entries as [$suffix,$delta,$type]) {
                if ($this->port_insert($wpdb->prefix.'point_transactions',['user_id'=>null,'pseudo_id'=>$pseudo_id,'points'=>$delta,'type'=>$type,'source_type'=>'offerwall_v2','source_id'=>$operation_id,'dedupe_key'=>'owv2:points:'.$operation_id.':'.$suffix,'metadata'=>wp_json_encode($metadata),'created_at'=>$now])!==1) throw new RuntimeException('ledger_write_failed');
            }
            $new_total=$available+$applied;
            if ($new_total<0 || $this->port_query($wpdb->prepare("UPDATE {$wpdb->prefix}user_points SET points_total=%d,points_lifetime=points_lifetime+%d,last_activity_at=%s WHERE pseudo_id=%s",$new_total,max(0,$units),$now,$pseudo_id))===false) throw new RuntimeException('balance_write_failed');
            $change=(new Sharity_Level_Manager())->check_and_update_level_for_pseudo($pseudo_id,$new_total,false);
            if (!($change['success'] ?? false)) throw new RuntimeException('level_write_failed');
            if ($this->port_insert($wpdb->prefix.'sharity_offerwall_v2_port_operations',$operation)!==1) throw new RuntimeException('operation_write_failed');
            $committed=$this->port_query('COMMIT')!==false;
            if (!$committed && $this->port_query('ROLLBACK') === false) return ['ok'=>false,'success'=>false,'error'=>'commit_unconfirmed'];
            $read=$this->read_offerwall_operation($pseudo_id,$operation_id);
            if ($read===null) return ['ok'=>false,'success'=>false,'error'=>'commit_unconfirmed'];
            if ($committed) {
                if ($change['changed']) do_action('sharity_level_changed',$pseudo_id,$change['old_level'],$change['new_level'],$new_total);
                do_action('sharity_points_earned',null,$applied,'offerwall_v2',['pseudo_id'=>$pseudo_id,'operation_id'=>$operation_id,'new_total'=>$new_total]);
            }
            return array_merge($read,['duplicate'=>false,'recovered_commit'=>!$committed]);
        } catch (Throwable $error) {
            $this->port_query('ROLLBACK');
            return ['ok'=>false,'success'=>false,'error'=>$error->getMessage()];
        }
    }

    /** Verifies the immutable operation AND each real canonical ledger entry. */
    public function read_offerwall_operation(string $pseudo_id,string $operation_id): ?array
    {
        global $wpdb;
        $pseudo_id=$this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id==='') return null;
        $row=$this->port_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sharity_offerwall_v2_port_operations WHERE port=%s AND operation_id=%s AND pseudo_id=%s LIMIT 1",'points',$operation_id,$pseudo_id),ARRAY_A);
        if (!is_array($row)) return null;
        $units=(int)$row['requested_units']; $applied=(int)$row['applied_units'];
        $reverses=$row['reverses']===null ? null : (string)$row['reverses'];
        if (!hash_equals((string)$row['content_hash'],hash('sha256',wp_json_encode([$pseudo_id,$units,$operation_id,$reverses])))) return null;
        if ($units===0 || abs($units)>100 || (int)$row['debt_units']<0 || ($units>0 && ($applied<0 || $applied>$units)) || ($units<0 && ($applied>0 || $applied<$units))) return null;
        $metadata=['operation_id'=>$operation_id,'requested_units'=>$units,'applied_units'=>$applied,'debt_units'=>(int)$row['debt_units']];
        $main_delta=$units>0 ? $units : $applied;
        $main=$this->read_point_transaction('owv2:points:'.$operation_id.':main',$pseudo_id);
        if ($main===null || !$this->point_transaction_matches($main,['pseudo_id'=>$pseudo_id,'user_id'=>null,'points'=>$main_delta,'type'=>'offerwall','source_id'=>$operation_id,'source_type'=>'offerwall_v2','metadata'=>$metadata])) return null;
        $ids=[(int)$main['id']];
        if ($units>0 && $applied<$units) {
            $settlement=$this->read_point_transaction('owv2:points:'.$operation_id.':debt',$pseudo_id);
            if ($settlement===null || !$this->point_transaction_matches($settlement,['pseudo_id'=>$pseudo_id,'user_id'=>null,'points'=>-($units-$applied),'type'=>'admin_adjustment','source_id'=>$operation_id,'source_type'=>'offerwall_v2','metadata'=>$metadata])) return null;
            $ids[]=(int)$settlement['id'];
        }
        return ['ok'=>true,'success'=>true,'operation_id'=>$operation_id,'requested_units'=>$units,'applied_units'=>$applied,'debt_units'=>(int)$row['debt_units'],'ledger_dedupe_key'=>'owv2:points:'.$operation_id.':main','ledger_transaction_ids'=>$ids];
    }

    public function get_points_snapshot(int $user_id): array
    {
        global $wpdb;
        $row = $this->port_row($wpdb->prepare(
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
        $row = $this->port_row($wpdb->prepare(
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
        $exists = (int) $this->port_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        if ($exists > 0) {
            return;
        }

        $this->port_insert("{$wpdb->prefix}user_points", [
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
        $exists = (int) $this->port_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        if ($exists > 0) {
            return;
        }

        $this->port_insert("{$wpdb->prefix}user_points", [
            'user_id' => null,
            'pseudo_id' => $pseudo_id,
            'points_total' => 0,
            'points_lifetime' => 0,
            'points_decayed' => 0,
            'current_level' => 'basic',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
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
    /** Use the existing mysqli handle directly: wpdb must not reconnect/replay a transaction write. */
    private function port_query(string $sql)
    {
        global $wpdb;
        if ($sql === 'START TRANSACTION' && ($wpdb->dbh ?? null) instanceof mysqli) $this->port_connection=$wpdb->dbh;
        $connection=$this->port_connection ?? ($wpdb->dbh ?? null);
        if (!$connection instanceof mysqli) { $wpdb->last_error='mysqli_connection_required'; return false; }
        try {
            $result=$connection->query($sql);
            $wpdb->last_error=$result===false ? ($connection->error ?: 'sql_failed') : '';
            $wpdb->insert_id=(int)$connection->insert_id;
            if ($result !== false && ($sql === 'COMMIT' || $sql === 'ROLLBACK')) $this->port_connection=null;
            if ($result === false && $sql === 'ROLLBACK') {
                // A later START on this shared connection must not commit the failed transaction.
                try { $connection->close(); } catch (Throwable $ignored) {}
                $this->port_connection=null;
            }
            return $result instanceof mysqli_result ? $result : ($result===false ? false : $connection->affected_rows);
        } catch (Throwable $error) {
            if ($sql === 'ROLLBACK') {
                try { $connection->close(); } catch (Throwable $ignored) {}
                $this->port_connection=null;
            }
            $wpdb->last_error='connection_unavailable';
            return false;
        }
    }

    private function port_row(string $sql,$format=null): ?array
    {
        $result=$this->port_query($sql);
        if (!$result instanceof mysqli_result) return null;
        $row=$result->fetch_assoc(); $result->free();
        return is_array($row) ? $row : null;
    }

    private function port_var(string $sql)
    {
        $result=$this->port_query($sql);
        if (!$result instanceof mysqli_result) return null;
        $row=$result->fetch_row(); $result->free();
        return $row[0] ?? null;
    }

    private function port_insert(string $table,array $data)
    {
        global $wpdb;
        $values=[];
        foreach ($data as $value) $values[]=$value===null ? 'NULL' : $wpdb->prepare('%s',$value);
        return $this->port_query('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($data)).'`) VALUES ('.implode(',',$values).')');
    }

    private function port_update(string $table,array $data,array $where)
    {
        global $wpdb; $sets=[]; $conditions=[];
        foreach ($data as $key=>$value) $sets[]='`'.$key.'`='.($value===null ? 'NULL' : $wpdb->prepare('%s',$value));
        foreach ($where as $key=>$value) $conditions[]='`'.$key.'`='.$wpdb->prepare('%s',$value);
        return $this->port_query('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE '.implode(' AND ',$conditions));
    }

}
