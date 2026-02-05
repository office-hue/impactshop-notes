<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_SURVEY_SCHEMA_VERSION = '1.0.0';
const IMPACTSHOP_SURVEY_OPTION_SCHEMA = 'impactshop_survey_schema_version';
const IMPACTSHOP_SURVEY_OPTION_MAPPING_HASH = 'impactshop_survey_mapping_hash';
const IMPACTSHOP_SURVEY_OPTION_TAXONOMY_HASH = 'impactshop_survey_taxonomy_hash';
const IMPACTSHOP_SURVEY_DATA_DIR = __DIR__ . '/impactshop-offerwall-survey-data';
const IMPACTSHOP_SURVEY_MAPPING_FILE = IMPACTSHOP_SURVEY_DATA_DIR . '/question_mapping.csv';
const IMPACTSHOP_SURVEY_TAXONOMY_FILE = IMPACTSHOP_SURVEY_DATA_DIR . '/segment_taxonomy.csv';
const IMPACTSHOP_SURVEY_W_TARGET = 12;
const IMPACTSHOP_SURVEY_RATE_LIMIT = 10; // per user per hour

add_action('muplugins_loaded', 'impactshop_offerwall_survey_bootstrap');

function impactshop_offerwall_survey_bootstrap(): void
{
    impactshop_offerwall_survey_maybe_install();
    impactshop_offerwall_survey_ensure_provider();
    add_filter('impactshop_offerwall_iframe_url', 'impactshop_offerwall_survey_iframe_url', 10, 3);
    add_filter('rest_pre_dispatch', 'impactshop_offerwall_survey_pre_dispatch', 10, 3);
    add_action('impactshop_offerwall_rewards_awarded', 'impactshop_offerwall_survey_handle_rewards', 10, 2);
    add_action('impactshop_offerwall_fraud', 'impactshop_offerwall_survey_log_fraud', 10, 2);
    add_action('admin_menu', 'impactshop_offerwall_survey_admin_menu');
    add_action('wp_dashboard_setup', 'impactshop_offerwall_survey_dashboard_widget');
}

function impactshop_offerwall_survey_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_SURVEY_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_SURVEY_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $answers_table = $wpdb->prefix . 'impactshop_survey_answers';
    $scores_table = $wpdb->prefix . 'impactshop_segment_scores';
    $prefs_table = $wpdb->prefix . 'impactshop_segment_prefs';
    $fraud_table = $wpdb->prefix . 'impactshop_offerwall_fraud_log';

    $sql_answers = "CREATE TABLE {$answers_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        survey_id VARCHAR(64) NOT NULL,
        target_id VARCHAR(32) NOT NULL DEFAULT 'impactad',
        answers_json LONGTEXT NOT NULL,
        question_count TINYINT UNSIGNED NOT NULL,
        survey_version VARCHAR(16) DEFAULT 'v1',
        mapping_version VARCHAR(16) DEFAULT 'v1',
        request_id VARCHAR(128) DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_user_survey (pseudo_id, survey_id),
        KEY idx_pseudo (pseudo_id),
        KEY idx_survey (survey_id),
        KEY idx_created (created_at)
    ) {$charset};";

    $sql_scores = "CREATE TABLE {$scores_table} (
        pseudo_id VARCHAR(32) NOT NULL,
        segment_code VARCHAR(32) NOT NULL,
        sum_val FLOAT NOT NULL DEFAULT 0,
        weight_val FLOAT NOT NULL DEFAULT 0,
        conf_val FLOAT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (pseudo_id, segment_code)
    ) {$charset};";

    $sql_prefs = "CREATE TABLE {$prefs_table} (
        pseudo_id VARCHAR(32) NOT NULL,
        segment_code VARCHAR(32) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (pseudo_id, segment_code)
    ) {$charset};";

    $sql_fraud = "CREATE TABLE {$fraud_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        reason VARCHAR(64) NOT NULL,
        provider VARCHAR(64) NOT NULL DEFAULT '',
        pseudo_id VARCHAR(32) NOT NULL DEFAULT '',
        ip VARCHAR(64) NOT NULL DEFAULT '',
        context_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_reason (reason),
        KEY idx_provider (provider),
        KEY idx_created (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_answers);
    dbDelta($sql_scores);
    dbDelta($sql_prefs);
    dbDelta($sql_fraud);

    update_option(IMPACTSHOP_SURVEY_OPTION_SCHEMA, IMPACTSHOP_SURVEY_SCHEMA_VERSION, false);
}

