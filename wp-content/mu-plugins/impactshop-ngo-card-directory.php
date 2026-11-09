<?php
/**
 * Plugin Name: ImpactShop NGO Card directory supplement
 * Description: Opt-in public Card identities from existing native Card approvals.
 */
if (!defined('ABSPATH')) { exit; }

final class ImpactShop_Ngo_Card_Directory
{
    public const OPTION = 'impactshop_ngo_card_directory_v1';
    private const LIMIT = 10000;
    private const TEXT_FIELDS = ['city', 'category_label', 'legal_status_label', 'short_mission'];
    private const URL_FIELDS = ['website_url', 'logo_url', 'cover_image_url', 'share_url', 'details_url'];

    public static function bootstrap(): void
    {
        add_filter('rest_request_after_callbacks', [self::class, 'extend'], 10, 3);
    }

    public static function extend($response, $handler, $request)
    {
        // This never changes the ordinary catalog or the VB2026 selection lane.
        if ($request->get_method() !== 'GET' || $request->get_route() !== '/impact/v1/ngo-catalog'
            || (string) $request->get_param('card_directory') !== '1'
            || is_wp_error($response) || !($response instanceof WP_REST_Response)
            || $response->get_status() !== 200) {
            return $response;
        }
        try {
            if (!function_exists('impactshop_vb2026_catalog_query') || !class_exists('ImpactShop_NGO_Card_API')) {
                throw new RuntimeException('Card source unavailable');
            }
            $campaign = (string) ($request->get_param('campaign') ?: IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY);
            if ($campaign !== IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY
                || !in_array($request->get_param('active_only'), [null, '1', 1, true], true)
                || !in_array($request->get_param('featured_only'), [null, '0', 0, false], true)
                || (string) $request->get_param('county') !== '') {
                return new WP_Error('impact_card_directory_filters', 'A Card-lista csak a teljes aktív katalógust használja.', ['status' => 400]);
            }
            $filters = ['campaign' => $campaign, 'active_only' => true, 'featured_only' => false,
                'q' => '', 'city' => '', 'county' => '', 'category' => '', 'legal_status' => ''];
            $primary = impactshop_vb2026_catalog_query($filters, 1, self::LIMIT);
            $items = $primary['items'] ?? null;
            if (!is_array($items) || !$items || !isset($primary['pagination']['total_items'])
                || (int) $primary['pagination']['total_items'] !== count($items)
                || count($items) >= self::LIMIT) {
                throw new RuntimeException('Incomplete primary catalog');
            }
            $registered = get_option(self::OPTION, null);
            if (!is_array($registered)) { throw new RuntimeException('Invalid Card directory'); }
            $entries = self::merge($items, $registered, ImpactShop_NGO_Card_API::get_approved_slug_records());
            $q = self::key((string) $request->get_param('q'));
            $entries = array_values(array_filter($entries, static function (array $entry) use ($request, $q): bool {
                foreach (['city' => 'city', 'category' => 'category_label', 'legal_status' => 'legal_status_label'] as $param => $field) {
                    $value = (string) $request->get_param($param);
                    if ($value !== '' && ($entry[$field] ?? '') !== $value) { return false; }
                }
                return $q === '' || str_contains(self::key($entry['name'] . ' ' . ($entry['short_mission'] ?? '')), $q);
            }));
            $page = max(1, (int) $request->get_param('page'));
            $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?: 48)));
            $total = count($entries);
            $result = new WP_REST_Response(['ok' => true, 'campaign' => $campaign,
                'identity_scope' => 'ngo_card_directory_v1',
                'results' => array_slice($entries, ($page - 1) * $perPage, $perPage),
                'pagination' => ['page' => $page, 'per_page' => $perPage, 'total_items' => $total,
                    'total_pages' => max(1, (int) ceil($total / $perPage))]], 200);
            $result->header('Cache-Control', 'no-store, max-age=0');
            return $result;
        } catch (Throwable $error) {
            return new WP_Error('impact_card_directory_unavailable', 'A teljes Card-lista átmenetileg nem érhető el.', ['status' => 503]);
        }
    }

    /** Public identities only. Missing financial NGO IDs stay missing. No writes. */
    public static function merge(array $primary, array $registered, array $approved): array
    {
        $entries = [];
        $names = [];
        foreach ($primary as $raw) {
            $entry = self::project($raw, true);
            if (isset($entries[$entry['slug']])) { throw new RuntimeException('Duplicate canonical slug'); }
            $entries[$entry['slug']] = $entry;
            $names[self::key($entry['name'])] = true;
        }
        $seen = [];
        foreach ($registered as $raw) {
            if (!is_array($raw)) { throw new RuntimeException('Invalid Card record'); }
            if (($raw['is_active'] ?? false) !== true) { continue; }
            $entry = self::project($raw, false);
            $slug = $entry['slug'];
            if (isset($seen[$slug])) { throw new RuntimeException('Duplicate registered slug'); }
            $seen[$slug] = true;
            if (!isset($approved[$slug])) { continue; }
            if (($approved[$slug]['name'] ?? null) !== $entry['name']) {
                throw new RuntimeException('Native approval name mismatch');
            }
            if (isset($entries[$slug])) {
                if (self::key($entries[$slug]['name']) !== self::key($entry['name'])) {
                    throw new RuntimeException('Canonical slug belongs to another organization');
                }
                continue;
            }
            if (isset($names[self::key($entry['name'])])) { continue; }
            $entries[$slug] = $entry;
            $names[self::key($entry['name'])] = true;
        }
        return array_values($entries);
    }

    private static function project($raw, bool $canonical): array
    {
        if (!is_array($raw) || ($raw['is_active'] ?? false) !== true
            || !is_string($raw['slug'] ?? null) || !is_string($raw['name'] ?? null)) {
            throw new RuntimeException('Invalid public identity');
        }
        $slug = $raw['slug'];
        $name = trim(wp_strip_all_tags($raw['name']));
        if (!preg_match('/^(?:[a-z0-9]|%[0-9a-f]{2})+(?:-(?:[a-z0-9]|%[0-9a-f]{2})+)*$/D', $slug)
            || strlen($slug) > 200 || $name === '' || mb_strlen($name) > 200) {
            throw new RuntimeException('Invalid Card identity');
        }
        $entry = ['slug' => $slug, 'name' => $name, 'is_active' => true];
        if ($canonical) {
            $id = $raw['ngo_id'] ?? null;
            if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || (int) $id <= 0) {
                throw new RuntimeException('Missing canonical NGO ID');
            }
            $entry['ngo_id'] = $id;
        }
        foreach (self::TEXT_FIELDS as $field) {
            if (isset($raw[$field]) && is_string($raw[$field])) {
                $entry[$field] = mb_substr(trim(wp_strip_all_tags($raw[$field])), 0, 320);
            }
        }
        foreach (self::URL_FIELDS as $field) {
            if (!isset($raw[$field]) || !is_string($raw[$field]) || $raw[$field] === '') { continue; }
            $url = $raw[$field];
            $parts = wp_parse_url($url);
            if (!is_array($parts) || !in_array($parts['scheme'] ?? '', $canonical ? ['https', 'http'] : ['https'], true) || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20]/', $url)) {
                if ($canonical) { continue; }
                throw new RuntimeException('Unsafe registered URL');
            }
            $entry[$field] = $url;
        }
        return $entry;
    }

    private static function key(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(remove_accents($name))) ?? '';
    }
}
ImpactShop_Ngo_Card_Directory::bootstrap();
