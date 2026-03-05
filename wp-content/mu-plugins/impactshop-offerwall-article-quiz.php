<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_ARTICLE_QUIZ_SCHEMA_VERSION = '1.0.0';
const IMPACTSHOP_ARTICLE_QUIZ_OPTION_SCHEMA = 'impactshop_article_quiz_schema_version';
const IMPACTSHOP_ARTICLE_QUIZ_DATA_DIR = __DIR__ . '/impactshop-offerwall-article-quiz-data';
const IMPACTSHOP_ARTICLE_QUIZ_DATA_FILE = IMPACTSHOP_ARTICLE_QUIZ_DATA_DIR . '/articles_quiz.json';
const IMPACTSHOP_ARTICLE_QUIZ_RATE_LIMIT = 10; // per user per hour

add_action('muplugins_loaded', 'impactshop_article_quiz_bootstrap');

function impactshop_article_quiz_bootstrap(): void
{
    impactshop_article_quiz_maybe_install();
    impactshop_article_quiz_ensure_provider();
    add_filter('impactshop_offerwall_iframe_url', 'impactshop_article_quiz_iframe_url', 10, 3);
    add_filter('rest_pre_dispatch', 'impactshop_article_quiz_pre_dispatch', 10, 3);
    add_action('rest_api_init', 'impactshop_article_quiz_register_routes');
    add_shortcode('impactshop_article_quiz', 'impactshop_article_quiz_shortcode');
    add_action('impactshop_offerwall_rewards_awarded', 'impactshop_article_quiz_handle_rewards', 10, 2);
}

function impactshop_article_quiz_maybe_install(): void
{
    $current = get_option(IMPACTSHOP_ARTICLE_QUIZ_OPTION_SCHEMA, '');
    if ($current === IMPACTSHOP_ARTICLE_QUIZ_SCHEMA_VERSION) {
        return;
    }

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $answers_table = $wpdb->prefix . 'impactshop_article_quiz_answers';

    $sql_answers = "CREATE TABLE {$answers_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pseudo_id VARCHAR(32) NOT NULL,
        quiz_id VARCHAR(64) NOT NULL,
        answers_json LONGTEXT NOT NULL,
        correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
        question_count TINYINT UNSIGNED NOT NULL,
        request_id VARCHAR(128) DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY uniq_user_quiz (pseudo_id, quiz_id),
        KEY idx_pseudo (pseudo_id),
        KEY idx_quiz (quiz_id),
        KEY idx_created (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_answers);

    update_option(IMPACTSHOP_ARTICLE_QUIZ_OPTION_SCHEMA, IMPACTSHOP_ARTICLE_QUIZ_SCHEMA_VERSION, false);
}

function impactshop_article_quiz_ensure_provider(): void
{
    if (!function_exists('impactshop_offerwall_get_providers')) {
        return;
    }

    $providers = impactshop_offerwall_get_providers();
    if (!isset($providers['internal_article_quiz'])) {
        $providers['internal_article_quiz'] = [
            'enabled' => false,
            'name' => 'Impact kvíz',
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

function impactshop_article_quiz_get_secret(array $provider): string
{
    $secret = (string) ($provider['survey_token_secret'] ?? '');
    if ($secret === '') {
        $secret = (string) ($provider['api_key'] ?? '');
    }
    if ($secret === '') {
        $secret = (string) ($provider['postback_secret'] ?? '');
    }
    return $secret;
}

function impactshop_article_quiz_iframe_url(string $url, array $provider, string $pseudo_id): string
{
    if (($provider['signature_mode'] ?? '') !== 'canonical_v1') {
        return $url;
    }
    if ($url === '' || $pseudo_id === '') {
        return $url;
    }
    $secret = impactshop_article_quiz_get_secret($provider);
    $token = impactshop_article_quiz_build_token($pseudo_id, $secret);
    if ($token === '') {
        return $url;
    }
    return add_query_arg('survey_token', $token, $url);
}

function impactshop_article_quiz_build_token(string $pseudo_id, string $secret): string
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

function impactshop_article_quiz_decode_token(string $token, string $secret): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$payload_b64, $sig] = $parts;
    $json = base64_decode(strtr($payload_b64, '-_', '+/'), true);
    if ($json === false || $json === '') {
        return null;
    }
    $expected = hash_hmac('sha256', $json, $secret);
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return null;
    }
    $exp = (int) ($payload['exp'] ?? 0);
    if ($exp > 0 && $exp < time()) {
        return null;
    }
    return $payload;
}

