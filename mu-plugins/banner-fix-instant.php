<?php
<?php
/**
 * Banner Link Fix - AZONNALI VÉGREHAJTÁS
 * 
 * Ez a plugin automatikusan javítja a banner linkeket
 * amikor aktiválódik - REST API megkerülésével!
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
        
        // 1. Function_exists védelem hozzáadása
        $function_fixes = $wpdb->query("
            UPDATE $snippets_table 
            SET code = REPLACE(code, 
                'function impactshop_shortcode_scroller', 
                'if (!function_exists(\"impactshop_shortcode_scroller\")) {\nfunction impactshop_shortcode_scroller'
            )
            WHERE active = 1 
            AND code LIKE '%function impactshop_shortcode_scroller%' 
            AND code NOT LIKE '%function_exists%'
        ");
        
        // 2. Banner href javítás hozzáadása
        $banner_fixes = $wpdb->query("
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
        $php_fixes = $wpdb->query("
            UPDATE $snippets_table 
            SET code = REPLACE(code, '<?php\n<?php', '<?php')
            WHERE active = 1 
            AND code LIKE '%<?php%<?php%'
        ");
        
        // Log eredmény
        error_log("Banner fix executed: function_fixes=$function_fixes, banner_fixes=$banner_fixes, php_fixes=$php_fixes at " . current_time('mysql'));
    }
}

// Manuális futtatás admin oldalon
add_action('admin_notices', function() {
    if (isset($_GET['run_banner_fix'])) {
        banner_fix_execute();
        echo '<div class="notice notice-success"><p>Banner fix executed successfully!</p></div>';
    }
});
?>