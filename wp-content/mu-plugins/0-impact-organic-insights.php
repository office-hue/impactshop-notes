<?php
/**
 * Loader shim to ensure impact-organic-insights.php betöltődik elsőként.
 */
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/impact-organic-insights.php';
