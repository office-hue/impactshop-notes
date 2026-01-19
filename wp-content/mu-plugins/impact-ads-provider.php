<?php
/**
 * WHAT: Egyszerű REST provider stub NormalizedAdMetric tömbre (fetch endpoint).
 * WHY: Az ads:fetch-to-ledger parancs igényel egy HTTP forrást, ami a NormalizedAdMetric sémát adja vissza.
 * HOW: GET /wp-json/impact/v1/ads-metrics?platform=<meta|google|tiktok|youtube>&since=YYYY-MM-DD&until=YYYY-MM-DD
 *      Visszatér egy üres lista, vagy ha IMPACT_ADS_SAMPLE=1, egy mintametrikát (dokumentációs célra).
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/ads-metrics', [
        'methods' => 'GET',
        'permission_callback' => '__return_true', // read-only, publikus; ha kell, később secret headerrel szűkíthető
        'callback' => function (WP_REST_Request $request) {
            $platform = sanitize_text_field($request->get_param('platform'));
            $since = sanitize_text_field($request->get_param('since'));
            $until = sanitize_text_field($request->get_param('until'));
            $with_sample = getenv('IMPACT_ADS_SAMPLE') === '1';

            if (empty($platform)) {
                return new WP_Error('missing_platform', 'platform param kötelező', ['status' => 400]);
            }

            $metrics = [];
            if ($with_sample) {
                $metrics[] = [
                    'schema_version' => 'v1',
                    'platform' => $platform,
                    'account_id' => 'demo-account',
                    'campaign_id' => 'demo-camp',
                    'ad_id' => 'demo-ad',
                    'date' => $since ?: date('Y-m-d'),
                    'views' => 100,
                    'clicks' => 10,
                    'spend' => 1234,
                    'est_donation' => 1500,
                    'ledger_source' => 'view',
                    'ngo_code' => null,
                    'advertiser_code' => 'demo-adv',
                    'cap' => null,
                    'meta' => [
                        'since' => $since,
                        'until' => $until,
                    ],
                ];
            }

            return [
                'platform' => $platform,
                'since' => $since,
                'until' => $until,
                'metrics' => $metrics,
            ];
        },
    ]);

    // Organikus (nem hirdetéses) social metrikák – monitoring célra, adomány elszámolás nélkül.
    register_rest_route('impact/v1', '/organic-insights', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function () {
            $file = WP_CONTENT_DIR . '/../.codex/logs/organic-insights.json';
            if (file_exists($file)) {
                $json = file_get_contents($file);
                $data = json_decode($json, true);
                if (is_array($data)) {
                    return $data;
                }
            }
            return [
                'generated_at' => gmdate('c'),
                'note' => 'Dummy organic insights – replace file at .codex/logs/organic-insights.json with real export.',
                'platforms' => [
                    [
                        'platform' => 'meta',
                        'page' => 'sharity.hu',
                        'period' => 'last_7_days',
                        'reach' => 12345,
                        'views' => 6789,
                        'engagements' => 456,
                        'top_post' => [
                            'title' => 'Impact Shop ismertető',
                            'views' => 3100,
                            'reactions' => 420,
                            'comments' => 38,
                            'shares' => 55,
                        ],
                    ],
                    [
                        'platform' => 'youtube',
                        'channel' => 'SharityHU',
                        'period' => 'last_7_days',
                        'views' => 5400,
                        'watch_time_minutes' => 8200,
                        'subscribers_delta' => 24,
                        'top_video' => [
                            'title' => 'Impact Challenge teaser',
                            'views' => 2100,
                            'likes' => 180,
                            'comments' => 22,
                        ],
                    ],
                    [
                        'platform' => 'tiktok',
                        'profile' => '@sharity',
                        'period' => 'last_7_days',
                        'views' => 6100,
                        'likes' => 740,
                        'comments' => 65,
                        'shares' => 48,
                        'top_clip' => [
                            'title' => 'Videós támogatás magyarázat',
                            'views' => 1900,
                            'likes' => 260,
                        ],
                    ],
                ],
            ];
        },
    ]);
});
