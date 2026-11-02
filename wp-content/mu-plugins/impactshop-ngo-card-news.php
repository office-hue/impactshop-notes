<?php

/**
 * Plugin Name: ImpactShop NGO Card Public News
 * Description: Read-only access to the existing global Sharity Card announcement.
 */

defined('ABSPATH') || exit;

final class ImpactShop_Ngo_Card_News
{
    public static function register(): void
    {
        register_rest_route('impact/v1', '/ngo-card-news', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'read'],
            'permission_callback' => '__return_true',
        ]);
    }

    private static function announcement($value): ?array
    {
        if (!is_array($value) || !isset($value['text']) || !is_string($value['text'])) {
            return null;
        }
        $text = trim(wp_strip_all_tags($value['text'], true));

        if ($text === '' || preg_match('//u', $text) !== 1) {
            return null;
        }
        // Keep the existing 200-character limit without splitting UTF-8 text.
        preg_match('/^.{0,200}/us', $text, $matched);
        $text = trim($matched[0] ?? '');

        if ($text === '') {
            return null;
        }
        $url = isset($value['url']) && is_string($value['url']) ? trim($value['url']) : '';

        if ($url !== '') {
            $parts = wp_parse_url($url);

            if (strlen($url) > 2048 || !is_array($parts)
                                    || strtolower($parts['scheme'] ?? '') !== 'https'
                                    || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
                                    || preg_match('/[\x00-\x20\x7f]/', $url)) {
                $url = '';
            } else {
                $url = esc_url_raw($url, ['https']);
            }
        }

        return ['text' => $text, 'url' => $url];
    }

    public static function read(): WP_REST_Response
    {
        $stored   = get_option('impactshop_ngo_card_global_announcement', []);
        $stored   = is_array($stored) ? $stored : [];
        $base     = self::announcement($stored) ?? [];
        $filtered = apply_filters('impactshop_ngo_card_share_announcement', $base, $stored);

        return new WP_REST_Response([
            'schema_version' => 'sharity-ngo-card-news-v1',
            'announcement'   => self::announcement($filtered),
        ], 200, [
            'Cache-Control'          => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

add_action('rest_api_init', [ImpactShop_Ngo_Card_News::class, 'register']);
