<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function sharity_normalize_pseudo_id(string $pseudo_id): string
{
    $pseudo_id = strtolower(sanitize_text_field($pseudo_id));
    if ($pseudo_id === '') {
        return '';
    }
    if (function_exists('impactshop_identity_profile_valid_pseudo')) {
        return impactshop_identity_profile_valid_pseudo($pseudo_id) ? $pseudo_id : '';
    }
    return preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id) ? $pseudo_id : '';
}
