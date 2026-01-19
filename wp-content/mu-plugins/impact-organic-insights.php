<?php
/**
 * WHAT: Organikus (nem hirdetéses) social metrikák egyszerű REST endpointja (monitoring célra).
 * WHY: Kiegészítő infó (reach/view/engagement) riportálás, adomány elszámolás nélkül.
 * HOW: GET /wp-json/impact/v1/organic-insights -> .codex/logs/organic-insights.json tartalma vagy minta.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
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
            // Mintapélda, amíg nincs éles adat (nem kerül a ledgerbe).
            return [
                'generated_at' => gmdate('c'),
                'note' => 'Dummy organic insights – replace file at .codex/logs/organic-insights.json with real export.',
                'platforms' => [
                    [
                        'platform' => 'meta',
                        'page' => 'sharity.hu',
                        'period' => 'last_7_days',
                        'reach' => 12500,
                        'views' => 7200,
                        'engagements' => 980,
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
