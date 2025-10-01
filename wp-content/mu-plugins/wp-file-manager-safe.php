<?php
/**
 * WordPress File Manager API - JAVÍTOTT VERZIÓ
 * 
 * 404 hiba javítás: megfelelő WordPress hook timing
 */

// Várjuk meg, hogy a WordPress teljesen betöltődjön
add_action('wp_loaded', function() {
    
    class WP_File_Manager_Safe {
        
        private $backup_dir;
        
        public function __construct() {
            $this->backup_dir = WP_CONTENT_DIR . '/file-manager-backups/';
            
            // Backup könyvtár létrehozása
            if (!file_exists($this->backup_dir)) {
                wp_mkdir_p($this->backup_dir);
            }
            
            // REST API routes regisztrálása
            add_action('rest_api_init', [$this, 'register_routes']);
        }
        
        /**
         * REST API végpontok regisztrálása
         */
        public function register_routes() {
            // Egyszerű teszt endpoint
            register_rest_route('wp-file-manager/v1', '/test', [
                'methods' => 'GET',
                'callback' => [$this, 'test_endpoint'],
                'permission_callback' => '__return_true' // Teszt célból nyitott
            ]);
            
            // Backups lista
            register_rest_route('wp-file-manager/v1', '/backups', [
                'methods' => 'GET',
                'callback' => [$this, 'list_backups'],
                'permission_callback' => [$this, 'check_permissions']
            ]);
            
            // Fájl letöltés - egyszerűsített regex
            register_rest_route('wp-file-manager/v1', '/download/(?P<type>[\w-]+)/(?P<file>[\w.-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'download_file'],
                'permission_callback' => [$this, 'check_permissions']
            ]);
            
            // Banner javítás specifikus endpoint
            register_rest_route('wp-file-manager/v1', '/fix-banner-links', [
                'methods' => 'POST',
                'callback' => [$this, 'fix_banner_links'],
                'permission_callback' => [$this, 'check_permissions']
            ]);
        }
        
        /**
         * Teszt endpoint
         */
        public function test_endpoint() {
            return rest_ensure_response([
                'success' => true,
                'message' => 'WP File Manager API működik!',
                'wp_content_dir' => WP_CONTENT_DIR,
                'backup_dir' => $this->backup_dir,
                'time' => current_time('mysql')
            ]);
        }
        
        /**
         * Jogosultság ellenőrzés
         */
        public function check_permissions() {
            return current_user_can('manage_options');
        }
        
        /**
         * Fájl elérési út lekérése
         */
        private function get_file_path($type, $file) {
            $base_paths = [
                'snippets' => WP_CONTENT_DIR . '/uploads/', // Létező könyvtár
                'mu-plugins' => WPMU_PLUGIN_DIR . '/',
                'plugins' => WP_PLUGIN_DIR . '/',
                'themes' => get_theme_root() . '/'
            ];
            
            if (!isset($base_paths[$type])) {
                return false;
            }
            
            return $base_paths[$type] . $file;
        }
        
        /**
         * Backup lista
         */
        public function list_backups($request = null) {
            $backups = [];
            
            if (!file_exists($this->backup_dir)) {
                wp_mkdir_p($this->backup_dir);
            }
            
            $files = glob($this->backup_dir . '*.backup');
            
            foreach ($files as $file) {
                $basename = basename($file, '.backup');
                $backups[] = [
                    'file' => $basename,
                    'path' => $file,
                    'size' => filesize($file),
                    'date' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
            
            return rest_ensure_response([
                'success' => true,
                'backup_dir' => $this->backup_dir,
                'backups' => $backups,
                'count' => count($backups)
            ]);
        }
        
        /**
         * Fájl letöltés
         */
        public function download_file($request) {
            $type = $request['type'];
            $file = $request['file'];
            
            // Speciális kezelés a snippet fájlokhoz
            if ($type === 'snippets') {
                // Próbáljuk megkeresni a fájlt több helyen
                $possible_paths = [
                    WP_CONTENT_DIR . '/uploads/' . $file,
                    WP_CONTENT_DIR . '/' . $file,
                    ABSPATH . 'wp-content/' . $file
                ];
                
                foreach ($possible_paths as $path) {
                    if (file_exists($path)) {
                        $content = file_get_contents($path);
                        return rest_ensure_response([
                            'success' => true,
                            'content' => base64_encode($content),
                            'file_path' => $path,
                            'size' => strlen($content)
                        ]);
                    }
                }
                
                return new WP_Error('file_not_found', "File not found: $file. Tried paths: " . implode(', ', $possible_paths), ['status' => 404]);
            }
            
            $file_path = $this->get_file_path($type, $file);
            if (!$file_path || !file_exists($file_path)) {
                return new WP_Error('file_not_found', "File not found: $file_path", ['status' => 404]);
            }
            
            $content = file_get_contents($file_path);
            
            return rest_ensure_response([
                'success' => true,
                'content' => base64_encode($content),
                'file_path' => $file_path,
                'size' => strlen($content)
            ]);
        }
        
        /**
         * Banner link javítás
         */
        public function fix_banner_links($request) {
            // Banner javítás logika
            $fixes = [];
            
            // 1. Keressük meg az aktív snippet fájlokat
            $snippet_sources = [
                WP_CONTENT_DIR . '/TELJES_AKCIOS_SNIPPET_JAVITOTT.php',
                WP_CONTENT_DIR . '/uploads/TELJES_AKCIOS_SNIPPET_JAVITOTT.php'
            ];
            
            foreach ($snippet_sources as $source_file) {
                if (file_exists($source_file)) {
                    $content = file_get_contents($source_file);
                    
                    // Dupla PHP tag javítás
                    if (preg_match('/(<\?php\s*<\?php)/', $content)) {
                        $content = preg_replace('/(<\?php)\s*(<\?php)/', '$1', $content);
                        $fixes[] = 'Removed duplicate <?php tags from ' . basename($source_file);
                    }
                    
                    // Banner href javítás ellenőrzése
                    if (strpos($content, '$banner_href = $banner[\'href\'];') !== false && 
                        strpos($content, 'form.fillout.com') === false) {
                        
                        // Banner href javítás hozzáadása
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
                    
                    // Backup készítése
                    $backup_file = $this->backup_dir . basename($source_file) . '_' . date('Y-m-d_H-i-s') . '.backup';
                    copy($source_file, $backup_file);
                    
                    // Javított fájl mentése
                    file_put_contents($source_file, $content);
                    
                    break; // Első megtalált fájl javítása után kilépés
                }
            }
            
            return rest_ensure_response([
                'success' => true,
                'fixes_applied' => $fixes,
                'backup_created' => isset($backup_file) ? $backup_file : null
            ]);
        }
    }
    
    // Class inicializálás
    new WP_File_Manager_Safe();
});

/**
 * Egyszerű teszt függvény
 */
function test_wp_file_manager() {
    $response = wp_remote_get(home_url('/wp-json/wp-file-manager/v1/test'));
    
    if (is_wp_error($response)) {
        return 'ERROR: ' . $response->get_error_message();
    }
    
    return wp_remote_retrieve_body($response);
}

/**
 * TESZTELÉSI ÚTMUTATÓ:
 * 
 * 1. Telepítsd ezt a fájlt: /wp-content/mu-plugins/wp-file-manager-safe.php
 * 2. Tesztelés: https://app.sharity.hu/wp-json/wp-file-manager/v1/test
 * 3. Banner javítás: POST https://app.sharity.hu/wp-json/wp-file-manager/v1/fix-banner-links
 * 
 * VÉGPONTOK:
 * - GET /wp-json/wp-file-manager/v1/test (nyitott teszt)
 * - GET /wp-json/wp-file-manager/v1/backups (admin szükséges)
 * - POST /wp-json/wp-file-manager/v1/fix-banner-links (admin szükséges)
 */
?>