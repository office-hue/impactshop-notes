<?php
/**
 * Proxy /ai-agent/* kérések az ai-agent service felé, mod_proxy nélkül.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Debug jelzés, hogy a MU plugin betöltődött.
if (function_exists('error_log')) {
    error_log('ai-agent-proxy MU plugin loaded');
}

add_action('init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/ai-agent') === false) {
        return;
    }
    ai_agent_forward_request($uri);
});

function ai_agent_forward_request(string $uri): void {
    // WordPress alap path nélkül továbbítjuk (ha /impactshop/... prefix van, levágjuk).
    $path = $uri;
    if (strpos($path, '/impactshop/ai-agent') === 0) {
        $path = substr($path, strlen('/impactshop'));
    }
    if (strpos($path, '/ai-agent') === 0) {
        $path = substr($path, strlen('/ai-agent'));
        if ($path === '') {
            $path = '/';
        }
    }
    $target = 'http://127.0.0.1:4000' . $path;
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $body = file_get_contents('php://input');

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    unset($headers['Host'], $headers['Content-Length']);

    $response = wp_remote_request($target, [
        'method'  => $method,
        'body'    => $body,
        'headers' => $headers,
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        status_header(502);
        echo 'ai-agent proxy error';
        exit;
    }

    $code = wp_remote_retrieve_response_code($response);
    $respHeaders = wp_remote_retrieve_headers($response);
    if ($respHeaders && isset($respHeaders['content-type'])) {
        header('Content-Type: ' . $respHeaders['content-type']);
    }
    status_header($code);
    echo wp_remote_retrieve_body($response);
    exit;
}

/**
 * WP REST proxy: /wp-json/ai-agent/v1/ping és /wp-json/ai-agent/v1/chat
 */
add_action('rest_api_init', function () {
    register_rest_route('ai-agent/v1', '/debug', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            return new WP_REST_Response(['status' => 'ok', 'message' => 'ai-agent-proxy MU route active'], 200);
        },
    ]);

    register_rest_route('ai-agent/v1', '/ping', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            $resp = wp_remote_get('http://127.0.0.1:4000/healthz', ['timeout' => 10]);
            if (is_wp_error($resp)) {
                return new WP_REST_Response(['status' => 'error', 'message' => $resp->get_error_message()], 502);
            }
            $code = wp_remote_retrieve_response_code($resp) ?: 500;
            $body = wp_remote_retrieve_body($resp);
            $data = json_decode($body, true);
            return new WP_REST_Response($data ?: ['raw' => $body], $code);
        },
    ]);

    register_rest_route('ai-agent/v1', '/chat', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $request) {
            $payload = $request->get_body();
            $resp = wp_remote_request('http://127.0.0.1:4000/api/v1/chat/impi', [
                'method'  => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => $payload,
                'timeout' => 20,
            ]);
            if (is_wp_error($resp)) {
                return new WP_REST_Response(['status' => 'error', 'message' => $resp->get_error_message()], 502);
            }
            $code = wp_remote_retrieve_response_code($resp) ?: 500;
            $body = wp_remote_retrieve_body($resp);
            $data = json_decode($body, true);
            return new WP_REST_Response($data ?: ['raw' => $body], $code);
        },
    ]);
});
