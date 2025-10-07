# ImpactShop – Perf hotfix + Diagnostics export fix (2025-10-07)

## Context
- Prod első betöltés lassú; /wp-json/impactshop/v1/totals néha 500/timeout.
- Safari első kérésre nem tölt, másodikra igen → valószínű remote API késleltetés/timeout.
- Cél: kíméletes gyorsítás WP-szinten, tartalom/témák módosítása nélkül.

## Objectives
- Cache /impactshop/v1/totals válasz 120s (request-key alapú).
- Rövid HTTP timeout a távoli (Dognet/API) hívásokra (≤2.5s).
- Opcionális defenzív mód: WP_Error/500 → 200 + üres rows a vizsgálat idejére.
- Diagnostics CSV export friss/felület-kompatibilis (flatten collisions).

## Changes to apply (code)
````php
// filepath: /Users/bujdosoarnold/Documents/GitHub/impactshop-notes/wp-content/mu-plugins/001-impact-perf-guard.php
<?php
/*
 * 001 Impact Performance Guard
 * - REST /impactshop/v1/totals cache (120s)
 * - Optional defensive 500→200 empty rows
 * - Shorter HTTP timeouts for remote calls
 */
if (!defined('ABSPATH')) exit;

// define('IMPACT_DEFENSIVE_TOTALS', true); // ideiglenesen bekapcsolható

add_filter('rest_pre_dispatch', function($result, $server, $request){
    if (!($request instanceof WP_REST_Request)) return $result;
    if ($request->get_route() === '/impactshop/v1/totals') {
        $qs = (array)$request->get_query_params();
        ksort($qs);
        $key = 'impact_totals_' . md5(http_build_query($qs));
        $cached = get_transient($key);
        if ($cached instanceof WP_REST_Response) {
            return $cached;
        }
        // átadjuk a kulcsot a későbbi cache-hez
        $request->set_param('_impact_cache_key', $key);
    }
    return $result;
}, 10, 3);

add_filter('rest_request_after_callbacks', function($response, $handler, $request){
    if (!($request instanceof WP_REST_Request)) return $response;
    if ($request->get_route() !== '/impactshop/v1/totals') return $response;

    // WP_Error → opcionális defenzív üres válasz
    if (is_wp_error($response) && defined('IMPACT_DEFENSIVE_TOTALS') && IMPACT_DEFENSIVE_TOTALS) {
        error_log('ImpactShop totals defensive: ' . $response->get_error_message());
        $response = new WP_REST_Response(['rows'=>[], 'note'=>'defensive'], 200);
    }

    // Sikeres válasz cache-elése 120 mp-re
    if ($response instanceof WP_REST_Response) {
        $status = $response->get_status();
        if ($status >= 200 && $status < 300) {
            $key = $request->get_param('_impact_cache_key');
            if ($key) set_transient($key, $response, 120);
        }
    }
    return $response;
}, 10, 3);

// Rövidebb timeout a távoli kérésekre (Dognet/API)
add_filter('http_request_args', function($args, $url){
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    if (preg_match('~(dognet|api|sharity)\.~i', $host)) {
        $args['timeout'] = min((float)($args['timeout'] ?? 5), 2.5);
        $args['redirection'] = 2;
        $args['blocking'] = true;
    }
    return $args;
}, 10, 2);
````

````php
// filepath: /Users/bujdosoarnold/Documents/GitHub/impactshop-notes/wp-content/mu-plugins/impactshop-link-diag.php
// ...existing code...
    public function export_csv() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'export_diag_csv')) {
            wp_die('Insufficient permissions', 'Export blocked', 403);
        }
        // Friss diagnosztika export előtt
        $this->run_full_diagnostics();

        $type = sanitize_text_field($_GET['type'] ?? 'all');
        $timestamp = date('Y-m-d_H-i-s');
        switch ($type) {
            case 'functions': $this->export_functions_csv($timestamp); break;
            case 'shortcodes': $this->export_shortcodes_csv($timestamp); break;
            case 'samples': $this->export_samples_csv($timestamp); break;
            default: $this->export_all_csv($timestamp);
        }
    }
// ...existing code...
    private function export_all_csv($timestamp) {
        $filename = "impactshop_diagnostics_$timestamp.csv";
        $filepath = trailingslashit($this->csv_dir) . $filename;

        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Category', 'Type', 'File', 'Line', 'Issue', 'Code', 'Suggestion']);

        foreach ($this->issues as $category => $issues) {
            if ($category === 'sample_analysis') continue;
            $cat = ucfirst(str_replace('_', ' ', $category));

            if ($category === 'function_collisions') {
                foreach ($issues as $func => $defs) {
                    foreach ((array)$defs as $def) {
                        fputcsv($fp, [$cat, $func, $def['file'] ?? '', $def['line'] ?? '', 'Function collision', $def['code'] ?? '', ($def['has_protection'] ?? false) ? 'OK' : "Add function_exists('$func')"]);
                    }
                }
                continue;
            }

            if ($category === 'shortcode_collisions') {
                foreach ($issues as $shortcode => $defs) {
                    foreach ((array)$defs as $def) {
                        fputcsv($fp, [$cat, $shortcode, $def['file'] ?? '', $def['line'] ?? '', 'Shortcode collision', $def['code'] ?? '', 'Use unique shortcode name']);
                    }
                }
                continue;
            }

            foreach ((array)$issues as $item) {
                if (!is_array($item)) continue;
                fputcsv($fp, [
                    $cat,
                    $item['type'] ?? ($item['issue'] ?? ''),
                    $item['file'] ?? '',
                    $item['line'] ?? '',
                    $item['issue'] ?? '',
                    $item['code'] ?? '',
                    $item['suggestion'] ?? 'Review and fix'
                ]);
            }
        }

        fclose($fp);
        $this->download_file($filepath, $filename);
    }
// ...existing code...

