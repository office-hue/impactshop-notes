<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('sharity_level_changed', 'sharity_points_notify_level_change', 10, 4);
add_action('sharity_decay_warning', 'sharity_points_notify_decay_warning', 10, 2);
add_action('sharity_decay_warning_pseudo', 'sharity_points_notify_decay_warning_pseudo', 10, 2);

function sharity_points_notify_level_change($subject, string $old_level, string $new_level, int $points_total): void
{
    $pseudo_id = sharity_points_notification_subject_to_pseudo($subject);
    if ($pseudo_id === '') {
        return;
    }

    $order = ['basic' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4, 'legend' => 5];
    $old_rank = $order[$old_level] ?? 0;
    $new_rank = $order[$new_level] ?? 0;
    $direction = $new_rank <=> $old_rank;

    if ($direction >= 0) {
        $content = 'Szintfrissítés: gratulálunk, elérted a ' . ucfirst($new_level) . ' szintet!';
    } else {
        $content = 'Szintfrissítés: a szinted ' . ucfirst($old_level) . ' → ' . ucfirst($new_level) . ' lett. Aktivitas segit visszaerositeni.';
    }

    sharity_points_insert_targeted_message($pseudo_id, $content, 'Szintfrissítés:', 7);
}

function sharity_points_notify_decay_warning(int $user_id, int $points_total): void
{
    $pseudo_id = sharity_points_notification_subject_to_pseudo($user_id);
    if ($pseudo_id === '') {
        return;
    }
    sharity_points_insert_targeted_message(
        $pseudo_id,
        'Inaktivitás figyelmeztetés: 5 napja nem volt aktivitásod. Ha folytatod, a pontjaid csökkenhetnek.',
        'Inaktivitás figyelmeztetés:',
        3
    );
}

function sharity_points_notify_decay_warning_pseudo(string $pseudo_id, int $points_total): void
{
    $pseudo_id = sharity_points_notification_subject_to_pseudo($pseudo_id);
    if ($pseudo_id === '') {
        return;
    }
    sharity_points_insert_targeted_message(
        $pseudo_id,
        'Inaktivitás figyelmeztetés: 5 napja nem volt aktivitásod. Ha folytatod, a pontjaid csökkenhetnek.',
        'Inaktivitás figyelmeztetés:',
        3
    );
}

function sharity_points_notification_subject_to_pseudo($subject): string
{
    if (is_string($subject) && preg_match('/^[a-z0-9]{10,12}$/', $subject)) {
        return strtolower($subject);
    }

    $user_id = (int) $subject;
    if ($user_id <= 0) {
        return '';
    }

    global $wpdb;
    $pseudo = $wpdb->get_var($wpdb->prepare(
        "SELECT pseudo_id FROM {$wpdb->prefix}user_points WHERE user_id = %d",
        $user_id
    ));

    return is_string($pseudo) ? strtolower($pseudo) : '';
}

function sharity_points_insert_targeted_message(string $pseudo_id, string $content, string $prefix, int $ttl_days): void
{
    global $wpdb;

    $messages_table = $wpdb->prefix . 'impact_vote_messages';
    $targets_table = $wpdb->prefix . 'impact_vote_message_targets';

    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $messages_table));
    if (!$table_exists) {
        return;
    }

    $since = gmdate('Y-m-d H:i:s', strtotime('-' . max(1, $ttl_days) . ' days'));
    $existing = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$messages_table} m
         INNER JOIN {$targets_table} t ON t.message_id = m.id
         WHERE t.pseudo_id = %s
           AND m.type = 'targeted'
           AND m.content LIKE %s
           AND m.start_at >= %s",
        $pseudo_id,
        $wpdb->esc_like($prefix) . '%',
        $since
    ));

    if ($existing > 0) {
        return;
    }

    $start_at = current_time('mysql');
    $end_at = gmdate('Y-m-d H:i:s', strtotime('+' . max(1, $ttl_days) . ' days'));

    $wpdb->insert($messages_table, [
        'type' => 'targeted',
        'content' => $content,
        'start_at' => $start_at,
        'end_at' => $end_at,
        'priority' => 10,
        'created_at' => current_time('mysql'),
    ], ['%s', '%s', '%s', '%s', '%d', '%s']);

    $message_id = (int) $wpdb->insert_id;
    if ($message_id <= 0) {
        return;
    }

    $wpdb->insert($targets_table, [
        'message_id' => $message_id,
        'pseudo_id' => $pseudo_id,
        'is_read' => 0,
        'read_at' => null,
    ], ['%d', '%s', '%d', '%s']);
}
