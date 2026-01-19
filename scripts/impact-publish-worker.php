<?php
// WHAT: Queue/worker skeleton MySQL fallbackhoz (DNS/Redis nélkül).
// WHY: Alap ellenőrzés stuck jobokra és sor státuszra, külső connector hívás nélkül.
// HOW: wp eval-file scripts/impact-publish-worker.php -- --limit=20 --unstick=1

if (php_sapi_name() !== 'cli') {
    exit(0);
}

global $wpdb;
$table = $wpdb->prefix . 'impact_publish_queue';
$limit = 20;
$unstick = false;
$stuck_minutes = 30;
$retry = false;
$max_attempts = 3;
$retry_status = 'failed'; // melyik státuszból hozzuk vissza queued-be, ha retry flag aktív
$auto_fail_processing = false;

foreach ($argv as $idx => $arg) {
    if ($arg === '--limit' && isset($argv[$idx + 1])) {
        $limit = (int) $argv[$idx + 1];
    }
    if ($arg === '--unstick') {
        $unstick = true;
    }
    if ($arg === '--stuck-minutes' && isset($argv[$idx + 1])) {
        $stuck_minutes = (int) $argv[$idx + 1];
    }
    if ($arg === '--retry') {
        $retry = true;
    }
    if ($arg === '--retry-status' && isset($argv[$idx + 1])) {
        $retry_status = $argv[$idx + 1];
    }
    if ($arg === '--max-attempts' && isset($argv[$idx + 1])) {
        $max_attempts = (int) $argv[$idx + 1];
    }
    if ($arg === '--fail-processing') {
        $auto_fail_processing = true;
    }
}

$queued = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status IN ('queued','pending','approved') ORDER BY priority ASC, id ASC LIMIT %d",
        $limit
    ),
    ARRAY_A
);

$stuck = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = 'processing' AND processed_at IS NOT NULL AND processed_at < %s",
        gmdate('Y-m-d H:i:s', time() - ($stuck_minutes * 60))
    ),
    ARRAY_A
);

$output = [
    'queued' => $queued ?: [],
    'stuck' => $stuck ?: [],
];

echo wp_json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;

if ($unstick && !empty($stuck)) {
    foreach ($stuck as $row) {
        $wpdb->update(
            $table,
            [
                'status' => 'failed',
                'error' => 'stuck timeout, auto-failed',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $row['id']]
        );
    }
    fwrite(STDERR, "[info] unstuck " . count($stuck) . " job(s)\n");
}

// Egyszerű státuszváltás stub: a queued → processing jelölés (nincs tényleges publish)
foreach ($queued as $row) {
    $wpdb->update(
        $table,
        [
            'status' => 'processing',
            'processed_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ],
        ['id' => $row['id']]
    );
}

// Ha kérjük, a túl sokáig processing státuszú jobokat failre állítjuk (stuck rule kiterjesztése)
if ($auto_fail_processing && !empty($stuck)) {
    foreach ($stuck as $row) {
        $wpdb->update(
            $table,
            [
                'status' => 'failed',
                'error' => 'processing timeout',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $row['id']]
        );
    }
    fwrite(STDERR, "[info] auto-failed " . count($stuck) . " processing job(s)\n");
}

// Retry stub: failed/rejected/etc. vissza queued-be, ha attempts < max_attempts és --retry flag aktív
if ($retry) {
    $to_retry = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %s AND attempts < %d LIMIT %d",
            $retry_status,
            $max_attempts,
            $limit
        ),
        ARRAY_A
    );
    foreach ($to_retry as $row) {
        $wpdb->update(
            $table,
            [
                'status' => 'queued',
                'attempts' => (int) $row['attempts'] + 1,
                'updated_at' => current_time('mysql'),
                'error' => null,
            ],
            ['id' => $row['id']]
        );
    }
    if (!empty($to_retry)) {
        fwrite(STDERR, "[info] retried " . count($to_retry) . " job(s) from status={$retry_status}\n");
    }
}
