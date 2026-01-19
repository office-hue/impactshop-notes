<?php
/**
 * WHAT: Egyszerű ledger riport + shortcode-ok (összeg és táblázat) az impact_ledger tábla alapján.
 * WHY: Gyors áttekintés NGO/Hirdető oldalról, amíg a teljes UI készül.
 * HOW: WP-Admin Tools menü + [impact_total], [impact_total_range], [impact_ledger_table] shortcodes.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Segédfüggvény: összegzés megadott dátum intervallumra.
 */
function impact_ledger_sum_range($from, $to, $status = null, $filters = [])
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $where = $wpdb->prepare('created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59');
    if ($status) {
        $where .= $wpdb->prepare(' AND status = %s', $status);
    }
    if (!empty($filters['ngo'])) {
        $where .= $wpdb->prepare(' AND ngo_code = %s', $filters['ngo']);
    }
    if (!empty($filters['advertiser'])) {
        $where .= $wpdb->prepare(' AND advertiser_code = %s', $filters['advertiser']);
    }
    if (!empty($filters['source'])) {
        $where .= $wpdb->prepare(' AND source = %s', $filters['source']);
    }
    if (!empty($filters['platform'])) {
        $where .= $wpdb->prepare(' AND platform = %s', $filters['platform']);
    }
    if (!empty($filters['campaign'])) {
        $where .= $wpdb->prepare(' AND campaign_id = %s', $filters['campaign']);
    }
    if (!empty($filters['ad'])) {
        $where .= $wpdb->prepare(' AND ad_id = %s', $filters['ad']);
    }
    $sql = "SELECT SUM(COALESCE(amount_huf, amount_gross)) AS total FROM $table WHERE $where";
    $total = $wpdb->get_var($sql);
    return floatval($total ?: 0);
}

/**
 * Segédfüggvény: státusz frissítés (admin poszt handler használja).
 * Guard: ha máshol már definiáltuk (impact-ledger-approval.php), ne redeklaráljuk.
 */
if (!function_exists('impact_ledger_update_status')) {
    function impact_ledger_update_status($id, $status)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'impact_ledger';
        $allowed = ['pending', 'approved', 'paid', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return new WP_Error('invalid_status', 'Érvénytelen státusz');
        }
        $updated = $wpdb->update($table, ['status' => $status], ['id' => intval($id)]);
        return $updated !== false;
    }
}

add_action('admin_post_impact_ledger_status', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Nincs jogosultság');
    }
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    check_admin_referer('impact_ledger_status_' . $id);
    $res = impact_ledger_update_status($id, $status);
    $redirect = wp_get_referer() ?: admin_url('tools.php?page=impact-ledger-report');
    $redirect = add_query_arg('ledger_status_msg', $res ? 'ok' : 'fail', $redirect);
    wp_safe_redirect($redirect);
    exit;
});

/**
 * Segédfüggvény: darabszám megadott dátum intervallumra.
 */
function impact_ledger_count_range($from, $to, $status = null, $filters = [])
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $where = $wpdb->prepare('created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59');
    if ($status) {
        $where .= $wpdb->prepare(' AND status = %s', $status);
    }
    if (!empty($filters['ngo'])) {
        $where .= $wpdb->prepare(' AND ngo_code = %s', $filters['ngo']);
    }
    if (!empty($filters['advertiser'])) {
        $where .= $wpdb->prepare(' AND advertiser_code = %s', $filters['advertiser']);
    }
    if (!empty($filters['source'])) {
        $where .= $wpdb->prepare(' AND source = %s', $filters['source']);
    }
    if (!empty($filters['platform'])) {
        $where .= $wpdb->prepare(' AND platform = %s', $filters['platform']);
    }
    if (!empty($filters['campaign'])) {
        $where .= $wpdb->prepare(' AND campaign_id = %s', $filters['campaign']);
    }
    if (!empty($filters['ad'])) {
        $where .= $wpdb->prepare(' AND ad_id = %s', $filters['ad']);
    }
    return intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where"));
}

/**
 * Shortcode: [impact_total from="YYYY-MM-DD" to="YYYY-MM-DD" status="approved"]
 */