function impactshop_offerwall_survey_ensure_provider(): void
{
    if (!function_exists('impactshop_offerwall_get_providers')) {
        return;
    }

    $providers = impactshop_offerwall_get_providers();
    if (!isset($providers['internal_survey'])) {
        $providers['internal_survey'] = [
            'enabled' => false,
            'name' => 'Saját kérdőívek',
            'iframe_url' => '',
            'api_key' => '',
            'postback_secret' => '',
            'signature_param' => 'signature',
            'signature_mode' => 'canonical_v1',
            'user_param' => 'user_id',
            'iframe_hash_secret' => '',
            'iframe_hash_param' => 'secure_hash',
            'iframe_hash_format' => '{user}-{secret}',
            'survey_token_secret' => '',
            'points_multiplier' => 1.0,
            'votes_multiplier' => 1.0,
            'allow_ips' => [],
        ];
        impactshop_offerwall_save_providers($providers);
    }
}

function impactshop_offerwall_survey_iframe_url(string $url, array $provider, string $pseudo_id): string
{
    if (($provider['signature_mode'] ?? '') !== 'canonical_v1') {
        return $url;
    }
    if ($url === '' || $pseudo_id === '') {
        return $url;
    }
    $secret = (string) ($provider['survey_token_secret'] ?? '');
    if ($secret === '') {
        $secret = (string) ($provider['api_key'] ?? '');
    }
    $token = impactshop_offerwall_survey_build_token($pseudo_id, $secret);
    if ($token === '') {
        return $url;
    }
    return add_query_arg('survey_token', $token, $url);
}

function impactshop_offerwall_survey_build_token(string $pseudo_id, string $secret): string
{
    if ($secret === '') {
        return '';
    }
    $payload = [
        'pseudo_id' => $pseudo_id,
        'exp' => time() + 900,
    ];
    $json = wp_json_encode($payload);
    if ($json === false) {
        return '';
    }
    $sig = hash_hmac('sha256', $json, $secret);
    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '.' . $sig;
}

function impactshop_offerwall_survey_pre_dispatch($result, WP_REST_Server $server, WP_REST_Request $request)
{
    if (strpos($request->get_route(), '/impact/v1/offerwall/callback/') !== 0) {
        return $result;
    }
    $provider_key = (string) $request['provider'];
    if ($provider_key !== 'internal_survey') {
        return $result;
    }

    $providers = function_exists('impactshop_offerwall_get_providers') ? impactshop_offerwall_get_providers() : [];
    $provider = $providers[$provider_key] ?? [];

    $params = array_merge($request->get_query_params(), $request->get_json_params());
    $transaction_id = sanitize_text_field((string) ($params['transaction_id'] ?? $params['tx_id'] ?? ''));
    if ($transaction_id === '') {
        return new WP_REST_Response(['status' => 'missing_transaction'], 400);
    }
    if (!preg_match('/^survey-\d{8}-[A-Za-z0-9]+$/', $transaction_id)) {
        return new WP_REST_Response(['status' => 'invalid_transaction'], 400);
    }

    $timestamp = (int) ($params['timestamp'] ?? 0);
    if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
        return new WP_REST_Response(['status' => 'invalid_timestamp'], 400);
    }

    $pseudo_id = sanitize_text_field((string) ($params['pseudo_id'] ?? $params['user_id'] ?? $params['ext_user_id'] ?? ''));
    if ($pseudo_id === '' && function_exists('impactshop_offerwall_get_pseudo_id')) {
        $pseudo_id = impactshop_offerwall_get_pseudo_id();
    }
    if ($pseudo_id === '') {
        return new WP_REST_Response(['status' => 'missing_pseudo'], 400);
    }

    $payout = (string) ($params['payout'] ?? $params['amount'] ?? $params['amount_usd'] ?? 0);
    $signature = (string) ($params['signature'] ?? '');
    $secret = (string) ($provider['postback_secret'] ?? '');
    if ($secret === '' || $signature === '') {
        return new WP_REST_Response(['status' => 'missing_signature'], 403);
    }

    $canonical = $transaction_id . '|' . $pseudo_id . '|' . $payout . '|' . $timestamp;
    $expected = hash_hmac('sha256', $canonical, $secret);
    if (!hash_equals($expected, $signature)) {
        if (function_exists('impactshop_offerwall_log_fraud')) {
            impactshop_offerwall_log_fraud('invalid_signature', ['provider' => $provider_key, 'transaction_id' => $transaction_id]);
        }
        return new WP_REST_Response(['status' => 'invalid_signature'], 403);
    }

    $rate_key = 'offerwall_internal_survey_' . md5($pseudo_id);
    if (function_exists('impactshop_offerwall_rate_limit') && !impactshop_offerwall_rate_limit($rate_key, IMPACTSHOP_SURVEY_RATE_LIMIT, HOUR_IN_SECONDS)) {
        if (function_exists('impactshop_offerwall_log_fraud')) {
            impactshop_offerwall_log_fraud('rate_limited_user', ['provider' => $provider_key, 'pseudo_id' => $pseudo_id]);
        }
        return new WP_REST_Response(['status' => 'rate_limited'], 429);
    }

    $survey_id = sanitize_text_field((string) ($params['survey_id'] ?? ''));
    if ($survey_id === '') {
        return new WP_REST_Response(['status' => 'missing_survey'], 400);
    }

    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_survey_answers';
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$answers_table} WHERE pseudo_id = %s AND survey_id = %s",
        $pseudo_id,
        $survey_id
    ));
    if ($existing > 0) {
        return new WP_REST_Response(['status' => 'duplicate_survey'], 200);
    }

    $answers = $params['answers'] ?? [];
    if (!is_array($answers)) {
        return new WP_REST_Response(['status' => 'invalid_answers'], 400);
    }

    $question_count = (int) ($params['question_count'] ?? count($answers));
    if ($question_count <= 0 || $question_count > 5) {
        return new WP_REST_Response(['status' => 'invalid_question_count'], 400);
    }
    if (count($answers) !== $question_count) {
        return new WP_REST_Response(['status' => 'answer_mismatch'], 400);
    }

    foreach ($answers as $value) {
        if (is_array($value)) {
            foreach ($value as $nested) {
                if (impactshop_offerwall_survey_contains_pii((string) $nested)) {
                    return new WP_REST_Response(['status' => 'pii_detected'], 400);
                }
            }
            continue;
        }
        if (impactshop_offerwall_survey_contains_pii((string) $value)) {
            return new WP_REST_Response(['status' => 'pii_detected'], 400);
        }
    }

    $categories = $params['question_category'] ?? [];
    if (!is_array($categories) || empty($categories)) {
        return new WP_REST_Response(['status' => 'missing_category'], 400);
    }
    if (count($categories) !== $question_count) {
        return new WP_REST_Response(['status' => 'category_mismatch'], 400);
    }

    $mapping = impactshop_offerwall_survey_load_mapping();
    if (empty($mapping['map']) || empty($mapping['valid'])) {
        return new WP_REST_Response(['status' => 'mapping_unavailable'], 500);
    }
    foreach ($categories as $cat) {
        $cat = (string) $cat;
        if (!isset($mapping['map'][$cat])) {
            return new WP_REST_Response(['status' => 'invalid_category'], 400);
        }
    }

    $request->set_param('survey_context', [
        'pseudo_id' => $pseudo_id,
        'transaction_id' => $transaction_id,
        'survey_id' => $survey_id,
        'answers' => $answers,
        'question_count' => $question_count,
        'categories' => $categories,
        'timestamp' => $timestamp,
        'consent' => (int) ($params['consent_pers'] ?? 0),
        'answers_correct' => (array) ($params['answers_correct'] ?? []),
        'request_id' => sanitize_text_field((string) ($params['request_id'] ?? '')),
    ]);

    return $result;
}

