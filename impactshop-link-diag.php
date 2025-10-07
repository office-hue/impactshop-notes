<?php
/**
 * ImpactShop Link Diagnostics
 * Plugin Name: ImpactShop Link Diagnostics
 * Description: Comprehensive link logic diagnostics for ImpactShop
 * Version: 1.0.0
 * Author: ImpactShop Team
 */

// filepath: /Users/bujdosoarnold/Documents/GitHub/impactshop-notes/wp-content/mu-plugins/impactshop-link-diag.php

if (!defined('ABSPATH')) {
    exit;
}

class ImpactShop_Link_Diagnostics {
    
    private $issues = [];
    private $scan_paths = [];
    private $csv_dir;
    
    public function __construct() {
        $this->csv_dir = WP_CONTENT_DIR . '/uploads/impactshop-diag/';
        
        // Ensure CSV directory exists
        if (!file_exists($this->csv_dir)) {
            wp_mkdir_p($this->csv_dir);
        }
        
        // Set scan paths
        $this->scan_paths = [
            'themes' => get_template_directory(),
            'child_theme' => get_stylesheet_directory(),
            'mu_plugins' => WPMU_PLUGIN_DIR,
            'plugins' => WP_PLUGIN_DIR,
            'wp_content' => WP_CONTENT_DIR,
        ];
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_export_diag_csv', [$this, 'export_csv']);
    }
    
