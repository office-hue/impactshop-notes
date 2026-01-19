<?php
// WHAT: Token health guard (CLI): expiring/expired tokenok listázása JSON-ként.
// WHY: Cronból vagy manuálisan futtatva jelzi a lejáró platform tokeneket DNS nélkül.
// HOW: wp eval-file scripts/token-health-guard.php -- --hours=24

if (php_sapi_name() !== 'cli') {
    exit(0);
}

// Argumentum parsing (egyszerű)
$hours = 24;
foreach ($argv as $idx => $arg) {
    if ($arg === '--hours' && isset($argv[$idx + 1])) {
        $hours = (int) $argv[$idx + 1];
    }
}

if (!function_exists('impact_publisher_token_health')) {
    fwrite(STDERR, "impact_publisher_token_health() not found. Ensure MU plugin is loaded.\n");
    exit(1);
}

$report = impact_publisher_token_health($hours);
echo wp_json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;

if (!empty($report['expiring']) || !empty($report['expired'])) {
    fwrite(STDERR, "[warn] expiring/expired tokens detected\n");
}
