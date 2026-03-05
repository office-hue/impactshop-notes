<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'sharity_points_admin_menu');

function sharity_points_admin_menu(): void
{
    add_menu_page(
        'Sharity Pontok',
        'Sharity Pontok',
        'manage_sharity_points',
        'sharity-points-admin',
        'sharity_points_admin_page',
        'dashicons-awards',
        56
    );
}

function sharity_points_admin_page(): void
{
    if (!current_user_can('manage_sharity_points')) {
        return;
    }

    $notice = '';
    $notice_type = 'updated';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['sharity_points_action'])) {
        check_admin_referer('sharity_points_admin_action', 'sharity_points_nonce');
        $action = sanitize_text_field((string) $_POST['sharity_points_action']);
        if ($action === 'adjust') {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            $pseudo_id = sanitize_text_field((string) ($_POST['pseudo_id'] ?? ''));
            $points = (int) ($_POST['points'] ?? 0);
            $reason = sanitize_text_field((string) ($_POST['reason'] ?? ''));
            if ($points === 0 || ($user_id <= 0 && $pseudo_id === '')) {
                $notice = 'Hibás pont érték vagy hiányzó azonosító.';
                $notice_type = 'error';
            } else {
                $manager = new Sharity_Points_Manager();
                $metadata = [
                    'source_type' => 'admin_adjustment',
                    'reason' => $reason,
                    'actor_user_id' => get_current_user_id(),
                ];
                $dedupe = 'admin_adjustment:' . ($user_id > 0 ? $user_id : $pseudo_id) . ':' . time();

                if ($user_id > 0) {
                    $result = $manager->award_points($user_id, $points, 'admin_adjustment', $reason, $metadata, $dedupe);
                } else {
                    $result = $manager->award_points_for_pseudo($pseudo_id, $points, 'admin_adjustment', $reason, $metadata, $dedupe);
                }

                if (!($result['success'] ?? false)) {
                    $notice = 'Pont korrekció sikertelen: ' . ($result['error'] ?? 'ismeretlen hiba');
                    $notice_type = 'error';
                } else {
                    $notice = 'Pont korrekció mentve.';
                }
            }
        }
    }

    $query = isset($_GET['q']) ? sanitize_text_field((string) $_GET['q']) : '';
    $row = null;
    if ($query !== '') {
        global $wpdb;
        if (ctype_digit($query)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_points WHERE user_id = %d",
                (int) $query
            ), ARRAY_A);
        }
        if (!$row) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}user_points WHERE pseudo_id = %s",
                $query
            ), ARRAY_A);
        }
    }

    $levels = ['basic', 'bronze', 'silver', 'gold', 'platinum', 'legend'];
    $distribution = array_fill_keys($levels, 0);
    $total = 0;
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT current_level, COUNT(*) AS cnt FROM {$wpdb->prefix}user_points GROUP BY current_level",
        ARRAY_A
    );
    foreach ($rows as $entry) {
        $level = (string) ($entry['current_level'] ?? '');
        $count = (int) ($entry['cnt'] ?? 0);
        if (isset($distribution[$level])) {
            $distribution[$level] = $count;
        }
        $total += $count;
    }

    $badges = [];
    if ($row && !empty($row['pseudo_id']) && function_exists('impact_get_user_badges')) {
        $badges = impact_get_user_badges((string) $row['pseudo_id']);
    }

    echo '<div class="wrap">';
    echo '<h1>Sharity Pontok</h1>';

    if ($notice !== '') {
        echo '<div class="' . esc_attr($notice_type) . '"><p>' . esc_html($notice) . '</p></div>';
    }

    echo '<h2>Keresés</h2>';
    echo '<form method="get" style="margin-bottom:20px;">';
    echo '<input type="hidden" name="page" value="sharity-points-admin" />';
    echo '<input type="text" name="q" value="' . esc_attr($query) . '" placeholder="Pseudo ID vagy user ID" style="min-width:280px;" />';
    echo '<button class="button button-primary" type="submit">Keresés</button>';
    echo '</form>';

    if ($query !== '') {
        if (!$row) {
            echo '<p>Nincs találat.</p>';
        } else {
            echo '<div class="card" style="padding:16px;max-width:900px;">';
            echo '<h3>Találat</h3>';
            echo '<table class="widefat striped" style="max-width:860px;">';
            echo '<tbody>';
            echo '<tr><th>Pseudo ID</th><td>' . esc_html((string) ($row['pseudo_id'] ?? '')) . '</td></tr>';
            echo '<tr><th>User ID</th><td>' . esc_html((string) ($row['user_id'] ?? '')) . '</td></tr>';
            echo '<tr><th>Aktuális szint</th><td>' . esc_html((string) ($row['current_level'] ?? '')) . '</td></tr>';
            echo '<tr><th>Összpont</th><td>' . esc_html(number_format((int) ($row['points_total'] ?? 0), 0, ',', ' ')) . '</td></tr>';
            echo '<tr><th>Élettartam pont</th><td>' . esc_html(number_format((int) ($row['points_lifetime'] ?? 0), 0, ',', ' ')) . '</td></tr>';
            echo '<tr><th>Elavult pont</th><td>' . esc_html(number_format((int) ($row['points_decayed'] ?? 0), 0, ',', ' ')) . '</td></tr>';
            echo '<tr><th>Utolsó aktivitás</th><td>' . esc_html((string) ($row['last_activity_at'] ?? '')) . '</td></tr>';
            echo '<tr><th>Level lock</th><td>' . esc_html((string) ($row['level_locked_until'] ?? '')) . '</td></tr>';
            echo '<tr><th>Freeze</th><td>' . esc_html((string) ($row['freeze_until'] ?? '')) . '</td></tr>';
            echo '</tbody>';
            echo '</table>';

            if (!empty($badges)) {
                echo '<p style="margin-top:12px;"><strong>Jelvények:</strong></p>';
                echo '<ul style="margin-left:18px;">';
                foreach ($badges as $badge) {
                    $key = (string) ($badge['badge_key'] ?? '');
                    $tier = (string) ($badge['tier'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    echo '<li>' . esc_html($key . ($tier !== '' ? ' (' . $tier . ')' : '')) . '</li>';
                }
                echo '</ul>';
            }

            echo '</div>';

            echo '<h3 style="margin-top:24px;">Kézi pont korrekció</h3>';
            echo '<form method="post" style="max-width:420px;">';
            wp_nonce_field('sharity_points_admin_action', 'sharity_points_nonce');
            echo '<input type="hidden" name="sharity_points_action" value="adjust" />';
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) ($row['user_id'] ?? '')) . '" />';
            echo '<input type="hidden" name="pseudo_id" value="' . esc_attr((string) ($row['pseudo_id'] ?? '')) . '" />';
            echo '<p><label>Pont változás (negatív is lehet)</label><br />';
            echo '<input type="number" name="points" required style="width:100%;" /></p>';
            echo '<p><label>Indoklás</label><br />';
            echo '<input type="text" name="reason" style="width:100%;" /></p>';
            echo '<button class="button button-secondary" type="submit">Mentés</button>';
            echo '</form>';
        }
    }

    echo '<h2 style="margin-top:32px;">Szinteloszlás</h2>';
    echo '<div style="max-width:600px;">';
    foreach ($levels as $level) {
        $count = $distribution[$level] ?? 0;
        $percent = $total > 0 ? round(($count / $total) * 100, 2) : 0;
        echo '<div style="margin-bottom:8px;">';
        echo '<strong>' . esc_html(ucfirst($level)) . ':</strong> ' . esc_html($count) . ' (' . esc_html($percent) . '%)';
        echo '<div style="background:#e5e7eb;height:8px;border-radius:4px;overflow:hidden;margin-top:4px;">';
        echo '<div style="background:#0f8a9d;width:' . esc_attr((string) $percent) . '%;height:8px;"></div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';

    echo '</div>';
}
