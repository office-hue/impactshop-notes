<?php
/**
 * Legacy sticky sum shim.
 *
 * The original implementation depended on WPCode snippet helpers that no longer exist.
 * The real shortcode lives inside impactshop-sum-pack.php – this file merely prevents
 * the legacy MU plugin from re-registering the shortcode.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Nothing to do: the new pack registers/maintains the shortcode.