    public function add_admin_menu() {
        add_management_page(
            'ImpactShop Link Diagnostics',
            'ImpactShop Link Diag',
            'manage_options',
            'impactshop-link-diag',
            [$this, 'admin_page']
        );
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Run diagnostics
        $this->run_full_diagnostics();
        
        ?>
        <div class="wrap">
            <h1>🔍 ImpactShop – Link Diagnosztika</h1>
            
            <div class="notice notice-info">
                <p><strong>Diagnosztika célja:</strong> Linklogika ütközések és szabálysértések feltárása a kódbázisban.</p>
                <p><strong>Jelölések:</strong> 🔴 Kritikus hiba, 🟡 Figyelmeztetés, ✅ Rendben, 📊 Statisztika</p>
                <p><strong>Deeplink-first elv:</strong> Mindig deeplink-et preferáljunk URL helyett konverziós célokra.</p>
                <p><strong>Guard szabály:</strong> A /go-deal guard ismerje fel ?u= és #u= paramétereket is.</p>
            </div>
            
            <?php $this->render_summary(); ?>
            <?php $this->render_function_collisions(); ?>
            <?php $this->render_shortcode_collisions(); ?>
            <?php $this->render_deeplink_violations(); ?>
            <?php $this->render_slug_confusion(); ?>
            <?php $this->render_guard_issues(); ?>
            <?php $this->render_parameter_loss(); ?>
            <?php $this->render_sample_analysis(); ?>
            
            <div style="margin-top: 30px;">
                <h3>📥 CSV Export</h3>
                <button class="button button-primary" onclick="exportCSV('all')">Export All Tables</button>
                <button class="button" onclick="exportCSV('functions')">Functions Only</button>
                <button class="button" onclick="exportCSV('shortcodes')">Shortcodes Only</button>
                <button class="button" onclick="exportCSV('samples')">Sample Analysis</button>
            </div>
        </div>
        
        <script>
        function exportCSV(type) {
            window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=export_diag_csv&type=' + type + '&_wpnonce=<?php echo wp_create_nonce('export_diag_csv'); ?>';
        }
        </script>
        
        <style>
        .diag-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .diag-table th, .diag-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .diag-table th { background-color: #f2f2f2; font-weight: bold; }
        .diag-critical { background-color: #ffe6e6; }
        .diag-warning { background-color: #fff3cd; }
        .diag-ok { background-color: #e6ffe6; }
        .code-snippet { font-family: monospace; background: #f4f4f4; padding: 2px 4px; }
        </style>
        <?php
    }
    
    private function run_full_diagnostics() {
        $this->issues = [
            'function_collisions' => $this->scan_function_collisions(),
            'shortcode_collisions' => $this->scan_shortcode_collisions(),
            'deeplink_violations' => $this->scan_deeplink_violations(),
            'slug_confusion' => $this->scan_slug_confusion(),
            'guard_issues' => $this->scan_guard_issues(),
            'parameter_loss' => $this->scan_parameter_loss(),
            'sample_analysis' => $this->analyze_sample_data()
        ];
    }
    
    private function scan_function_collisions() {
        $functions = [];
        $collisions = [];
        
        foreach ($this->scan_paths as $path_name => $path) {
            if (!is_dir($path)) continue;
            
            $files = $this->get_php_files($path);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line_num => $line) {
                    // Scan for function definitions
                    if (preg_match('/function\s+(impact|dognet|sharity)_([a-z0-9_]+)\s*\(/i', $line, $matches)) {
                        $func_name = $matches[1] . '_' . $matches[2];
                        
                        if (!isset($functions[$func_name])) {
                            $functions[$func_name] = [];
                        }
                        
                        $functions[$func_name][] = [
                            'file' => str_replace(ABSPATH, '', $file),
                            'line' => $line_num + 1,
                            'code' => trim($line),
                            'has_protection' => $this->check_function_exists_protection($content, $func_name)
                        ];
                    }
                }
            }
        }
        
        // Find collisions
        foreach ($functions as $func_name => $definitions) {
            if (count($definitions) > 1) {
                $collisions[$func_name] = $definitions;
            }
        }
        
        return $collisions;
    }
    
    private function scan_shortcode_collisions() {
        $shortcodes = [];
        $collisions = [];
        
        foreach ($this->scan_paths as $path_name => $path) {
            if (!is_dir($path)) continue;
            
            $files = $this->get_php_files($path);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line_num => $line) {
                    // Scan for shortcode registrations
                    if (preg_match('/add_shortcode\s*\(\s*[\'"]([a-z0-9_:-]+)[\'"]/', $line, $matches)) {
                        $shortcode_name = $matches[1];
                        
                        if (!isset($shortcodes[$shortcode_name])) {
                            $shortcodes[$shortcode_name] = [];
                        }
                        
                        $shortcodes[$shortcode_name][] = [
                            'file' => str_replace(ABSPATH, '', $file),
                            'line' => $line_num + 1,
                            'code' => trim($line)
                        ];
                    }
                }
            }
        }
        
        // Find collisions
        foreach ($shortcodes as $shortcode_name => $definitions) {
            if (count($definitions) > 1) {
                $collisions[$shortcode_name] = $definitions;
            }
        }
        
        return $collisions;
    }
    
    private function scan_deeplink_violations() {
        $violations = [];
        
        foreach ($this->scan_paths as $path_name => $path) {
            if (!is_dir($path)) continue;
            
            $files = $this->get_php_files($path);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line_num => $line) {
                    // Check for URL preference over deeplink
                    if (preg_match('/\$href[^;=]*=\s*.*\$r\[\'url\'\].*\?\?.*deeplink/i', $line) ||
                        preg_match('/\$href[^;=]*=\s*\$r\[\'url\'\]\s*\?\?/i', $line)) {
                        
                        $violations[] = [
                            'file' => str_replace(ABSPATH, '', $file),
                            'line' => $line_num + 1,
                            'code' => trim($line),
                            'issue' => 'URL preferred over deeplink',
                            'suggestion' => 'Change to: $href = $r[\'deeplink\'] ?? $r[\'url\'];'
                        ];
                    }
                    
                    // Check for explicit URL selection
                    if (preg_match('/deeplink[^;=\n]+null\s*\?\?\s*[\'"]?\s*\$r\[\'url\'\]/i', $line)) {
                        $violations[] = [
                            'file' => str_replace(ABSPATH, '', $file),
                            'line' => $line_num + 1,
                            'code' => trim($line),
                            'issue' => 'Deeplink explicitly set to null before URL fallback',
                            'suggestion' => 'Ensure deeplink has valid value before fallback'
                        ];
                    }
                }
            }
        }
        