function impactshop_offerwall_survey_handle_rewards(string $pseudo_id, array $payload): void
{
    if (($payload['provider'] ?? '') !== 'internal_survey') {
        return;
    }

    $request = rest_get_server()->get_current_request();
    $context = $request ? $request->get_param('survey_context') : null;
    if (!is_array($context)) {
        return;
    }

    $answers = $context['answers'] ?? [];
    $survey_id = (string) ($context['survey_id'] ?? '');
    $question_count = (int) ($context['question_count'] ?? 0);
    $categories = (array) ($context['categories'] ?? []);
    $consent = (int) ($context['consent'] ?? 0);
    $answers_correct = (array) ($context['answers_correct'] ?? []);
    $request_id = (string) ($context['request_id'] ?? '');
    if ($survey_id === '' || $question_count <= 0) {
        return;
    }

    $mapping = impactshop_offerwall_survey_load_mapping();
    $taxonomy = impactshop_offerwall_survey_load_taxonomy();
    if (empty($mapping['map']) || empty($taxonomy['by_code'])) {
        return;
    }

    $mapping_version = $mapping['hash'] ?? 'v1';
    $survey_version = 'v1';

    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_survey_answers';

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$answers_table} (pseudo_id, survey_id, target_id, answers_json, question_count, survey_version, mapping_version, request_id, created_at)
         VALUES (%s, %s, %s, %s, %d, %s, %s, %s, %s)",
        $pseudo_id,
        $survey_id,
        'impactad',
        wp_json_encode($answers),
        $question_count,
        $survey_version,
        $mapping_version,
        $request_id !== '' ? $request_id : wp_generate_uuid4(),
        gmdate('Y-m-d H:i:s')
    ));

    if ($consent !== 1) {
        impactshop_offerwall_survey_set_consent($pseudo_id, false);
        return;
    }

    impactshop_offerwall_survey_set_consent($pseudo_id, true);

    foreach ($categories as $idx => $category) {
        $category = (string) $category;
        $answer = $answers[$category] ?? ($answers[$idx] ?? null);
        $rule = $mapping['map'][$category] ?? null;
        if (!$rule) {
            continue;
        }
        $is_correct = $answers_correct[$category] ?? null;
        impactshop_offerwall_survey_apply_rule($pseudo_id, $rule, $answer, $taxonomy, $is_correct);
    }
}

