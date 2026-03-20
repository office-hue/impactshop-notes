<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function sharity_points_discount_params_for_pseudo(string $pseudo_id): array
{
    $pseudo_id = strtolower(trim($pseudo_id));
    if ($pseudo_id === '') {
        return [];
    }

    $manager = new Sharity_Points_Manager();
    $snapshot = $manager->get_points_snapshot_for_pseudo($pseudo_id);
    if (empty($snapshot)) {
        return [];
    }

    $level = (string) ($snapshot['current_level'] ?? 'basic');
    $level_manager = new Sharity_Level_Manager();
    $discount = $level_manager->get_discount_percent($level);

    return [
        'sid' => $pseudo_id,
        'sharity_level' => $level,
        'sharity_discount' => (string) $discount,
    ];
}

function sharity_points_discount_params_from_cookie(): array
{
    if (function_exists('impactshop_identity_profile_cookie')) {
        $pseudo = (string) impactshop_identity_profile_cookie();
    } else {
        $pseudo = isset($_COOKIE['impactshop_pseudo_id']) ? (string) $_COOKIE['impactshop_pseudo_id'] : '';
    }

    $pseudo = strtolower(sanitize_text_field($pseudo));
    if ($pseudo === '' || !preg_match('/^[a-z0-9]{10,12}$/', $pseudo)) {
        return [];
    }

    return sharity_points_discount_params_for_pseudo($pseudo);
}
