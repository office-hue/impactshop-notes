<?php
/**
 * CLI helper: NormalizedAdMetric JSON -> impact_ledger insert (via impact_ledger_upsert_metric).
 * Usage: wp eval-file scripts/impact-ledger-import.php -- [--dry-run] < metrics.json
 * Input: JSON array of metrics: [{platform, date, ledger_source, campaign_id?, ad_id?, views?, clicks?, spend?, est_donation, ngo_code?, advertiser_code?, cap?, meta?}]
 */

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__) . '/wp-load.php';
}

if (!function_exists('impact_ledger_upsert_metric')) {
    fwrite(STDERR, "impact_ledger_upsert_metric() not found. Load impact-ledger-sync.php MU plugin.\n");
    exit(1);
}

// Simple arg parse for --dry-run (guard argv null)
$argv = isset($argv) && is_array($argv) ? $argv : [];
$dry_run = in_array('--dry-run', $argv, true);

function impact_validate_metric(array $metric)
{
    $required = ['platform', 'date', 'ledger_source', 'est_donation'];
    foreach ($required as $field) {
        if (!isset($metric[$field])) {
            return new WP_Error('missing_field', "Missing: $field");
        }
    }
    if (!in_array($metric['platform'], ['meta', 'google', 'tiktok', 'youtube', 'ga4', 'dognet', 'manual'], true)) {
        return new WP_Error('invalid_platform', $metric['platform']);
    }
    if (!in_array($metric['ledger_source'], ['view', 'click'], true)) {
        return new WP_Error('invalid_source', $metric['ledger_source']);
    }
    if (!is_numeric($metric['est_donation'])) {
        return new WP_Error('invalid_amount', 'est_donation must be numeric');
    }
    return true;
}

$input = stream_get_contents(STDIN);
$data = json_decode($input, true);
if (is_array($data) && array_key_exists('metrics', $data) && is_array($data['metrics'])) {
    $data = $data['metrics'];
}
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON input.\n");
    exit(1);
}

$input_count = count($data);
$inserted = 0;
$skipped = 0;
$errors = 0;
$start = microtime(true);

$chunked = array_chunk($data, 500);
foreach ($chunked as $idx => $chunk) {
    fwrite(STDERR, "Processing chunk " . ($idx + 1) . "/" . count($chunked) . " (" . count($chunk) . " items)...\n");
    foreach ($chunk as $metric) {
        if (!is_array($metric)) {
            $errors++;
            continue;
        }
        $valid = impact_validate_metric($metric);
        if (is_wp_error($valid)) {
            $errors++;
            fwrite(STDERR, "Validation error: " . $valid->get_error_message() . " | metric=" . json_encode($metric) . "\n");
            continue;
        }
        if ($dry_run) {
            $skipped++;
            continue;
        }
        $res = impact_ledger_upsert_metric($metric);
        if (is_wp_error($res)) {
            $errors++;
            fwrite(STDERR, "Error: " . $res->get_error_message() . " | metric=" . json_encode($metric) . "\n");
            continue;
        }
        if ($res === false) {
            $errors++;
            continue;
        }
        // dedup vagy új beszúrás: sikeres
        $inserted++;
    }
}

$duration = microtime(true) - $start;

echo json_encode([
    'timestamp' => gmdate('c'),
    'input_count' => $input_count,
    'inserted' => $inserted,
    'skipped' => $skipped,
    'errors' => $errors,
    'duration_sec' => round($duration, 3),
    'dry_run' => $dry_run,
], JSON_PRETTY_PRINT) . PHP_EOL;