function impactshop_offerwall_survey_log_fraud(string $reason, array $context = []): void
{
    global $wpdb;
    $table = $wpdb->prefix . 'impactshop_offerwall_fraud_log';
    $provider = sanitize_text_field((string) ($context['provider'] ?? ''));
    $pseudo_id = sanitize_text_field((string) ($context['pseudo_id'] ?? ''));
    $ip = sanitize_text_field((string) ($context['ip'] ?? ''));
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$table} (reason, provider, pseudo_id, ip, context_json, created_at)
         VALUES (%s, %s, %s, %s, %s, %s)",
        $reason,
        $provider,
        $pseudo_id,
        $ip,
        wp_json_encode($context),
        gmdate('Y-m-d H:i:s')
    ));
}

function impactshop_offerwall_survey_set_consent(string $pseudo_id, bool $consent): void
{
    $code = $consent ? 'CONS-PERS-1' : 'CONS-PERS-0';
    impactshop_offerwall_survey_upsert_segment($pseudo_id, $code, 1, 1);
}

function impactshop_offerwall_survey_apply_rule(string $pseudo_id, array $rule, $answer, array $taxonomy, $is_correct = null): void
{
    $segments = $rule['segments'] ?? [];
    $update_type = strtolower((string) ($rule['update_type'] ?? ''));

    if (strpos($update_type, 'direct assignment') !== false) {
        $code = is_string($answer) ? trim($answer) : '';
        if ($code !== '' && isset($taxonomy['by_code'][$code])) {
            impactshop_offerwall_survey_clear_category($pseudo_id, $taxonomy['by_code'][$code]['category'], $taxonomy);
            impactshop_offerwall_survey_upsert_segment($pseudo_id, $code, 1, 1);
        }
        return;
    }

    if (strpos($update_type, 'scaled') !== false) {
        $score = impactshop_offerwall_survey_scale_answer($answer, [-2, -1, 1, 2]);
        foreach ($segments as $segment) {
            impactshop_offerwall_survey_update_axis($pseudo_id, $segment, $score, 1);
        }
        return;
    }

    if (strpos($update_type, 'frequency') !== false) {
        $score = impactshop_offerwall_survey_scale_answer($answer, [0, 1, 2, 3]);
        foreach ($segments as $segment) {
            impactshop_offerwall_survey_update_axis($pseudo_id, $segment, $score, 1);
        }
        return;
    }

    if (strpos($update_type, '+1') !== false) {
        $correct = (bool) $is_correct;
        if ($correct) {
            foreach ($segments as $segment) {
                impactshop_offerwall_survey_update_axis($pseudo_id, $segment, 1, 1);
            }
        }
        return;
    }

    if (strpos($update_type, 'top') !== false) {
        $weights = [3, 2, 1];
        if (is_array($answer)) {
            foreach ($answer as $index => $code) {
                $code = (string) $code;
                if ($code === '' || !isset($taxonomy['by_code'][$code])) {
                    continue;
                }
                impactshop_offerwall_survey_add_pref($pseudo_id, $code, $weights[$index] ?? 1);
            }
        } elseif (is_string($answer) && $answer !== '' && isset($taxonomy['by_code'][$answer])) {
            impactshop_offerwall_survey_add_pref($pseudo_id, $answer, 3);
        }
        return;
    }

    if (strpos($update_type, 'stage') !== false) {
        $code = is_string($answer) ? trim($answer) : '';
        if ($code !== '' && isset($taxonomy['by_code'][$code])) {
            impactshop_offerwall_survey_clear_category($pseudo_id, $taxonomy['by_code'][$code]['category'], $taxonomy);
            impactshop_offerwall_survey_upsert_segment($pseudo_id, $code, 1, 1);
        }
    }
}

function impactshop_offerwall_survey_scale_answer($answer, array $map): int
{
    $choices = ['A', 'B', 'C', 'D'];
    $answer = strtoupper((string) $answer);
    $idx = array_search($answer, $choices, true);
    if ($idx === false) {
        return 0;
    }
    return (int) ($map[$idx] ?? 0);
}

