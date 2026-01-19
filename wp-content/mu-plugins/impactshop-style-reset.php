<?php
/**
 * Plugin Name: ImpactShop Style Reset
 * Description: Törli a sötétkék inline fixet (impactshop-style-fix-inline) és visszaállítja a világos alap hátteret az ImpactShop landing oldalon.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Page ID for the ImpactShop landing (azonos, mint a korábbi inline fix targetje)
const IMPACTSHOP_LANDING_PAGE_ID = 16348;

// Korábbi “világosra resetelő” blokk kikapcsolva, hogy a sötét ImpactShop stílus érvényesüljön.
add_action('wp_head', function () {
    return;
}, 999);
