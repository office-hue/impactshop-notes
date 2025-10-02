<?php
/**
 * ImpactShop Diagnostics CSV Analyzer
 * Elemzi a meglévő diagnosztikai CSV fájlt
 */

$csv_file = '/Users/bujdosoarnold/Documents/GitHub/impactshop-notes/impactshop_diagnostics_2025-10-02_04-53-16.csv';

class DiagnosticsAnalyzer {
    
    private $data = [];
    private $stats = [];
    
    public function __construct($csv_file) {
        $this->loadCSV($csv_file);
        $this->analyzeData();
    }
    
    private function loadCSV($file) {
        if (!file_exists($file)) {
            die("CSV fájl nem található: $file\n");
        }
        
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);
        
        echo "📋 **CSV HEADERS:** " . implode(' | ', $headers) . "\n\n";
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= count($headers)) {
                $this->data[] = array_combine($headers, $row);
            }
        }
        
        fclose($handle);
        echo "📊 **TOTAL RECORDS:** " . count($this->data) . "\n\n";
    }
    
    private function analyzeData() {
        $this->stats = [
            'total_issues' => count($this->data),
            'categories' => [],
            'files' => [],
            'critical_issues' => 0,
            'function_issues' => 0,
            'shortcode_issues' => 0,
            'deeplink_issues' => 0,
            'guard_issues' => 0,
            'param_issues' => 0
        ];
        
        foreach ($this->data as $row) {
            // Category analysis
            $category = $row['Category'] ?? 'Unknown';
            if (!isset($this->stats['categories'][$category])) {
                $this->stats['categories'][$category] = 0;
            }
            $this->stats['categories'][$category]++;
            
            // File analysis
            $file = $row['File'] ?? 'Unknown';
            if (!isset($this->stats['files'][$file])) {
                $this->stats['files'][$file] = 0;
            }
            $this->stats['files'][$file]++;
            
            // Issue type analysis
            $issue = strtolower($row['Issue'] ?? '');
            if (strpos($issue, 'function') !== false) {
                $this->stats['function_issues']++;
            }
            if (strpos($issue, 'shortcode') !== false) {
                $this->stats['shortcode_issues']++;
            }
            if (strpos($issue, 'deeplink') !== false) {
                $this->stats['deeplink_issues']++;
            }
            if (strpos($issue, 'guard') !== false) {
                $this->stats['guard_issues']++;
            }
            if (strpos($issue, 'parameter') !== false || strpos($issue, 'param') !== false) {
                $this->stats['param_issues']++;
            }
            if (strpos($issue, 'critical') !== false || strpos($issue, 'error') !== false) {
                $this->stats['critical_issues']++;
            }
        }
    }
    
    public function generateReport() {
        echo "🎯 **IMPACTSHOP DIAGNOSTICS ANALYSIS REPORT**\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
        
        $this->printSummary();
        $this->printCategoryBreakdown();
        $this->printFileAnalysis();
        $this->printCriticalIssues();
        $this->printRecommendations();
    }
    
    private function printSummary() {
        echo "📊 **ÖSSZEFOGLALÓ STATISZTIKÁK**\n";
        echo "-" . str_repeat("-", 30) . "\n";
        echo "Összes probléma: {$this->stats['total_issues']}\n";
        echo "Függvény problémák: {$this->stats['function_issues']}\n";
        echo "Shortcode problémák: {$this->stats['shortcode_issues']}\n";
        echo "Deeplink problémák: {$this->stats['deeplink_issues']}\n";
        echo "Guard problémák: {$this->stats['guard_issues']}\n";
        echo "Paraméter problémák: {$this->stats['param_issues']}\n";
        echo "Kritikus problémák: {$this->stats['critical_issues']}\n\n";
    }
    
    private function printCategoryBreakdown() {
        echo "📋 **KATEGÓRIA BREAKDOWN**\n";
        echo "-" . str_repeat("-", 25) . "\n";
        arsort($this->stats['categories']);
        foreach ($this->stats['categories'] as $category => $count) {
            echo sprintf("%-25s: %d\n", $category, $count);
        }
        echo "\n";
    }
    
    private function printFileAnalysis() {
        echo "📁 **PROBLÉMÁS FÁJLOK (TOP 10)**\n";
        echo "-" . str_repeat("-", 30) . "\n";
        arsort($this->stats['files']);
        $top_files = array_slice($this->stats['files'], 0, 10, true);
        foreach ($top_files as $file => $count) {
            echo sprintf("%-50s: %d probléma\n", basename($file), $count);
        }
        echo "\n";
    }
    
    private function printCriticalIssues() {
        echo "🚨 **KRITIKUS PROBLÉMÁK RÉSZLETESEN**\n";
        echo "-" . str_repeat("-", 35) . "\n";
        
        $critical_count = 0;
        foreach ($this->data as $row) {
            $issue = strtolower($row['Issue'] ?? '');
            if (strpos($issue, 'critical') !== false || 
                strpos($issue, 'error') !== false ||
                strpos($issue, 'collision') !== false ||
                strpos($issue, 'redeclare') !== false) {
                
                $critical_count++;
                echo "🔴 **KRITIKUS #{$critical_count}**\n";
                echo "   Fájl: " . basename($row['File'] ?? 'N/A') . "\n";
                echo "   Sor: " . ($row['Line'] ?? 'N/A') . "\n";
                echo "   Probléma: " . ($row['Issue'] ?? 'N/A') . "\n";
                echo "   Javaslat: " . ($row['Suggestion'] ?? 'N/A') . "\n";
                echo "   Kód: " . substr($row['Code'] ?? '', 0, 80) . "...\n\n";
            }
        }
        
        if ($critical_count === 0) {
            echo "✅ Nincsenek kritikus problémák!\n\n";
        }
    }
    
    private function printRecommendations() {
        echo "💡 **JAVASOLT LÉPÉSEK PRIORITÁS SZERINT**\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        if ($this->stats['function_issues'] > 0) {
            echo "🔥 **PRIORITÁS 1: Függvény ütközések**\n";
            echo "   - Add hozzá function_exists() védelmet minden impact_/dognet_/sharity_ függvényhez\n";
            echo "   - Ellenőrizd a dupla definíciókat\n\n";
        }
        
        if ($this->stats['shortcode_issues'] > 0) {
            echo "⚡ **PRIORITÁS 2: Shortcode ütközések**\n";
            echo "   - Egyedi shortcode neveket használj\n";
            echo "   - Ellenőrizd az add_shortcode() hívásokat\n\n";
        }
        
        if ($this->stats['deeplink_issues'] > 0) {
            echo "🎯 **PRIORITÁS 3: Deeplink-first szabály**\n";
            echo "   - Módosítsd: \$href = \$r['deeplink'] ?? \$r['url'];\n";
            echo "   - Mindig deeplink-et preferálj URL helyett\n\n";
        }
        
        if ($this->stats['guard_issues'] > 0) {
            echo "🛡️ **PRIORITÁS 4: Guard javítások**\n";
            echo "   - Bővítsd a go-deal guard-ot ?u= és #u= paraméterek felismerésére\n";
            echo "   - Ellenőrizd az URL fragment parsing-ot\n\n";
        }
        
        if ($this->stats['param_issues'] > 0) {
            echo "🔗 **PRIORITÁS 5: Paraméter megőrzés**\n";
            echo "   - Használj add_query_arg()-ot redirecteknél\n";
            echo "   - Őrizd meg d1/amb/src/utm paramétereket\n\n";
        }
        
        echo "🔧 **ÁLTALÁNOS JAVASLATOK:**\n";
        echo "   - Backup készítése minden módosítás előtt\n";
        echo "   - Staging environment-ben tesztelés\n";
        echo "   - Code review minden változtatásnál\n";
        echo "   - Automatikus tesztek írása kritikus funkciókhoz\n\n";
    }
    
    public function exportFixScript() {
        $script_file = dirname(__FILE__) . '/fix-diagnostics.php';
        
        $script = "<?php\n";
        $script .= "/**\n";
        $script .= " * Auto-generated fix script based on diagnostics\n";
        $script .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $script .= " */\n\n";
        
        $script .= "// Function protection fixes\n";
        foreach ($this->data as $row) {
            if (strpos(strtolower($row['Issue'] ?? ''), 'function') !== false) {
                $script .= "// Fix for {$row['File']} line {$row['Line']}\n";
                $script .= "// Issue: {$row['Issue']}\n";
                $script .= "// Suggestion: {$row['Suggestion']}\n\n";
            }
        }
        
        file_put_contents($script_file, $script);
        echo "🔧 **FIX SCRIPT GENERATED:** $script_file\n\n";
    }
}

// Run analysis
if (file_exists($csv_file)) {
    $analyzer = new DiagnosticsAnalyzer($csv_file);
    $analyzer->generateReport();
    $analyzer->exportFixScript();
} else {
    echo "❌ CSV fájl nem található: $csv_file\n";
    echo "Ellenőrizd a fájl elérési útját!\n";
}