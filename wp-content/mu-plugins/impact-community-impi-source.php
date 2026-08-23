<?php
/**
 * Plugin Name: Impact Community — Impi source context
 * Description: Default-off, read-only context projection for the Hatás Körök Impi shadow service.
 * Version:     0.1.0
 * Author:      ImpactShop
 *
 * This module is intentionally separate from the browser/session community
 * routes. It never publishes, mutates, or exposes identity/economic fields.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACT_IMPI_COMMUNITY_SOURCE_ENABLED') || !IMPACT_IMPI_COMMUNITY_SOURCE_ENABLED) {
    return;
}

const IC_IMPI_SOURCE_MAX_ACTIVITIES = 24;
const IC_IMPI_SOURCE_MAX_BODY_BYTES = 4000;
const IC_IMPI_SOURCE_MAX_RETENTION_DAYS = 30;

function ic_impi_source_error($code, $status) {
    return new WP_Error($code, 'Impi forráskörnyezet nem érhető el.', ['status' => $status]);
}

function ic_impi_source_token() {
    if (!defined('IMPACT_IMPI_COMMUNITY_SOURCE_TOKEN')) {
        return '';
    }

    $token = (string) constant('IMPACT_IMPI_COMMUNITY_SOURCE_TOKEN');
    return strlen($token) >= 64 ? $token : '';
}

function ic_impi_source_authorization(WP_REST_Request $request) {
    $authorization = trim((string) $request->get_header('authorization'));
    if (!preg_match('/^Bearer ([A-Za-z0-9._~+\/-]{64,})$/', $authorization, $matches)) {
        return false;
    }

    $expected = ic_impi_source_token();
    return $expected !== '' && hash_equals($expected, $matches[1]);
}

function ic_impi_source_request_id(WP_REST_Request $request) {
    $request_id = trim((string) $request->get_header('x-sharity-impi-request-id'));
    return (bool) preg_match('/^[A-Za-z0-9._:-]{16,120}$/', $request_id);
}

function ic_impi_source_pilot_ids() {
    if (!defined('IMPACT_IMPI_COMMUNITY_SOURCE_CIRCLES')) {
        return [];
    }

    $raw = (string) constant('IMPACT_IMPI_COMMUNITY_SOURCE_CIRCLES');
    $ids = [];
    foreach (preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $candidate) {
        if (ctype_digit($candidate) && (int) $candidate > 0) {
            $ids[(int) $candidate] = true;
        }
    }

    return array_keys($ids);
}

function ic_impi_source_is_pilot($circle_id) {
    return in_array((int) $circle_id, ic_impi_source_pilot_ids(), true);
}

function ic_impi_source_limit_text($value, $limit = IC_IMPI_SOURCE_MAX_BODY_BYTES) {
    $value = trim(wp_strip_all_tags((string) $value));
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strcut')) {
        return trim(mb_strcut($value, 0, $limit, 'UTF-8'));
    }

    return substr($value, 0, $limit);
}

function ic_impi_source_redact_text($value) {
    $value = ic_impi_source_limit_text($value);
    if ($value === '') {
        return '';
    }

    $patterns = [
        '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/iu',
        '/\b[\w.+-]+@[\w.-]+\.[A-Z]{2,}\b/iu',
        '/\b(?:\+?\d[\d\s().-]{7,}\d)\b/u',
        '/\b\d{8,}\b/u',
        '~https?://[^\s]+~iu',
        '/\b(?:bearer|token|cookie|session|nonce|api[_ -]?key)\s*[:=]\s*[^\s,;]+/iu',
    ];
    $redacted = preg_replace($patterns, '[REDACTED]', $value);
    if (!is_string($redacted)) {
        return '';
    }

    return ic_impi_source_limit_text($redacted);
}

function ic_impi_source_activity_kind($post_type) {
    $post_type = (string) $post_type;
    if ($post_type === 'event') {
        return 'event';
    }
    if ($post_type === 'receipt') {
        return 'update';
    }
    if ($post_type === 'decision') {
        return 'help';
    }

    return 'text';
}

function ic_impi_source_context($circle_id) {
    global $wpdb;

    $circle_id = (int) $circle_id;
    if ($circle_id <= 0 || !ic_impi_source_is_pilot($circle_id)) {
        return ic_impi_source_error('context_not_found', 404);
    }

    $prefix = $wpdb->prefix;
    $circle = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, description, is_active FROM {$prefix}ic_circles WHERE id=%d LIMIT 1",
        $circle_id
    ));
    if (!$circle || (int) $circle->is_active !== 1) {
        return ic_impi_source_error('context_not_found', 404);
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, circle_id, post_type, body, created_at FROM {$prefix}ic_posts WHERE circle_id=%d AND is_deleted=0 ORDER BY created_at DESC LIMIT %d",
        $circle_id,
        IC_IMPI_SOURCE_MAX_ACTIVITIES
    ));
    if (!is_array($rows)) {
        return ic_impi_source_error('context_unavailable', 503);
    }

    $activities = [];
    foreach ($rows as $row) {
        $body = ic_impi_source_redact_text($row->body ?? '');
        if ($body === '') {
            continue;
        }
        $created_at = strtotime((string) ($row->created_at ?? ''));
        if ($created_at === false) {
            continue;
        }
        $activities[] = [
            'id' => 'post_' . (int) $row->id,
            'body' => $body,
            'kind' => ic_impi_source_activity_kind($row->post_type ?? 'text'),
            'created_at_utc' => gmdate('c', $created_at),
        ];
    }

    $as_of = gmdate('c');
    return [
        'circle_id' => $circle_id,
        'circle_name' => ic_impi_source_limit_text($circle->name ?? '', 180),
        'mission' => ic_impi_source_redact_text($circle->description ?? ''),
        'public_rules' => '',
        'mode' => 'shadow',
        'topic_allowlist' => [],
        'activities' => array_slice($activities, 0, IC_IMPI_SOURCE_MAX_ACTIVITIES),
        'summary' => 'A körben ' . count($activities) . ' redaktált közösségi aktivitás érhető el.',
        'as_of_utc' => $as_of,
    ];
}

function ic_impi_source_permission(WP_REST_Request $request) {
    if (!ic_impi_source_authorization($request) || !ic_impi_source_request_id($request)) {
        return ic_impi_source_error('unauthorized', 401);
    }

    return true;
}

function ic_impi_source_context_route(WP_REST_Request $request) {
    if (!ic_impi_source_authorization($request) || !ic_impi_source_request_id($request)) {
        return ic_impi_source_error('unauthorized', 401);
    }

    return ic_impi_source_context($request->get_param('circle_id'));
}

function ic_impi_source_register_route() {
    register_rest_route('impact/v1', '/internal/impi/circles/(?P<circle_id>\d+)/context', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'ic_impi_source_context_route',
        'permission_callback' => 'ic_impi_source_permission',
    ]);
}

add_action('rest_api_init', 'ic_impi_source_register_route');
