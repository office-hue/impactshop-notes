<?php
/** Virtual private filesystem fixture; never writes the real fixed host path. */
declare(strict_types=1);
if (($argv[1] ?? '') === 'child') {
    define('ABSPATH', __DIR__ . '/');
    function add_action(...$args): void {}
    function add_filter(...$args): void {}
    $GLOBALS['vfs'] = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
    $source = file_get_contents(__DIR__ . '/../wp-content/mu-plugins/impactshop-jysk-game.php');
    $source = str_replace(['<?php', 'declare(strict_types=1);'], '', $source);
    $filesystem = <<<'PHP'
namespace PrivateFixture;
use \RuntimeException;
function file_exists($p) { return $GLOBALS['vfs']['exists'] ?? true; }
function is_link($p) { return $GLOBALS['vfs'][str_ends_with($p, '.json') ? 'fileSymlink' : 'dirSymlink'] ?? false; }
function is_dir($p) { return true; }
function is_file($p) { return true; }
function fileperms($p) { return $GLOBALS['vfs'][str_ends_with($p, '.json') ? 'fileMode' : 'dirMode'] ?? (str_ends_with($p, '.json') ? 0600 : 0700); }
function filesize($p) { return $GLOBALS['vfs']['size'] ?? strlen($GLOBALS['vfs']['raw']); }
function fileowner($p) { return str_ends_with($p, '.json') ? ($GLOBALS['vfs']['fileOwner'] ?? 501) : 501; }
function file_get_contents(...$args) { return $GLOBALS['vfs']['raw']; }
PHP;
    eval($filesystem . $source);
    echo json_encode(PrivateFixture\Impactshop_Jysk_Game::configurationStatus());
    exit;
}
$valid = ['ENABLED' => true, 'SERVICE_KEY' => str_repeat('1', 64), 'SUBJECT_KEY' => str_repeat('2', 64), 'REWARDS_ENABLED' => false, 'TRANSACTION_READY' => false];
$cases = [
    ['absent', ['exists' => false], 'absent', false],
    ['valid own profile', [], 'ready', true],
    ['symlink file', ['fileSymlink' => true], 'invalid', false],
    ['symlink directory', ['dirSymlink' => true], 'invalid', false],
    ['public-readable file', ['fileMode' => 0644], 'invalid', false],
    ['public-searchable dir', ['dirMode' => 0755], 'invalid', false],
    ['owner mismatch', ['fileOwner' => 502], 'invalid', false],
    ['oversized', ['size' => 16385], 'invalid', false],
    ['unknown field', ['raw' => json_encode($valid + ['UNKNOWN' => true])], 'invalid', false],
    ['missing key', ['raw' => '{"ENABLED":true}'], 'invalid', false],
    ['wrong type', ['raw' => json_encode(array_replace($valid, ['ENABLED' => 1]))], 'invalid', false],
    ['same keys', ['raw' => json_encode(array_replace($valid, ['SUBJECT_KEY' => str_repeat('1', 64)]))], 'invalid', false],
    ['multiple technical users', ['raw' => json_encode($valid + ['TEST_SUBJECTS' => [str_repeat('a', 64), str_repeat('b', 64)]])], 'invalid', false],
    ['invalid json', ['raw' => '{'], 'invalid', false],
];
$count = 0;
foreach ($cases as [$name, $overrides, $status, $enabled]) {
    $process = proc_open([PHP_BINARY, __FILE__, 'child'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    fwrite($pipes[0], json_encode(array_replace(['raw' => json_encode($valid)], $overrides))); fclose($pipes[0]);
    $actual = json_decode(stream_get_contents($pipes[1]), true); fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || $errors !== '' || ($actual['privateProfile'] ?? null) !== $status || ($actual['enabled'] ?? null) !== $enabled
        || ($actual['rewardsEnabled'] ?? null) !== false || ($actual['transactionFlag'] ?? null) !== false) {
        throw new RuntimeException('FAIL private profile: ' . $name . ' ' . $errors);
    }
    $count++;
}
echo "PASS {$count} virtual private-profile scenarios (no host files written)\n";
