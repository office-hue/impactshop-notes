<?php
/**
 * WHAT: PDF export a ledger riportból; Dompdf ha elérhető, egyébként HTML→PDF fallback.
 * WHY: Nyomtatható/letölthető riport az admin oldalról.
 * HOW: Tools > Impact Ledger Report oldal "PDF" gombja hívja; szűrőket átveszi (dátum, státusz, ngo/hirdető).
 */

if (!defined('ABSPATH')) {
    exit;
}

function impact_ledger_pdf_load_dompdf()
{
    if (class_exists('\\Dompdf\\Dompdf')) {
        return true;
    }
    $paths = [
        ABSPATH . 'vendor/autoload.php',
        dirname(__DIR__, 2) . '/vendor/autoload.php',
        dirname(__DIR__, 3) . '/vendor/autoload.php',
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) {
            require_once $p;
            if (class_exists('\\Dompdf\\Dompdf')) {
                return true;
            }
        }
    }
    return false;
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['impact_ledger_pdf']) || $_GET['impact_ledger_pdf'] !== '1') {
        return;
    }
    global $wpdb;

    $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-01');
    $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
    $ngo = isset($_GET['ngo']) ? sanitize_text_field($_GET['ngo']) : '';
    $advertiser = isset($_GET['advertiser']) ? sanitize_text_field($_GET['advertiser']) : '';

    $table = $wpdb->prefix . 'impact_ledger';
    $where = $wpdb->prepare('created_at BETWEEN %s AND %s', $from . ' 00:00:00', $to . ' 23:59:59');
    if ($status) {
        $where .= $wpdb->prepare(' AND status = %s', $status);
    }
    if ($ngo) {
        $where .= $wpdb->prepare(' AND ngo_code = %s', $ngo);
    }
    if ($advertiser) {
        $where .= $wpdb->prepare(' AND advertiser_code = %s', $advertiser);
    }
    $rows = $wpdb->get_results("SELECT created_at, source, platform, ngo_code, advertiser_code, campaign_id, ad_id, status, COALESCE(amount_huf, amount_gross) AS amount, meta FROM $table WHERE $where ORDER BY created_at DESC LIMIT 500", ARRAY_A);
    $total = $wpdb->get_var("SELECT SUM(COALESCE(amount_huf, amount_gross)) FROM $table WHERE $where");

    // Forrás bontás (shop/view/click/match)
    $sources = ['shop', 'view', 'click', 'match'];
    $source_totals = [];
    foreach ($sources as $src) {
        $source_totals[$src] = $wpdb->get_var($wpdb->prepare("SELECT SUM(COALESCE(amount_huf, amount_gross)) FROM $table WHERE $where AND source=%s", $src));
    }
    // Státusz bontás
    $statuses = ['pending', 'approved', 'paid', 'rejected'];
    $status_totals = [];
    foreach ($statuses as $st) {
        $status_totals[$st] = $wpdb->get_var($wpdb->prepare("SELECT SUM(COALESCE(amount_huf, amount_gross)) FROM $table WHERE $where AND status=%s", $st));
    }

    ob_start();
    ?>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
            h1 { font-size: 20px; margin: 0 0 8px 0; }
            h2 { font-size: 14px; margin: 12px 0 6px 0; }
            .header { background: #4CAF50; color: #fff; padding: 12px; border-radius: 6px; }
            .summary-card { border: 1px solid #e0e0e0; border-radius: 6px; padding: 8px; margin: 6px 0; background: #f5f5f5; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th, td { border: 1px solid #ccc; padding: 4px; }
            th { background: #f5f5f5; }
            .section { margin-top: 12px; }
        </style>
    </head>
    <body>
    <div class="header">
        <h1>Sharity Impact Report</h1>
        <div>Időszak: <?php echo esc_html($from); ?> - <?php echo esc_html($to); ?></div>
        <div>NGO: <?php echo esc_html($ngo ?: '-'); ?> | Hirdető: <?php echo esc_html($advertiser ?: '-'); ?></div>
    </div>

    <div class="section summary">
        <h2>Összegzés</h2>
        <div class="summary-card"><strong>Összesen:</strong> <?php echo esc_html(number_format(floatval($total), 0, '.', ' ')); ?> HUF</div>
        <div class="summary-card">
            <strong>Forrás bontás:</strong><br>
            Shop: <?php echo esc_html(number_format(floatval($source_totals['shop']), 0, '.', ' ')); ?> HUF<br>
            View: <?php echo esc_html(number_format(floatval($source_totals['view']), 0, '.', ' ')); ?> HUF<br>
            Click: <?php echo esc_html(number_format(floatval($source_totals['click']), 0, '.', ' ')); ?> HUF<br>
            Match: <?php echo esc_html(number_format(floatval($source_totals['match']), 0, '.', ' ')); ?> HUF
        </div>
        <div class="summary-card">
            <strong>Státusz bontás:</strong><br>
            Pending: <?php echo esc_html(number_format(floatval($status_totals['pending']), 0, '.', ' ')); ?> HUF<br>
            Approved: <?php echo esc_html(number_format(floatval($status_totals['approved']), 0, '.', ' ')); ?> HUF<br>
            Paid: <?php echo esc_html(number_format(floatval($status_totals['paid']), 0, '.', ' ')); ?> HUF<br>
            Rejected: <?php echo esc_html(number_format(floatval($status_totals['rejected']), 0, '.', ' ')); ?> HUF
        </div>
        <div>Generálva: <?php echo esc_html(gmdate('Y-m-d H:i') . ' UTC'); ?></div>
    </div>

    <div class="section">
        <h2>Részletek</h2>
        <table>
            <thead>
            <tr>
                <th>Dátum</th><th>Forrás</th><th>Platform</th><th>NGO</th><th>Hirdető</th><th>Kampány</th><th>Ad ID</th><th>Státusz</th><th>Összeg (HUF)</th><th>Views</th><th>Clicks</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows): foreach ($rows as $r):
                $views = '';
                $clicks = '';
                if (!empty($r['meta'])) {
                    $m = json_decode($r['meta'], true);
                    if (is_array($m)) {
                        $views = isset($m['views']) ? $m['views'] : (isset($m['meta']['views']) ? $m['meta']['views'] : '');
                        $clicks = isset($m['clicks']) ? $m['clicks'] : (isset($m['meta']['clicks']) ? $m['meta']['clicks'] : '');
                    }
                }
                ?>
                <tr>
                    <td><?php echo esc_html($r['created_at']); ?></td>
                    <td><?php echo esc_html($r['source']); ?></td>
                    <td><?php echo esc_html($r['platform']); ?></td>
                    <td><?php echo esc_html($r['ngo_code']); ?></td>
                    <td><?php echo esc_html($r['advertiser_code']); ?></td>
                    <td><?php echo esc_html($r['campaign_id']); ?></td>
                    <td><?php echo esc_html($r['ad_id']); ?></td>
                    <td><?php echo esc_html($r['status']); ?></td>
                    <td><?php echo esc_html(number_format(floatval($r['amount']), 0, '.', ' ')); ?></td>
                    <td><?php echo esc_html($views); ?></td>
                    <td><?php echo esc_html($clicks); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="11">Nincs adat.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p style="margin-top: 12px;">Megjegyzés: ha a Dompdf elérhető (vendor/autoload), tényleges PDF generálás történik, különben HTML-pdf fallback.</p>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    if (impact_ledger_pdf_load_dompdf()) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="impact-ledger-' . $from . '-' . $to . '.pdf"');
        echo $output;
        exit;
    }

    // Fallback: HTML-pdf fejléc
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="impact-ledger-' . $from . '-' . $to . '.pdf"');
    echo $html;
    exit;
});