function impactshop_article_quiz_register_routes(): void
{
    register_rest_route('impact/v1', '/offerwall/article-quiz/submit', [
        'methods' => 'POST',
        'callback' => 'impactshop_article_quiz_submit',
        'permission_callback' => '__return_true',
    ]);
}

function impactshop_article_quiz_submit(WP_REST_Request $request): WP_REST_Response
{
    $providers = function_exists('impactshop_offerwall_get_providers') ? impactshop_offerwall_get_providers() : [];
    $provider = $providers['internal_article_quiz'] ?? [];
    $secret = impactshop_article_quiz_get_secret($provider);

    $params = array_merge($request->get_query_params(), $request->get_json_params() ?: []);
    $token = sanitize_text_field((string) ($params['quiz_token'] ?? $params['survey_token'] ?? ''));
    if ($token === '' || $secret === '') {
        if (function_exists('impactshop_offerwall_debug_log')) {
            impactshop_offerwall_debug_log('quiz_submit_missing_token', [
                'quiz_id' => (string) ($params['quiz_id'] ?? ''),
            ]);
        }
        return new WP_REST_Response(['status' => 'missing_token'], 403);
    }

    $payload = impactshop_article_quiz_decode_token($token, $secret);
    if (!$payload) {
        if (function_exists('impactshop_offerwall_debug_log')) {
            impactshop_offerwall_debug_log('quiz_submit_invalid_token', [
                'quiz_id' => (string) ($params['quiz_id'] ?? ''),
            ]);
        }
        return new WP_REST_Response(['status' => 'invalid_token'], 403);
    }

    $pseudo_id = sanitize_text_field((string) ($payload['pseudo_id'] ?? ''));
    if ($pseudo_id === '') {
        if (function_exists('impactshop_offerwall_debug_log')) {
            impactshop_offerwall_debug_log('quiz_submit_missing_pseudo', [
                'quiz_id' => (string) ($params['quiz_id'] ?? ''),
            ]);
        }
        return new WP_REST_Response(['status' => 'missing_pseudo'], 400);
    }

    $quiz_id = sanitize_text_field((string) ($params['quiz_id'] ?? ''));
    $answers = (array) ($params['answers'] ?? []);
    $correct_flags = (array) ($params['answers_correct'] ?? []);
    $question_count = (int) ($params['question_count'] ?? count($answers));
    $time_spent = (int) ($params['time_spent_sec'] ?? 0);

    if ($quiz_id === '' || $question_count !== 3 || count($answers) !== 3) {
        if (function_exists('impactshop_offerwall_debug_log')) {
            impactshop_offerwall_debug_log('quiz_submit_invalid_payload', [
                'pseudo_id' => $pseudo_id,
                'quiz_id' => $quiz_id,
                'question_count' => $question_count,
            ]);
        }
        return new WP_REST_Response(['status' => 'invalid_payload'], 400);
    }

    $correct_count = 0;
    foreach ($correct_flags as $flag) {
        if ($flag === true || $flag === 1 || $flag === '1') {
            $correct_count++;
        }
    }

    $transaction_id = 'quiz-' . gmdate('Ymd') . '-' . substr(bin2hex(random_bytes(8)), 0, 12);
    $timestamp = time();
    $payout = (string) ($params['payout'] ?? '1');
    $postback_secret = (string) ($provider['postback_secret'] ?? '');
    if ($postback_secret === '') {
        return new WP_REST_Response(['status' => 'missing_secret'], 500);
    }

    $canonical = $transaction_id . '|' . $pseudo_id . '|' . $payout . '|' . $timestamp;
    $signature = hash_hmac('sha256', $canonical, $postback_secret);

    $postback_payload = [
        'transaction_id' => $transaction_id,
        'timestamp' => $timestamp,
        'pseudo_id' => $pseudo_id,
        'payout' => $payout,
        'signature' => $signature,
        'quiz_id' => $quiz_id,
        'question_count' => $question_count,
        'correct_count' => $correct_count,
        'answers' => $answers,
        'answers_correct' => $correct_flags,
        'time_spent_sec' => $time_spent,
        'request_id' => sanitize_text_field((string) ($params['request_id'] ?? '')),
    ];

    $postback_request = new WP_REST_Request('POST', '/impact/v1/offerwall/callback/internal_article_quiz');
    foreach ($postback_payload as $key => $value) {
        $postback_request->set_param($key, $value);
    }

    if (function_exists('impactshop_offerwall_debug_log')) {
        impactshop_offerwall_debug_log('quiz_submit_postback', [
            'pseudo_id' => $pseudo_id,
            'quiz_id' => $quiz_id,
            'transaction_id' => $transaction_id,
        ]);
    }

    $response = rest_do_request($postback_request);
    return $response instanceof WP_REST_Response ? $response : new WP_REST_Response(['status' => 'ok'], 200);
}