        return $violations;
    }
    
    private function scan_slug_confusion() {
        $confusions = [];
        
        foreach ($this->scan_paths as $path_name => $path) {
            if (!is_dir($path)) continue;
            
            $files = $this->get_php_files($path);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line_num => $line) {
                    // Check for slug/shop_slug mixing
                    if (preg_match('/\bshop_slug\b.*\bslug\b|\bslug\b.*\bshop_slug\b/', $line)) {
                        $confusions[] = [
                            'file' => str_replace(ABSPATH, '', $file),
                            'line' => $line_num + 1,
                            'code' => trim($line),
                            'issue' => 'Potential slug/shop_slug confusion',
                            'suggestion' => 'Verify field mapping: slug vs shop_slug usage'
                        ];
                    }
                }
            }
        }
        
        return $confusions;
    }
    
    private function scan_guard_issues() {
        $issues = [];
        
        foreach ($this->scan_paths as $path_name => $path) {
            if (!is_dir($path)) continue;
            
            $files = $this->get_php_files($path);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line_num => $line) {
                    // Check for narrow go-deal guards
                    if (preg_match('/\/go-deal/', $line) && 
                        !preg_match('/\?u=|\#u=|[?&]u=/', $line)) {
                        
                        $issues[] = [
                            'file' => str_replace(ABSPATH, '', $file),
                            'line' => $line_num + 1,
                            'code' => trim($line),
                            'issue' => 'go-deal guard too narrow, missing ?u= and #u= detection',
                            'suggestion' => 'Add checks for ?u= and #u= parameters in URL fragments'
                        ];
                    }
                }
            }
        }
        
        return $issues;
    }
    
    private function scan_parameter_loss() {
        $losses = [];
        
        foreach ($this->scan_paths as $path_name => $path) {
            if (!is_dir($path)) continue;
            
            $files = $this->get_php_files($path);
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line_num => $line) {
                    // Check for redirects without parameter preservation
                    if (preg_match('/wp_redirect|wp_safe_redirect/', $line) &&
                        !preg_match('/add_query_arg|http_build_query/', $line)) {
                        
                        // Check if d1, amb, src, utm parameters might be lost
                        $context = $this->get_line_context($lines, $line_num, 3);
                        if (!preg_match('/d1|amb|src|utm_/', $context)) {
                            $losses[] = [
                                'file' => str_replace(ABSPATH, '', $file),
                                'line' => $line_num + 1,
                                'code' => trim($line),
                                'issue' => 'Redirect without parameter preservation',
                                'suggestion' => 'Use add_query_arg() to preserve d1/amb/src/utm parameters'
                            ];
                        }
                    }
                }
            }
        }
        
        return $losses;
    }
    
    private function analyze_sample_data() {
        $samples = [];
        
        // Try to get sample data from various sources
        $data_sources = [
            WP_CONTENT_DIR . '/uploads/banners.json',
            WP_CONTENT_DIR . '/uploads/products.json',
            WP_CONTENT_DIR . '/Banners.csv',
            WP_CONTENT_DIR . '/Shop.csv'
        ];
        
        foreach ($data_sources as $source) {
            if (file_exists($source)) {
                $samples = array_merge($samples, $this->extract_sample_data($source));
            }
        }
        
        // If no file data, create mock samples
        if (empty($samples)) {
            $samples = $this->create_mock_samples();
        }
        
        // Analyze each sample
        $analyzed = [];
        foreach (array_slice($samples, 0, 20) as $sample) {
            $analyzed[] = $this->analyze_single_sample($sample);
        }
        
        return $analyzed;
    }
    
    private function extract_sample_data($file) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $samples = [];
        
        if ($extension === 'json') {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                foreach ($data as $category => $items) {
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $samples[] = [
                                'shop_slug' => $item['shop_slug'] ?? $category,
                                'deeplink' => $item['deeplink'] ?? '',
                                'url' => $item['url'] ?? $item['href'] ?? '',
                                'source' => basename($file)
                            ];
                        }
                    }
                }
            }
        } elseif ($extension === 'csv') {
            $handle = fopen($file, 'r');
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= count($headers)) {
                    $item = array_combine($headers, $row);
                    $samples[] = [
                        'shop_slug' => $item['shop_slug'] ?? $item['slug'] ?? '',
                        'deeplink' => $item['deeplink'] ?? '',
                        'url' => $item['url'] ?? $item['Target_URL'] ?? '',
                        'source' => basename($file)
                    ];
                }
            }
            fclose($handle);
        }
        
        return $samples;
    }
    
    private function create_mock_samples() {
        return [
            ['shop_slug' => 'netflix', 'deeplink' => 'https://deals.com/netflix', 'url' => 'https://netflix.com', 'source' => 'mock'],
            ['shop_slug' => 'spotify', 'deeplink' => '', 'url' => 'https://spotify.com', 'source' => 'mock'],
            ['shop_slug' => 'disney', 'deeplink' => 'https://deals.com/disney', 'url' => 'https://disney.com', 'source' => 'mock'],
            ['shop_slug' => 'hbo', 'deeplink' => null, 'url' => 'https://hbo.com', 'source' => 'mock'],
            ['shop_slug' => 'amazon', 'deeplink' => 'https://deals.com/amazon', 'url' => 'https://amazon.com', 'source' => 'mock']
        ];
    }
    
    private function analyze_single_sample($sample) {
        $deeplink = $sample['deeplink'] ?? '';
        $url = $sample['url'] ?? '';
        $shop_slug = $sample['shop_slug'] ?? '';
        
        // Determine which would win with current logic
        $decision = 'unknown';
        $guard_match = false;
        $explanation = '';
        $is_violation = false;
        
        if (!empty($deeplink) && !empty($url)) {
            // Simulate deeplink-first logic
            $decision = 'deeplink';
            $explanation = 'Deeplink available and preferred';
        } elseif (empty($deeplink) && !empty($url)) {
            $decision = 'url';
            $explanation = 'No deeplink, fallback to URL';
        } elseif (!empty($deeplink) && empty($url)) {
            $decision = 'deeplink';
            $explanation = 'Only deeplink available';
        } else {
            $decision = 'none';
            $explanation = 'No valid links';
            $is_violation = true;
        }
        
        // Check for go-deal guard match
        if (strpos($url, '/go-deal') !== false || strpos($deeplink, '/go-deal') !== false) {
            $guard_match = true;
        }
        
        // Check for potential issues
        if (strpos($url, '?u=') !== false || strpos($url, '#u=') !== false) {
            if (!$guard_match) {
                $is_violation = true;
                $explanation .= ' | Guard miss: ?u= or #u= present but not detected';
            }
        }
        
        return [
            'shop_slug' => $shop_slug,
            'deeplink' => substr($deeplink, 0, 50) . (strlen($deeplink) > 50 ? '...' : ''),
            'url' => substr($url, 0, 50) . (strlen($url) > 50 ? '...' : ''),
            'decision' => $decision,
            'guard_match' => $guard_match ? 'true' : 'false',
            'explanation' => $explanation,
            'is_violation' => $is_violation,
            'source' => $sample['source'] ?? 'unknown'
        ];
    }
    
    private function get_php_files($dir) {
        $files = [];
        
        if (!is_dir($dir)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && 
                strpos($file->getPathname(), 'vendor') === false) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function check_function_exists_protection($content, $func_name) {
        return strpos($content, "function_exists('$func_name')") !== false ||
               strpos($content, "function_exists(\"$func_name\")") !== false;
    }
    
    private function get_line_context($lines, $line_num, $context_size) {
        $start = max(0, $line_num - $context_size);
        $end = min(count($lines) - 1, $line_num + $context_size);
        
        return implode("\n", array_slice($lines, $start, $end - $start + 1));
    }
    
    private function render_summary() {
        $total_functions = count($this->issues['function_collisions']);
        $total_shortcodes = count($this->issues['shortcode_collisions']);
        $total_deeplink = count($this->issues['deeplink_violations']);
        $total_guard = count($this->issues['guard_issues']);
        $total_params = count($this->issues['parameter_loss']);
        $total_slug = count($this->issues['slug_confusion']);
        
        echo "<div class='notice notice-warning'>";
        echo "<h3>📊 Diagnosztika Összefoglaló</h3>";
        echo "<p><strong>$total_functions</strong> függvénynév-ütközés | ";
        echo "<strong>$total_shortcodes</strong> shortcode-ütközés | ";
        echo "<strong>$total_deeplink</strong> deeplink-first anomália | ";
        echo "<strong>$total_guard</strong> guard-szűk egyezés | ";
        echo "<strong>$total_params</strong> paraméter-vesztés | ";
        echo "<strong>$total_slug</strong> slug keveredés</p>";
        echo "</div>";
    }
    
    private function render_function_collisions() {
        echo "<h3>🔴 Függvénynév-ütközések</h3>";
        
        if (empty($this->issues['function_collisions'])) {
            echo "<p class='diag-ok'>✅ Nincs függvénynév-ütközés.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Függvénynév</th><th>Fájl</th><th>Sor</th><th>Védelem</th><th>Kód</th><th>Javasolt lépés</th></tr></thead><tbody>";
        
        foreach ($this->issues['function_collisions'] as $func_name => $definitions) {
            foreach ($definitions as $def) {
                $protection_class = $def['has_protection'] ? 'diag-ok' : 'diag-critical';
                $protection_text = $def['has_protection'] ? '✅ Van' : '🔴 Hiányzik';
                $suggestion = $def['has_protection'] ? 'Rendben' : 'Add hozzá: if (!function_exists(\'' . $func_name . '\'))';
                
                echo "<tr class='$protection_class'>";
                echo "<td><strong>$func_name</strong></td>";
                echo "<td><code>{$def['file']}</code></td>";
                echo "<td>{$def['line']}</td>";
                echo "<td>$protection_text</td>";
                echo "<td class='code-snippet'>" . htmlspecialchars($def['code']) . "</td>";
                echo "<td>$suggestion</td>";
                echo "</tr>";
            }
        }
        
        echo "</tbody></table>";
    }
    
    private function render_shortcode_collisions() {
        echo "<h3>🟡 Shortcode-ütközések</h3>";
        
        if (empty($this->issues['shortcode_collisions'])) {
            echo "<p class='diag-ok'>✅ Nincs shortcode-ütközés.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Shortcode név</th><th>Fájl</th><th>Sor</th><th>Kód</th><th>Javasolt lépés</th></tr></thead><tbody>";
        
        foreach ($this->issues['shortcode_collisions'] as $shortcode_name => $definitions) {
            foreach ($definitions as $def) {
                echo "<tr class='diag-warning'>";
                echo "<td><strong>$shortcode_name</strong></td>";
                echo "<td><code>{$def['file']}</code></td>";
                echo "<td>{$def['line']}</td>";
                echo "<td class='code-snippet'>" . htmlspecialchars($def['code']) . "</td>";
                echo "<td>Egyedülálló shortcode név használata</td>";
                echo "</tr>";
            }
        }
        
        echo "</tbody></table>";
    }
    
    private function render_deeplink_violations() {
        echo "<h3>🔴 Deeplink-first szabálysértések</h3>";
        
        if (empty($this->issues['deeplink_violations'])) {
            echo "<p class='diag-ok'>✅ Nincs deeplink-first szabálysértés.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Fájl</th><th>Sor</th><th>Probléma</th><th>Kód</th><th>Javasolt javítás</th></tr></thead><tbody>";
        
        foreach ($this->issues['deeplink_violations'] as $violation) {
            echo "<tr class='diag-critical'>";
            echo "<td><code>{$violation['file']}</code></td>";
            echo "<td>{$violation['line']}</td>";
            echo "<td>{$violation['issue']}</td>";
            echo "<td class='code-snippet'>" . htmlspecialchars($violation['code']) . "</td>";
            echo "<td>{$violation['suggestion']}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
    }
    
    private function render_slug_confusion() {
        echo "<h3>🟡 Slug/Shop_slug keveredések</h3>";
        
        if (empty($this->issues['slug_confusion'])) {
            echo "<p class='diag-ok'>✅ Nincs slug keveredés.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Fájl</th><th>Sor</th><th>Probléma</th><th>Kód</th><th>Javasolt javítás</th></tr></thead><tbody>";
        
        foreach ($this->issues['slug_confusion'] as $confusion) {
            echo "<tr class='diag-warning'>";
            echo "<td><code>{$confusion['file']}</code></td>";
            echo "<td>{$confusion['line']}</td>";
            echo "<td>{$confusion['issue']}</td>";
            echo "<td class='code-snippet'>" . htmlspecialchars($confusion['code']) . "</td>";
            echo "<td>{$confusion['suggestion']}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
    }
    
    private function render_guard_issues() {
        echo "<h3>🔴 Go-deal Guard problémák</h3>";
        
        if (empty($this->issues['guard_issues'])) {
            echo "<p class='diag-ok'>✅ Nincs guard probléma.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Fájl</th><th>Sor</th><th>Probléma</th><th>Kód</th><th>Javasolt javítás</th></tr></thead><tbody>";
        
        foreach ($this->issues['guard_issues'] as $issue) {
            echo "<tr class='diag-critical'>";
            echo "<td><code>{$issue['file']}</code></td>";
            echo "<td>{$issue['line']}</td>";
            echo "<td>{$issue['issue']}</td>";
            echo "<td class='code-snippet'>" . htmlspecialchars($issue['code']) . "</td>";
            echo "<td>{$issue['suggestion']}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
    }
    
    private function render_parameter_loss() {
        echo "<h3>🟡 Paraméter-propagáció elvesztések</h3>";
        
        if (empty($this->issues['parameter_loss'])) {
            echo "<p class='diag-ok'>✅ Nincs paraméter elvesztés.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Fájl</th><th>Sor</th><th>Probléma</th><th>Kód</th><th>Javasolt javítás</th></tr></thead><tbody>";
        
        foreach ($this->issues['parameter_loss'] as $loss) {
            echo "<tr class='diag-warning'>";
            echo "<td><code>{$loss['file']}</code></td>";
            echo "<td>{$loss['line']}</td>";
            echo "<td>{$loss['issue']}</td>";
            echo "<td class='code-snippet'>" . htmlspecialchars($loss['code']) . "</td>";
            echo "<td>{$loss['suggestion']}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
    }
    
    private function render_sample_analysis() {
        echo "<h3>📊 Mintaalapú elemzés (20 minta)</h3>";
        
        if (empty($this->issues['sample_analysis'])) {
            echo "<p class='diag-warning'>🟡 Nincs elérhető mintaadat.</p>";
            return;
        }
        
        echo "<table class='diag-table'>";
        echo "<thead><tr><th>Shop Slug</th><th>Deeplink</th><th>URL</th><th>Döntés</th><th>Guard Match</th><th>Indoklás</th><th>Forrás</th></tr></thead><tbody>";
        
        foreach ($this->issues['sample_analysis'] as $sample) {
            $row_class = $sample['is_violation'] ? 'diag-critical' : 'diag-ok';
            
            echo "<tr class='$row_class'>";
            echo "<td><strong>{$sample['shop_slug']}</strong></td>";
            echo "<td class='code-snippet'>{$sample['deeplink']}</td>";
            echo "<td class='code-snippet'>{$sample['url']}</td>";
            echo "<td>{$sample['decision']}</td>";
            echo "<td>{$sample['guard_match']}</td>";
            echo "<td>{$sample['explanation']}</td>";
            echo "<td>{$sample['source']}</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
    }
    
    public function export_csv() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'export_diag_csv')) {
            wp_die('Insufficient permissions', 'Export blocked', 403);
        }
        // Friss diagnosztika export előtt
        $this->run_full_diagnostics();

        $type = sanitize_text_field($_GET['type'] ?? 'all');
        $timestamp = date('Y-m-d_H-i-s');
        
        switch ($type) {
            case 'functions':
                $this->export_functions_csv($timestamp);
                break;
            case 'shortcodes':
                $this->export_shortcodes_csv($timestamp);
                break;
            case 'samples':
                $this->export_samples_csv($timestamp);
                break;
            default:
                $this->export_all_csv($timestamp);
        }
    }
    
    private function export_functions_csv($timestamp, $stream = true) {
        $filename = "functions_collisions_$timestamp.csv";
        $filepath = $this->csv_dir . $filename;
        
        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Function Name', 'File', 'Line', 'Has Protection', 'Code', 'Suggestion']);
        
        foreach ($this->issues['function_collisions'] as $func_name => $definitions) {
            foreach ($definitions as $def) {
                fputcsv($fp, [
                    $func_name,
                    $def['file'],
                    $def['line'],
                    $def['has_protection'] ? 'Yes' : 'No',
                    $def['code'],
                    $def['has_protection'] ? 'OK' : 'Add function_exists protection'
                ]);
            }
        }
        
        fclose($fp);
        if ($stream) { $this->download_file($filepath, $filename); } else { echo $filepath; }
    }
    
    private function export_samples_csv($timestamp, $stream = true) {
        $filename = "sample_analysis_$timestamp.csv";
        $filepath = $this->csv_dir . $filename;
        
        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Shop Slug', 'Deeplink', 'URL', 'Decision', 'Guard Match', 'Explanation', 'Is Violation', 'Source']);
        
        foreach ($this->issues['sample_analysis'] as $sample) {
            fputcsv($fp, [
                $sample['shop_slug'],
                $sample['deeplink'],
                $sample['url'],
                $sample['decision'],
                $sample['guard_match'],
                $sample['explanation'],
                $sample['is_violation'] ? 'Yes' : 'No',
                $sample['source']
            ]);
        }
        
        fclose($fp);
        if ($stream) { $this->download_file($filepath, $filename); } else { echo $filepath; }
    }
    
    private function export_all_csv($timestamp, $stream = true) {
        // Create a comprehensive CSV with all issues (flattened)
        $filename = "impactshop_diagnostics_$timestamp.csv";
        $filepath = $this->csv_dir . $filename;

        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Category', 'Type', 'File', 'Line', 'Issue', 'Code', 'Suggestion']);

        foreach ($this->issues as $category => $issues) {
            if ($category === 'sample_analysis') {
                continue; // skip sample_analysis in all export
            }
            $cat = ucfirst(str_replace('_', ' ', $category));

            // Function collisions: map name => [defs]
            if ($category === 'function_collisions' && is_array($issues)) {
                foreach ($issues as $func => $defs) {
                    foreach ((array)$defs as $def) {
                        fputcsv($fp, [
                            $cat,
                            $func,
                            $def['file'] ?? '',
                            $def['line'] ?? '',
                            'Function collision',
                            $def['code'] ?? '',
                            (!empty($def['has_protection'])) ? 'OK' : "Add function_exists('$func')"
                        ]);
                    }
                }
                continue;
            }

            // Shortcode collisions: map shortcode => [defs]
            if ($category === 'shortcode_collisions' && is_array($issues)) {
                foreach ($issues as $shortcode => $defs) {
                    foreach ((array)$defs as $def) {
                        fputcsv($fp, [
                            $cat,
                            $shortcode,
                            $def['file'] ?? '',
                            $def['line'] ?? '',
                            'Shortcode collision',
                            $def['code'] ?? '',
                            'Use unique shortcode name'
                        ]);
                    }
                }
                continue;
            }

            // Flat arrays of issues
            if (is_array($issues)) {
                foreach ($issues as $item) {
                    if (!is_array($item)) continue;
                    fputcsv($fp, [
                        $cat,
                        $item['type'] ?? ($item['issue'] ?? ''),
                        $item['file'] ?? '',
                        $item['line'] ?? '',
                        $item['issue'] ?? '',
                        $item['code'] ?? '',
                        $item['suggestion'] ?? 'Review and fix'
                    ]);
                }
            }
        }

        fclose($fp);
        if ($stream) { $this->download_file($filepath, $filename); } else { echo $filepath; }
    }

    private function export_shortcodes_csv($timestamp, $stream = true) {
        $filename = "shortcodes_collisions_$timestamp.csv";
        $filepath = $this->csv_dir . $filename;

        $fp = fopen($filepath, 'w');
        fputcsv($fp, ['Shortcode', 'File', 'Line', 'Code', 'Suggestion']);

        if (!empty($this->issues['shortcode_collisions'])) {
            foreach ($this->issues['shortcode_collisions'] as $shortcode => $defs) {
                foreach ((array)$defs as $def) {
                    fputcsv($fp, [
                        $shortcode,
                        $def['file'] ?? '',
                        $def['line'] ?? '',
                        $def['code'] ?? '',
                        'Use unique shortcode name'
                    ]);
                }
            }
        }

        fclose($fp);
        if ($stream) { $this->download_file($filepath, $filename); } else { echo $filepath; }
    }
    
    private function download_file($filepath, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}

// WP-CLI parancs: CSV export közvetlenül
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('impactshop diag-export', function($args){
        $type = $args[0] ?? 'all';
        $obj = new ImpactShop_Link_Diagnostics();
        // Privát diagnosztika futtatása publikus út nélkül: trükk – admin_page()-t nem hívjuk
        // Ehelyett a publikus export_csv-et kerüljük és közvetlen CSV írást választunk
        $ref = new ReflectionClass($obj);
        $method = $ref->getMethod('run_full_diagnostics');
        $method->setAccessible(true);
        $method->invoke($obj);

        $timestamp = date('Y-m-d_H-i-s');
        switch ($type) {
            case 'functions': $obj->export_functions_csv($timestamp, false); break;
            case 'shortcodes': $obj->export_shortcodes_csv($timestamp, false); break;
            case 'samples': $obj->export_samples_csv($timestamp, false); break;
            default: $obj->export_all_csv($timestamp, false); break;
        }
        \WP_CLI::success('CSV elkészült');
    });
}

// Initialize the diagnostics
new ImpactShop_Link_Diagnostics();
