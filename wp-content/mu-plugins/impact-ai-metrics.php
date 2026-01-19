<?php
/**
 * WHAT: AI performance metrika placeholder (/impact/v1/ai-metrics) + /healthz meta kiegészítés.
 * WHY: Alap mutatók (latencia, intent QA score) dummy értékkel, amíg nincs valódi mérés.
 * HOW: GET /wp-json/impact/v1/ai-metrics → statikus/dummy payload; filter a /healthz kimenethez (ha létezik impactshop-health-endpoint plugin).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/ai-metrics', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $file = WP_CONTENT_DIR . '/../.codex/logs/ai-metrics.jsonl';
            $latest = null;
            $avg = null;
            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    $last = array_pop($lines);
                    $decoded = json_decode($last, true);
                    if (is_array($decoded)) {
                        $latest = $decoded;
                    }
                    // egyszerű átlag az utolsó 50 sorból
                    $slice = array_slice($lines, -50);
                    $sumLat = $sumAcc = $sumQa = 0;
                    $n = 0;
                    foreach ($slice as $ln) {
                        $d = json_decode($ln, true);
                        if (!is_array($d)) {
                            continue;
                        }
                        $sumLat += floatval($d['latency_ms_p95'] ?? 0);
                        $sumAcc += floatval($d['intent_accuracy'] ?? 0);
                        $sumQa += floatval($d['qa_score'] ?? 0);
                        $n++;
                    }
                    if ($n > 0) {
                        $avg = [
                            'latency_ms_p95_avg' => round($sumLat / $n, 2),
                            'intent_accuracy_avg' => round($sumAcc / $n, 3),
                            'qa_score_avg' => round($sumQa / $n, 2),
                            'samples' => $n,
                        ];
                    }
                }
            }
            if (!$latest) {
                $latest = [
                    'ts' => gmdate('c'),
                    'latency_ms_p95' => 1200,
                    'intent_accuracy' => 0.82,
                    'qa_score' => 4.1,
                    'notes' => 'Dummy metrics; replace with real collector when available.',
                    'dummy' => true,
                ];
            }
            return [
                'latest' => $latest,
                'avg' => $avg,
            ];
        },
    ]);
});

// Healthz kiegészítés, ha a health endpoint meglévő filtert használ
add_filter('impactshop_health_extra', function ($extra) {
    $metrics = apply_filters('impact_ai_metrics_get', null);
    if (!$metrics || !is_array($metrics)) {
        $metrics = [
            'latency_ms_p95' => 1200,
            'intent_accuracy' => 0.82,
            'qa_score' => 4.1,
            'dummy' => true,
            'ts' => gmdate('c'),
        ];
    }
    $extra['ai_metrics'] = $metrics;
    return $extra;
});
