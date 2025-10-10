<?php
/**
 * SNIPPET KERESŐ ÉS JAVÍTÓ - FUNCTIONS.PHP-BAN
 */

// SNIPPET KERESÉS ADATBÁZISBAN
add_action('wp_ajax_find_impactshop_snippets', 'find_impactshop_snippets');
add_action('wp_ajax_nopriv_find_impactshop_snippets', 'find_impactshop_snippets');

function find_impactshop_snippets() {
    global $wpdb;
    
    $results = [];
    
    // 1. Code Snippets plugin
    $snippets_table = $wpdb->prefix . 'snippets';
    if ($wpdb->get_var("SHOW TABLES LIKE '$snippets_table'") == $snippets_table) {
        $snippets = $wpdb->get_results("
            SELECT id, name, code, active 
            FROM $snippets_table 
            WHERE code LIKE '%impactshop_shortcode_scroller%'
        ");
        
        foreach ($snippets as $snippet) {
            $results[] = [
                'source' => 'Code Snippets Plugin',
                'id' => $snippet->id,
                'name' => $snippet->name,
                'active' => $snippet->active ? 'YES' : 'NO',
                'edit_url' => admin_url('admin.php?page=snippets&action=edit&id=' . $snippet->id)
            ];
        }
    }
    
    // 2. WPCode plugin
    $wpcode_table = $wpdb->prefix . 'wpcode_snippets';
    if ($wpdb->get_var("SHOW TABLES LIKE '$wpcode_table'") == $wpcode_table) {
        $wpcode = $wpdb->get_results("
            SELECT id, title, code, status 
            FROM $wpcode_table 
            WHERE code LIKE '%impactshop_shortcode_scroller%'
        ");
        
        foreach ($wpcode as $snippet) {
            $results[] = [
                'source' => 'WPCode Plugin',
                'id' => $snippet->id,
                'name' => $snippet->title,
                'active' => $snippet->status === 'active' ? 'YES' : 'NO',
                'edit_url' => admin_url('admin.php?page=wpcode&action=edit&snippet_id=' . $snippet->id)
            ];
        }
    }
    
    wp_send_json_success([
        'found_snippets' => $results,
        'count' => count($results),
        'message' => count($results) > 0 ? 'Snippets found!' : 'No snippets found with impactshop_shortcode_scroller'
    ]);
}

// SNIPPET JAVÍTÁS
add_action('wp_ajax_fix_impactshop_snippet', 'fix_impactshop_snippet');
add_action('wp_ajax_nopriv_fix_impactshop_snippet', 'fix_impactshop_snippet');

function fix_impactshop_snippet() {
    global $wpdb;
    
    $snippet_id = intval($_POST['snippet_id']);
    $source = sanitize_text_field($_POST['source']);
    
    if ($source === 'Code Snippets Plugin') {
        $table = $wpdb->prefix . 'snippets';
        $snippet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $snippet_id));
        
        if ($snippet) {
            $code = $snippet->code;
            
            // Function_exists védelem hozzáadása
            if (strpos($code, 'function impactshop_shortcode_scroller') !== false && 
                strpos($code, 'function_exists') === false) {
                
                $code = str_replace(
                    'function impactshop_shortcode_scroller',
                    'if (!function_exists(\'impactshop_shortcode_scroller\')) {' . "\n" . 'function impactshop_shortcode_scroller',
                    $code
                );
                
                // Snippet végéhez } hozzáadása
                $code = rtrim($code) . "\n}";
                
                // Banner href javítás
                if (strpos($code, '$banner_href = $banner[\'href\'];') !== false && 
                    strpos($code, 'form.fillout.com') === false) {
                    
                    $code = str_replace(
                        '$banner_href = $banner[\'href\'];',
                        '$banner_href = $banner[\'href\'];
                        
            // JAVÍTOTT BANNER HREF LOGIKA
            if (strpos($banner_href, \'form.fillout.com\') !== false && !empty($d1)) {
                $banner_href = add_query_arg([\'d1\' => $d1], $banner_href);
            }',
                        $code
                    );
                }
                
                // Frissítés
                $wpdb->update(
                    $table,
                    ['code' => $code],
                    ['id' => $snippet_id],
                    ['%s'],
                    ['%d']
                );
                
                wp_send_json_success([
                    'message' => 'Snippet successfully updated with function_exists protection!',
                    'snippet_id' => $snippet_id,
                    'snippet_name' => $snippet->name
                ]);
            } else {
                wp_send_json_error('Function_exists protection already exists or function not found.');
            }
        }
    }
    
    wp_send_json_error('Snippet not found or invalid source.');
}

// ADMIN OLDAL HOZZÁADÁSA
add_action('admin_menu', function() {
    add_management_page(
        'Impact Snippet Fixer',
        'Impact Snippet Fixer', 
        'manage_options',
        'impact-snippet-fixer',
        'impact_snippet_fixer_page'
    );
});

function impact_snippet_fixer_page() {
    ?>
    <div class="wrap">
        <h1>Impact Snippet Fixer</h1>
        
        <div id="snippet-results">
            <button id="find-snippets" class="button button-primary">Find impactshop_shortcode_scroller Snippets</button>
        </div>
        
        <div id="results-container" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#find-snippets').on('click', function() {
            $.post(ajaxurl, {
                action: 'find_impactshop_snippets'
            }, function(response) {
                if (response.success) {
                    let html = '<h3>Found ' + response.data.count + ' snippets:</h3>';
                    
                    if (response.data.count > 0) {
                        html += '<table class="wp-list-table widefat fixed striped">';
                        html += '<thead><tr><th>Source</th><th>Name</th><th>ID</th><th>Active</th><th>Action</th></tr></thead><tbody>';
                        
                        response.data.found_snippets.forEach(function(snippet) {
                            html += '<tr>';
                            html += '<td>' + snippet.source + '</td>';
                            html += '<td>' + snippet.name + '</td>';
                            html += '<td>' + snippet.id + '</td>';
                            html += '<td>' + snippet.active + '</td>';
                            html += '<td><button class="button fix-snippet" data-id="' + snippet.id + '" data-source="' + snippet.source + '">Fix This Snippet</button></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $('#results-container').html(html);
                }
            });
        });
        
        $(document).on('click', '.fix-snippet', function() {
            let snippetId = $(this).data('id');
            let source = $(this).data('source');
            
            $.post(ajaxurl, {
                action: 'fix_impactshop_snippet',
                snippet_id: snippetId,
                source: source
            }, function(response) {
                if (response.success) {
                    alert('SUCCESS: ' + response.data.message);
                    location.reload();
                } else {
                    alert('ERROR: ' + response.data);
                }
            });
        });
    });
    </script>
    <?php
}

// SHORTCODE REGISZTRÁCIÓ HOZZÁADÁSA
add_action('init', function() {
    // Ellenőrizzük, hogy a függvény létezik-e
    if (function_exists('impactshop_shortcode_scroller')) {
        // Regisztráljuk a shortcode-ot
        add_shortcode('impact_deals_netflix', 'impactshop_shortcode_scroller');
        add_shortcode('impact_deals', 'impactshop_shortcode_scroller'); // backup név
    }
});

// DEBUG: Shortcode teszt
add_action('wp_footer', function() {
    if (is_user_logged_in() && current_user_can('administrator')) {
        echo '<!-- DEBUG: ';
        echo 'Function exists: ' . (function_exists('impactshop_shortcode_scroller') ? 'YES' : 'NO');
        echo ' | Shortcode registered: ' . (shortcode_exists('impact_deals_netflix') ? 'YES' : 'NO');
        echo ' -->';
    }
});