<?php
/**
 * Plugin Name: ImpactShop NGO Card
 * Description: REST endpoint + shortcode for NGO embed cards (share/pass support).
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACTSHOP_NGO_CARD_DEFAULT_FROM')) {
    define('IMPACTSHOP_NGO_CARD_DEFAULT_FROM', '2025-10-23');
}

if (!defined('IMPACTSHOP_NGO_CARD_DEFAULT_RATE_HUF')) {
    define('IMPACTSHOP_NGO_CARD_DEFAULT_RATE_HUF', defined('IMPACTSHOP_SUM_DEFAULT_RATE_HUF') ? IMPACTSHOP_SUM_DEFAULT_RATE_HUF : 392);
}

if (!defined('IMPACTSHOP_NGO_CARD_DONATION_RATE')) {
    define('IMPACTSHOP_NGO_CARD_DONATION_RATE', defined('IMPACTSHOP_SUM_DEFAULT_DONATION_RATE') ? IMPACTSHOP_SUM_DEFAULT_DONATION_RATE : 0.5);
}

if (!function_exists('impactshop_ngo_card_today')) {
    function impactshop_ngo_card_today(): string
    {
        return current_time('Y-m-d');
    }
}

if (!function_exists('impactshop_ngo_card_load_map')) {
    function impactshop_ngo_card_load_map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $path = trailingslashit(ABSPATH) . 'ngo_codes.csv';
        if (!file_exists($path)) {
            return $map;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return $map;
        }

        $row = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1) {
                continue;
            }
            $label = isset($data[0]) ? trim((string)$data[0]) : '';
            $slug = isset($data[1]) ? sanitize_title($data[1]) : '';
            if ($label !== '' && $slug !== '') {
                $map[$slug] = $label;
            }
        }
        fclose($handle);

        return $map;
    }
}

if (!function_exists('impactshop_ngo_card_label')) {
    function impactshop_ngo_card_label(string $slug, string $fallback = ''): string
    {
        $slug = sanitize_title($slug);
        $map = impactshop_ngo_card_load_map();
        if ($slug && isset($map[$slug])) {
            return $map[$slug];
        }
        return $fallback !== '' ? $fallback : $slug;
    }
}

if (!function_exists('impactshop_ngo_card_fetch_totals')) {
    function impactshop_ngo_card_fetch_totals(string $from, string $to, string $status = 'all')
    {
        if (function_exists('impactshop_totals_collect')) {
            return impactshop_totals_collect($from, $to, $status, 'ngo');
        }

        if (function_exists('impactshop_sum_fetch_totals')) {
            return impactshop_sum_fetch_totals([
                'from' => $from,
                'to' => $to,
                'status' => $status,
                'group' => 'ngo',
            ]);
        }

        $url = add_query_arg(
            [
                'from' => $from,
                'to' => $to,
                'status' => $status,
                'group' => 'ngo',
            ],
            home_url('/wp-json/impactshop/v1/totals')
        );
        $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($body) ? $body : new WP_Error('ngo_card_json', 'Invalid totals response');
    }
}

if (!function_exists('impactshop_ngo_card_format_huf')) {
    function impactshop_ngo_card_format_huf(float $value): string
    {
        return number_format($value, 0, ',', ' ') . ' Ft';
    }
}

if (!function_exists('impactshop_ngo_card_format_eur')) {
    function impactshop_ngo_card_format_eur(float $value): string
    {
        return number_format($value, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('impactshop_ngo_card_build_payload')) {
    function impactshop_ngo_card_build_payload(string $slug, array $args = []): array
    {
        $slug = sanitize_title($slug);
        $from = isset($args['from']) ? (string)$args['from'] : IMPACTSHOP_NGO_CARD_DEFAULT_FROM;
        $to = isset($args['to']) && $args['to'] !== '' ? (string)$args['to'] : impactshop_ngo_card_today();
        $status = isset($args['status']) ? (string)$args['status'] : 'all';
        $rate = isset($args['rate_huf']) ? (float)$args['rate_huf'] : (float)IMPACTSHOP_NGO_CARD_DEFAULT_RATE_HUF;
        $currency = strtoupper((string)($args['currency'] ?? 'HUF'));

        $totals = impactshop_ngo_card_fetch_totals($from, $to, $status);
        if (is_wp_error($totals)) {
            return [
                'error' => $totals->get_error_message(),
                'slug' => $slug,
            ];
        }

        $rows = is_array($totals['rows'] ?? null) ? $totals['rows'] : [];
        $commission = 0.0;
        $rank = null;
        $map = [];

        foreach ($rows as $row) {
            $rowSlug = sanitize_title((string)($row['ngo'] ?? ''));
            if ($rowSlug === '') {
                continue;
            }
            $amount = (float)($row['commission'] ?? 0);
            $map[$rowSlug] = ($map[$rowSlug] ?? 0.0) + $amount;
            if ($rowSlug === $slug) {
                $commission += $amount;
            }
        }

        if ($map) {
            arsort($map);
            $rank = 1;
            foreach (array_keys($map) as $key) {
                if ($key === $slug) {
                    break;
                }
                $rank++;
            }
        }

        $donation = max(0.0, $commission) * IMPACTSHOP_NGO_CARD_DONATION_RATE;
        $amountHuf = $donation * $rate;
        $amountEur = $donation;

        $label = impactshop_ngo_card_label($slug);
        $baseUrl = rtrim(home_url('/impactshop/'), '/') . '/';
        $ctaUrl = $baseUrl . '?d1=' . rawurlencode($slug) . '&ngo=' . rawurlencode($slug) . '&src=ngo-card';

        $announcementText = trim((string)get_option('impactshop_ngo_card_announcement_text', 'ImpactShop frissítés alatt'));
        $announcementUrl = trim((string)get_option('impactshop_ngo_card_announcement_url', ''));
        $tombolaUrl = trim((string)get_option('impactshop_ngo_card_tombola_url', ''));
        $videoUrl = trim((string)get_option('impactshop_ngo_card_video_support_url', ''));

        return [
            'slug' => $slug,
            'name' => $label,
            'amount' => [
                'huf' => (int)round($amountHuf),
                'eur' => round($amountEur, 2),
                'formatted' => impactshop_ngo_card_format_huf($amountHuf),
                'eur_formatted' => impactshop_ngo_card_format_eur($amountEur),
            ],
            'rank' => $rank,
            'badge_status' => null,
            'next_milestone' => null,
            'last_updated' => current_time('mysql'),
            'share_url' => home_url('/ngo/' . rawurlencode($slug) . '/share/'),
            'cta_url' => $ctaUrl,
            'go_url' => $ctaUrl,
            'fillout_url' => null,
            'requires_fillout' => false,
            'announcement' => [
                'text' => $announcementText,
                'url' => $announcementUrl,
            ],
            'tombola_url' => $tombolaUrl,
            'video_support_url' => $videoUrl,
        ];
    }
}

add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/ngo-card/(?P<slug>[a-z0-9\\-]+)', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $slug = sanitize_title($request->get_param('slug'));
            $from = sanitize_text_field($request->get_param('from') ?: IMPACTSHOP_NGO_CARD_DEFAULT_FROM);
            $to = sanitize_text_field($request->get_param('to') ?: impactshop_ngo_card_today());
            $status = sanitize_text_field($request->get_param('status') ?: 'all');
            $rate = (float)sanitize_text_field($request->get_param('rate_huf') ?: IMPACTSHOP_NGO_CARD_DEFAULT_RATE_HUF);
            $currency = strtoupper(sanitize_text_field($request->get_param('currency') ?: 'HUF'));

            $cacheKey = 'impactshop_ngo_card_' . md5($slug . '|' . $from . '|' . $to . '|' . $status . '|' . $rate . '|' . $currency);
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                $resp = new WP_REST_Response($cached, 200);
                $resp->header('Cache-Control', 'public, max-age=900, stale-while-revalidate=3600');
                return $resp;
            }

            $payload = impactshop_ngo_card_build_payload($slug, [
                'from' => $from,
                'to' => $to,
                'status' => $status,
                'rate_huf' => $rate,
                'currency' => $currency,
            ]);

            if (!empty($payload['error'])) {
                return new WP_REST_Response([
                    'code' => 'ngo_card_error',
                    'message' => $payload['error'],
                ], 503);
            }

            $payload['_cache'] = [
                'generated_at' => time(),
                'age' => 0,
                'stale' => false,
                'expires' => gmdate('c', time() + 900),
                'stale_expires' => gmdate('c', time() + 3600),
            ];

            set_transient($cacheKey, $payload, 900);
            $resp = new WP_REST_Response($payload, 200);
            $resp->header('Cache-Control', 'public, max-age=900, stale-while-revalidate=3600');
            return $resp;
        },
    ]);
});

if (!shortcode_exists('impact_ngo_card')) {
    add_shortcode('impact_ngo_card', function ($atts) {
        $a = shortcode_atts([
            'ngo' => '',
            'label' => '',
            'from' => IMPACTSHOP_NGO_CARD_DEFAULT_FROM,
            'to' => '',
            'status' => 'all',
            'rate_huf' => (string)IMPACTSHOP_NGO_CARD_DEFAULT_RATE_HUF,
            'currency' => 'HUF',
            'accent' => '#7c3aed',
        ], $atts, 'impact_ngo_card');

        $slug = sanitize_title($a['ngo']);
        if ($slug === '') {
            return '';
        }

        $payload = impactshop_ngo_card_build_payload($slug, [
            'from' => $a['from'],
            'to' => $a['to'],
            'status' => $a['status'],
            'rate_huf' => (float)$a['rate_huf'],
            'currency' => $a['currency'],
        ]);

        $display = $a['label'] !== '' ? $a['label'] : ($payload['name'] ?? $slug);
        $amount = $payload['amount']['formatted'] ?? '';
        $accent = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';
        $from = esc_html($a['from']);
        $to = esc_html($a['to'] !== '' ? $a['to'] : impactshop_ngo_card_today());

        ob_start(); ?>
        <style>
          .impact-ngo-card {
            --accent: <?php echo esc_html($accent); ?>;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            border-radius: 18px;
            padding: 18px 20px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.08), #ffffff 60%);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
          }
          .impact-ngo-card__title {
            margin: 0 0 6px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
          }
          .impact-ngo-card__amount {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--accent);
            margin: 0 0 6px;
          }
          .impact-ngo-card__meta {
            font-size: 0.85rem;
            color: #64748b;
          }
        </style>
        <div class="impact-ngo-card">
          <h3 class="impact-ngo-card__title"><?php echo esc_html($display); ?></h3>
          <div class="impact-ngo-card__amount"><?php echo esc_html($amount); ?></div>
          <div class="impact-ngo-card__meta"><?php echo esc_html($from . ' → ' . $to); ?></div>
        </div>
        <?php
        return ob_get_clean();
    });
}
