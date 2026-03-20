<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Level_Manager
{
    public const LEVELS = [
        'legend' => ['percentile_min' => 97, 'percentile_max' => 100, 'multiplier' => 1.25, 'vote_ad' => 6, 'vote_sponsor' => 6, 'discount' => 100],
        'platinum' => ['percentile_min' => 90, 'percentile_max' => 97, 'multiplier' => 1.20, 'vote_ad' => 5, 'vote_sponsor' => 5, 'discount' => 90],
        'gold' => ['percentile_min' => 75, 'percentile_max' => 90, 'multiplier' => 1.15, 'vote_ad' => 4, 'vote_sponsor' => 4, 'discount' => 80],
        'silver' => ['percentile_min' => 45, 'percentile_max' => 75, 'multiplier' => 1.10, 'vote_ad' => 3, 'vote_sponsor' => 3, 'discount' => 70],
        'bronze' => ['percentile_min' => 15, 'percentile_max' => 45, 'multiplier' => 1.05, 'vote_ad' => 2, 'vote_sponsor' => 2, 'discount' => 60],
        'basic' => ['percentile_min' => 0, 'percentile_max' => 15, 'multiplier' => 1.00, 'vote_ad' => 1, 'vote_sponsor' => 1, 'discount' => 50],
    ];
    private const LEVEL_ORDER = ['basic', 'bronze', 'silver', 'gold', 'platinum', 'legend'];

    public function get_level_config(string $level): array
    {
        return self::LEVELS[$level] ?? self::LEVELS['basic'];
    }

    public function get_discount_percent(string $level): int
    {
        $config = $this->get_level_config($level);
        return (int) ($config['discount'] ?? 0);
    }

    public function calculate_level(int $user_id): string
    {
        global $wpdb;

        $points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        return $this->calculate_level_for_points($points);
    }

    public function calculate_level_for_pseudo(string $pseudo_id): string
    {
        global $wpdb;

        $points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT points_total FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        return $this->calculate_level_for_points($points);
    }

    public function check_and_update_level(int $user_id, int $points_total): array
    {
        global $wpdb;

        $current_level = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT current_level FROM {$wpdb->prefix}user_points WHERE user_id = %d",
            $user_id
        ));

        if ($current_level === '') {
            $current_level = 'basic';
        }

        $new_level = $this->calculate_level($user_id);

        if ($current_level !== $new_level) {
            $wpdb->update(
                "{$wpdb->prefix}user_points",
                [
                    'current_level' => $new_level,
                    'level_upgraded_at' => current_time('mysql'),
                    'level_locked_until' => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
                ],
                ['user_id' => $user_id]
            );

            $wpdb->insert("{$wpdb->prefix}level_history", [
                'user_id' => $user_id,
                'pseudo_id' => null,
                'old_level' => $current_level,
                'new_level' => $new_level,
                'points_at_change' => $points_total,
                'reason' => $this->compare_levels($new_level, $current_level) > 0 ? 'upgrade' : 'downgrade',
                'created_at' => current_time('mysql'),
            ]);

            do_action('sharity_level_changed', $user_id, $current_level, $new_level, $points_total);

            return [
                'changed' => true,
                'old_level' => $current_level,
                'new_level' => $new_level,
            ];
        }

        return [
            'changed' => false,
            'old_level' => $current_level,
            'new_level' => $current_level,
        ];
    }

    public function check_and_update_level_for_pseudo(string $pseudo_id, int $points_total): array
    {
        global $wpdb;

        $current_level = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT current_level FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
            $pseudo_id
        ));

        if ($current_level === '') {
            $current_level = 'basic';
        }

        $new_level = $this->calculate_level_for_pseudo($pseudo_id);

        if ($current_level !== $new_level) {
            $wpdb->update(
                "{$wpdb->prefix}user_points",
                [
                    'current_level' => $new_level,
                    'level_upgraded_at' => current_time('mysql'),
                    'level_locked_until' => gmdate('Y-m-d H:i:s', strtotime('+7 days')),
                ],
                ['pseudo_id' => $pseudo_id]
            );

            $wpdb->insert("{$wpdb->prefix}level_history", [
                'user_id' => null,
                'pseudo_id' => $pseudo_id,
                'old_level' => $current_level,
                'new_level' => $new_level,
                'points_at_change' => $points_total,
                'reason' => $this->compare_levels($new_level, $current_level) > 0 ? 'upgrade' : 'downgrade',
                'created_at' => current_time('mysql'),
            ]);

            do_action('sharity_level_changed', $pseudo_id, $current_level, $new_level, $points_total);

            return [
                'changed' => true,
                'old_level' => $current_level,
                'new_level' => $new_level,
            ];
        }

        return [
            'changed' => false,
            'old_level' => $current_level,
            'new_level' => $current_level,
        ];
    }

    private function calculate_level_for_points(int $points): string
    {
        if ($points <= 0) {
            return 'basic';
        }

        return $this->calculate_level_by_absolute_points($points);
    }

    private function calculate_level_by_absolute_points(int $points): string
    {
        if ($points >= 250000) {
            return 'legend';
        }
        if ($points >= 120000) {
            return 'platinum';
        }
        if ($points >= 50000) {
            return 'gold';
        }
        if ($points >= 15000) {
            return 'silver';
        }
        if ($points >= 2000) {
            return 'bronze';
        }
        return 'basic';
    }

    private function compare_levels(string $a, string $b): int
    {
        $order = array_flip(self::LEVEL_ORDER);
        return ($order[$a] ?? 0) <=> ($order[$b] ?? 0);
    }
}
