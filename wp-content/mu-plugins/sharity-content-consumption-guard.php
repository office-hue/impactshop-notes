<?php
/**
 * Plugin Name: Sharity Content Consumption Guard (MU)
 * Description: WP admin dashboard a tartalom-fogyasztás és kimerülés monitorozására.
 * Version: 0.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'sharity_content_guard_admin_menu', 60);
add_action('wp_dashboard_setup', 'sharity_content_guard_dashboard_widget');

function sharity_content_guard_admin_menu(): void
{
    add_submenu_page(
        'sharity-points-admin',
        'Content Guard',
        'Content Guard',
        'manage_sharity_points',
        'sharity-content-guard',
        'sharity_content_guard_admin_page'
    );
}

function sharity_content_guard_dashboard_widget(): void
{
    if (!current_user_can('manage_sharity_points')) {
        return;
    }
    wp_add_dashboard_widget(
        'sharity_content_guard_widget',
        'Content Guard - gyors nézet',
        'sharity_content_guard_dashboard_widget_render'
    );
}

function sharity_content_guard_dashboard_widget_render(): void
{
    $metrics = sharity_content_guard_collect_metrics(30);
    $active_users = (int) ($metrics['active_users'] ?? 0);
    $video_all_pct = (float) ($metrics['video_all_seen_pct'] ?? 0.0);
    $quiz_pct = (float) ($metrics['quiz_pct'] ?? 0.0);
    $survey_pct = (float) ($metrics['survey_pct'] ?? 0.0);
    $game_pct = (float) ($metrics['game_pct'] ?? 0.0);

    echo '<p><strong>Aktív userek (30 nap):</strong> ' . esc_html(number_format_i18n($active_users)) . '</p>';
    echo '<ul style="margin:0 0 10px 18px;list-style:disc;">';
    echo '<li>Minden videót megnézte: <strong>' . esc_html(number_format_i18n($video_all_pct, 1)) . '%</strong></li>';
    echo '<li>Kvíz kitöltés: <strong>' . esc_html(number_format_i18n($quiz_pct, 1)) . '%</strong></li>';
    echo '<li>Kérdőív completion: <strong>' . esc_html(number_format_i18n($survey_pct, 1)) . '%</strong></li>';
    echo '<li>Játék completion: <strong>' . esc_html(number_format_i18n($game_pct, 1)) . '%</strong></li>';
    echo '</ul>';
    echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sharity-content-guard')) . '">Teljes dashboard</a></p>';
}

function sharity_content_guard_admin_page(): void
{
    if (!current_user_can('manage_sharity_points')) {
        wp_die('Nincs jogosultság.');
    }

    $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
    if (!in_array($days, [7, 30, 90], true)) {
        $days = 30;
    }

    $metrics = sharity_content_guard_collect_metrics($days);
    $active_users = (int) ($metrics['active_users'] ?? 0);
    $inventory = (array) ($metrics['inventory'] ?? []);
    $advice = sharity_content_guard_build_advice($metrics);
    $video_rows = (array) ($metrics['video_rows'] ?? []);

    echo '<div class="wrap">';
    echo '<h1>Content Guard Dashboard</h1>';
    echo '<p>Használat: ez a nézet azt mutatja, hogy az aktív userek mekkora része fogyasztotta el a fő tartalomtípusokat. Így látszik, mikor kell új tartalmat berakni.</p>';

    echo '<form method="get" style="margin:16px 0;">';
    echo '<input type="hidden" name="page" value="sharity-content-guard">';
    echo '<label for="days"><strong>Időszak:</strong></label> ';
    echo '<select id="days" name="days">';
    foreach ([7 => '7 nap', 30 => '30 nap', 90 => '90 nap'] as $value => $label) {
        echo '<option value="' . esc_attr((string) $value) . '"' . selected($days, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select> ';
    submit_button('Frissítés', 'secondary', '', false);
    echo '</form>';

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:1100px;">';
    sharity_content_guard_kpi_card('Aktív userek', number_format_i18n($active_users), 'Bázis a kiválasztott időszakban.');
    sharity_content_guard_kpi_card(
        'Minden videó kész',
        number_format_i18n((float) ($metrics['video_all_seen_pct'] ?? 0.0), 1) . '%',
        number_format_i18n((int) ($metrics['video_all_seen_users'] ?? 0)) . ' / ' . number_format_i18n($active_users)
    );
    sharity_content_guard_kpi_card(
        'Kvíz completion',
        number_format_i18n((float) ($metrics['quiz_pct'] ?? 0.0), 1) . '%',
        number_format_i18n((int) ($metrics['quiz_users'] ?? 0)) . ' user'
    );
    sharity_content_guard_kpi_card(
        'Kérdőív completion',
        number_format_i18n((float) ($metrics['survey_pct'] ?? 0.0), 1) . '%',
        number_format_i18n((int) ($metrics['survey_users'] ?? 0)) . ' user'
    );
    sharity_content_guard_kpi_card(
        'Játék completion',
        number_format_i18n((float) ($metrics['game_pct'] ?? 0.0), 1) . '%',
        number_format_i18n((int) ($metrics['game_users'] ?? 0)) . ' user'
    );
    echo '</div>';

    echo '<h2 style="margin-top:24px;">Tartalomkészlet</h2>';
    echo '<table class="widefat striped" style="max-width:900px;">';
    echo '<thead><tr><th>Elem</th><th>Érték</th></tr></thead><tbody>';
    echo '<tr><td>Aktív edukációs videók</td><td>' . esc_html(number_format_i18n((int) ($inventory['education_videos'] ?? 0))) . '</td></tr>';
    echo '<tr><td>Kvíz cikkek a bankban</td><td>' . esc_html(number_format_i18n((int) ($inventory['quiz_articles'] ?? 0))) . '</td></tr>';
    echo '<tr><td>Kérdőív kérdések a bankban</td><td>' . esc_html(number_format_i18n((int) ($inventory['survey_questions'] ?? 0))) . '</td></tr>';
    echo '<tr><td>Játék offer típusok (' . esc_html((string) $days) . ' nap)</td><td>' . esc_html(number_format_i18n((int) ($inventory['game_offer_types_recent'] ?? 0))) . '</td></tr>';
    echo '</tbody></table>';

    echo '<h2 style="margin-top:24px;">Guard jelzés</h2>';
    echo '<div class="notice notice-' . esc_attr($advice['level']) . '" style="max-width:1100px;"><p><strong>' . esc_html($advice['title']) . ':</strong> ' . esc_html($advice['message']) . '</p></div>';

    echo '<h2 style="margin-top:24px;">Videó lefedettség (aktív userekből)</h2>';
    if (empty($video_rows)) {
        echo '<p>Nincs videó lefedettségi adat.</p>';
    } else {
        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr><th>Videó</th><th>Egyedi néző</th><th>Lefedettség</th></tr></thead><tbody>';
        foreach ($video_rows as $row) {
            $coverage = (float) ($row['coverage_pct'] ?? 0.0);
            echo '<tr>';
            echo '<td>' . esc_html((string) ($row['title'] ?? 'Ismeretlen videó')) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((int) ($row['viewers'] ?? 0))) . '</td>';
            echo '<td>' . esc_html(number_format_i18n($coverage, 1)) . '%</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<p style="margin-top:20px;color:#555;">Megjegyzés: a százalékok alapja a kiválasztott időszak aktív pseudo ID bázisa.</p>';
    echo '</div>';
}

function sharity_content_guard_kpi_card(string $title, string $value, string $hint): void
{
    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">';
    echo '<div style="font-size:12px;text-transform:uppercase;color:#6b7280;margin-bottom:4px;">' . esc_html($title) . '</div>';
    echo '<div style="font-size:28px;font-weight:700;line-height:1.2;color:#111827;">' . esc_html($value) . '</div>';
    echo '<div style="font-size:12px;color:#6b7280;margin-top:6px;">' . esc_html($hint) . '</div>';
    echo '</div>';
}

function sharity_content_guard_collect_metrics(int $days): array
{
    global $wpdb;
    $prefix = $wpdb->prefix;
    $start_dt = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));

    $table_ads = $prefix . 'impactshop_ads_views';
    $table_edu = $prefix . 'impactshop_education_views';
    $table_offer = $prefix . 'impactshop_offerwall_completions';
    $table_quiz = $prefix . 'impactshop_article_quiz_answers';
    $table_survey = $prefix . 'impactshop_survey_answers';
    $table_user_points = $prefix . 'user_points';

    $selects = [];
    $select_args = [];

    foreach ([$table_ads, $table_edu, $table_offer, $table_quiz, $table_survey] as $table) {
        if (!sharity_content_guard_table_exists($table)) {
            continue;
        }
        $selects[] = "SELECT pseudo_id FROM {$table} WHERE pseudo_id <> '' AND created_at >= %s";
        $select_args[] = $start_dt;
    }

    // Include active profiles from points table as well, so users with valid profile+points
    // are counted even if browser credential save was not used.
    if (sharity_content_guard_table_exists($table_user_points)) {
        $selects[] = "SELECT pseudo_id
                      FROM {$table_user_points}
                      WHERE pseudo_id <> ''
                        AND (
                          COALESCE(last_activity_at, updated_at, created_at) >= %s
                          OR points_total > 0
                        )";
        $select_args[] = $start_dt;
    }

    if (empty($selects)) {
        return [
            'active_users' => 0,
            'video_all_seen_users' => 0,
            'video_all_seen_pct' => 0.0,
            'quiz_users' => 0,
            'quiz_pct' => 0.0,
            'survey_users' => 0,
            'survey_pct' => 0.0,
            'game_users' => 0,
            'game_pct' => 0.0,
            'inventory' => [
                'education_videos' => 0,
                'quiz_articles' => 0,
                'survey_questions' => 0,
                'game_offer_types_recent' => 0,
            ],
            'video_rows' => [],
        ];
    }

    $active_union = '(' . implode(' UNION ', $selects) . ')';
    $active_union_sql = $wpdb->prepare($active_union, $select_args);

    $active_users = (int) $wpdb->get_var("SELECT COUNT(DISTINCT pseudo_id) FROM {$active_union_sql}");
    $den = max(1, $active_users);

    $quiz_users = 0;
    if (sharity_content_guard_table_exists($table_quiz)) {
        $quiz_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pseudo_id) FROM {$table_quiz} WHERE pseudo_id <> '' AND created_at >= %s",
            $start_dt
        ));
    }

    $survey_users = 0;
    if (sharity_content_guard_table_exists($table_survey)) {
        $survey_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pseudo_id) FROM {$table_survey} WHERE pseudo_id <> '' AND created_at >= %s",
            $start_dt
        ));
    }

    $game_users = 0;
    $game_offer_types_recent = 0;
    if (sharity_content_guard_table_exists($table_offer)) {
        $like_game = '%' . $wpdb->esc_like('game') . '%';
        $like_jatek = '%' . $wpdb->esc_like('játék') . '%';
        $like_jatek_ascii = '%' . $wpdb->esc_like('jatek') . '%';

        $game_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pseudo_id)
             FROM {$table_offer}
             WHERE pseudo_id <> ''
               AND created_at >= %s
               AND status NOT IN ('reversed','capped')
               AND (
                    LOWER(COALESCE(offer_type, '')) LIKE %s
                 OR LOWER(COALESCE(offer_name, '')) LIKE %s
                 OR LOWER(COALESCE(offer_name, '')) LIKE %s
               )",
            $start_dt,
            strtolower($like_game),
            strtolower($like_jatek),
            strtolower($like_jatek_ascii)
        ));

        $game_offer_types_recent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT COALESCE(NULLIF(TRIM(offer_type), ''), CONCAT('offer:', offer_id)))
             FROM {$table_offer}
             WHERE created_at >= %s
               AND status NOT IN ('reversed','capped')
               AND (
                    LOWER(COALESCE(offer_type, '')) LIKE %s
                 OR LOWER(COALESCE(offer_name, '')) LIKE %s
                 OR LOWER(COALESCE(offer_name, '')) LIKE %s
               )",
            $start_dt,
            strtolower($like_game),
            strtolower($like_jatek),
            strtolower($like_jatek_ascii)
        ));
    }

    $active_videos = sharity_content_guard_get_active_education_videos();
    $active_video_ids = array_keys($active_videos);
    $active_video_count = count($active_video_ids);

    $video_all_seen_users = 0;
    $video_rows = [];

    if ($active_video_count > 0 && sharity_content_guard_table_exists($table_edu)) {
        $in_sql = implode(',', array_fill(0, $active_video_count, '%s'));
        $args = $active_video_ids;

        $view_sql = $wpdb->prepare(
            "SELECT ev.pseudo_id, COUNT(DISTINCT ev.content_id) AS seen_count
             FROM {$table_edu} ev
             INNER JOIN {$active_union_sql} a ON a.pseudo_id = ev.pseudo_id
             WHERE ev.content_type = 'education' AND ev.content_id IN ({$in_sql})
             GROUP BY ev.pseudo_id",
            $args
        );

        $rows = $wpdb->get_results($view_sql, ARRAY_A);
        foreach ($rows as $row) {
            if ((int) ($row['seen_count'] ?? 0) >= $active_video_count) {
                $video_all_seen_users++;
            }
        }

        $coverage_sql = $wpdb->prepare(
            "SELECT ev.content_id, COUNT(DISTINCT ev.pseudo_id) AS viewers
             FROM {$table_edu} ev
             INNER JOIN {$active_union_sql} a ON a.pseudo_id = ev.pseudo_id
             WHERE ev.content_type = 'education' AND ev.content_id IN ({$in_sql})
             GROUP BY ev.content_id
             ORDER BY viewers DESC",
            $args
        );

        $coverage_rows = $wpdb->get_results($coverage_sql, ARRAY_A);
        $coverage_map = [];
        foreach ($coverage_rows as $row) {
            $coverage_map[(string) $row['content_id']] = (int) $row['viewers'];
        }

        foreach ($active_videos as $video_id => $title) {
            $viewers = (int) ($coverage_map[$video_id] ?? 0);
            $video_rows[] = [
                'title' => $title,
                'viewers' => $viewers,
                'coverage_pct' => ($viewers / $den) * 100,
            ];
        }
    }

    $quiz_articles = sharity_content_guard_quiz_inventory_count();
    $survey_questions = sharity_content_guard_survey_inventory_count();

    return [
        'active_users' => $active_users,
        'video_all_seen_users' => $video_all_seen_users,
        'video_all_seen_pct' => ($video_all_seen_users / $den) * 100,
        'quiz_users' => $quiz_users,
        'quiz_pct' => ($quiz_users / $den) * 100,
        'survey_users' => $survey_users,
        'survey_pct' => ($survey_users / $den) * 100,
        'game_users' => $game_users,
        'game_pct' => ($game_users / $den) * 100,
        'inventory' => [
            'education_videos' => $active_video_count,
            'quiz_articles' => $quiz_articles,
            'survey_questions' => $survey_questions,
            'game_offer_types_recent' => $game_offer_types_recent,
        ],
        'video_rows' => $video_rows,
    ];
}

function sharity_content_guard_build_advice(array $metrics): array
{
    $video_all = (float) ($metrics['video_all_seen_pct'] ?? 0.0);
    $quiz = (float) ($metrics['quiz_pct'] ?? 0.0);
    $survey = (float) ($metrics['survey_pct'] ?? 0.0);
    $game = (float) ($metrics['game_pct'] ?? 0.0);
    $inventory = (array) ($metrics['inventory'] ?? []);
    $video_count = (int) ($inventory['education_videos'] ?? 0);

    if ($video_count <= 2 || $video_all >= 55.0 || ($quiz >= 65.0 && $survey >= 65.0 && $game >= 65.0)) {
        return [
            'level' => 'error',
            'title' => 'Piros',
            'message' => 'Tartalomkimerülés közel van. Most érdemes új videó/kvíz/kérdőív/játék elemeket feltölteni.',
        ];
    }

    if ($video_count <= 4 || $video_all >= 35.0 || $quiz >= 50.0 || $survey >= 50.0 || $game >= 50.0) {
        return [
            'level' => 'warning',
            'title' => 'Sárga',
            'message' => 'Közeledik a kimerülés. 1-2 héten belül javasolt új tartalom előkészítése.',
        ];
    }

    return [
        'level' => 'success',
        'title' => 'Zöld',
        'message' => 'A jelenlegi tartalomkészlet még biztonságos. Monitorozás maradhat heti ritmusban.',
    ];
}

function sharity_content_guard_table_exists(string $table): bool
{
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return is_string($exists) && $exists !== '';
}

function sharity_content_guard_get_active_education_videos(): array
{
    $videos = [];

    if (function_exists('impactshop_ads_watch_get_education_videos')) {
        $source = impactshop_ads_watch_get_education_videos();
        foreach ($source as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (function_exists('impactshop_ads_watch_is_education_active') && !impactshop_ads_watch_is_education_active($item)) {
                continue;
            }
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $title = (string) ($item['title'] ?? ('Videó #' . $id));
            $videos[$id] = $title;
        }
    }

    if (!empty($videos)) {
        return $videos;
    }

    $posts = get_posts([
        'post_type' => 'impact_edu_video',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    foreach ($posts as $post_id) {
        $videos[(string) $post_id] = get_the_title((int) $post_id) ?: ('Videó #' . (int) $post_id);
    }

    return $videos;
}

function sharity_content_guard_quiz_inventory_count(): int
{
    if (defined('IMPACTSHOP_ARTICLE_QUIZ_DATA_FILE') && is_readable((string) IMPACTSHOP_ARTICLE_QUIZ_DATA_FILE)) {
        $raw = file_get_contents((string) IMPACTSHOP_ARTICLE_QUIZ_DATA_FILE);
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return count($json);
            }
        }
    }
    return 0;
}

function sharity_content_guard_survey_inventory_count(): int
{
    if (defined('IMPACTSHOP_SURVEY_QUESTION_FILE') && is_readable((string) IMPACTSHOP_SURVEY_QUESTION_FILE)) {
        $raw = file_get_contents((string) IMPACTSHOP_SURVEY_QUESTION_FILE);
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return count($json);
            }
        }
    }
    return 0;
}