function impactshop_offerwall_survey_update_axis(string $pseudo_id, string $axis, int $delta, int $weight): void
{
    global $wpdb;
    $scores_table = $wpdb->prefix . 'impactshop_segment_scores';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT sum_val, weight_val FROM {$scores_table} WHERE pseudo_id = %s AND segment_code = %s",
        $pseudo_id,
        $axis
    ), ARRAY_A);

    $sum = (float) ($existing['sum_val'] ?? 0) + $delta;
    $w = (float) ($existing['weight_val'] ?? 0) + $weight;
    $conf = min(1.0, $w / IMPACTSHOP_SURVEY_W_TARGET);

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$scores_table} (pseudo_id, segment_code, sum_val, weight_val, conf_val, updated_at)
         VALUES (%s, %s, %f, %f, %f, %s)
         ON DUPLICATE KEY UPDATE sum_val = VALUES(sum_val), weight_val = VALUES(weight_val), conf_val = VALUES(conf_val), updated_at = VALUES(updated_at)",
        $pseudo_id,
        $axis,
        $sum,
        $w,
        $conf,
        gmdate('Y-m-d H:i:s')
    ));

    impactshop_offerwall_survey_update_level($pseudo_id, $axis, $sum, $w, $conf);
}

function impactshop_offerwall_survey_update_level(string $pseudo_id, string $axis, float $sum, float $w, float $conf): void
{
    if ($w <= 0) {
        return;
    }
    $score_norm = $sum / $w;
    $score_norm = max(0.0, min(1.0, ($score_norm + 2) / 4));
    $percent = $score_norm * 100;

    $level = 0;
    if ($percent >= 80) {
        $level = 5;
    } elseif ($percent >= 60) {
        $level = 4;
    } elseif ($percent >= 40) {
        $level = 3;
    } elseif ($percent >= 20) {
        $level = 2;
    } elseif ($percent > 0) {
        $level = 1;
    }

    $level_code = $axis . '-L' . $level;
    impactshop_offerwall_survey_clear_level($pseudo_id, $axis);
    impactshop_offerwall_survey_upsert_segment($pseudo_id, $level_code, $level, 1, $conf);
}

function impactshop_offerwall_survey_clear_level(string $pseudo_id, string $axis): void
{
    global $wpdb;
    $scores_table = $wpdb->prefix . 'impactshop_segment_scores';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$scores_table} WHERE pseudo_id = %s AND segment_code LIKE %s",
        $pseudo_id,
        $wpdb->esc_like($axis . '-L') . '%'
    ));
}

function impactshop_offerwall_survey_clear_category(string $pseudo_id, string $category, array $taxonomy): void
{
    if ($category === '') {
        return;
    }
    $codes = $taxonomy['by_category'][$category] ?? [];
    if (!$codes) {
        return;
    }
    global $wpdb;
    $scores_table = $wpdb->prefix . 'impactshop_segment_scores';
    $placeholders = implode(',', array_fill(0, count($codes), '%s'));
    $params = array_merge([$pseudo_id], $codes);
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$scores_table} WHERE pseudo_id = %s AND segment_code IN ({$placeholders})",
        $params
    ));
}

function impactshop_offerwall_survey_upsert_segment(string $pseudo_id, string $segment_code, float $sum, float $weight, float $conf = 1.0): void
{
    global $wpdb;
    $scores_table = $wpdb->prefix . 'impactshop_segment_scores';
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$scores_table} (pseudo_id, segment_code, sum_val, weight_val, conf_val, updated_at)
         VALUES (%s, %s, %f, %f, %f, %s)
         ON DUPLICATE KEY UPDATE sum_val = VALUES(sum_val), weight_val = VALUES(weight_val), conf_val = VALUES(conf_val), updated_at = VALUES(updated_at)",
        $pseudo_id,
        $segment_code,
        $sum,
        $weight,
        $conf,
        gmdate('Y-m-d H:i:s')
    ));
}

function impactshop_offerwall_survey_add_pref(string $pseudo_id, string $segment_code, int $score): void
{
    global $wpdb;
    $prefs_table = $wpdb->prefix . 'impactshop_segment_prefs';
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$prefs_table} (pseudo_id, segment_code, score, updated_at)
         VALUES (%s, %s, %d, %s)
         ON DUPLICATE KEY UPDATE score = score + VALUES(score), updated_at = VALUES(updated_at)",
        $pseudo_id,
        $segment_code,
        $score,
        gmdate('Y-m-d H:i:s')
    ));
}

function impactshop_offerwall_survey_contains_pii(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $email = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';
    $phone = '/\+?\d[\d\s().-]{6,}/';
    return (bool) (preg_match($email, $value) || preg_match($phone, $value));
}

function impactshop_offerwall_survey_read_csv(string $path, string $header_key): array
{
    if (!file_exists($path)) {
        return ['header' => [], 'rows' => []];
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        return ['header' => [], 'rows' => []];
    }

    $header = null;
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) {
        if (!$row || !array_filter($row, 'strlen')) {
            continue;
        }
        if ($header === null) {
            $row = array_map(
                static function ($cell) {
                    $cell = (string) $cell;
                    $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                    return trim($cell);
                },
                $row
            );
            if (!in_array($header_key, $row, true)) {
                continue;
            }
            $header = $row;
            continue;
        }

        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');
        } elseif (count($row) > count($header)) {
            $row = array_slice($row, 0, count($header));
        }
        $rows[] = array_combine($header, $row);
    }
    fclose($handle);

    if ($header === null) {
        return ['header' => [], 'rows' => []];
    }

    return ['header' => $header, 'rows' => $rows];
}

