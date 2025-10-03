<?php
/**
 * Plugin Name: Impact Safety Loader (MU)
 * Description: Hibrid biztonsági keretréteg (guard + circuit breaker + fallback). SAFE MODE alatt nem regisztrál új hookot.
 * Author: Impact
 * Version: 1.0.0
 */

// --- Core: osztály és helper mindig betölt ---

if (!class_exists('Impact_Safety')) {
    final class Impact_Safety {

        /** Globális SAFE MODE */
        public static function is_safe_mode(): bool {
            return defined('IMPACT_SAFE_MODE') && IMPACT_SAFE_MODE;
        }

        /** Modul-flag (option) olvasása */
        public static function flag(string $key): bool {
            return (bool) get_option('impact_disable_' . $key, false);
        }

        /**
         * Canary user?
         * - Admin, vagy ims=1, vagy ims_beta cookie
         * - ims=1 esetén beállít egy 30 napos canary cookie-t (Secure; HttpOnly; SameSite=Lax), ha még nem küldtünk headert
         */
        public static function is_canary(): bool {
            if (function_exists('current_user_can') && current_user_can('manage_options')) {
                return true;
            }

            if (isset($_GET['ims']) && $_GET['ims'] === '1') {
                if (!headers_sent()) {
                    $expires = time() + 30 * DAY_IN_SECONDS;
                    // PHP 7.3+ forma, WordPress alatt működik
                    @setcookie('ims_beta', '1', [
                        'expires'  => $expires,
                        'path'     => '/',
                        'secure'   => is_ssl(),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }
                return true;
            }

            return (isset($_COOKIE['ims_beta']) && $_COOKIE['ims_beta'] === '1');
        }

        /**
         * Guard wrapper modulokhoz.
         * - SAFE MODE / modulflag / BETA_ONLY_ADMINS ellenőrzés
         * - Throwable elkapás + circuit breaker trip
         * Vissza: callback eredménye vagy WP_Error
         */
        public static function guard(string $module, callable $fn) {
            if (self::is_safe_mode()) {
                return new WP_Error('impact_safe_mode', 'SAFE MODE aktív');
            }

            if (self::flag($module)) {
                return new WP_Error('impact_module_disabled', "Modul letiltva: {$module}");
            }

            if (defined('IMPACT_BETA_ONLY_ADMINS') && IMPACT_BETA_ONLY_ADMINS && ! self::is_canary()) {
                return new WP_Error('impact_beta_only', 'Csak canary felhasználóknak engedélyezett');
            }

            try {
                return $fn();
            } catch (\Throwable $t) {
                error_log("Impact Safety: {$module} hiba - " . $t->getMessage());
                self::trip($module);
                return new WP_Error('impact_execution_error', $t->getMessage());
            }
        }

        /**
         * Circuit breaker: 60s ablak, 10+ hiba → modul letiltása
         */
        public static function trip(string $module): void {
            $key = "impact_errors_{$module}";
            $cnt = (int) get_transient($key);
            $cnt++;
            // 60 mp ablak
            set_transient($key, $cnt, 60);

            if ($cnt >= 10) {
                update_option('impact_disable_' . $module, true);
                /**
                 * impact_circuit_breaker
                 *
                 * @param string $module
                 * @param int    $count
                 */
                do_action('impact_circuit_breaker', $module, $cnt);
                error_log("Impact Safety: Circuit OPEN → {$module} (count={$cnt})");
            }
        }
    }
}

/**
 * Biztonságos fallback URL generátor (globálisan használható helper)
 */
if (!function_exists('impact_safe_fallback_url')) {
    function impact_safe_fallback_url(array $context = []): string {
        // 1) Deeplink
        if (!empty($context['deeplink'])) {
            $u = $context['deeplink'];
            if (function_exists('wp_http_validate_url')) {
                if (wp_http_validate_url($u)) {
                    return $u;
                }
            } elseif (filter_var($u, FILTER_VALIDATE_URL)) {
                return $u;
            }
        }

        // 2) Shop főoldal slug alapján
        if (!empty($context['shop_slug'])) {
            $slug = sanitize_title($context['shop_slug']);
            return home_url("/shop/{$slug}/");
        }

        // 3) Főoldal + log
        $payload = function_exists('wp_json_encode') ? wp_json_encode($context) : json_encode($context);
        error_log('Impact Safety: Fallback → home | ctx=' . $payload);
        return home_url('/');
    }
}

// --- Hooks: csak akkor regisztrálunk, ha NINCS SAFE MODE ---
if (!Impact_Safety::is_safe_mode()) {

    // Circuit breaker esemény log
    add_action('impact_circuit_breaker', function (string $module, int $error_count) {
        error_log("CIRCUIT_OPEN module={$module} count={$error_count}");
    }, 10, 2);

    // Admin notice: ha bármely modul letiltott
    add_action('admin_notices', function () {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return;
        }
        $modules = ['link_guard', 'slug_normalizer', 'aff_preserve', 'shortcode_reg'];
        $disabled = [];
        foreach ($modules as $m) {
            if (get_option('impact_disable_' . $m, false)) {
                $disabled[] = $m;
            }
        }
        if ($disabled) {
            $url = esc_url(admin_url('tools.php?page=impact-safety'));
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Impact Safety:</strong> Letiltott modulok: ' . esc_html(implode(', ', $disabled));
            echo ' | <a href="' . $url . '">Beállítások</a>';
            echo '</p></div>';
        }
    });
}

// KÉSZ: az osztály és a helper SAFE MODE alatt is elérhető,
// de új hookokat csak SAFE MODE nélkül regisztrálunk.