function impactshop_article_quiz_pre_dispatch($result, WP_REST_Server $server, WP_REST_Request $request)
{
    if (strpos($request->get_route(), '/impact/v1/offerwall/callback/') !== 0) {
        return $result;
    }
    $provider_key = (string) $request['provider'];
    if ($provider_key !== 'internal_article_quiz') {
        return $result;
    }

    $providers = function_exists('impactshop_offerwall_get_providers') ? impactshop_offerwall_get_providers() : [];
    $provider = $providers[$provider_key] ?? [];

    $params = array_merge($request->get_query_params(), $request->get_json_params() ?: []);
    $transaction_id = sanitize_text_field((string) ($params['transaction_id'] ?? $params['tx_id'] ?? ''));
    if ($transaction_id === '') {
        return new WP_REST_Response(['status' => 'missing_transaction'], 400);
    }
    if (!preg_match('/^quiz-\d{8}-[A-Za-z0-9]+$/', $transaction_id)) {
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

    $rate_key = 'offerwall_internal_article_quiz_' . md5($pseudo_id);
    if (function_exists('impactshop_offerwall_rate_limit') && !impactshop_offerwall_rate_limit($rate_key, IMPACTSHOP_ARTICLE_QUIZ_RATE_LIMIT, HOUR_IN_SECONDS)) {
        if (function_exists('impactshop_offerwall_log_fraud')) {
            impactshop_offerwall_log_fraud('rate_limited_user', ['provider' => $provider_key, 'pseudo_id' => $pseudo_id]);
        }
        return new WP_REST_Response(['status' => 'rate_limited'], 429);
    }

    $quiz_id = sanitize_text_field((string) ($params['quiz_id'] ?? ''));
    if ($quiz_id === '') {
        return new WP_REST_Response(['status' => 'missing_quiz'], 400);
    }

    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_article_quiz_answers';
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT correct_count, question_count FROM {$answers_table} WHERE pseudo_id = %s AND quiz_id = %s",
        $pseudo_id,
        $quiz_id
    ), ARRAY_A);
    if ($existing && (int) ($existing['correct_count'] ?? 0) >= (int) ($existing['question_count'] ?? 3)) {
        return new WP_REST_Response(['status' => 'duplicate_quiz'], 200);
    }

    $answers = $params['answers'] ?? [];
    if (!is_array($answers) || count($answers) !== 3) {
        return new WP_REST_Response(['status' => 'invalid_answers'], 400);
    }

    $question_count = (int) ($params['question_count'] ?? count($answers));
    if ($question_count !== 3) {
        return new WP_REST_Response(['status' => 'invalid_question_count'], 400);
    }

    foreach ($answers as $value) {
        if (is_array($value)) {
            continue;
        }
        if (function_exists('impactshop_offerwall_survey_contains_pii') && impactshop_offerwall_survey_contains_pii((string) $value)) {
            return new WP_REST_Response(['status' => 'pii_detected'], 400);
        }
    }

    $request->set_param('article_quiz_context', [
        'pseudo_id' => $pseudo_id,
        'transaction_id' => $transaction_id,
        'quiz_id' => $quiz_id,
        'answers' => $answers,
        'question_count' => $question_count,
        'correct_count' => (int) ($params['correct_count'] ?? 0),
        'answers_correct' => (array) ($params['answers_correct'] ?? []),
        'time_spent_sec' => (int) ($params['time_spent_sec'] ?? 0),
        'request_id' => sanitize_text_field((string) ($params['request_id'] ?? '')),
    ]);

    return $result;
}

