<?php
/**
 * ImpactShop fragment cache diagnosztika
 *
 * Használat (WP gyökérből):
 *   wp eval-file scripts/diagnostics/fragment-probe.php type=netflix atts="max_items=3" query="d1=bator" preview=200
 *
 * type:
 *   - netflix  : `[impactshop_netflix]` (query paramok: d1, amb, src)
 *   - deals    : `[impact_deals_netflix]`
 *   - coupons  : `[impact_coupons_netflix]` (query paramok: d1, amb, src)
 *   - raw      : add meg a teljes `key=impactshop_fragment_...` értéket
 *
 * Opcionális kapcsolók:
 *   atts   = URL query formátumú rövidkód attribútumok (pl. `max_items=3&featured_only=1`)
 *   query  = URL query formátumú d1/amb/src értékek (netflix/coupons)
 *   preview= hány karaktert írjon ki a cache-elt HTML elejéből (alap: 160)
 */

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

if (!defined('ABSPATH')) {
    echo "Ezt a szkriptet a WordPress gyökeréből futtasd: wp eval-file ...\n";
    return;
}

function ifp_collect_args(): array
{
    $argv = $_SERVER['argv'] ?? [];
    $scriptIndex = 0;
    foreach ($argv as $idx => $value) {
        if (str_ends_with($value, 'fragment-probe.php')) {
            $scriptIndex = $idx;
            break;
        }
    }
    $slice = array_slice($argv, $scriptIndex + 1);
    $out = [];
    foreach ($slice as $item) {
        if (strpos($item, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $item, 2);
        $out[$k] = $v;
    }
    return $out;
}

function ifp_parse_pairs(string $input): array
{
    $input = trim($input);
    if ($input === '') {
        return [];
    }
    parse_str($input, $result);
    return is_array($result) ? $result : [];
}

$args = ifp_collect_args();
$type = strtolower($args['type'] ?? 'netflix');
$attsInput = ifp_parse_pairs($args['atts'] ?? '');
$queryInput = ifp_parse_pairs($args['query'] ?? '');
$previewLen = max(0, intval($args['preview'] ?? 160));

$defaultsNetflix = [
    'categories'    => '',
    'show_all'      => '1',
    'arrows'        => '1',
    'card_w'        => '150',
    'card_h'        => '110',
    'gap'           => '16',
    'max_items'     => '0',
    'shuffle'       => '0',
    'featured_only' => '0',
    'new_days'      => '14',
    'deals_badge'   => '1',
    'ga4'           => '1',
    'autoplay'      => '1',
    'interval'      => '3000',
];

$defaultsDeals = [
    'limit'     => '12',
    'autoplay'  => '1',
    'interval'  => '3000',
    'direction' => 'right',
    'ga4'       => '1',
];

$defaultsCoupons = [
    'autoplay'    => '1',
    'interval'    => '3000',
    'arrows'      => '1',
    'card_w'      => '320',
    'logo_h'      => '48',
    'gap'         => '18',
    'max_items'   => '0',
    'show_code'   => '1',
    'show_expiry' => '1',
];

$key = '';

switch ($type) {
    case 'netflix':
        $atts = shortcode_atts($defaultsNetflix, $attsInput, 'impactshop_netflix');
        $query = array_merge([
            'd1'  => function_exists('impactshop_q') ? impactshop_q('d1') : '',
            'amb' => function_exists('impactshop_q') ? impactshop_q('amb') : '',
            'src' => function_exists('impactshop_q') ? impactshop_q('src') : 'impactshop',
        ], $queryInput);
        $fragmentParams = [
            'atts' => $atts,
            'd1'   => $query['d1'] ?? '',
            'amb'  => $query['amb'] ?? '',
            'src'  => $query['src'] ?? 'impactshop',
        ];
        $key = 'impactshop_fragment_' . md5('impactshop_netflix_' . wp_json_encode($fragmentParams));
        break;
    case 'deals':
        $atts = shortcode_atts($defaultsDeals, $attsInput, 'impact_deals_netflix');
        $key = 'impactshop_fragment_' . md5('impact_deals_netflix_' . wp_json_encode($atts));
        break;
    case 'coupons':
        $atts = shortcode_atts($defaultsCoupons, $attsInput, 'impact_coupons_netflix');
        $query = array_merge([
            'd1'  => function_exists('impactshop_q') ? impactshop_q('d1') : '',
            'amb' => function_exists('impactshop_q') ? impactshop_q('amb') : '',
            'src' => function_exists('impactshop_q') ? impactshop_q('src') : 'impactshop',
        ], $queryInput);
        $fragmentParams = [
            'atts' => $atts,
            'd1'   => $query['d1'] ?? '',
            'amb'  => $query['amb'] ?? '',
            'src'  => $query['src'] ?? 'impactshop',
        ];
        $key = 'impactshop_fragment_' . md5('impact_coupons_netflix_' . wp_json_encode($fragmentParams));
        break;
    case 'raw':
        $key = $args['key'] ?? '';
        break;
    default:
        echo "Ismeretlen type: {$type}. Használható értékek: netflix/deals/coupons/raw\n";
        return;
}

if ($key === '') {
    echo "Nem sikerült fragment kulcsot képezni. Adj meg legalább egy key=... értéket raw módban.\n";
    return;
}

echo "Fragment key: {$key}\n";
$value = get_transient($key);
if ($value === false) {
    echo "➡️  Nem található transient (lehet, hogy még nem épült fel, vagy másik object cache-ben van).\n";
    return;
}

$length = strlen($value);
echo "✅ Transient megtalálva, méret: {$length} byte\n";
if ($previewLen > 0) {
    $snippet = substr($value, 0, $previewLen);
    echo "--- HTML preview ---\n{$snippet}\n--- /preview ---\n";
}
