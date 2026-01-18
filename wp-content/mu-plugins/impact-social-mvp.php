<?php
/**
 * Plugin Name: Impact Social MVP
 * Description: Minimal social ticker REST API + shortcode scaffold for Impact Shop.
 * Author: Arnold (solo operator)
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('Impact_Social_MVP')) {
    final class Impact_Social_MVP
    {
        private const DEFAULT_LIMIT = 10;
        private static $assets_enqueued = false;

        public static function bootstrap(): void
        {
            if (! self::is_enabled()) {
                return;
            }

            add_action('rest_api_init', [__CLASS__, 'register_routes']);
            add_shortcode('impact_social_ticker', [__CLASS__, 'render_shortcode']);
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        }

        public static function enqueue_assets(): void
        {
            if (self::$assets_enqueued) {
                return;
            }

            $css = <<<CSS
.impact-social-ticker {margin:1.5rem 0;padding:1.05rem;border:1px solid rgba(15,23,42,0.08);border-radius:16px;background:#ffffff;box-shadow:0 18px 36px rgba(15,23,42,0.08);}
.impact-social-ticker--dark {background:#0f172a;color:#f8fafc;border-color:rgba(248,250,252,0.08);}
.impact-social-ticker__list {list-style:none;margin:0;padding:0;display:grid;gap:1.25rem;}
.impact-social-ticker--grid .impact-social-ticker__list {grid-template-columns:repeat(auto-fit,minmax(260px,1fr));}
.impact-social-ticker__item {padding:1.1rem;border-radius:14px;border:1px solid rgba(15,23,42,0.06);background:#f9fafc;transition:transform .2s ease,box-shadow .2s ease;}
.impact-social-ticker__item:hover {transform:translateY(-2px);box-shadow:0 16px 28px rgba(15,23,42,0.12);}
.impact-social-ticker__item--owner {border-color:#4f46e5;background:rgba(79,70,229,0.08);box-shadow:0 18px 36px rgba(79,70,229,0.18);}
.impact-social-ticker__headline {font-size:0.95rem;font-weight:600;color:#1e293b;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;line-height:1.45;}
.impact-social-ticker__initials {background:#4f46e5;color:#fff;font-size:0.75rem;padding:0.25rem 0.55rem;border-radius:999px;letter-spacing:0.05em;text-transform:uppercase;box-shadow:0 6px 12px rgba(79,70,229,0.35);}
.impact-social-ticker__badge {display:inline-flex;align-items:center;background:#16a34a;color:#fff;font-size:0.68rem;font-weight:700;margin-right:.4rem;padding:0.18rem 0.55rem;border-radius:999px;text-transform:uppercase;letter-spacing:0.1em;}
.impact-social-ticker__meta {margin-top:.55rem;font-size:0.78rem;color:#475569;display:flex;flex-wrap:wrap;gap:.9rem;align-items:center;}
.impact-social-ticker__status {text-transform:uppercase;font-size:0.7rem;font-weight:700;letter-spacing:0.14em;color:#2563eb;}
.impact-social-ticker__status--pending {color:#ca8a04;}
.impact-social-ticker__cta {margin-top:.85rem;display:flex;flex-direction:column;gap:.4rem;}
.impact-social-ticker__share-buttons {display:flex;flex-wrap:wrap;gap:.45rem;}
.impact-social-ticker__share-btn {display:inline-flex;align-items:center;justify-content:center;padding:.48rem .95rem;border-radius:999px;background:#2563eb;color:#fff;font-weight:600;font-size:0.78rem;text-decoration:none;border:none;cursor:pointer;transition:transform .2s ease,box-shadow .2s ease,background .2s ease;box-shadow:0 10px 20px rgba(37,99,235,0.22);}
.impact-social-ticker__share-btn:hover {transform:translateY(-1px);box-shadow:0 14px 28px rgba(37,99,235,0.25);}
.impact-social-ticker__share-btn:focus {outline:2px solid #1d4ed8;outline-offset:2px;}
.impact-social-ticker__share-btn--x {background:#0f172a;}
.impact-social-ticker__share-btn--linkedin {background:#0a66c2;}
.impact-social-ticker__share-btn--messenger {background:#0084ff;}
.impact-social-ticker__share-btn--threads,.impact-social-ticker__share-btn--instagram,.impact-social-ticker__share-btn--tiktok {background:#1f2937;}
.impact-social-ticker__share-btn--copy {background:#0ea5e9;}
.impact-social-ticker__share-btn--copied {background:#16a34a;}
.impact-social-ticker__hint {font-size:0.72rem;color:#475569;}
.impact-social-ticker__cta--info,.impact-social-ticker__cta--pending {font-size:0.72rem;color:#475569;background:rgba(15,23,42,0.05);padding:.55rem .8rem;border-radius:10px;}
.impact-social-ticker--dark .impact-social-ticker__item {background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.08);}
.impact-social-ticker--dark .impact-social-ticker__headline {color:#e2e8f0;}
.impact-social-ticker--dark .impact-social-ticker__meta {color:#cbd5f5;}
.impact-social-ticker--dark .impact-social-ticker__share-btn {box-shadow:0 10px 20px rgba(99,102,241,0.25);}
.impact-social-ticker__empty {margin:0;font-size:0.9rem;color:#475569;text-align:center;}
@media (max-width:640px){.impact-social-ticker__headline{flex-direction:column;align-items:flex-start;}}
CSS;

            wp_register_style('impact-social-mvp', false);
            wp_enqueue_style('impact-social-mvp');
            wp_add_inline_style('impact-social-mvp', $css);

            $js = <<<JS
(function(){
  function copyToClipboard(btn,message){
    if (navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(message).then(function(){
        btn.classList.add('impact-social-ticker__share-btn--copied');
        setTimeout(function(){ btn.classList.remove('impact-social-ticker__share-btn--copied'); }, 2000);
      }).catch(function(){
        window.prompt('Másold ki a megosztáshoz:', message);
      });
    } else {
      window.prompt('Másold ki a megosztáshoz:', message);
    }
  }

  document.addEventListener('click', function(event){
    var btn = event.target.closest('.impact-social-ticker__share-btn');
    if (!btn) {
      return;
    }

    var type = btn.getAttribute('data-share-type') || 'url';
    if (type === 'copy') {
      event.preventDefault();
      var message = btn.getAttribute('data-share-message') || '';
      if (message) {
        copyToClipboard(btn, message);
      }
    }
  });
})();
JS;

            wp_register_script('impact-social-mvp', '', [], null, true);
            wp_enqueue_script('impact-social-mvp');
            wp_add_inline_script('impact-social-mvp', $js);

            self::$assets_enqueued = true;
        }

        private static function is_enabled(): bool
        {
            if (defined('IMPACT_SOCIAL_MVP_ENABLED')) {
                return (bool) IMPACT_SOCIAL_MVP_ENABLED;
            }

            $option = get_option('impact_social_mvp_enabled');
            return (bool) $option;
        }

        public static function register_routes(): void
        {
            register_rest_route(
                'impact/v1',
                '/social/ticker',
                [
                    'methods'             => 'GET',
                    'callback'            => [__CLASS__, 'handle_ticker_request'],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'limit' => [
                            'description'       => 'Number of records to return.',
                            'type'              => 'integer',
                            'default'           => self::DEFAULT_LIMIT,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => static function ($value): bool {
                                return $value > 0 && $value <= 50;
                            },
                        ],
                        'status' => [
                            'description'       => 'Ledger status filter (approved, pending, all).',
                            'type'              => 'string',
                            'default'           => 'approved',
                            'sanitize_callback' => [__CLASS__, 'sanitize_status'],
                            'validate_callback' => static function ($value): bool {
                                $value = strtolower((string) $value);
                                return in_array($value, ['approved', 'pending', 'all'], true);
                            },
                        ],
                    ],
                ]
            );

            register_rest_route(
                'impact/v1',
                '/leaderboard/donors',
                [
                    'methods'             => 'GET',
                    'callback'            => [__CLASS__, 'handle_donors_request'],
                    'permission_callback' => '__return_true',
                    'args'                => [
                        'limit' => [
                            'description'       => 'Number of donors to return.',
                            'type'              => 'integer',
                            'default'           => self::DEFAULT_LIMIT,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => static function ($value): bool {
                                return $value > 0 && $value <= 50;
                            },
                        ],
                        'status' => [
                            'description'       => 'Ledger status filter (approved, pending, all).',
                            'type'              => 'string',
                            'default'           => 'approved',
                            'sanitize_callback' => [__CLASS__, 'sanitize_status'],
                            'validate_callback' => static function ($value): bool {
                                $value = strtolower((string) $value);
                                return in_array($value, ['approved', 'pending', 'all'], true);
                            },
                        ],
                    ],
                ]
            );
        }

        public static function handle_ticker_request(\WP_REST_Request $request): \WP_REST_Response
        {
            $limit = (int) $request->get_param('limit');
            if ($limit < 1 || $limit > 50) {
                $limit = self::DEFAULT_LIMIT;
            }

            $statusParam = (string) ($request->get_param('status') ?: 'approved');
            $statuses = self::resolve_statuses($statusParam);

            $records = self::query_ledger($limit, $statuses);
            $records = self::filter_records($records);
            if (empty($records) || self::is_records_stale($records) || ! self::has_meaningful_ngo($records)) {
                $records = self::fallback_activity($limit);
            }
            $records = self::filter_records($records);

            return new \WP_REST_Response(
                [
                    'data'       => $records,
                    'meta'       => [
                        'count' => count($records),
                        'limit' => $limit,
                        'status'=> $statusParam,
                    ],
                    'generated'  => gmdate('c'),
                ]
            );
        }

        public static function handle_donors_request(\WP_REST_Request $request): \WP_REST_Response
        {
            $limit = (int) $request->get_param('limit');
            if ($limit < 1 || $limit > 50) {
                $limit = self::DEFAULT_LIMIT;
            }

            $statusParam = (string) ($request->get_param('status') ?: 'approved');
            $statuses = self::resolve_statuses($statusParam);

            $rows = self::query_top_donors($limit, $statuses);

            return new \WP_REST_Response(
                [
                    'data'       => $rows,
                    'meta'       => [
                        'count'  => count($rows),
                        'limit'  => $limit,
                        'status' => $statusParam,
                    ],
                    'generated'  => gmdate('c'),
                ]
            );
        }

        public static function sanitize_status($value): string
        {
            $value = strtolower(trim((string) $value));
            if (! in_array($value, ['approved', 'pending', 'all'], true)) {
                return 'approved';
            }
            return $value;
        }

        /**
         * @return string[]
         */
        private static function resolve_statuses(string $statusParam): array
        {
            $statusParam = strtolower($statusParam);
            if ($statusParam === 'all') {
                return [];
            }

            if ($statusParam === 'pending') {
                return ['pending'];
            }

            return ['approved'];
        }

        private static function query_ledger(int $limit, array $statuses): array
        {
            global $wpdb;

            $table = $wpdb->prefix . 'impact_ledger';
            $limit = max(1, min($limit, 50));

            $sql = "SELECT pseudo_id, ngo_slug, ngo_display, shop_slug, shop_display, amount_huf, channel, status, happened_at
                     FROM {$table}";

            $params = [];
            if (! empty($statuses)) {
                $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $sql .= " WHERE status IN ({$placeholders})";
                $params = array_merge($params, $statuses);
            } else {
                $sql .= " WHERE status IN ('approved','pending')";
            }

            $sql .= " ORDER BY happened_at DESC LIMIT %d";
            $params[] = $limit;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

            if (empty($rows)) {
                return [];
            }

            $domain = home_url('/go/');

            $currentPseudo = self::get_current_pseudo();

            return array_map(
                static function (array $row) use ($domain, $currentPseudo): array {
                    $rawPseudo = (string) ($row['pseudo_id'] ?? '');
                    $initials = self::mask_pseudo($rawPseudo);
                    $displayName = self::resolve_display_name($rawPseudo, $initials);
                    $normalizedPseudo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawPseudo));
                    $ngoSlug = sanitize_title($row['ngo_slug'] ?? '');
                    $ngoName = sanitize_text_field($row['ngo_display'] ?? $ngoSlug);
                    $amount = (int) ($row['amount_huf'] ?? 0);
                    $shopSlug = sanitize_title($row['shop_slug'] ?? '');
                    $shopName = sanitize_text_field($row['shop_display'] ?? $shopSlug);
                    $channel = sanitize_key($row['channel'] ?? 'unknown');
                    $timestamp = $row['happened_at'] ?? gmdate('c');
                    $status = strtolower($row['status'] ?? 'approved');

                    $isOwner = $currentPseudo && $normalizedPseudo !== '' && $normalizedPseudo === $currentPseudo;
                    $shareMessage = null;
                    $shareLinks = [];

                    $landingUrl = add_query_arg(
                        [
                            'ngo'         => $ngoSlug,
                            'shop'        => $shopSlug,
                            'utm_source'  => 'impacthub_social',
                            'utm_medium'  => 'share',
                            'utm_campaign'=> 'sprint7_mvp',
                        ],
                        $domain
                    );

                    if ($isOwner && in_array($status, ['approved', 'pending'], true)) {
                        $shareMessage = self::build_share_message($ngoName, $shopName, $amount, $status);
                        $shareLinks = self::build_share_links($landingUrl, $shareMessage);
                    }

                    return [
                        'pseudo_initials' => $initials,
                        'display_name'    => $displayName,
                        'ngo_slug'        => $ngoSlug,
                        'ngo_display'     => $ngoName,
                        'shop_slug'       => $shopSlug,
                        'shop_display'    => $shopName,
                        'amount_huf'      => $amount,
                        'channel'         => $channel,
                        'status'          => $status,
                        'happened_at'     => gmdate('c', strtotime($timestamp)),
                        'is_owner'        => (bool) $isOwner,
                        'can_share'       => (bool) ($isOwner && in_array($status, ['approved', 'pending'], true)),
                        'share_links'     => $shareLinks,
                        'share_message'   => $shareMessage,
                        'landing_url'     => esc_url_raw($landingUrl),
                    ];
                },
                $rows
            );
        }

        private static function query_top_donors(int $limit, array $statuses): array
        {
            global $wpdb;

            $table = $wpdb->prefix . 'impact_ledger';
            $limit = max(1, min($limit, 50));

            $sql = "SELECT pseudo_id, SUM(amount_huf) as total_huf
                     FROM {$table}";
            $params = [];

            if (! empty($statuses)) {
                $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $sql .= " WHERE status IN ({$placeholders})";
                $params = array_merge($params, $statuses);
            } else {
                $sql .= " WHERE status IN ('approved','pending')";
            }

            $sql .= " AND pseudo_id IS NOT NULL AND pseudo_id != ''";
            $sql .= " GROUP BY pseudo_id ORDER BY total_huf DESC LIMIT %d";
            $params[] = $limit;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

            if (empty($rows)) {
                return [];
            }

            return array_map(
                static function (array $row): array {
                    $rawPseudo = (string) ($row['pseudo_id'] ?? '');
                    $initials = self::mask_pseudo($rawPseudo);
                    $displayName = self::resolve_display_name($rawPseudo, $initials);
                    $total = (int) ($row['total_huf'] ?? 0);

                    return [
                        'display_name'    => $displayName,
                        'pseudo_initials' => $initials,
                        'amount_huf'      => $total,
                    ];
                },
                $rows
            );
        }

        private static function resolve_display_name(string $pseudoId, string $fallback): string
        {
            if ($pseudoId !== '' && function_exists('impactshop_identity_profile_load')) {
                $nickname = impactshop_identity_profile_load($pseudoId);
                if (is_string($nickname) && $nickname !== '') {
                    return $nickname;
                }
            }
            return $fallback;
        }

        private static function filter_records(array $records): array
        {
            $out = [];
            foreach ($records as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $ngoSlug = sanitize_title($row['ngo_slug'] ?? ($row['ngo_display'] ?? ''));
                if ($ngoSlug === 'teszt-ngo') {
                    continue;
                }
                $out[] = $row;
            }
            return $out;
        }

        private static function has_meaningful_ngo(array $records): bool
        {
            foreach ($records as $row) {
                $ngo = (string) ($row['ngo_display'] ?? $row['ngo_slug'] ?? '');
                if ($ngo !== '' && preg_match('/[a-záéíóöőúüű]/i', $ngo)) {
                    return true;
                }
            }
            return false;
        }

        private static function is_records_stale(array $records): bool
        {
            $latest = 0;
            foreach ($records as $row) {
                $ts = strtotime((string) ($row['happened_at'] ?? ''));
                if ($ts > $latest) {
                    $latest = $ts;
                }
            }
            if ($latest <= 0) {
                return true;
            }
            return $latest < (time() - 21 * DAY_IN_SECONDS);
        }

        private static function fallback_activity(int $limit): array
        {
            $from = defined('IMPACTSHOP_SUM_DEFAULT_FROM')
                ? IMPACTSHOP_SUM_DEFAULT_FROM
                : date('Y-m-d', strtotime('-60 days'));
            $to = date('Y-m-d');
            $request = new \WP_REST_Request('GET', '/impact/v1/activity');
            $request->set_param('limit', $limit);
            $request->set_param('from', $from);
            $request->set_param('to', $to);
            $response = rest_do_request($request);
            if ($response->is_error()) {
                return [];
            }
            $data = $response->get_data();
            if (!is_array($data) || empty($data)) {
                return [];
            }

            $rateHuf = defined('IMPACT_SUM_RATE_HUF') ? (float) IMPACT_SUM_RATE_HUF : 392;
            $items = [];
            foreach ($data as $row) {
                $text = isset($row['text']) ? (string) $row['text'] : '';
                if ($text === '') {
                    continue;
                }
                $parts = preg_split('/\s*•\s*/u', $text);
                $ngo = $parts[0] ?? '';
                if ($ngo === '') {
                    continue;
                }
                if (sanitize_title($ngo) === 'teszt-ngo') {
                    continue;
                }
                $amountEur = 0.0;
                if (!empty($parts[1])) {
                    $amt = str_replace(['€', ' '], '', $parts[1]);
                    $amt = str_replace(',', '.', $amt);
                    if (is_numeric($amt)) {
                        $amountEur = (float) $amt;
                    }
                }
                $amountHuf = (int) round($amountEur * $rateHuf);
                $happened = $parts[2] ?? gmdate('Y-m-d H:i');

                $items[] = [
                    'pseudo_initials' => '??*',
                    'display_name'    => '??*',
                    'ngo_slug'        => sanitize_title($ngo),
                    'ngo_display'     => $ngo,
                    'shop_slug'       => 'impactshop',
                    'shop_display'    => 'Impact Shop',
                    'amount_huf'      => $amountHuf,
                    'status'          => 'approved',
                    'happened_at'     => $happened,
                    'can_share'       => false,
                    'is_owner'        => false,
                    'share_links'     => [],
                    'share_message'   => '',
                ];
            }
            return $items;
        }

        private static function mask_pseudo(string $pseudoId): string
        {
            if ($pseudoId === '') {
                return '??*';
            }

            $clean = preg_replace('/[^A-Za-z0-9]/', '', $pseudoId);
            if ($clean === '') {
                return '??*';
            }

            return strtoupper(substr($clean, 0, 2)) . '*';
        }

        private static function get_current_pseudo(): ?string
        {
            $candidates = [
                $_COOKIE['impactshop_pseudo_id'] ?? null,
                $_COOKIE['impact_pseudo_id'] ?? null,
                $_COOKIE['impact_pseudo'] ?? null,
                $_GET['impact_pseudo_id'] ?? null,
            ];

            foreach ($candidates as $candidate) {
                if (! $candidate) {
                    continue;
                }

                $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $candidate);
                if ($clean !== '') {
                    return strtoupper($clean);
                }
            }

            return null;
        }

        private static function build_share_message(string $ngoName, string $shopName, int $amount, string $status): string
        {
            $formatted = number_format_i18n($amount);
            return sprintf('Én most támogattam a %s ügyét %s Ft-tal a(z) %s vásárlással az Impact Shopban.', $ngoName, $formatted, $shopName !== '' ? $shopName : 'Impact Shop');
        }

        private static function build_share_links(string $landingUrl, string $shareMessage): array
        {
            $encodedLanding = rawurlencode($landingUrl);
            $encodedMessage = rawurlencode($shareMessage);

            return [
                [
                    'platform' => 'facebook',
                    'label'    => 'Facebook',
                    'type'     => 'url',
                    'url'      => esc_url_raw("https://www.facebook.com/sharer/sharer.php?u={$encodedLanding}&quote={$encodedMessage}"),
                ],
                [
                    'platform' => 'x',
                    'label'    => 'X',
                    'type'     => 'url',
                    'url'      => esc_url_raw("https://twitter.com/intent/tweet?text={$encodedMessage}&url={$encodedLanding}"),
                ],
                [
                    'platform' => 'linkedin',
                    'label'    => 'LinkedIn',
                    'type'     => 'url',
                    'url'      => esc_url_raw("https://www.linkedin.com/sharing/share-offsite/?url={$encodedLanding}"),
                ],
                [
                    'platform' => 'messenger',
                    'label'    => 'Messenger',
                    'type'     => 'url',
                    'url'      => esc_url_raw("https://m.me/?link={$encodedLanding}&text={$encodedMessage}"),
                ],
                [
                    'platform' => 'threads',
                    'label'    => 'Threads',
                    'type'     => 'copy',
                    'message'  => $shareMessage,
                ],
                [
                    'platform' => 'instagram',
                    'label'    => 'Instagram',
                    'type'     => 'copy',
                    'message'  => $shareMessage,
                ],
                [
                    'platform' => 'tiktok',
                    'label'    => 'TikTok',
                    'type'     => 'copy',
                    'message'  => $shareMessage,
                ],
                [
                    'platform' => 'copy',
                    'label'    => 'Szöveg másolása',
                    'type'     => 'copy',
                    'message'  => $shareMessage,
                ],
            ];
        }

        public static function render_shortcode(array $attrs = [], string $content = null, string $tag = ''): string
        {
            if (! self::is_enabled()) {
                return '<div class="impact-social-ticker impact-social-ticker--disabled">A közösségi ticker jelenleg inaktív.</div>';
            }

            $atts = shortcode_atts(
                [
                    'limit'  => self::DEFAULT_LIMIT,
                    'layout' => 'list',
                    'theme'  => 'light',
                    'status' => 'approved',
                ],
                $attrs,
                $tag
            );

            $currentPseudo = self::get_current_pseudo() ?: 'anon';
            $cacheKey = 'impact_social_ticker_' . md5($currentPseudo . '|' . serialize($atts));
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }

            $request = new \WP_REST_Request('GET', '/impact/v1/social/ticker');
            $request->set_param('limit', (int) $atts['limit']);
            $request->set_param('status', self::sanitize_status($atts['status']));
            $response = rest_do_request($request);

            if ($response->is_error()) {
                return '<div class="impact-social-ticker impact-social-ticker--error">A közösségi ticker jelenleg nem elérhető.</div>';
            }

            $data = $response->get_data();
            $items = $data['data'] ?? [];

            $html = self::render_markup($items, $atts['layout'], $atts['theme']);
            set_transient($cacheKey, $html, MINUTE_IN_SECONDS);

            return $html;
        }

        private static function render_markup(array $items, string $layout, string $theme): string
        {
            $layoutClass = $layout === 'grid' ? 'impact-social-ticker--grid' : 'impact-social-ticker--list';
            $themeClass = $theme === 'dark' ? 'impact-social-ticker--dark' : 'impact-social-ticker--light';

            if (empty($items)) {
                return '<div class="impact-social-ticker ' . esc_attr($layoutClass . ' ' . $themeClass) . '"><p class="impact-social-ticker__empty">Légy te az első, aki támogat!</p></div>';
            }

            $rows = array_map(
                static function (array $item): string {
                    $amount = number_format_i18n((int) ($item['amount_huf'] ?? 0));
                    $ngo = esc_html($item['ngo_display'] ?? $item['ngo_slug'] ?? '');
                    $shop = esc_html($item['shop_display'] ?? $item['shop_slug'] ?? '');
                    $initials = esc_html($item['pseudo_initials'] ?? '??*');
                    $displayName = esc_html($item['display_name'] ?? $initials);
                    $channel = esc_html($item['channel'] ?? '');
                    $timestamp = esc_html($item['happened_at'] ?? '');
                    $statusRaw = strtolower($item['status'] ?? 'approved');
                    $status = esc_html($statusRaw);
                    $isOwner = ! empty($item['is_owner']);
                    $shareLinksData = is_array($item['share_links'] ?? null) ? $item['share_links'] : [];
                    $shareMessage = isset($item['share_message']) ? (string) $item['share_message'] : '';
                    $canShare = ! empty($item['can_share']) && ! empty($shareLinksData);

                    $cta = '';
                    if ($canShare) {
                        $hint = '';
                        if ($status === 'pending') {
                            $hint = '<span class="impact-social-ticker__hint">Jóváhagyásra vár – általában pár perc, de már megoszthatod.</span>';
                        }

                        $buttons = [];
                        foreach ($shareLinksData as $link) {
                            if (! is_array($link)) {
                                continue;
                            }
                            $platform = isset($link['platform']) ? sanitize_title($link['platform']) : 'share';
                            $label = esc_html($link['label'] ?? ucfirst($platform));
                            $type = $link['type'] ?? 'url';

                            if ($type === 'copy') {
                                $message = isset($link['message']) ? esc_attr($link['message']) : esc_attr($shareMessage);
                                $buttons[] = sprintf(
                                    '<button type="button" class="impact-social-ticker__share-btn impact-social-ticker__share-btn--%1$s" data-share-type="copy" data-share-platform="%1$s" data-share-message="%2$s">%3$s</button>',
                                    $platform,
                                    $message,
                                    $label
                                );
                                continue;
                            }

                            $url = esc_url($link['url'] ?? '#');
                            $fallback = ! empty($link['fallback']) ? ' data-share-fallback="' . esc_url($link['fallback']) . '"' : '';
                            $buttons[] = sprintf(
                                '<a href="%2$s" class="impact-social-ticker__share-btn impact-social-ticker__share-btn--%1$s" target="_blank" rel="noopener" data-share-type="url" data-share-platform="%1$s"%3$s>%4$s</a>',
                                $platform,
                                $url,
                                $fallback,
                                $label
                            );
                        }

                        $buttonsHtml = implode('', $buttons);
                        $cta = '<div class="impact-social-ticker__cta"><div class="impact-social-ticker__share-buttons">' . $buttonsHtml . '</div>' . $hint . '</div>';
                    } elseif ($isOwner) {
                        $cta = '<div class="impact-social-ticker__cta impact-social-ticker__cta--pending">Támogatás feldolgozás alatt – hamarosan megosztható.</div>';
                    } else {
                        $cta = '<div class="impact-social-ticker__cta impact-social-ticker__cta--info">Csak a saját támogatásodat tudod megosztani — használd ugyanazt az eszközt, amellyel támogattál.</div>';
                    }

                    $ownerBadge = $isOwner ? '<span class="impact-social-ticker__badge">Ez a te támogatásod</span>' : '';
                    $itemClass = 'impact-social-ticker__item impact-social-ticker__item--status-' . sanitize_title($statusRaw);
                    if ($isOwner) {
                        $itemClass .= ' impact-social-ticker__item--owner';
                    }
                    $itemClassAttr = esc_attr($itemClass);

                    return sprintf(
                        '<li class="%10$s">
                            <div class="impact-social-ticker__headline">%8$s<span class="impact-social-ticker__initials">%1$s</span> támogatta a(z) <strong>%2$s</strong> ügyet %3$s Ft-tal a(z) <strong>%4$s</strong> vásárlással.</div>
                            <div class="impact-social-ticker__meta">
                                <span class="impact-social-ticker__channel">%5$s</span>
                                <time datetime="%6$s">%6$s</time>
                                <span class="impact-social-ticker__status impact-social-ticker__status--%7$s">%7$s</span>
                            </div>
                            %9$s
                        </li>',
                        $displayName,
                        $ngo,
                        $amount,
                        $shop,
                        $channel,
                        $timestamp,
                        $status,
                        $ownerBadge,
                        $cta,
                        $itemClassAttr
                    );
                },
                $items
            );

            return '<div class="impact-social-ticker ' . esc_attr($layoutClass . ' ' . $themeClass) . '"><ul class="impact-social-ticker__list">' . implode('', $rows) . '</ul></div>';
        }

        public static function render_donors_shortcode(array $attrs = [], string $content = null, string $tag = ''): string
        {
            if (! self::is_enabled()) {
                return '<div class="impact-social-ticker impact-social-ticker--disabled">A toplista jelenleg inaktív.</div>';
            }

            $atts = shortcode_atts(
                [
                    'limit'  => self::DEFAULT_LIMIT,
                    'theme'  => 'light',
                    'status' => 'approved',
                ],
                $attrs,
                $tag
            );

            $request = new \WP_REST_Request('GET', '/impact/v1/leaderboard/donors');
            $request->set_param('limit', (int) $atts['limit']);
            $request->set_param('status', self::sanitize_status($atts['status']));
            $response = rest_do_request($request);

            if ($response->is_error()) {
                return '<div class="impact-social-ticker impact-social-ticker--error">A toplista jelenleg nem elérhető.</div>';
            }

            $data = $response->get_data();
            $items = $data['data'] ?? [];

            $themeClass = $atts['theme'] === 'dark' ? 'impact-social-ticker--dark' : 'impact-social-ticker--light';

            if (empty($items)) {
                return '<div class="impact-social-ticker ' . esc_attr($themeClass) . '"><p class="impact-social-ticker__empty">Még nincs toplistás adat.</p></div>';
            }

            $rows = array_map(
                static function (array $item): string {
                    $amount = number_format_i18n((int) ($item['amount_huf'] ?? 0));
                    $displayName = esc_html($item['display_name'] ?? $item['pseudo_initials'] ?? '??*');
                    return sprintf(
                        '<li class="impact-social-ticker__item"><span class="impact-social-ticker__initials">%1$s</span> <strong>%2$s Ft</strong></li>',
                        $displayName,
                        $amount
                    );
                },
                $items
            );

            return '<div class="impact-social-ticker ' . esc_attr($themeClass) . '"><ul class="impact-social-ticker__list">' . implode('', $rows) . '</ul></div>';
        }
    }
}

Impact_Social_MVP::bootstrap();
add_shortcode('impact_top_donors', ['Impact_Social_MVP', 'render_donors_shortcode']);