function impactshop_offerwall_survey_load_mapping(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (!file_exists(IMPACTSHOP_SURVEY_MAPPING_FILE)) {
        return ['map' => [], 'hash' => '', 'valid' => false];
    }

    $hash = hash_file('sha256', IMPACTSHOP_SURVEY_MAPPING_FILE);
    if (!$hash) {
        return ['map' => [], 'hash' => '', 'valid' => false];
    }
    update_option(IMPACTSHOP_SURVEY_OPTION_MAPPING_HASH, $hash, false);

    $csv = impactshop_offerwall_survey_read_csv(IMPACTSHOP_SURVEY_MAPPING_FILE, 'question_category');
    if (!$csv['rows']) {
        return ['map' => [], 'hash' => $hash, 'valid' => false];
    }

    $map = [];
    foreach ($csv['rows'] as $row) {
        $category = trim((string) ($row['question_category'] ?? ''));
        if ($category === '') {
            continue;
        }
        $segments = array_map('trim', explode(',', (string) ($row['segments_updated'] ?? '')));
        $update_type = (string) ($row['update_type'] ?? '');
        $map[$category] = [
            'segments' => array_filter($segments),
            'update_type' => $update_type,
        ];
    }

    $taxonomy = impactshop_offerwall_survey_load_taxonomy();
    $valid = true;
    foreach ($map as $rule) {
        $update_type = strtolower((string) ($rule['update_type'] ?? ''));
        $skip_segments = (strpos($update_type, 'direct assignment') !== false)
            || (strpos($update_type, 'top') !== false);
        if ($skip_segments) {
            continue;
        }
        foreach ($rule['segments'] as $segment) {
            if (isset($taxonomy['by_code'][$segment]) || isset($taxonomy['axis_codes'][$segment])) {
                continue;
            }
            if ($segment !== '') {
                $valid = false;
                error_log('[survey] invalid segment in mapping: ' . $segment);
            }
        }
    }

    $cache = ['map' => $map, 'hash' => $hash, 'valid' => $valid];
    return $cache;
}

