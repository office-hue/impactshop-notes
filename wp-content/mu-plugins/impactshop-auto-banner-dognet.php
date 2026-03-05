<?php
/**
 * Dognet coupons -> autobanner ingest
 *
 * Fetches Dognet /coupons/filter and inserts into wp_impactshop_auto_banners.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_AUTO_BANNER_DOGNET_LIMIT = 120;
const IMPACTSHOP_AUTO_BANNER_DOGNET_CRON = 'impactshop_auto_banner_dognet_cron';

add_action('muplugins_loaded', 'impactshop_auto_banner_dognet_boot');

function impactshop_auto_banner_dognet_boot(): void
{
    add_filter('cron_schedules', 'impactshop_auto_banner_dognet_schedules');
    if (!wp_next_scheduled(IMPACTSHOP_AUTO_BANNER_DOGNET_CRON)) {
        wp_schedule_event(time() + 300, 'impactshop_6h', IMPACTSHOP_AUTO_BANNER_DOGNET_CRON);
    }
    add_action(IMPACTSHOP_AUTO_BANNER_DOGNET_CRON, 'impactshop_auto_banner_dognet_run');
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::add_command('impactshop auto-banner dognet', 'impactshop_auto_banner_dognet_cli');
    }
}

function impactshop_auto_banner_dognet_schedules(array $schedules): array
{
    if (!isset($schedules['impactshop_6h'])) {
        $schedules['impactshop_6h'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'ImpactShop 6 óránként',
        ];
    }
    return $schedules;
}

function impactshop_auto_banner_dognet_cli(): void
{
    $result = impactshop_auto_banner_dognet_run();
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::success(sprintf(
            'Dognet autobanner ingest: %d processed, %d inserted, %d skipped',
            $result['processed'],
            $result['inserted'],
            $result['skipped']
        ));
    }
}

function impactshop_auto_banner_dognet_run(): array
{
    $result = [
        'processed' => 0,
        'inserted' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    if (!function_exists('dognet_api_request') || !function_exists('impactshop_get_shops')) {
        $result['errors'][] = 'Dognet helpers missing';
        return $result;
    }

    $ad_id = defined('DOGNET_AD_CHANNEL_ID') ? (int) DOGNET_AD_CHANNEL_ID : 0;
    $body = ['filter' => ['validity' => ['eq' => 'present']], 'per-page' => 500];
    if ($ad_id) {
        $body['ad_channel_id'] = $ad_id;
    }
    $resp = dognet_api_request('POST', '/coupons/filter', $body);
    if (is_wp_error($resp)) {
        $result['errors'][] = $resp->get_error_message();
        return $result;
    }
    $items = [];
    if (isset($resp['data']) && is_array($resp['data'])) {
        $items = $resp['data'];
    } elseif (isset($resp['items']) && is_array($resp['items'])) {
        $items = $resp['items'];
    }
    if (!$items) {
        return $result;
    }

    $shops = impactshop_get_shops();
    $cid_to_shop = [];
    foreach ($shops as $shop) {
        $cid = 0;
        if (function_exists('dognet_extract_campaign_id_from_base')) {
            $cid = (int) dognet_extract_campaign_id_from_base($shop['dognet_base'] ?? '');
        }
        if ($cid > 0) {
            $cid_to_shop[$cid] = $shop;
        }
    }

    $processed = 0;
    foreach ($items as $it) {
        if ($processed >= IMPACTSHOP_AUTO_BANNER_DOGNET_LIMIT) {
            break;
        }
        $cid = 0;
        foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
            if (isset($it[$k])) {
                $cid = is_array($it[$k]) ? (int) ($it[$k]['id'] ?? 0) : (int) $it[$k];
                break;
            }
        }
        if (!$cid || !isset($cid_to_shop[$cid])) {
            $result['skipped']++;
            continue;
        }
        $shop = $cid_to_shop[$cid];
        $shop_slug = (string) ($shop['shop_slug'] ?? '');
        if ($shop_slug === '' || (function_exists('impactshop_is_whitelisted_partner') && !impactshop_is_whitelisted_partner($shop_slug))) {
            $result['skipped']++;
            continue;
        }

        $title = '';
        foreach (['title','name','label','description'] as $k) {
            if (!empty($it[$k])) { $title = trim((string) $it[$k]); break; }
        }
        $pct = null;
        foreach (['percent','discount_percent','discount_pct'] as $k) {
            if (isset($it[$k]) && is_numeric($it[$k])) { $pct = (float) $it[$k]; break; }
        }
        $amt = null;
        foreach (['amount','discount_amount','value_off'] as $k) {
            if (isset($it[$k]) && is_numeric($it[$k])) { $amt = (float) $it[$k]; break; }
        }
        $cur = '';
        foreach (['currency','cur'] as $k) {
            if (!empty($it[$k])) { $cur = strtoupper(trim((string) $it[$k])); break; }
        }
        if ($title === '') {
            if ($pct !== null && $pct > 0) {
                $title = sprintf('%s – %s%% kedvezmény', $shop['name'] ?? $shop_slug, rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'));
            } elseif ($amt !== null && $amt > 0) {
                $title = sprintf('%s – %s %s kedvezmény', $shop['name'] ?? $shop_slug, rtrim(rtrim(number_format($amt, 2, '.', ''), '0'), '.'), $cur ?: '€');
            } else {
                $title = (string) ($shop['name'] ?? $shop_slug);
            }
        }

        $url = '';
        foreach (['url','cta_url','link','landing_url','landingUrl','product_url'] as $k) {
            if (!empty($it[$k])) { $url = trim((string) $it[$k]); break; }
        }
        if ($url === '') {
            $url = (string) ($shop['product_url'] ?? '');
        }
        if ($url === '') {
            $result['skipped']++;
            continue;
        }

        $image = '';
        foreach (['image_url','image','logo','logo_url'] as $k) {
            if (!empty($it[$k])) { $image = trim((string) $it[$k]); break; }
        }
        if ($image === '') {
            $image = (string) ($shop['logo'] ?? '');
        }

        $discount_pct = $pct !== null ? (int) round($pct) : 0;
        $offer = [
            'title' => $title,
            'url' => $url,
            'image_url' => $image,
            'shop_slug' => $shop_slug,
            'price_old' => 0,
            'price_new' => 0,
            'discount_percent' => $discount_pct,
        ];
        if (function_exists('impactshop_auto_banner_from_offer')) {
            impactshop_auto_banner_from_offer($offer, ['source' => 'dognet', 'status' => 'active']);
            $result['inserted']++;
        }
        $processed++;
    }

    $result['processed'] = $processed;
    return $result;
}
