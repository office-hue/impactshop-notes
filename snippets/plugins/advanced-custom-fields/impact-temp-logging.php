<?php
/**
 * Plugin Name: Impact Temporary Logging (no config edits)
 * Description: Átmeneti hibalog bekapcsolása wp-config módosítás nélkül + fatal error elfogás + [impact_log_tail].
 * Version:     1.0.0
 * Author:      Sharity
 */

if (!defined('ABSPATH')) exit;

// 1) Log cél: wp-content/debug.log
add_action('plugins_loaded', function () {
    $log = WP_CONTENT_DIR . '/debug.log';

    // PHP hibalog bekapcsolása futásidőben
    @ini_set('log_errors', '1');
    @ini_set('display_errors', '0');        // ne a képernyőre menjen
    @ini_set('error_log', $log);

    // Biztonság kedvéért hozzuk létre, ha nem létezik és írható a könyvtár
    if (!file_exists($log) && is_writable(WP_CONTENT_DIR)) {
        @file_put_contents($log, "=== Impact Temporary Logging started: " . date('c') . " ===\n");
    }

    // 2) Fatális hibák elkapása (shutdown)
    register_shutdown_function(function () use ($log) {
        $e = error_get_last();
        if (!$e) return;
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (in_array($e['type'] ?? 0, $fatal, true)) {
            $line = sprintf(
                "[%s] FATAL: %s in %s:%d\n",
                date('Y-m-d H:i:s'),
                trim(($e['message'] ?? '')),
                ($e['file'] ?? 'unknown'),
                ($e['line'] ?? 0)
            );
            @error_log($line); // az ini_set('error_log') miatt a debug.log-ba megy
        }
    });
});

// 3) Adminbar info (csak adminnak)
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $log = WP_CONTENT_DIR . '/debug.log';
    echo '<div class="notice notice-info"><p><strong>Impact Temporary Logging:</strong> hibák ide íródnak: <code>'
        . esc_html($log)
        . '</code>. Kikapcsoláshoz kapcsold ki a plugint.</p></div>';
});

// 4) Rövidkód: [impact_log_tail lines="150"]
add_shortcode('impact_log_tail', function ($atts) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '<em>Nincs jogosultság a log megtekintéséhez.</em>';
    }
    $a = shortcode_atts(['lines' => 150], $atts, 'impact_log_tail');
    $lines = max(10, min(2000, intval($a['lines'])));

    $file = WP_CONTENT_DIR . '/debug.log';
    if (!file_exists($file)) return '<em>debug.log nem található.</em>';
    if (!is_readable($file))  return '<em>debug.log nem olvasható.</em>';

    // Gyors tail nagy fájlokra is
    $fp = @fopen($file, 'rb');
    if (!$fp) return '<em>debug.log megnyitása sikertelen.</em>';
    $buffer = '';
    $pos = -1; $lineCount = 0;
    fseek($fp, 0, SEEK_END);
    $filesize = ftell($fp);

    while ($lineCount <= $lines && -$pos < $filesize) {
        fseek($fp, $pos, SEEK_END);
        $char = fgetc($fp);
        $buffer = $char . $buffer;
        if ($char === "\n") $lineCount++;
        $pos--;
    }
    fclose($fp);

    return '<pre style="max-height:420px;overflow:auto;background:#0b1220;color:#cbd5e1;border-radius:8px;padding:12px;">'
         . esc_html($buffer)
         . '</pre>';
});