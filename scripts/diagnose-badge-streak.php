<?php
/**
 * Badge streak diagnosztika
 * 
 * Használat: wp eval-file diagnose-badge-streak.php <pseudo_id>
 * 
 * Megvizsgálja:
 * 1. User stats (streak_days, last_view_day)
 * 2. User badges (milyen badge-ek vannak már)
 * 3. Ads view history (utolsó napok aktivitása)
 */

$pseudo_id = $args[0] ?? '9mnx6wqfkhr9';

echo "=== Badge Streak Diagnosztika ===\n";
echo "Pseudo ID: {$pseudo_id}\n\n";

global $wpdb;

// 1. User Stats ellenőrzése
echo "--- 1. User Stats ---\n";
$table_stats = $wpdb->prefix . 'impactshop_ads_user_stats';
$stats = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_stats} WHERE pseudo_id = %s",
    $pseudo_id
), ARRAY_A);

if ($stats) {
    echo "total_views: {$stats['total_views']}\n";
    echo "total_votes: {$stats['total_votes']}\n";
    echo "streak_days: {$stats['streak_days']}\n";
    echo "last_view_day: {$stats['last_view_day']}\n";
    echo "updated_at: " . ($stats['updated_at'] ?? 'N/A') . "\n";
} else {
    echo "NINCS USER STATS REKORD!\n";
}

// 2. User Badges ellenőrzése
echo "\n--- 2. Meglévő Badge-ek ---\n";
$table_badges = $wpdb->prefix . 'impact_user_badges';
$badges = $wpdb->get_results($wpdb->prepare(
    "SELECT badge_key, tier, awarded_at, source FROM {$table_badges} WHERE pseudo_id = %s ORDER BY awarded_at DESC",
    $pseudo_id
), ARRAY_A);

if ($badges) {
    foreach ($badges as $badge) {
        echo "  - {$badge['badge_key']} ({$badge['tier']}) | {$badge['awarded_at']} | source: {$badge['source']}\n";
    }
    
    // Streak badge-ek keresése
    $streak_badges = array_filter($badges, fn($b) => str_starts_with($b['badge_key'], 'streak_'));
    if (empty($streak_badges)) {
        echo "\n  ⚠️ NINCS STREAK BADGE!\n";
    }
} else {
    echo "NINCSENEK BADGE-EK!\n";
}

// 3. Aktivitás history (utolsó 7 nap views)
echo "\n--- 3. Utolsó 7 nap aktivitás ---\n";
$table_views = $wpdb->prefix . 'impactshop_ads_views';
$views = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(viewed_at) as day, COUNT(*) as cnt 
     FROM {$table_views} 
     WHERE pseudo_id = %s 
       AND viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(viewed_at)
     ORDER BY day DESC",
    $pseudo_id
), ARRAY_A);

if ($views) {
    foreach ($views as $v) {
        echo "  {$v['day']}: {$v['cnt']} view\n";
    }
    
    // Egymást követő napok ellenőrzése
    $days = array_column($views, 'day');
    $consecutive = 0;
    $prev = null;
    foreach ($days as $day) {
        if ($prev === null) {
            $consecutive = 1;
            $prev = $day;
            continue;
        }
        $expected = date('Y-m-d', strtotime($prev . ' -1 day'));
        if ($day === $expected) {
            $consecutive++;
            $prev = $day;
        } else {
            break;
        }
    }
    echo "\n  📊 Egymást követő napok (mostantól visszafelé): {$consecutive}\n";
} else {
    echo "NINCS VIEW AZ UTOLSÓ 7 NAPBAN!\n";
}

// 4. Badge award próba
echo "\n--- 4. Badge Award Ellenőrzés ---\n";
$streak = (int) ($stats['streak_days'] ?? 0);
if ($streak >= 3) {
    echo "✅ streak_days >= 3, jogosult a streak_3 badge-re\n";
    
    // Ellenőrizzük, hogy megvan-e már
    $has_streak_3 = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_badges} WHERE pseudo_id = %s AND badge_key = 'streak_3'",
        $pseudo_id
    ));
    
    if ($has_streak_3 > 0) {
        echo "  ✅ A streak_3 badge MEGVAN\n";
    } else {
        echo "  ⚠️ A streak_3 badge HIÁNYZIK - valami elromlott az award során!\n";
        
        // Próbáljuk manuálisan odaadni
        echo "\n  🔧 Manuális award próba...\n";
        if (function_exists('impact_award_badge')) {
            $result = impact_award_badge($pseudo_id, 'streak_3', 'bronze', 'manual_fix');
            echo "  Eredmény: " . ($result ? "SIKERES" : "Már megvolt vagy hiba") . "\n";
        } else {
            echo "  impact_award_badge() nem elérhető\n";
        }
    }
} else {
    echo "❌ streak_days = {$streak}, még nem érte el a 3-at\n";
}

echo "\n=== Diagnosztika vége ===\n";
