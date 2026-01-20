<?php
/**
 * WHAT: Alap brand safety/config seed és helper.
 * WHY: A Publishing Loophoz kell egy központi tiltólista + NGO-specifikus szabály, locale támogatással, DNS nélkül is.
 * HOW: Opcionális seed wp_options-ben, helper függvény a szabályok lekérésére.
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACT_BRAND_SAFETY_OPTION = 'impact_brand_safety_rules';

function impact_brand_safety_default_rules(): array
{
    return [
        'locales' => [
            'hu_HU' => [
                'banned_keywords' => [
                    'politika', 'választás', 'kormány', 'pornó', 'adult', 'alkohol', 'drog',
                    'gyűlölet', 'uszítás', 'szerencsejáték'
                ],
            ],
            'en_US' => [
                'banned_keywords' => [
                    'politics', 'election', 'government', 'explicit', 'adult', 'alcohol', 'drug',
                    'hate', 'gambling'
                ],
            ],
        ],
        'ngo_sensitive' => [
            'allatvedok' => ['hunting', 'meat industry', 'vadhús', 'trófea'],
            'kornyezetvedok' => ['fossil fuel', 'oil spill', 'szén', 'lignite'],
        ],
        'cta_whitelist' => ['app.sharity.hu', 'impactshop.hu'],
    ];
}

/**
 * Lekéri a brand safety szabályokat (option vagy default).
 */
function impact_brand_safety_get_rules(): array
{
    $rules = get_option(IMPACT_BRAND_SAFETY_OPTION);
    if (empty($rules) || !is_array($rules)) {
        $rules = impact_brand_safety_default_rules();
    }
    return $rules;
}

/**
 * Egyszerű seed: csak akkor ír, ha nincs beállítva.
 */
add_action('muplugins_loaded', function () {
    $existing = get_option(IMPACT_BRAND_SAFETY_OPTION);
    if (empty($existing)) {
        update_option(IMPACT_BRAND_SAFETY_OPTION, impact_brand_safety_default_rules(), true);
    }
});

/**
 * Admin notice, ha nincs szabály konfigurálva.
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $rules = get_option(IMPACT_BRAND_SAFETY_OPTION);
    if (empty($rules)) {
        echo '<div class="notice notice-warning"><p><strong>Impact Publisher</strong>: Brand safety szabályok nincsenek beállítva. A default seed van érvényben, állítsd be az admin felületen.</p></div>';
    }
});