function impactshop_offerwall_survey_load_taxonomy(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (!file_exists(IMPACTSHOP_SURVEY_TAXONOMY_FILE)) {
        return ['by_code' => [], 'by_category' => [], 'axis_codes' => [], 'hash' => ''];
    }

    $hash = hash_file('sha256', IMPACTSHOP_SURVEY_TAXONOMY_FILE);
    if (!$hash) {
        return ['by_code' => [], 'by_category' => [], 'axis_codes' => [], 'hash' => ''];
    }
    update_option(IMPACTSHOP_SURVEY_OPTION_TAXONOMY_HASH, $hash, false);

    $by_code = [];
    $by_category = [];
    $axis_codes = [];

    $csv = impactshop_offerwall_survey_read_csv(IMPACTSHOP_SURVEY_TAXONOMY_FILE, 'segment_code');
    if (!$csv['rows']) {
        return ['by_code' => [], 'by_category' => [], 'axis_codes' => [], 'hash' => $hash];
    }

    foreach ($csv['rows'] as $row) {
        $code = trim((string) ($row['segment_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        if (substr($code, -3) === '-L*') {
            $axis_codes[substr($code, 0, -3)] = true;
        }
        $category = trim((string) ($row['category'] ?? ''));
        $by_code[$code] = [
            'category' => $category,
            'targetable' => (string) ($row['targetable'] ?? ''),
            'sensitivity' => (string) ($row['sensitivity'] ?? ''),
        ];
        if ($category !== '') {
            $by_category[$category][] = $code;
        }
    }

    $cache = [
        'by_code' => $by_code,
        'by_category' => $by_category,
        'axis_codes' => $axis_codes,
        'hash' => $hash,
    ];
    return $cache;
}

function impactshop_offerwall_survey_admin_menu(): void
{
    add_submenu_page(
        'tools.php',
        'Offerwall Survey',
        'Offerwall Survey',
        'manage_options',
        'impactshop-offerwall-survey',
        'impactshop_offerwall_survey_admin_page'
    );
}

function impactshop_offerwall_survey_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $providers = function_exists('impactshop_offerwall_get_providers') ? impactshop_offerwall_get_providers() : [];
    $provider = $providers['internal_survey'] ?? [];
    $notice = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('impactshop_offerwall_survey_save')) {
        if (isset($_POST['survey_provider_save'])) {
            $provider['enabled'] = !empty($_POST['provider_enabled']);
            $provider['name'] = sanitize_text_field($_POST['provider_name'] ?? 'Saját kérdőívek');
            $provider['iframe_url'] = esc_url_raw($_POST['provider_iframe_url'] ?? '');
            $provider['postback_secret'] = sanitize_text_field($_POST['provider_postback_secret'] ?? '');
            $provider['survey_token_secret'] = sanitize_text_field($_POST['provider_survey_token_secret'] ?? '');
            $allowlist_raw = sanitize_text_field($_POST['provider_allow_ips'] ?? '');
            $provider['allow_ips'] = array_values(array_filter(array_map('trim', explode(',', $allowlist_raw))));
            $providers['internal_survey'] = $provider;
            if (function_exists('impactshop_offerwall_save_providers')) {
                impactshop_offerwall_save_providers($providers);
                $notice = 'Provider beállítások mentve.';
            } else {
                $error = 'Offerwall provider mentés nem elérhető.';
            }
        }

        if (!empty($_FILES['mapping_csv']['name']) || !empty($_FILES['taxonomy_csv']['name'])) {
            $upload_dir = IMPACTSHOP_SURVEY_DATA_DIR;
            if (!is_dir($upload_dir)) {
                wp_mkdir_p($upload_dir);
            }
            if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                $error = 'A survey data könyvtár nem írható.';
            } else {
                $mapping_ok = impactshop_offerwall_survey_handle_csv_upload('mapping_csv', IMPACTSHOP_SURVEY_MAPPING_FILE, 'question_category');
                $taxonomy_ok = impactshop_offerwall_survey_handle_csv_upload('taxonomy_csv', IMPACTSHOP_SURVEY_TAXONOMY_FILE, 'segment_code');
                if ($mapping_ok || $taxonomy_ok) {
                    $notice = 'CSV fájlok frissítve.';
                } elseif ($error === '') {
                    $error = 'CSV frissítés sikertelen.';
                }
            }
        }
    }

    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_survey_answers';
    $fraud_table = $wpdb->prefix . 'impactshop_offerwall_fraud_log';
    $rows = $wpdb->get_results("SELECT pseudo_id, survey_id, question_count, created_at FROM {$answers_table} ORDER BY created_at DESC LIMIT 20", ARRAY_A);
    $fraud_rows = $wpdb->get_results("SELECT reason, provider, pseudo_id, ip, created_at FROM {$fraud_table} ORDER BY created_at DESC LIMIT 20", ARRAY_A);
    $stats = $wpdb->get_row("SELECT COUNT(*) AS total, COUNT(DISTINCT pseudo_id) AS users FROM {$answers_table}", ARRAY_A);
    $by_survey = $wpdb->get_results("SELECT survey_id, COUNT(*) AS total FROM {$answers_table} GROUP BY survey_id ORDER BY total DESC LIMIT 10", ARRAY_A);
    $consent_users = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT pseudo_id) FROM {$wpdb->prefix}impactshop_segment_scores WHERE segment_code = %s",
        'CONS-PERS-1'
    ));

    echo '<div class="wrap"><h1>Offerwall Survey</h1>';
    if ($notice !== '') {
        echo '<div class="updated"><p>' . esc_html($notice) . '</p></div>';
    }
    if ($error !== '') {
        echo '<div class="error"><p>' . esc_html($error) . '</p></div>';
    }

    echo '<h2>Provider beállítások</h2>';
    echo '<form method="post">';
    wp_nonce_field('impactshop_offerwall_survey_save');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label>Aktív</label></th><td><input type="checkbox" name="provider_enabled" ' . checked(!empty($provider['enabled']), true, false) . ' /></td></tr>';
    echo '<tr><th><label>Név</label></th><td><input class="regular-text" type="text" name="provider_name" value="' . esc_attr((string) ($provider['name'] ?? 'Saját kérdőívek')) . '" /></td></tr>';
    echo '<tr><th><label>Iframe URL</label></th><td><input class="regular-text" type="url" name="provider_iframe_url" value="' . esc_url((string) ($provider['iframe_url'] ?? '')) . '" /></td></tr>';
    echo '<tr><th><label>Postback secret</label></th><td><input class="regular-text" type="text" name="provider_postback_secret" value="' . esc_attr((string) ($provider['postback_secret'] ?? '')) . '" /></td></tr>';
    echo '<tr><th><label>Survey token secret</label></th><td><input class="regular-text" type="text" name="provider_survey_token_secret" value="' . esc_attr((string) ($provider['survey_token_secret'] ?? '')) . '" /></td></tr>';
    echo '<tr><th><label>Allowlist IP-k</label></th><td><input class="regular-text" type="text" name="provider_allow_ips" value="' . esc_attr(implode(', ', (array) ($provider['allow_ips'] ?? []))) . '" placeholder="1.2.3.4, 5.6.7.8" /></td></tr>';
    echo '</tbody></table>';
    echo '<input type="hidden" name="survey_provider_save" value="1" />';
    submit_button('Provider mentése');
    echo '</form>';

    echo '<h2>CSV frissítés</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('impactshop_offerwall_survey_save');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th><label>question_mapping.csv</label></th><td><input type="file" name="mapping_csv" accept=".csv" /></td></tr>';
    echo '<tr><th><label>segment_taxonomy.csv</label></th><td><input type="file" name="taxonomy_csv" accept=".csv" /></td></tr>';
    echo '</tbody></table>';
    submit_button('CSV frissítése');
    echo '</form>';

    echo '<h2>Statisztikák</h2>';
    echo '<div class="notice notice-info"><p>';
    echo 'Összes completion: <strong>' . esc_html((string) ($stats['total'] ?? 0)) . '</strong> · ';
    echo 'Egyedi userek: <strong>' . esc_html((string) ($stats['users'] ?? 0)) . '</strong> · ';
    echo 'Consentált userek: <strong>' . esc_html((string) $consent_users) . '</strong>';
    echo '</p></div>';
    if (!empty($by_survey)) {
        echo '<table class="widefat striped"><thead><tr><th>Survey</th><th>Completion</th></tr></thead><tbody>';
        foreach ($by_survey as $row) {
            echo '<tr><td>' . esc_html((string) $row['survey_id']) . '</td><td>' . esc_html((string) $row['total']) . '</td></tr>';
        }
        echo '</tbody></table><br />';
    }
    echo '<table class="widefat striped"><thead><tr><th>Pseudo</th><th>Survey</th><th>Kérdések</th><th>Dátum</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . esc_html((string) $row['pseudo_id']) . '</td>';
        echo '<td>' . esc_html((string) $row['survey_id']) . '</td>';
        echo '<td>' . esc_html((string) $row['question_count']) . '</td>';
        echo '<td>' . esc_html((string) $row['created_at']) . '</td>';
        echo '</tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="4">Nincs adat.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h2>Fraud log</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Reason</th><th>Provider</th><th>Pseudo</th><th>IP</th><th>Dátum</th></tr></thead><tbody>';
    foreach ($fraud_rows as $row) {
        echo '<tr>';
        echo '<td>' . esc_html((string) $row['reason']) . '</td>';
        echo '<td>' . esc_html((string) $row['provider']) . '</td>';
        echo '<td>' . esc_html((string) $row['pseudo_id']) . '</td>';
        echo '<td>' . esc_html((string) $row['ip']) . '</td>';
        echo '<td>' . esc_html((string) $row['created_at']) . '</td>';
        echo '</tr>';
    }
    if (!$fraud_rows) {
        echo '<tr><td colspan="5">Nincs adat.</td></tr>';
    }
    echo '</tbody></table></div>';
}

