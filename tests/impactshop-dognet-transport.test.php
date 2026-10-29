<?php
/** Test each real transport definition in its own process, without plugin bootstrap/secrets/network. */
if (!isset($argv[1])) {
    foreach (['impactshop-netflix-shortcodes.php', 'impactshop-rest-coupons.php'] as $source) {
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($source), $status);
        if ($status !== 0) { exit($status); }
    }
    echo "Dognet transport fixtures passed\n";
    exit(0);
}
$allowed = ['impactshop-netflix-shortcodes.php', 'impactshop-rest-coupons.php'];
if (!in_array($argv[1], $allowed, true)) { throw new RuntimeException('Unexpected fixture source'); }
$tokens = token_get_all(file_get_contents(__DIR__ . '/../wp-content/mu-plugins/' . $argv[1]));
$function = ''; $collect = false; $opened = false; $depth = 0;
foreach ($tokens as $i => $token) {
    if (!$collect && is_array($token) && $token[0] === T_FUNCTION) {
        for ($j = $i + 1; isset($tokens[$j]); ++$j) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) { continue; }
            $collect = is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === 'dognet_api_request';
            break;
        }
    }
    if (!$collect) { continue; }
    $function .= is_array($token) ? $token[1] : $token;
    if ($token === '{') { ++$depth; $opened = true; }
    if ($token === '}') {
        --$depth;
        if ($opened && $depth === 0) { break; }
    }
}
if (!$opened || $depth !== 0) { throw new RuntimeException('Real transport function not found'); }
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data = []) {}
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function dognet_get_token($force = false) { if ($force) { ++$GLOBALS['refreshes']; } return 'fixture-token'; }
function get_transient($key) { return $GLOBALS['cached_token']; }
function delete_transient($key) { ++$GLOBALS['deletions']; }
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_request($url, $args) {
    ++$GLOBALS['requests'];
    if ($GLOBALS['requests'] > 2) { throw new RuntimeException('Unexpected unbounded resource retry'); }
    return ['status' => $GLOBALS['code'], 'body' => '{}'];
}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
eval($function);
function reset_case($code) {
    $GLOBALS['code'] = $code; $GLOBALS['requests'] = 0; $GLOBALS['refreshes'] = 0; $GLOBALS['deletions'] = 0;
    $GLOBALS['cached_token'] = 'fixture-token';
}
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
reset_case(401);
$cached_token = false;
$result = dognet_api_request('POST', '/raw-transactions/filter', [], false);
check(is_wp_error($result) && $requests === 0 && $refreshes === 0, 'No-refresh without cache must not log in or request');
reset_case(401);
$result = dognet_api_request('POST', '/raw-transactions/filter', [], false);
check(is_wp_error($result) && $requests === 1 && $refreshes === 0 && $deletions === 0, 'Points401 must make one request and no refresh');
reset_case(401);
$result = dognet_api_request('POST', '/raw-transactions/filter', []);
check(is_wp_error($result) && $requests === 2 && $refreshes === 1 && $deletions === 1, 'Default401 must refresh at most once');
reset_case(429);
$result = dognet_api_request('POST', '/raw-transactions/filter', []);
check(is_wp_error($result) && $requests === 1 && $refreshes === 0, '429 must not retry');
reset_case(200);
check(dognet_api_request('POST', '/raw-transactions/filter', [], false) === [] && $requests === 1, 'Normal response unchanged');
echo $argv[1] . " transport passed\n";