add_shortcode('impact_total', function ($atts) {
    $atts = shortcode_atts([
        'from' => date('Y-m-01'),
        'to' => date('Y-m-d'),
        'status' => null,
        'ngo' => '',
        'advertiser' => '',
        'source' => '',
        'platform' => '',
    ], $atts);
    $total = impact_ledger_sum_range($atts['from'], $atts['to'], $atts['status'], [
        'ngo' => $atts['ngo'],
        'advertiser' => $atts['advertiser'],
        'source' => $atts['source'],
        'platform' => $atts['platform'],
    ]);
    return esc_html(number_format($total, 0, '.', ' ')) . ' HUF';
});

/**
 * Shortcode alias: [impact_total_range ...] ugyanaz, mint impact_total.
 */
add_shortcode('impact_total_range', function ($atts) {
    return do_shortcode('[impact_total ' . http_build_query($atts, '', ' ') . ']');
});

/**
 * Shortcode: [impact_ledger_table from="YYYY-MM-DD" to="YYYY-MM-DD" limit="50"]
 * Egyszerű táblázat a ledger tételekkel.
 */
add_shortcode('impact_ledger_table', function ($atts) {
    global $wpdb;
    $atts = shortcode_atts([
        'from' => date('Y-m-01'),
        'to' => date('Y-m-d'),
        'limit' => 50,
        'ngo' => '',
        'advertiser' => '',
        'source' => '',
        'platform' => '',
        'campaign' => '',
        'ad' => '',
        'offset' => 0,
    ], $atts);
    $limit = intval($atts['limit']);
    if ($limit < 1 || $limit > 500) {
        $limit = 50;
    }
    $offset = intval($atts['offset']);
    $table = $wpdb->prefix . 'impact_ledger';
    $where = $wpdb->prepare('created_at BETWEEN %s AND %s', $atts['from'] . ' 00:00:00', $atts['to'] . ' 23:59:59');
    if (!empty($atts['ngo'])) {
        $where .= $wpdb->prepare(' AND ngo_code = %s', $atts['ngo']);
    }
    if (!empty($atts['advertiser'])) {
        $where .= $wpdb->prepare(' AND advertiser_code = %s', $atts['advertiser']);
    }
    if (!empty($atts['source'])) {
        $where .= $wpdb->prepare(' AND source = %s', $atts['source']);
    }
    if (!empty($atts['platform'])) {
        $where .= $wpdb->prepare(' AND platform = %s', $atts['platform']);
    }
    if (!empty($atts['campaign'])) {
        $where .= $wpdb->prepare(' AND campaign_id = %s', $atts['campaign']);
    }
    if (!empty($atts['ad'])) {
        $where .= $wpdb->prepare(' AND ad_id = %s', $atts['ad']);
    }
    $sql = $wpdb->prepare(
        "SELECT id, created_at, source, platform, ngo_code, advertiser_code, campaign_id, ad_id, status, COALESCE(amount_huf, amount_gross) AS amount, meta, event_id
         FROM $table
         WHERE $where
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        $limit,
        $offset
    );
    $rows = $wpdb->get_results($sql, ARRAY_A);

    if (!$rows) {
        return '<p>Nincs tétel a megadott idősávban.</p>';
    }

    ob_start();
    ?>
    <table class="widefat fixed" style="max-width:100%; margin:1em 0;">
        <thead>
        <tr>
            <th>Dátum</th>
            <th>Forrás</th>
            <th>Platform</th>
            <th>NGO</th>
            <th>Hirdető</th>
            <th>Kampány</th>
            <th>Ad ID</th>
            <th>Státusz</th>
            <th>Összeg (HUF)</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo esc_html($row['created_at']); ?></td>
                <td><?php echo esc_html($row['source']); ?></td>
                <td><?php echo esc_html($row['platform']); ?></td>
                <td><?php echo esc_html($row['ngo_code']); ?></td>
                <td><?php echo esc_html($row['advertiser_code']); ?></td>
                <td><?php echo esc_html($row['campaign_id']); ?></td>
                <td><?php echo esc_html($row['ad_id']); ?></td>
                <td><?php echo esc_html($row['status']); ?></td>
                <td><?php echo esc_html(number_format(floatval($row['amount']), 0, '.', ' ')); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
});

/**
 * Admin oldal: Tools > Impact Ledger Report
 */