function impactshop_offerwall_survey_handle_csv_upload(string $field, string $target_path, string $required_header): bool
{
    if (empty($_FILES[$field]['name'])) {
        return false;
    }
    if (!is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return false;
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        return false;
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $csv = impactshop_offerwall_survey_read_csv($tmp, $required_header);
    if (!$csv['rows']) {
        return false;
    }
    return (bool) move_uploaded_file($tmp, $target_path);
}

function impactshop_offerwall_survey_dashboard_widget(): void
{
    wp_add_dashboard_widget(
        'impactshop_offerwall_survey_widget',
        'Offerwall Survey – gyors infók',
        'impactshop_offerwall_survey_dashboard_widget_render'
    );
}

function impactshop_offerwall_survey_dashboard_widget_render(): void
{
    if (!current_user_can('manage_options')) {
        echo '<p>Nincs jogosultság.</p>';
        return;
    }

    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_survey_answers';
    $fraud_table = $wpdb->prefix . 'impactshop_offerwall_fraud_log';
    $today = gmdate('Y-m-d 00:00:00');
    $week = gmdate('Y-m-d 00:00:00', strtotime('-6 days'));
    $day_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$answers_table} WHERE created_at >= %s",
        $today
    ));
    $week_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$answers_table} WHERE created_at >= %s",
        $week
    ));
    $fraud_24h = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$fraud_table} WHERE created_at >= %s",
        gmdate('Y-m-d H:i:s', strtotime('-24 hours'))
    ));

    echo '<p><strong>Mai completions:</strong> ' . esc_html((string) $day_count) . '</p>';
    echo '<p><strong>Utolsó 7 nap:</strong> ' . esc_html((string) $week_count) . '</p>';
    echo '<p><strong>Fraud 24h:</strong> ' . esc_html((string) $fraud_24h) . '</p>';
}
