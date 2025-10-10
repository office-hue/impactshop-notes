<?php
/**
 * WordPress File Manager API
 * Biztonságos fájl letöltés/feltöltés backup funkcióval
 */

class WP_File_Manager {
    
    private $backup_dir;
    private $allowed_dirs = [
        'snippets' => WP_CONTENT_DIR . '/snippets/',
        'mu-plugins' => WPMU_PLUGIN_DIR . '/',
        'plugins' => WP_PLUGIN_DIR . '/',
        'themes' => get_template_directory() . '/'
    ];
    
    public function __construct() {
        $this->backup_dir = WP_CONTENT_DIR . '/backups/';
        $this->ensure_backup_dir();
        
        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // Letöltés
        register_rest_route('wp-manager/v1', '/download/(?P<type>[a-zA-Z0-9-]+)/(?P<file>[a-zA-Z0-9.-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'download_file'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Feltöltés backup-pal
        register_rest_route('wp-manager/v1', '/upload/(?P<type>[a-zA-Z0-9-]+)/(?P<file>[a-zA-Z0-9.-_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'upload_file'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Backup visszaállítás
        register_rest_route('wp-manager/v1', '/restore/(?P<type>[a-zA-Z0-9-]+)/(?P<file>[a-zA-Z0-9.-_]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restore_backup'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        // Backup lista
        register_rest_route('wp-manager/v1', '/backups', [
            'methods' => 'GET',
            'callback' => [$this, 'list_backups'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }
    
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    private function ensure_backup_dir() {
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }
    }
    
    /**
     * Fájl letöltése
     */
    public function download_file($request) {
        $type = $request['type'];
        $file = $request['file'];
        
        if (!isset($this->allowed_dirs[$type])) {
            return new WP_Error('invalid_type', 'Invalid file type', ['status' => 400]);
        }
        
        $file_path = $this->allowed_dirs[$type] . $file;
        
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'File not found', ['status' => 404]);
        }
        
        $content = file_get_contents($file_path);
        
        return [
            'success' => true,
            'file' => $file,
            'type' => $type,
            'content' => base64_encode($content),
            'size' => filesize($file_path),
            'modified' => filemtime($file_path)
        ];
    }
    
    /**
     * Fájl feltöltése automatikus backup-pal
     */
    public function upload_file($request) {
        $type = $request['type'];
        $file = $request['file'];
        $content = $request->get_param('content');
        
        if (!isset($this->allowed_dirs[$type])) {
            return new WP_Error('invalid_type', 'Invalid file type', ['status' => 400]);
        }
        
        if (empty($content)) {
            return new WP_Error('no_content', 'No content provided', ['status' => 400]);
        }
        
        $file_path = $this->allowed_dirs[$type] . $file;
        
        // 1. Backup készítése (ha létezik a fájl)
        $backup_created = false;
        if (file_exists($file_path)) {
            $backup_created = $this->create_backup($type, $file, $file_path);
            if (!$backup_created) {
                return new WP_Error('backup_failed', 'Failed to create backup', ['status' => 500]);
            }
        }
        
        // 2. Új fájl írása
        $decoded_content = base64_decode($content);
        $result = file_put_contents($file_path, $decoded_content);
        
        if ($result === false) {
            // Ha sikertelen, visszaállítjuk a backup-ot
            if ($backup_created) {
                $this->restore_from_backup($type, $file);
            }
            return new WP_Error('write_failed', 'Failed to write file', ['status' => 500]);
        }
        
        // 3. Syntax check PHP fájlokhoz
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $syntax_check = $this->check_php_syntax($file_path);
            if (!$syntax_check['valid']) {
                // Syntax hiba - visszaállítjuk a backup-ot
                if ($backup_created) {
                    $this->restore_from_backup($type, $file);
                }
                return new WP_Error('syntax_error', $syntax_check['error'], ['status' => 400]);
            }
        }
        
        return [
            'success' => true,
            'file' => $file,
            'type' => $type,
            'size' => $result,
            'backup_created' => $backup_created,
            'message' => 'File uploaded successfully'
        ];
    }
    
    /**
     * Backup készítése
     */
    private function create_backup($type, $file, $file_path) {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_name = "{$type}_{$file}_{$timestamp}.backup";
        $backup_path = $this->backup_dir . $backup_name;
        
        return copy($file_path, $backup_path);
    }
    
    /**
     * PHP syntax ellenőrzés
     */
    private function check_php_syntax($file_path) {
        $output = [];
        $return_code = 0;
        
        exec("php -l " . escapeshellarg($file_path) . " 2>&1", $output, $return_code);
        
        return [
            'valid' => $return_code === 0,
            'error' => $return_code !== 0 ? implode("\n", $output) : null
        ];
    }
    
    /**
     * Backup visszaállítása
     */
    public function restore_backup($request) {
        $type = $request['type'];
        $file = $request['file'];
        $backup_timestamp = $request->get_param('timestamp');
        
        if (!$backup_timestamp) {
            return new WP_Error('no_timestamp', 'Backup timestamp required', ['status' => 400]);
        }
        
        $backup_name = "{$type}_{$file}_{$backup_timestamp}.backup";
        $backup_path = $this->backup_dir . $backup_name;
        
        if (!file_exists($backup_path)) {
            return new WP_Error('backup_not_found', 'Backup not found', ['status' => 404]);
        }
        
        $file_path = $this->allowed_dirs[$type] . $file;
        $result = copy($backup_path, $file_path);
        
        if (!$result) {
            return new WP_Error('restore_failed', 'Failed to restore backup', ['status' => 500]);
        }
        
        return [
            'success' => true,
            'message' => 'Backup restored successfully',
            'file' => $file,
            'backup_timestamp' => $backup_timestamp
        ];
    }
    
    /**
     * Backup lista
     */
    public function list_backups($request) {
        $backups = [];
        $files = scandir($this->backup_dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            if (preg_match('/^(.+)_(.+)_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.backup$/', $file, $matches)) {
                $backups[] = [
                    'type' => $matches[1],
                    'file' => $matches[2],
                    'timestamp' => $matches[3],
                    'created' => filemtime($this->backup_dir . $file),
                    'size' => filesize($this->backup_dir . $file)
                ];
            }
        }
        
        // Legújabbak elől
        usort($backups, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        return [
            'success' => true,
            'backups' => $backups
        ];
    }
    
    private function restore_from_backup($type, $file) {
        // Legújabb backup keresése
        $files = scandir($this->backup_dir);
        $pattern = "/^{$type}_{$file}_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.backup$/";
        $latest_backup = null;
        $latest_time = 0;
        
        foreach ($files as $backup_file) {
            if (preg_match($pattern, $backup_file, $matches)) {
                $backup_path = $this->backup_dir . $backup_file;
                $backup_time = filemtime($backup_path);
                
                if ($backup_time > $latest_time) {
                    $latest_time = $backup_time;
                    $latest_backup = $backup_path;
                }
            }
        }
        
        if ($latest_backup) {
            $file_path = $this->allowed_dirs[$type] . $file;
            return copy($latest_backup, $file_path);
        }
        
        return false;
    }
}

// Inicializálás
new WP_File_Manager();

/**
 * Segédfüggvények CLI használathoz
 */

/**
 * Fájl letöltése cURL-lel
 */
function download_wp_file($type, $file, $site_url, $auth_token = null) {
    $url = rtrim($site_url, '/') . "/wp-json/wp-manager/v1/download/{$type}/{$file}";
    
    $headers = [];
    if ($auth_token) {
        $headers[] = "Authorization: Bearer {$auth_token}";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return base64_decode($data['content']);
    }
    
    return false;
}

/**
 * Fájl feltöltése cURL-lel
 */
function upload_wp_file($type, $file, $content, $site_url, $auth_token = null) {
    $url = rtrim($site_url, '/') . "/wp-json/wp-manager/v1/upload/{$type}/{$file}";
    
    $headers = ['Content-Type: application/json'];
    if ($auth_token) {
        $headers[] = "Authorization: Bearer {$auth_token}";
    }
    
    $data = json_encode([
        'content' => base64_encode($content)
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return false;
}
?>