add_action('admin_menu', function () {
    add_management_page('Impact Ledger Report', 'Impact Ledger Report', 'manage_options', 'impact-ledger-report', function () {
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-01');
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        $export = isset($_GET['export']) ? sanitize_text_field($_GET['export']) : null;
        $ngo = isset($_GET['ngo']) ? sanitize_text_field($_GET['ngo']) : '';
        $advertiser = isset($_GET['advertiser']) ? sanitize_text_field($_GET['advertiser']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        $platform = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : '';
        $campaign = isset($_GET['campaign']) ? sanitize_text_field($_GET['campaign']) : '';
        $ad = isset($_GET['ad']) ? sanitize_text_field($_GET['ad']) : '';
        $group = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : '';
        $compare = isset($_GET['compare']) ? sanitize_text_field($_GET['compare']) : '';

        $page_num = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
        $per_page = isset($_GET['per_page']) ? min(100, max(1, intval($_GET['per_page']))) : 50;
        $offset = ($page_num - 1) * $per_page;

        if ($export === 'csv') {
            impact_ledger_export_csv($from, $to, $status, [
                'ngo' => $ngo,
                'advertiser' => $advertiser,
                'source' => $source,
                'platform' => $platform,
                'campaign' => $campaign,
                'ad' => $ad,
            ]);
            exit;
        }

        $filters = [
            'ngo' => $ngo,
            'advertiser' => $advertiser,
            'source' => $source,
            'platform' => $platform,
            'campaign' => $campaign,
            'ad' => $ad,
        ];
        $table = $wpdb->prefix . 'impact_ledger';
        $where_base = $wpdb->prepare('created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59');
        if ($status) {
            $where_base .= $wpdb->prepare(' AND status = %s', $status);
        }
        if ($ngo) {
            $where_base .= $wpdb->prepare(' AND ngo_code = %s', $ngo);
        }
        if ($advertiser) {
            $where_base .= $wpdb->prepare(' AND advertiser_code = %s', $advertiser);
        }
        if ($source) {
            $where_base .= $wpdb->prepare(' AND source = %s', $source);
        }
        if ($platform) {
            $where_base .= $wpdb->prepare(' AND platform = %s', $platform);
        }
        if ($campaign) {
            $where_base .= $wpdb->prepare(' AND campaign_id = %s', $campaign);
        }
        if ($ad) {
            $where_base .= $wpdb->prepare(' AND ad_id = %s', $ad);
        }
        $total = impact_ledger_sum_range($from, $to, $status, $filters);
        $prev_from = date('Y-m-d', strtotime($from . ' -1 month'));
        $prev_to = date('Y-m-d', strtotime($to . ' -1 month'));
        $prev_total = ($compare === 'prev') ? impact_ledger_sum_range($prev_from, $prev_to, $status, $filters) : 0;
        echo '<div class="wrap"><h1>Impact Ledger Report</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="impact-ledger-report" />';
        echo 'Dátum: <input type="date" name="from" value="' . esc_attr($from) . '" /> - ';
        echo '<input type="date" name="to" value="' . esc_attr($to) . '" /> ';
        echo 'Státusz: <select name="status">';
        $statuses = ['', 'pending', 'approved', 'paid', 'rejected'];
        foreach ($statuses as $s) {
            $sel = ($s === $status) ? 'selected' : '';
            $label = $s === '' ? 'mind' : $s;
            echo '<option value="' . esc_attr($s) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo 'NGO: <input type="text" name="ngo" value="' . esc_attr($ngo) . '" placeholder="ngo_code" /> ';
        echo 'Hirdető: <input type="text" name="advertiser" value="' . esc_attr($advertiser) . '" placeholder="advertiser_code" /> ';
        $source_opts = ['', 'shop', 'view', 'click', 'challenge', 'match', 'donation'];
        echo 'Forrás: <select name="source">';
        foreach ($source_opts as $opt) {
            $sel = ($opt === $source) ? 'selected' : '';
            $label = $opt === '' ? 'mind' : $opt;
            echo '<option value="' . esc_attr($opt) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        $platform_opts = ['', 'meta', 'google', 'tiktok', 'youtube', 'dognet', 'manual'];
        echo 'Platform: <select name="platform">';
        foreach ($platform_opts as $opt) {
            $sel = ($opt === $platform) ? 'selected' : '';
            $label = $opt === '' ? 'mind' : $opt;
            echo '<option value="' . esc_attr($opt) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo 'Kampány: <input type="text" name="campaign" value="' . esc_attr($campaign) . '" placeholder="campaign_id" /> ';
        echo 'Ad ID: <input type="text" name="ad" value="' . esc_attr($ad) . '" placeholder="ad_id" /> ';
        echo 'Csoportosítás: <select name="group">';
        $groups = ['', 'campaign', 'ad', 'platform'];
        foreach ($groups as $g) {
            $sel = ($g === $group) ? 'selected' : '';
            $label = $g === '' ? 'nincs' : $g;
            echo '<option value="' . esc_attr($g) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo 'Oldalanként: <select name="per_page">';
        foreach ([25, 50, 100] as $pp) {
            $sel = $pp == $per_page ? 'selected' : '';
            echo '<option value="' . $pp . '" ' . $sel . '>' . $pp . '</option>';
        }
        echo '</select> ';
        echo '<label><input type="checkbox" name="compare" value="prev" ' . checked($compare, 'prev', false) . '> Előző hónap összehasonlítás</label> ';
        echo '<button class="button button-primary">Szűrés</button> ';
        echo '<a class="button" href="' . esc_url(add_query_arg([
            'page' => 'impact-ledger-report',
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'ngo' => $ngo,
            'advertiser' => $advertiser,
            'source' => $source,
            'platform' => $platform,
            'campaign' => $campaign,
            'ad' => $ad,
            'group' => $group,
            'compare' => $compare,
            'per_page' => $per_page,
            'export' => 'csv',
        ])) . '">Export CSV</a> ';
        echo '<a class="button" href="' . esc_url(add_query_arg([
            'impact_ledger_pdf' => '1',
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'ngo' => $ngo,
            'advertiser' => $advertiser,
        ])) . '">PDF (demo)</a>';
        echo '</form>';

        echo '<p><strong>Összesen:</strong> ' . esc_html(number_format($total, 0, '.', ' ')) . ' HUF</p>';
        if ($compare === 'prev') {
            $delta = $total - $prev_total;
            $pct = ($prev_total > 0) ? round(($delta / $prev_total) * 100, 1) : 0;
            echo '<p><em>Előző hónap: ' . esc_html(number_format($prev_total, 0, '.', ' ')) . ' HUF | Változás: ' . esc_html(number_format($delta, 0, '.', ' ')) . ' HUF (' . esc_html($pct) . '%)</em></p>';
        }

        // Státusz tabok számlálóval
        $status_labels = [
            '' => 'Összes',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'rejected' => 'Rejected',
        ];
        echo '<p>';
        foreach ($status_labels as $code => $label) {
            $cnt = impact_ledger_count_range($from, $to, $code ?: null, $filters);
            $tab_params = [
                'page' => 'impact-ledger-report',
                'from' => $from,
                'to' => $to,
                'status' => $code,
                'ngo' => $ngo,
                'advertiser' => $advertiser,
                'source' => $source,
                'platform' => $platform,
                'campaign' => $campaign,
                'ad' => $ad,
                'group' => $group,
                'compare' => $compare,
                'per_page' => $per_page,
            ];
            $class = ($code === $status || ($code === '' && !$status)) ? 'button-primary' : 'button-secondary';
            echo '<a class="button ' . esc_attr($class) . '" style="margin-right:6px;" href="' . esc_url(add_query_arg($tab_params)) . '">' . esc_html($label) . ' (' . esc_html($cnt) . ')</a>';
        }
        echo '</p>';

        if (!empty($group)) {
            $table_group = $wpdb->prefix . 'impact_ledger';
            $where_group = $wpdb->prepare('created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59');
            if ($status) {
                $where_group .= $wpdb->prepare(' AND status = %s', $status);
            }
            if ($ngo) {
                $where_group .= $wpdb->prepare(' AND ngo_code = %s', $ngo);
            }
            if ($advertiser) {
                $where_group .= $wpdb->prepare(' AND advertiser_code = %s', $advertiser);
            }
            if ($source) {
                $where_group .= $wpdb->prepare(' AND source = %s', $source);
            }
            if ($platform) {
                $where_group .= $wpdb->prepare(' AND platform = %s', $platform);
            }
            if ($campaign) {
                $where_group .= $wpdb->prepare(' AND campaign_id = %s', $campaign);
            }
            if ($ad) {
                $where_group .= $wpdb->prepare(' AND ad_id = %s', $ad);
            }
            $group_col = $group === 'campaign' ? 'campaign_id' : ($group === 'ad' ? 'ad_id' : 'platform');
            $sql_group = "SELECT $group_col AS grp, COUNT(*) c, SUM(COALESCE(amount_huf, amount_gross)) s FROM $table_group WHERE $where_group GROUP BY $group_col ORDER BY s DESC LIMIT 100";
            $rows_group = $wpdb->get_results($sql_group, ARRAY_A);
            if ($rows_group) {
                echo '<h2>Csoportosított nézet: ' . esc_html($group_col) . '</h2>';
                echo '<table class=\"widefat fixed\"><thead><tr><th>' . esc_html($group_col) . '</th><th>Darab</th><th>Összeg (HUF)</th></tr></thead><tbody>';
                foreach ($rows_group as $gr) {
                    echo '<tr><td>' . esc_html($gr['grp']) . '</td><td>' . esc_html($gr['c']) . '</td><td>' . esc_html(number_format(floatval($gr['s']), 0, '.', ' ')) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        }

        // Táblázat + lapozás
        $sql_rows = $wpdb->prepare(
            "SELECT created_at, source, platform, ngo_code, advertiser_code, campaign_id, ad_id, status, COALESCE(amount_huf, amount_gross) AS amount, meta, event_id
             FROM $table
             WHERE $where_base
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        $rows = $wpdb->get_results($sql_rows, ARRAY_A);
        $total_rows = intval($wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_base"));
        if ($rows) {
            echo '<table class="widefat fixed" style="max-width:100%; margin:1em 0;">';
            echo '<thead><tr><th>Dátum</th><th>Forrás</th><th>Platform</th><th>NGO</th><th>Hirdető</th><th>Kampány</th><th>Ad ID</th><th>Státusz</th><th>Összeg (HUF)</th><th>Views</th><th>Clicks</th><th>Művelet</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $meta = json_decode($row['meta'] ?? '', true);
                $views = '';
                $clicks = '';
                if (is_array($meta)) {
                    $views = $meta['views'] ?? ($meta['meta']['views'] ?? '');
                    $clicks = $meta['clicks'] ?? ($meta['meta']['clicks'] ?? '');
                }
                // gyors szűrő linkek kampány/ad szerint
                $camp_link = add_query_arg(array_merge($_GET, ['campaign' => $row['campaign_id'], 'page_num' => 1]));
                $ad_link = add_query_arg(array_merge($_GET, ['ad' => $row['ad_id'], 'page_num' => 1]));
                echo '<tr>';
                echo '<td>' . esc_html($row['created_at']) . '</td>';
                echo '<td>' . esc_html($row['source']) . '</td>';
                echo '<td>' . esc_html($row['platform']) . '</td>';
                echo '<td>' . esc_html($row['ngo_code']) . '</td>';
                echo '<td>' . esc_html($row['advertiser_code']) . '</td>';
                echo '<td><a href="' . esc_url($camp_link) . '">' . esc_html($row['campaign_id']) . '</a></td>';
                echo '<td><a href="' . esc_url($ad_link) . '">' . esc_html($row['ad_id']) . '</a></td>';
                echo '<td>' . esc_html($row['status']) . '</td>';
                echo '<td>' . esc_html(number_format(floatval($row['amount']), 0, '.', ' ')) . '</td>';
                echo '<td>' . esc_html($views) . '</td>';
                echo '<td>' . esc_html($clicks) . '</td>';
                echo '<td>';
                $nonce = wp_create_nonce('impact_ledger_status_' . $row['id']);
                $actions = [];
                if ($row['status'] !== 'approved') {
                    $actions[] = '<a class="button button-small" href="' . esc_url(add_query_arg(['action' => 'impact_ledger_status', 'id' => $row['id'], 'status' => 'approved', '_wpnonce' => $nonce], admin_url('admin-post.php'))) . '">Approve</a>';
                }
                if ($row['status'] !== 'rejected') {
                    $actions[] = '<a class="button button-small" href="' . esc_url(add_query_arg(['action' => 'impact_ledger_status', 'id' => $row['id'], 'status' => 'rejected', '_wpnonce' => $nonce], admin_url('admin-post.php'))) . '">Reject</a>';
                }
                if ($row['status'] !== 'pending') {
                    $actions[] = '<a class="button button-small" href="' . esc_url(add_query_arg(['action' => 'impact_ledger_status', 'id' => $row['id'], 'status' => 'pending', '_wpnonce' => $nonce], admin_url('admin-post.php'))) . '">Pending</a>';
                }
                echo implode(' ', $actions);
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Nincs tétel a megadott szűrésre.</p>';
        }

        // Oldalszámozás
        $total_pages = max(1, ceil($total_rows / $per_page));
        $next_params = [
            'page' => 'impact-ledger-report',
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'ngo' => $ngo,
            'advertiser' => $advertiser,
            'source' => $source,
            'platform' => $platform,
            'campaign' => $campaign,
            'ad' => $ad,
            'group' => $group,
            'compare' => $compare,
            'per_page' => $per_page,
        ];
        echo '<p>Oldal: ' . esc_html($page_num) . ' / ' . esc_html($total_pages) . '</p><p>';
        if ($page_num > 1) {
            $prev_params = $next_params;
            $prev_params['page_num'] = max(1, $page_num - 1);
            echo '<a class="button" href="' . esc_url(add_query_arg($prev_params)) . '">Előző oldal</a> ';
        }
        if ($page_num < $total_pages) {
            $next_params['page_num'] = $page_num + 1;
            echo '<a class="button" href="' . esc_url(add_query_arg($next_params)) . '">Következő oldal</a>';
        }
        echo '</p>';
        echo '</div>';
    });
});

/**
 * CSV export helper
 */
function impact_ledger_export_csv($from, $to, $status = null, $filters = [])
{
    global $wpdb;
    $table = $wpdb->prefix . 'impact_ledger';
    $where = $wpdb->prepare('created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59');
    if ($status) {
        $where .= $wpdb->prepare(' AND status = %s', $status);
    }
    if (!empty($filters['ngo'])) {
        $where .= $wpdb->prepare(' AND ngo_code = %s', $filters['ngo']);
    }
    if (!empty($filters['advertiser'])) {
        $where .= $wpdb->prepare(' AND advertiser_code = %s', $filters['advertiser']);
    }
    if (!empty($filters['source'])) {
        $where .= $wpdb->prepare(' AND source = %s', $filters['source']);
    }
    if (!empty($filters['platform'])) {
        $where .= $wpdb->prepare(' AND platform = %s', $filters['platform']);
    }
    if (!empty($filters['campaign'])) {
        $where .= $wpdb->prepare(' AND campaign_id = %s', $filters['campaign']);
    }
    if (!empty($filters['ad'])) {
        $where .= $wpdb->prepare(' AND ad_id = %s', $filters['ad']);
    }
    $sql = "SELECT created_at, source, platform, ngo_code, advertiser_code, campaign_id, ad_id, status, COALESCE(amount_huf, amount_gross) AS amount, meta, event_id FROM $table WHERE $where ORDER BY created_at DESC";
    $rows = $wpdb->get_results($sql, ARRAY_A);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="impact-ledger-' . $from . '-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['created_at', 'source', 'platform', 'ngo_code', 'advertiser_code', 'campaign_id', 'ad_id', 'status', 'amount_huf', 'views', 'clicks', 'event_id']);
    if ($rows) {
        foreach ($rows as $row) {
            $meta = json_decode($row['meta'] ?? '', true);
            $views = '';
            $clicks = '';
            if (is_array($meta)) {
                $views = $meta['views'] ?? ($meta['meta']['views'] ?? '');
                $clicks = $meta['clicks'] ?? ($meta['meta']['clicks'] ?? '');
            }
            fputcsv($out, [
                $row['created_at'],
                $row['source'],
                $row['platform'],
                $row['ngo_code'],
                $row['advertiser_code'],
                $row['campaign_id'],
                $row['ad_id'],
                $row['status'],
                $row['amount'],
                $views,
                $clicks,
                $row['event_id'] ?? '',
            ]);
        }
    }
    fclose($out);
}
