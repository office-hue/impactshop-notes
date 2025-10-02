<?php
/**
 * Banner Link Fix - JAVÍTOTT VERZIÓ
 * 
 * Megfelelő function_exists védelem és függvény lezárás
 */

add_action('init', function() {
    // Futtatás csak egyszer naponta
    $last_run = get_option('banner_fix_last_run', 0);
    $now = time();
    
    if (($now - $last_run) > DAY_IN_SECONDS) {
        banner_fix_execute();
        update_option('banner_fix_last_run', $now);
    }
});

function banner_fix_execute() {
    global $wpdb;
    $snippets_table = $wpdb->prefix . 'snippets';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$snippets_table'") == $snippets_table) {
        
        // 1. JAVÍTOTT Function_exists védelem - teljes snippet cseréje
        $snippets = $wpdb->get_results("
            SELECT id, code 
            FROM $snippets_table 
            WHERE active = 1 
            AND code LIKE '%function impactshop_shortcode_scroller%' 
            AND code NOT LIKE '%function_exists%'
        ");
        
        foreach ($snippets as $snippet) {
            $code = $snippet->code;
            
            // Teljes függvény körülvétele function_exists-szel
            $code = str_replace(
                'function impactshop_shortcode_scroller',
                'if (!function_exists(\'impactshop_shortcode_scroller\')) {' . "\n" . 'function impactshop_shortcode_scroller',
                $code
            );
            
            // Snippet végéhez } hozzáadása
            $code = rtrim($code) . "\n}";
            
            // Frissítés
            $wpdb->update(
                $snippets_table,
                ['code' => $code],
                ['id' => $snippet->id],
                ['%s'],
                ['%d']
            );
        }
        
        // 2. Banner href javítás
        $wpdb->query("
            UPDATE $snippets_table 
            SET code = REPLACE(code, 
                '\$banner_href = \$banner[\"href\"];', 
                '\$banner_href = \$banner[\"href\"];\n            // JAVÍTOTT BANNER HREF LOGIKA\n            if (strpos(\$banner_href, \"form.fillout.com\") !== false && !empty(\$d1)) {\n                \$banner_href = add_query_arg([\"d1\" => \$d1], \$banner_href);\n            }'
            )
            WHERE active = 1 
            AND code LIKE '%\$banner_href = \$banner%' 
            AND code NOT LIKE '%form.fillout.com%'
        ");
        
        // 3. Dupla PHP tag javítás
        $wpdb->query("
            UPDATE $snippets_table 
            SET code = REPLACE(code, '<?php\n<?php', '<?php')
            WHERE active = 1 
            AND code LIKE '%<?php%<?php%'
        ");
        
        error_log("Banner fix executed with improved function protection at " . current_time('mysql'));
    }
}

// Manuális futtatás
add_action('admin_notices', function() {
    if (isset($_GET['run_banner_fix'])) {
        banner_fix_execute();
        echo '<div class="notice notice-success"><p>Banner fix executed successfully!</p></div>';
    }
    
    // Debug info megjelenítése
    if (isset($_GET['debug_snippets'])) {
        global $wpdb;
        $snippets_table = $wpdb->prefix . 'snippets';
        $snippets = $wpdb->get_results("SELECT id, name, active FROM $snippets_table WHERE code LIKE '%impactshop_shortcode_scroller%'");
        
        echo '<div class="notice notice-info"><p><strong>Snippets with impactshop_shortcode_scroller:</strong><br>';
        foreach ($snippets as $snippet) {
            $status = $snippet->active ? 'ACTIVE' : 'INACTIVE';
            echo "ID: {$snippet->id}, Name: {$snippet->name}, Status: $status<br>";
        }
        echo '</p></div>';
    }
});

// RESET funkció - törli a rossz function_exists védelmet
add_action('admin_notices', function() {
    if (isset($_GET['reset_function_exists'])) {
        global $wpdb;
        $snippets_table = $wpdb->prefix . 'snippets';
        
        // Törli a rossz function_exists védelmet
        $wpdb->query("
            UPDATE $snippets_table 
            SET code = REPLACE(
                REPLACE(code, 'if (!function_exists(\"impactshop_shortcode_scroller\")) {', ''),
                'if (!function_exists(\"impactshop_shortcode_scroller\")) {\nfunction', 'function'
            )
            WHERE active = 1 
            AND code LIKE '%function_exists%impactshop_shortcode_scroller%'
        ");
        
        echo '<div class="notice notice-warning"><p>Function_exists protection reset! Run banner fix again.</p></div>';
    }
});
?>