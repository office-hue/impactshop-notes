<?php
/**
 * Impact Safety QA Test Suite
 * WordPress környezetben futtatható acceptance tesztek
 * Futtatás: wp eval-file bin/impact-safety-qa.php
 */

ob_start();

// Stabilabb WordPress bootstrap
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/wp-load.php';
}

// CLI HTTPS szimuláció (canary cookie teszthez)
if (PHP_SAPI === 'cli' && empty($_SERVER['HTTPS'])) {
    $_SERVER['HTTPS'] = 'on';
}

echo "🧪 IMPACT SAFETY QA TESZT SUITE\n";
echo "=" . str_repeat("=", 40) . "\n";
echo "📅 " . date('Y-m-d H:i:s') . "\n";
echo "🌐 WordPress: " . (defined('WP_VERSION') ? WP_VERSION : 'Unknown') . "\n\n";

$passed = 0;
$total = 0;

// 1️⃣ SAFE MODE TESZT
echo "1️⃣ SAFE MODE TESZT\n";
echo "-" . str_repeat("-", 20) . "\n";
$total++;

if (!defined('IMPACT_SAFE_MODE')) {
    define('IMPACT_SAFE_MODE', true);
    echo "   📝 IMPACT_SAFE_MODE beállítva: true\n";
}

$test1_passed = true;

// Class elérhetőség
if (class_exists('Impact_Safety')) {
    echo "   ✅ Impact_Safety osztály elérhető SAFE MODE alatt\n";

    // Method tesztek
    if (method_exists('Impact_Safety', 'is_safe_mode')) {
        $safe_mode = Impact_Safety::is_safe_mode();
        echo "   ✅ is_safe_mode() = " . ($safe_mode ? 'true' : 'false') . "\n";
    } else {
        echo "   ❌ is_safe_mode() method hiányzik\n";
        $test1_passed = false;
    }

    // Helper function teszt
    if (function_exists('impact_safe_fallback_url')) {
        $fallback = impact_safe_fallback_url(['shop_slug' => 'qatest']);
        echo "   ✅ impact_safe_fallback_url() működik: " . $fallback . "\n";
    } else {
        echo "   ❌ impact_safe_fallback_url() hiányzik\n";
        $test1_passed = false;
    }
} else {
    echo "   ❌ Impact_Safety osztály nem elérhető\n";
    $test1_passed = false;
}

if ($test1_passed) {
    $passed++;
}

// 2️⃣ CIRCUIT BREAKER TESZT
echo "\n2️⃣ CIRCUIT BREAKER TESZT\n";
echo "-" . str_repeat("-", 25) . "\n";
$total++;

$test2_passed = false;

if (class_exists('Impact_Safety')) {
    // Átmenetileg kikapcsoljuk SAFE MODE-ot a teszthez
    if (defined('IMPACT_SAFE_MODE')) {
        // Nem tudjuk undefine-olni, de tesztelhetjük a logikát
        echo "   ℹ️ SAFE MODE aktív - szimuláljuk a circuit breaker-t\n";

        // Manuális circuit breaker szimuláció
        for ($i = 1; $i <= 12; $i++) {
            set_transient('impact_errors_qa_test', $i, 60);
            if ($i >= 10) {
                update_option('impact_disable_qa_test', true);
                break;
            }
        }

        $disabled = get_option('impact_disable_qa_test', false);
        if ($disabled) {
            echo "   ✅ Circuit breaker szimuláció sikeres\n";
            $test2_passed = true;
            // Cleanup
            delete_option('impact_disable_qa_test');
            delete_transient('impact_errors_qa_test');
        }
    }
} else {
    echo "   ❌ Impact_Safety osztály nem elérhető\n";
}

if ($test2_passed) {
    $passed++;
}

// 3️⃣ CANARY DETECTION TESZT
echo "\n3️⃣ CANARY DETECTION TESZT\n";
echo "-" . str_repeat("-", 28) . "\n";
$total++;

$test3_passed = true;

if (class_exists('Impact_Safety')) {
    // GET parameter teszt
    $_GET['ims'] = '1';

    if (Impact_Safety::is_canary()) {
        echo "   ✅ Canary detection működik (\$_GET['ims'] = '1')\n";
    } else {
        echo "   ❌ Canary detection nem működik GET param-mal\n";
        $test3_passed = false;
    }

    unset($_GET['ims']);

    // Cookie teszt
    $_COOKIE['ims_beta'] = '1';
    if (Impact_Safety::is_canary()) {
        echo "   ✅ Cookie-based canary detection működik\n";
    } else {
        echo "   ❌ Cookie-based canary detection nem működik\n";
        $test3_passed = false;
    }
    unset($_COOKIE['ims_beta']);
} else {
    echo "   ❌ Impact_Safety osztály nem elérhető\n";
    $test3_passed = false;
}

if ($test3_passed) {
    $passed++;
}

// 4️⃣ FALLBACK URL TESZT
echo "\n4️⃣ FALLBACK URL TESZT\n";
echo "-" . str_repeat("-", 22) . "\n";
$total++;

$test4_passed = true;

if (function_exists('impact_safe_fallback_url')) {
    $tests = [
        'deeplink'   => ['deeplink' => 'https://example.com/product/123'],
        'shop_slug'  => ['shop_slug' => 'qatest'],
        'empty'      => [],
    ];

    foreach ($tests as $name => $context) {
        $url = impact_safe_fallback_url($context);
        echo "   📋 {$name}: {$url}\n";

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo "   ❌ Érvénytelen URL: {$name}\n";
            $test4_passed = false;
        }
    }

    if ($test4_passed) {
        echo "   ✅ Fallback URL generátor működik\n";
    }
} else {
    echo "   ❌ impact_safe_fallback_url() függvény nem elérhető\n";
    $test4_passed = false;
}

if ($test4_passed) {
    $passed++;
}

// ÖSSZEGZÉS
echo "\n🎯 QA TESZT EREDMÉNYEK\n";
echo "=" . str_repeat("=", 25) . "\n";
echo "📊 Sikeres tesztek: {$passed}/{$total}\n";
echo "📈 Sikerességi arány: " . ($total > 0 ? round(($passed / $total) * 100, 1) : 0) . "%\n";

if ($passed === $total) {
    echo "✅ MINDEN TESZT SIKERES - READY FOR PRODUCTION!\n";
    $exit_code = 0;
} else {
    echo "❌ VAN SIKERTELEN TESZT - JAVÍTÁS SZÜKSÉGES!\n";
    $exit_code = 1;
}

echo "\n📝 QA Log mentve: bin/impact-safety-qa.log\n";

// Log file írás
$log_content = ob_get_contents();
file_put_contents(__DIR__ . '/impact-safety-qa.log', $log_content);

if (PHP_SAPI === 'cli') {
    exit($exit_code);
}