function impactshop_article_quiz_handle_rewards(string $pseudo_id, array $payload): void
{
    if (($payload['provider'] ?? '') !== 'internal_article_quiz') {
        return;
    }

    $request = rest_get_server()->get_current_request();
    $context = $request ? $request->get_param('article_quiz_context') : null;
    if (!is_array($context)) {
        return;
    }

    $quiz_id = (string) ($context['quiz_id'] ?? '');
    $question_count = (int) ($context['question_count'] ?? 0);
    if ($quiz_id === '' || $question_count !== 3) {
        return;
    }

    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_article_quiz_answers';
    if ((int) ($context['correct_count'] ?? 0) < $question_count) {
        return;
    }

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$answers_table} (pseudo_id, quiz_id, answers_json, correct_count, question_count, request_id, created_at)
         VALUES (%s, %s, %s, %d, %d, %s, %s)
         ON DUPLICATE KEY UPDATE correct_count = VALUES(correct_count), answers_json = VALUES(answers_json), request_id = VALUES(request_id), created_at = VALUES(created_at)",
        $pseudo_id,
        $quiz_id,
        wp_json_encode($context['answers'] ?? []),
        (int) ($context['correct_count'] ?? 0),
        $question_count,
        (string) ($context['request_id'] ?? ''),
        gmdate('Y-m-d H:i:s')
    ));
}

function impactshop_article_quiz_load_bank(): array
{
    if (is_readable(IMPACTSHOP_ARTICLE_QUIZ_DATA_FILE)) {
        $raw = file_get_contents(IMPACTSHOP_ARTICLE_QUIZ_DATA_FILE);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data) && !empty($data)) {
                $validated = [];
                foreach ($data as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $id = sanitize_text_field((string) ($row['id'] ?? ''));
                    $title = sanitize_text_field((string) ($row['title'] ?? ''));
                    $link = esc_url_raw((string) ($row['link'] ?? ''));
                    $summary = sanitize_text_field((string) ($row['summary'] ?? ''));
                    $questions = $row['questions'] ?? [];
                    if ($id === '' || $title === '' || $link === '' || !is_array($questions) || count($questions) < 3) {
                        continue;
                    }
                    $validated_questions = [];
                    foreach ($questions as $q) {
                        if (!is_array($q)) {
                            continue;
                        }
                        $label = sanitize_text_field((string) ($q['label'] ?? ''));
                        $options = $q['options'] ?? [];
                        if ($label === '' || !is_array($options) || count($options) < 4) {
                            continue;
                        }
                        $validated_questions[] = [
                            'label' => $label,
                            'options' => $options,
                            'correct' => sanitize_text_field((string) ($q['correct'] ?? '')),
                        ];
                    }
                    if (count($validated_questions) < 3) {
                        continue;
                    }
                    $validated[] = [
                        'id' => $id,
                        'title' => $title,
                        'link' => $link,
                        'summary' => $summary,
                        'questions' => array_slice($validated_questions, 0, 3),
                    ];
                }
                if (!empty($validated)) {
                    return $validated;
                }
            }
        }
    }

    return [
        [
            'id' => 'demo-article',
            'title' => 'Demo cikk',
            'link' => 'https://example.org',
            'summary' => 'Ez egy demo cikk kifejezetten teszt celra.',
            'questions' => [
                [
                    'label' => 'Mi a demo kerdes helyes valasza?',
                    'options' => ['A' => 'Egyik sem', 'B' => 'Ez', 'C' => 'Az', 'D' => 'Mind'],
                    'correct' => 'B',
                ],
                [
                    'label' => 'Mi tortenik a demoban?',
                    'options' => ['A' => 'Semmi', 'B' => 'Teszt', 'C' => 'Kiserlet', 'D' => 'Mind'],
                    'correct' => 'B',
                ],
                [
                    'label' => 'Miert jo a demo?',
                    'options' => ['A' => 'Gyors', 'B' => 'Rovid', 'C' => 'Attekintheto', 'D' => 'Mind'],
                    'correct' => 'D',
                ],
            ],
        ],
    ];
}

