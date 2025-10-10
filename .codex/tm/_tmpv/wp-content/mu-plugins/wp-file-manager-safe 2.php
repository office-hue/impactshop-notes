<?php
/**
 * WordPress File Manager API - FUNCTION REDECLARE JAVÍTÁS
 * 
 * Function_exists védelem hozzáadása a snippet függvényekhez
 */

add_action('init', function() {
    
    class WP_File_Manager_Safe {
        
        private $backup_dir;
        
        public function __construct() {
            $this->backup_dir = WP_CONTENT_DIR . '/file-manager-backups/';
            
            if (!file_exists($this->backup_dir)) {
                wp_mkdir_p($this->backup_dir);
            }
            
            add_action('rest_api_init', [$this, 'register_routes']);
        }
        
        public function register_routes() {
            register_rest_route('wp-file-manager/v1', '/test', [
                'methods' => 'GET',
                'callback' => [$this, 'test_endpoint'],
                'permission_callback' => '__return_true'
            ]);
            
            register_rest_route('wp-file-manager/v1', '/fix-banner-links', [
                'methods' => 'POST',
                'callback' => [$this, 'fix_banner_links'],
                'permission_callback' => '__return_true'
            ]);
            
            register_rest_route('wp-file-manager/v1', '/backups', [
                'methods' => 'GET',
                'callback' => [$this, 'list_backups'],
                'permission_callback' => '__return_true'
            ]);
        }
        
        public function test_endpoint() {
            return rest_ensure_response([
                'success' => true,
                'message' => 'WP File Manager API működik!',
                'wp_content_dir' => WP_CONTENT_DIR,
                'backup_dir' => $this->backup_dir,
                'time' => current_time('mysql')
            ]);
        }
        
        public function fix_banner_links($request) {
            $fixes = [];
            
            // Snippet fájlok keresése
            $snippet_sources = [
                WP_CONTENT_DIR . '/TELJES_AKCIOS_SNIPPET_JAVITOTT.php',
                WP_CONTENT_DIR . '/uploads/TELJES_AKCIOS_SNIPPET_JAVITOTT.php',
                WP_CONTENT_DIR . '/plugins/code-snippets/php/TELJES_AKCIOS_SNIPPET_JAVITOTT.php'
            ];
            
            foreach ($snippet_sources as $source_file) {
                if (file_exists($source_file)) {
                    $original_content = file_get_contents($source_file);
                    $content = $original_content;
                    
                    // 1. Function_exists védelem hozzáadása
                    if (strpos($content, 'function impactshop_shortcode_scroller') !== false && 
                        strpos($content, 'function_exists') === false) {
                        
                        // Teljes függvény körülvétele function_exists-szel
                        $pattern = '/(function impactshop_shortcode_scroller.*?^\})/ms';
                        $replacement = "if (!function_exists('impactshop_shortcode_scroller')) {\n$1\n}";
                        $content = preg_replace($pattern, $replacement, $content);
                        
                        $fixes[] = 'Added function_exists protection for impactshop_shortcode_scroller in ' . basename($source_file);
                    }
                    
                    // 2. Dupla PHP tag javítás
                    if (preg_match('/(<\?php\s*<\?php)/', $content)) {
                        $content = preg_replace('/(<\?php)\s*(<\?php)/', '$1', $content);
                        $fixes[] = 'Removed duplicate <?php tags from ' . basename($source_file);
                    }
                    
                    // 3. Banner href javítás
                    if (strpos($content, '$banner_href = $banner[\'href\'];') !== false && 
                        strpos($content, 'form.fillout.com') === false) {
                        
                        $content = str_replace(
                            '$banner_href = $banner[\'href\'];',
                            '$banner_href = $banner[\'href\'];
            
            // JAVÍTOTT BANNER HREF LOGIKA
            if (strpos($banner_href, \'form.fillout.com\') !== false && !empty($d1)) {
                $banner_href = add_query_arg([\'d1\' => $d1], $banner_href);
            }',
                            $content
                        );
                        
                        $fixes[] = 'Added banner href d1 parameter logic to ' . basename($source_file);
                    }
                    
                    // 4. Ha volt módosítás, mentés backup-pal
                    if ($content !== $original_content) {
                        // Backup készítés
                        $backup_file = $this->backup_dir . basename($source_file) . '_' . date('Y-m-d_H-i-s') . '.backup';
                        copy($source_file, $backup_file);
                        
                        // Javított fájl mentése
                        file_put_contents($source_file, $content);
                    }
                    
                    break; // Első megtalált fájl után kilépés
                }
            }
            
            return rest_ensure_response([
                'success' => true,
                'fixes_applied' => $fixes,
                'searched_paths' => $snippet_sources,
                'backup_dir' => $this->backup_dir,
                'timestamp' => current_time('mysql')
            ]);
        }
        
        public function list_backups($request = null) {
            $backups = [];
            
            if (file_exists($this->backup_dir)) {
                $files = glob($this->backup_dir . '*.backup');
                
                foreach ($files as $file) {
                    $backups[] = [
                        'file' => basename($file),
                        'size' => filesize($file),
                        'date' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
            }
            
            return rest_ensure_response([
                'success' => true,
                'backup_dir' => $this->backup_dir,
                'backups' => $backups,
                'count' => count($backups)
            ]);
        }
    }
    
    if (!class_exists('WP_File_Manager_Safe') || !isset($GLOBALS['wp_file_manager_safe'])) {
        $GLOBALS['wp_file_manager_safe'] = new WP_File_Manager_Safe();
    }
});
?>