<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Sharity_Referral_Manager
{
    public const REFERRAL_TTL_DAYS = 60;

    public function get_or_create_for_user(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'user_referrals';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT referral_code, status, expires_at FROM {$table}
             WHERE referrer_user_id = %d
             ORDER BY id DESC
             LIMIT 1",
            $user_id
        ), ARRAY_A);

        if ($row && !empty($row['referral_code'])) {
            return [
                'referral_code' => (string) $row['referral_code'],
                'status' => (string) ($row['status'] ?? 'pending'),
                'expires_at' => $row['expires_at'] ?? null,
            ];
        }

        $code = $this->generate_unique_code();
        $expires = gmdate('Y-m-d H:i:s', strtotime('+' . self::REFERRAL_TTL_DAYS . ' days'));

        $wpdb->insert($table, [
            'referrer_user_id' => $user_id,
            'referrer_pseudo_id' => null,
            'referred_pseudo_id' => null,
            'referral_code' => $code,
            'status' => 'pending',
            'click_count' => 0,
            'created_at' => current_time('mysql'),
            'expires_at' => $expires,
        ]);

        return [
            'referral_code' => $code,
            'status' => 'pending',
            'expires_at' => $expires,
        ];
    }

    public function get_or_create_for_pseudo(string $pseudo_id): array
    {
        $pseudo_id = $this->normalize_pseudo_id($pseudo_id);
        if ($pseudo_id === '') {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'user_referrals';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT referral_code, status, expires_at FROM {$table}
             WHERE referrer_pseudo_id = %s
             ORDER BY id DESC
             LIMIT 1",
            $pseudo_id
        ), ARRAY_A);

        if ($row && !empty($row['referral_code'])) {
            return [
                'referral_code' => (string) $row['referral_code'],
                'status' => (string) ($row['status'] ?? 'pending'),
                'expires_at' => $row['expires_at'] ?? null,
            ];
        }

        $code = $this->generate_unique_code();
        $expires = gmdate('Y-m-d H:i:s', strtotime('+' . self::REFERRAL_TTL_DAYS . ' days'));

        $wpdb->insert($table, [
            'referrer_user_id' => null,
            'referrer_pseudo_id' => $pseudo_id,
            'referred_pseudo_id' => null,
            'referral_code' => $code,
            'status' => 'pending',
            'click_count' => 0,
            'created_at' => current_time('mysql'),
            'expires_at' => $expires,
        ]);

        return [
            'referral_code' => $code,
            'status' => 'pending',
            'expires_at' => $expires,
        ];
    }

    public function expire_old_referrals(): int
    {
        global $wpdb;
        $now = current_time('mysql');

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}user_referrals
             SET status = 'expired'
             WHERE status IN ('pending','active')
               AND expires_at IS NOT NULL
               AND expires_at < %s",
            $now
        ));

        return (int) $updated;
    }

    private function generate_unique_code(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'user_referrals';

        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(wp_generate_password(10, false, false));
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE referral_code = %s",
                $code
            ));
            if ($exists === 0) {
                return $code;
            }
        }

        return strtoupper(wp_generate_password(12, false, false));
    }

    private function normalize_pseudo_id(string $pseudo_id): string
    {
        if (function_exists('sharity_normalize_pseudo_id')) {
            return sharity_normalize_pseudo_id($pseudo_id);
        }

        $pseudo_id = strtolower(trim($pseudo_id));
        if (function_exists('impactshop_identity_profile_valid_pseudo')) {
            return impactshop_identity_profile_valid_pseudo($pseudo_id) ? $pseudo_id : '';
        }
        return preg_match('/^[a-z0-9]{10,12}$/', $pseudo_id) ? $pseudo_id : '';
    }
}
