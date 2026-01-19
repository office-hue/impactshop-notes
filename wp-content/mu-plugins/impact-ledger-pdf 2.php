<?php
/**
 * WHAT: PDF export skeleton a ledger riport HTML-ből (mock adat, demo célra).
 * WHY: Riport drilldown/nyomtatható verzió alapjai.
 * HOW: Admin gomb Tools > Impact Ledger Report oldalon, dompdf/MPDF helyett sima HTML→PDF placeholder (most csak HTML-t küld le application/pdf content-type-pal).
 *
 * Megjegyzés: DNS nélkül, külső lib telepítése nélkül csak skeleton; éles PDF-hez dompdf/mpdf szükséges.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!isset($_GET['impact_ledger_pdf']) || $_GET['impact_ledger_pdf'] !== '1') {
        return;
    }
    $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-01');
    $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');
    $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
    $ngo = isset($_GET['ngo']) ? sanitize_text_field($_GET['ngo']) : '';
    $advertiser = isset($_GET['advertiser']) ? sanitize_text_field($_GET['advertiser']) : '';

    $html = '<h1>Impact Ledger Report (PDF demo)</h1>';
    $html .= '<p>Időszak: ' . esc_html($from) . ' - ' . esc_html($to) . '</p>';
    $html .= '<p>Státusz: ' . esc_html($status ?: 'mind') . '</p>';
    $html .= '<p>NGO: ' . esc_html($ngo ?: '-') . ' | Hirdető: ' . esc_html($advertiser ?: '-') . '</p>';
    $html .= '<p>Megjegyzés: Ez egy skeleton; dompdf/mpdf integrációval lehet valódi PDF-et generálni.</p>';

    // Placeholder PDF response (valójában HTML)
    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="impact-ledger-' . $from . '-' . $to . '.pdf"');
    echo $html;
    exit;
});