function impactshop_article_quiz_get_completed_ids(): array
{
    $providers = function_exists('impactshop_offerwall_get_providers') ? impactshop_offerwall_get_providers() : [];
    $provider = $providers['internal_article_quiz'] ?? [];
    $secret = impactshop_article_quiz_get_secret($provider);
    if ($secret === '') {
        return [];
    }
    $token = sanitize_text_field((string) ($_GET['quiz_token'] ?? $_GET['survey_token'] ?? ''));
    if ($token === '') {
        return [];
    }
    $payload = impactshop_article_quiz_decode_token($token, $secret);
    if (!$payload) {
        return [];
    }
    $pseudo_id = sanitize_text_field((string) ($payload['pseudo_id'] ?? ''));
    if ($pseudo_id === '') {
        return [];
    }
    global $wpdb;
    $answers_table = $wpdb->prefix . 'impactshop_article_quiz_answers';
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT quiz_id FROM {$answers_table} WHERE pseudo_id = %s AND correct_count >= question_count",
        $pseudo_id
    ));
    return array_values(array_unique(array_map('sanitize_text_field', $rows ?: [])));
}

function impactshop_article_quiz_shortcode(): string
{
    $bank = impactshop_article_quiz_load_bank();
    $completed = impactshop_article_quiz_get_completed_ids();
    $bank_json = wp_json_encode($bank, JSON_UNESCAPED_UNICODE);
    $completed_json = wp_json_encode($completed, JSON_UNESCAPED_UNICODE);

    $html = '<div class="impactshop-article-quiz-shell">';
    $html .= '<div class="impactshop-article-quiz" data-role="impactshop-article-quiz">';
    $html .= '<div class="impactshop-article-quiz-kicker">Impact kvíz</div>';
    $html .= '<h2>Olvasás + 3 kérdés</h2>';
    $html .= '<p class="impactshop-article-quiz-lead">Olvasd el a cikket, majd válaszolj a 3 kérdésre. A jutalom a beküldés után azonnal megjelenik.</p>';
    $html .= '<div class="impactshop-article-quiz-card">';
    $html .= '<div class="impactshop-article-quiz-article" data-role="impactshop-article-quiz-article"></div>';
    $html .= '<div class="impactshop-article-quiz-start-wrap"><button type="button" class="impactshop-article-quiz-start" data-role="impactshop-article-quiz-start" disabled>Kvíz indítása</button></div>';
    $html .= '<div class="impactshop-article-quiz-progress"><span data-role="impactshop-article-quiz-progress"></span></div>';
    $html .= '<form class="impactshop-article-quiz-form" data-role="impactshop-article-quiz-form">';
    $html .= '<div class="impactshop-article-quiz-question" data-role="impactshop-article-quiz-question"></div>';
    $html .= '<div class="impactshop-article-quiz-actions">';
    $html .= '<button type="button" class="impactshop-article-quiz-back" data-role="impactshop-article-quiz-back">Vissza</button>';
    $html .= '<button type="submit" class="impactshop-article-quiz-next" data-role="impactshop-article-quiz-next">Tovább</button>';
    $html .= '</div>';
    $html .= '<p class="impactshop-article-quiz-status" data-role="impactshop-article-quiz-status" aria-live="polite"></p>';
    $html .= '</form></div></div></div>';

    $html .= '<style>
@import url("https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap");
.impactshop-article-quiz-shell{padding:32px 16px;position:relative;overflow:hidden;background:linear-gradient(135deg,#fff2e3 0%,#e7f4ff 50%,#eafff6 100%)}
.impactshop-article-quiz{max-width:720px;margin:0 auto;padding:36px;border-radius:28px;background:rgba(255,255,255,0.95);backdrop-filter:blur(18px);color:#0f172a;box-shadow:0 24px 60px rgba(15,23,42,0.16),0 0 0 1px rgba(255,255,255,0.6) inset;font-family:"Space Grotesk",system-ui,-apple-system,sans-serif}
.impactshop-article-quiz-kicker{font-size:12px;text-transform:uppercase;letter-spacing:0.3em;color:#f97316;font-weight:600;margin-bottom:12px}
.impactshop-article-quiz h2{margin:0 0 10px;font-size:32px;font-weight:700;letter-spacing:-0.6px;color:#0f172a}
.impactshop-article-quiz-lead{margin:0 0 20px;color:#475569;font-size:16px;line-height:1.6}
.impactshop-article-quiz-card{background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);border-radius:22px;padding:24px;border:1px solid rgba(148,163,184,0.2);box-shadow:0 10px 26px rgba(15,23,42,0.08)}
.impactshop-article-quiz-article{padding:16px 18px;border-radius:16px;border:1px solid rgba(148,163,184,0.25);background:#fff;margin-bottom:16px}
.impactshop-article-quiz-article h3{margin:0 0 8px;font-size:18px}
.impactshop-article-quiz-article p{margin:0 0 12px;color:#475569}
.impactshop-article-quiz-article a{display:inline-block;background:#0ea5e9;color:#fff;text-decoration:none;padding:10px 14px;border-radius:12px;font-weight:600}
.impactshop-article-quiz-start-wrap{margin:8px 0 16px;display:flex;justify-content:flex-start}
.impactshop-article-quiz-start{background:#0f172a;color:#fff;border:0;border-radius:12px;padding:12px 16px;font-weight:600;font-size:15px;cursor:pointer;opacity:0.7}
.impactshop-article-quiz-start:disabled{cursor:not-allowed;opacity:0.5}
.impactshop-article-quiz-progress{font-size:12px;letter-spacing:0.6px;text-transform:uppercase;color:#64748b;margin-bottom:12px}
.impactshop-article-quiz-question legend{font-weight:600;margin-bottom:16px;font-size:18px;color:#0f172a;display:block}
.impactshop-article-quiz-option{display:flex;align-items:center;gap:14px;margin:0 0 12px;padding:16px 18px;border:2px solid rgba(148,163,184,0.25);border-radius:16px;cursor:pointer;transition:all 0.2s ease;background:#fff}
.impactshop-article-quiz-option input{margin:0;accent-color:#ff6b4a;width:20px;height:20px;cursor:pointer}
.impactshop-article-quiz-option input:checked + span{color:#ff6b4a;font-weight:600}
.impactshop-article-quiz-option span{font-size:15px;color:#1f2937}
.impactshop-article-quiz-actions{display:flex;gap:12px;margin-top:12px}
.impactshop-article-quiz-back{flex:1;background:#eef2f7;color:#0f172a;border:0;border-radius:16px;padding:14px 18px;font-weight:600;font-size:15px;cursor:pointer}
.impactshop-article-quiz-next{flex:2;background:linear-gradient(135deg,#ff6b4a,#f49f5a);color:#fff;border:0;border-radius:16px;padding:14px 18px;font-weight:600;font-size:15px;cursor:pointer}
.impactshop-article-quiz-status{margin-top:12px;font-size:14px;color:#0f172a;text-align:center;padding:10px;border-radius:12px;background:rgba(34,193,195,0.12);font-weight:500}
@media (max-width:640px){.impactshop-article-quiz{padding:24px}.impactshop-article-quiz-actions{flex-direction:column}.impactshop-article-quiz-back,.impactshop-article-quiz-next{flex:1}}
</style>';

    $script = <<<JS
(function(){
window.impactshopArticleQuizBank = {$bank_json};
window.impactshopArticleQuizCompleted = {$completed_json};
var root=document.querySelector("[data-role=impactshop-article-quiz]");
if(!root){return;}
var form=root.querySelector("[data-role=impactshop-article-quiz-form]");
var articleWrap=root.querySelector("[data-role=impactshop-article-quiz-article]");
var questionWrap=root.querySelector("[data-role=impactshop-article-quiz-question]");
var progress=root.querySelector("[data-role=impactshop-article-quiz-progress]");
var backBtn=root.querySelector("[data-role=impactshop-article-quiz-back]");
var nextBtn=root.querySelector("[data-role=impactshop-article-quiz-next]");
var status=root.querySelector("[data-role=impactshop-article-quiz-status]");
var startBtn=root.querySelector("[data-role=impactshop-article-quiz-start]");
var params=new URLSearchParams(window.location.search);
var token=params.get("quiz_token") || params.get("survey_token") || "";
var bank=window.impactshopArticleQuizBank||[];
var completed=window.impactshopArticleQuizCompleted||[];
var minReadSeconds=20;
var readStart=null;
var readTimer=null;
var readSeconds=0;
var started=false;
var quizStarted=false;
var quizTimeLimit=45;
var quizTimer=null;
var quizStart=null;
var quizSubmitted=false;
var answers=[];
var correctFlags=[];
var currentIndex=0;
var roundSize=3;
var sessionId=Math.random().toString(36).slice(2,10);
var statusPinned=false;

function setStatus(message, pin){
  status.textContent=message;
  statusPinned=!!pin;
}

function pickRandom(list){
  return list[Math.floor(Math.random()*list.length)];
}
function pickArticle(){
  var pool=bank.filter(function(item){
    return completed.indexOf(item.id)===-1;
  });
  if(pool.length){return pickRandom(pool);}
  return pickRandom(bank);
}
function updateTimer(){
  if(!readStart){return;}
  readSeconds=Math.floor((Date.now()-readStart)/1000);
  if(quizStarted){return;}
  if(statusPinned){return;}
  if(readSeconds>=minReadSeconds){
    setStatus("Rendben, indithatod a kvízt.", false);
    if(startBtn){
      startBtn.disabled=false;
      startBtn.textContent="Kviz inditasa";
    }
  } else {
    setStatus("Olvasasi ido: "+readSeconds+" / "+minReadSeconds+" mp", false);
    if(startBtn){
      startBtn.disabled=true;
      startBtn.textContent="Kviz inditasa ("+(minReadSeconds-readSeconds)+" mp)";
    }
  }
}
function updateQuizTimer(){
  if(!quizStart || quizSubmitted){return;}
  if(statusPinned){return;}
  var elapsed=Math.floor((Date.now()-quizStart)/1000);
  var remaining=Math.max(0, quizTimeLimit - elapsed);
  setStatus("Kviz ido: "+remaining+" mp", false);
  if(remaining<=0){
    quizSubmitted=true;
    if(quizTimer){clearInterval(quizTimer);}
    setStatus("Lejart az ido, bekuldjuk a valaszokat.", true);
    forceSubmit(article);
  }
}
function startQuizTimer(){
  if(quizTimer){return;}
  quizStart=Date.now();
  updateQuizTimer();
  quizTimer=setInterval(updateQuizTimer, 1000);
}
function startRead(){
  if(readStart){return;}
  readStart=Date.now();
  updateTimer();
  readTimer=setInterval(updateTimer, 1000);
}
function renderArticle(article){
  var linkLabel='Cikk megnyitasa';
  articleWrap.innerHTML='';
  var box=document.createElement('div');
  box.innerHTML='<h3>'+article.title+'</h3>'+
    '<p>'+article.summary+'</p>'+
    '<a href="'+article.link+'" target="_blank" rel="noopener">'+linkLabel+'</a>'+
    '<p style="margin-top:10px;font-size:13px;color:#64748b;">Olvasd el a cikket, majd inditsd a kvízt.</p>';
  articleWrap.appendChild(box);
  var link=articleWrap.querySelector('a');
  link.addEventListener('click', startRead);
}
function setQuizVisibility(show){
  questionWrap.style.display=show ? "block" : "none";
  progress.style.display=show ? "block" : "none";
  backBtn.style.display=show && currentIndex>0 ? "inline-flex" : "none";
}
function renderQuestion(article){
  questionWrap.innerHTML='';
  if(!article || !article.questions){return;}
  var q=article.questions[currentIndex];
  if(!q){return;}
  var fieldset=document.createElement('fieldset');
  fieldset.className='impactshop-article-quiz-question';
  var legend=document.createElement('legend');
  legend.textContent=q.label;
  fieldset.appendChild(legend);
  Object.keys(q.options||{}).forEach(function(key){
    var id='q_'+currentIndex+'_'+key;
    var label=document.createElement('label');
    label.className='impactshop-article-quiz-option';
    label.setAttribute('for', id);
    var input=document.createElement('input');
    input.type='radio';
    input.name='answer';
    input.id=id;
    input.value=key;
    input.required=true;
    if(answers[currentIndex] && String(answers[currentIndex])===String(key)){
      input.checked=true;
    }
    var span=document.createElement('span');
    span.textContent=q.options[key];
    label.appendChild(input);
    label.appendChild(span);
    fieldset.appendChild(label);
  });
  questionWrap.appendChild(fieldset);
  progress.textContent=(currentIndex+1)+'/'+roundSize+' kerdes';
  backBtn.style.display=currentIndex>0?'inline-flex':'none';
  nextBtn.textContent=currentIndex===roundSize-1?'Pontok jovairasa':'Tovabb';
}
function readAnswer(){
  var input=form.querySelector('input[name=answer]:checked');
  return input?input.value:null;
}
function submitQuiz(article){
  if(!token){
    setStatus('Nem az offerwallbol inditottad a feladatot. Nyisd meg az offerwall feladatbol, hogy megkapd a jutalmat.', true);
    return;
  }
  if(quizSubmitted){return;}
  var correctCount=correctFlags.filter(Boolean).length;
  var incorrectCount=roundSize - correctCount;
  if(incorrectCount > 0){
    setStatus('Hibas valaszok szama: '+incorrectCount+'. Javithatsz a bekuldes elott.', true);
    return;
  }
  quizSubmitted=true;
  if(quizTimer){clearInterval(quizTimer);}
  var quizId=article.id;
  setStatus('Kuldes folyamatban...', true);
  fetch('/wp-json/impact/v1/offerwall/article-quiz/submit',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({
      quiz_token:token,
      quiz_id:quizId,
      answers:answers,
      answers_correct:correctFlags,
      question_count:roundSize,
      correct_count:correctCount,
      time_spent_sec:readSeconds
    })
  })
    .then(function(resp){return resp.json().catch(function(){return {};});})
    .then(function(resp){
      if(resp && resp.status && resp.status!=="ok" && resp.status!=="duplicate_quiz"){throw new Error(resp.status);}
      setStatus('Koszonjuk! Hibas valaszok: 0. A jutalom hamarosan megjelenik.', true);
      nextBtn.disabled=true;
      backBtn.disabled=true;
    })
    .catch(function(){setStatus('Nem sikerult elkuldeni. Probalj ujra par perc mulva.', true);});
}
function forceSubmit(article){
  for(var i=0;i<roundSize;i++){
    if(typeof answers[i]==="undefined"){answers[i]="";}
    if(typeof correctFlags[i]==="undefined"){correctFlags[i]=false;}
  }
  var correctCount=correctFlags.filter(Boolean).length;
  var incorrectCount=roundSize - correctCount;
  quizSubmitted=true;
  if(quizTimer){clearInterval(quizTimer);}
  setStatus('Lejart az ido. Hibas valaszok: '+incorrectCount+'. Probald kesobb ujra.', true);
  nextBtn.disabled=true;
  backBtn.disabled=true;
}

var article=pickArticle();
if(!article){status.textContent='Nincs elerheto kvíz.';return;}
renderArticle(article);
setQuizVisibility(false);
progress.textContent='';
questionWrap.innerHTML='';
if(!token){
  setStatus('Tipp: az offerwall feladatbol inditva kapod meg a jutalmat.', false);
}
if(startBtn){
  startBtn.addEventListener('click', function(){
    if(readSeconds < minReadSeconds){
      setStatus('Elobb olvasd el a cikket (legalabb '+minReadSeconds+' mp).', false);
      return;
    }
    quizStarted=true;
    if(readTimer){clearInterval(readTimer);}
    startBtn.disabled=true;
    startQuizTimer();
    setQuizVisibility(true);
    renderQuestion(article);
  });
}

form.addEventListener('submit', function(e){
  e.preventDefault();
  if(!quizStarted){
    setStatus('Inditsd el a kvízt a gombbal.', false);
    return;
  }
  if(!readStart || readSeconds < minReadSeconds){
    setStatus('Elobb olvasd el a cikket (legalabb '+minReadSeconds+' mp).', false);
    return;
  }
  var q=article.questions[currentIndex];
  var value=readAnswer();
  if(!value){setStatus('Valassz egy opciot a tovabblepeshez.', true);return;}
  answers[currentIndex]=value;
  correctFlags[currentIndex]=(String(value)===String(q.correct));
  if(currentIndex===roundSize-1){
    submitQuiz(article);
    return;
  }
  currentIndex++;
  renderQuestion(article);
});
form.addEventListener('change', function(){
  if(!statusPinned){return;}
  statusPinned=false;
  if(quizStarted){
    updateQuizTimer();
  } else {
    updateTimer();
  }
});
backBtn.addEventListener('click', function(){
  if(currentIndex>0){
    currentIndex--;
    renderQuestion(article);
  }
});
})();
JS;

    $html .= '<script>' . $script . '</script>';

    return $html;
}
