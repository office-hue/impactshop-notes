<?php
/**
 * WHAT: Egyszerű approval/reject UI az impact_ledger tételekhez, audit loggal.
 * WHY: Manual approval workflow alapjai (státuszváltás + indok).
 * HOW: Tools > Impact Ledger Approval, nonce-olt action linkek, audit tábla írása.
 */

if (!defined('ABSPATH')) {
    exit;
}

function impact_ledger_update_status($id, $new_status, $reason = null)
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $audit = $wpdb->prefix . 'impact_ledger_audit';

    $row = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM $table WHERE id = %d", $id), ARRAY_A);
    if (!$row) {
        return new WP_Error('not_found', 'Ledger tétel nem található');
    }
    $old_status = $row['status'];
    $updates = [
        'status' => $new_status,
        'updated_at' => current_time('mysql'),
    ];
    if ($new_status === 'approved') {
        $updates['approved_at'] = current_time('mysql');
    }
    if ($new_status === 'paid') {
        $updates['paid_at'] = current_time('mysql');
    }
    if ($new_status === 'rejected') {
        $updates['rejection_reason'] = $reason;
    }

    $wpdb->update($table, $updates, ['id' => $id]);
    $wpdb->insert($audit, [
        'ledger_id' => $id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'changed_by' => wp_get_current_user()->user_login,
        'changed_at' => current_time('mysql'),
    ]);
    return true;
}

add_action('admin_menu', function () {
    add_management_page('Impact Ledger Approval', 'Impact Ledger Approval', 'manage_options', 'impact-ledger-approval', function () {
        global $wpdb;
        $table = $wpdb->prefix . 'impact_ledger';
        $action = isset($_GET['ila_action']) ? sanitize_text_field($_GET['ila_action']) : '';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        $message = '';

        if ($action && $id && wp_verify_nonce($nonce, 'ila_' . $id . '_' . $action)) {
            if ($action === 'approve') {
                $res = impact_ledger_update_status($id, 'approved');
                if (is_wp_error($res)) {
                    $message = 'Hiba: ' . $res->get_error_message();
                } else {
                    $message = "Tétel #$id jóváhagyva.";
                }
            } elseif ($action === 'reject') {
                $reason = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : '';
                $res = impact_ledger_update_status($id, 'rejected', $reason);
                if (is_wp_error($res)) {
                    $message = 'Hiba: ' . $res->get_error_message();
                } else {
                    $message = "Tétel #$id elutasítva.";
                }
            }
        }

        $pending = $wpdb->get_results("SELECT id, created_at, source, platform, ngo_code, advertiser_code, campaign_id, ad_id, COALESCE(amount_huf, amount_gross) AS amount FROM $table WHERE status='pending' ORDER BY created_at ASC LIMIT 50", ARRAY_A);

        echo '<div class="wrap"><h1>Impact Ledger Approval</h1>';
        if ($message) {
            echo '<div class="updated notice"><p>' . esc_html($message) . '</p></div>';
        }
        if (!$pending) {
            echo '<p>Nincs pending tétel.</p></div>';
            return;
        }
        echo '<table class="widefat fixed"><thead><tr><th>ID</th><th>Dátum</th><th>Forrás</th><th>Platform</th><th>NGO</th><th>Hirdető</th><th>Kampány</th><th>Ad ID</th><th>Összeg</th><th>Akció</th></tr></thead><tbody>';
        foreach ($pending as $row) {
            $approve_url = wp_nonce_url(add_query_arg(['page' => 'impact-ledger-approval', 'ila_action' => 'approve', 'id' => $row['id']]), 'ila_' . $row['id'] . '_approve');
            $reject_url = wp_nonce_url(add_query_arg(['page' => 'impact-ledger-approval', 'ila_action' => 'reject', 'id' => $row['id'], 'reason' => '']), 'ila_' . $row['id'] . '_reject');
            echo '<tr>';
            echo '<td>' . esc_html($row['id']) . '</td>';
            echo '<td>' . esc_html($row['created_at']) . '</td>';
            echo '<td>' . esc_html($row['source']) . '</td>';
            echo '<td>' . esc_html($row['platform']) . '</td>';
            echo '<td>' . esc_html($row['ngo_code']) . '</td>';
            echo '<td>' . esc_html($row['advertiser_code']) . '</td>';
            echo '<td>' . esc_html($row['campaign_id']) . '</td>';
            echo '<td>' . esc_html($row['ad_id']) . '</td>';
            echo '<td>' . esc_html(number_format(floatval($row['amount']), 0, '.', ' ')) . '</td>';
            echo '<td><a class="button button-primary" href="' . esc_url($approve_url) . '">Approve</a> ';
            echo '<a class="button" href="' . esc_url($reject_url) . '">Reject</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    });
});
