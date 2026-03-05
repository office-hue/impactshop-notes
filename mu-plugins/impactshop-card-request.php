<?php
/**
 * Plugin Name: ImpactShop Card Request Shortcode
 * Description: Provides a shortcode to collect embed/share pass igénylések (kép + név + videó URL) AJAX alapokon, Fillout nélkül.
 * Author: ImpactShop Ops
 */

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_CARD_REQUEST_NONCE = 'impactshop_card_request';

/**
 * Register shortcode.
 */
function impactshop_register_card_request_shortcode()
{
    add_shortcode('impactshop_card_request', 'impactshop_render_card_request_form');
}
add_action('init', 'impactshop_register_card_request_shortcode');

/**
 * Enqueue JS only when shortcode present.
 */
function impactshop_enqueue_card_request_assets()
{
    if (!is_singular()) {
        return;
    }

    global $post;
    if (!$post || !has_shortcode($post->post_content, 'impactshop_card_request')) {
        return;
    }

    wp_enqueue_script(
        'impactshop-card-request',
        plugin_dir_url(__FILE__) . 'impactshop-card-request.js',
        ['jquery'],
        '20251129',
        true
    );

    wp_localize_script('impactshop-card-request', 'impactshopCardRequest', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce(IMPACTSHOP_CARD_REQUEST_NONCE),
    ]);
}
add_action('wp_enqueue_scripts', 'impactshop_enqueue_card_request_assets');

/**
 * Shortcode renderer.
 */
function impactshop_render_card_request_form($atts = [])
{
    $atts = shortcode_atts([
        'title'       => 'ImpactShop kártya igénylés',
        'description' => 'Töltsd fel a borítóképed, add meg a szervezet/neved és a videónézős URL-t. A kitöltés után e-mailt kap az ImpactShop csapat.',
        'button'      => 'Igénylés elküldése',
    ], $atts, 'impactshop_card_request');

    ob_start();
    ?>
    <section class="impactshop-card-request">
        <h3><?php echo esc_html($atts['title']); ?></h3>
        <p><?php echo esc_html($atts['description']); ?></p>
        <form class="impactshop-card-request__form" enctype="multipart/form-data">
            <div class="impactshop-card-request__row">
                <label>Profil / borítókép (PNG, JPG, WEBP)
                    <input type="file" name="impact_image" accept="image/png,image/jpeg,image/webp" required>
                </label>
            </div>
            <div class="impactshop-card-request__row">
                <label>Szervezet vagy projekt neve
                    <input type="text" name="impact_name" maxlength="120" placeholder="Pl. Patrónus Ház" required>
                </label>
            </div>
            <div class="impactshop-card-request__row">
                <label>Videónézős link (https://)
                    <input type="url" name="impact_video_url" placeholder="https://..." required>
                </label>
            </div>
            <div class="impactshop-card-request__row">
                <label>Kapcsolattartó e-mail (válasz)
                    <input type="email" name="impact_email" placeholder="te@sharity.hu">
                </label>
            </div>
            <div class="impactshop-card-request__row">
                <label>Megjegyzés / üzenet
                    <textarea name="impact_message" rows="3" placeholder="Extra infó a kártya beállításához"></textarea>
                </label>
            </div>
            <div class="impactshop-card-request__row">
                <button type="submit"><?php echo esc_html($atts['button']); ?></button>
                <span class="impactshop-card-request__status" aria-live="polite"></span>
            </div>
        </form>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler – stores upload and küld értesítés e-mailben.
 */
function impactshop_handle_card_request()
{
    check_ajax_referer(IMPACTSHOP_CARD_REQUEST_NONCE, 'nonce');

    $name = isset($_POST['impact_name']) ? sanitize_text_field(wp_unslash($_POST['impact_name'])) : '';
    $video = isset($_POST['impact_video_url']) ? esc_url_raw(wp_unslash($_POST['impact_video_url'])) : '';
    $email = isset($_POST['impact_email']) ? sanitize_email(wp_unslash($_POST['impact_email'])) : '';
    $message = isset($_POST['impact_message']) ? wp_kses_post(wp_unslash($_POST['impact_message'])) : '';

    if (!$name || !$video || empty($_FILES['impact_image'])) {
        wp_send_json_error(['message' => 'Hiányzó kötelező mező (kép / név / videó URL).']);
    }

    if (!filter_var($video, FILTER_VALIDATE_URL)) {
        wp_send_json_error(['message' => 'A videó linkje nem érvényes URL.']);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($_FILES['impact_image']['type'], $allowed, true)) {
        wp_send_json_error(['message' => 'Csak PNG / JPG / WEBP kép tölthető fel.']);
    }

    $upload = wp_handle_upload($_FILES['impact_image'], ['test_form' => false]);
    if (!empty($upload['error'])) {
        wp_send_json_error(['message' => 'A kép feltöltése sikertelen: ' . esc_html($upload['error'])]);
    }

    $admin_email = get_option('impactshop_card_request_email');
    if (!$admin_email) {
        $admin_email = get_option('admin_email');
    }

    $subject = sprintf('[ImpactShop] Új kártya igénylés – %s', $name);
    $body = sprintf(
        "Név: %s\nVideó: %s\nE-mail: %s\nMegjegyzés: %s\nKép: %s",
        $name,
        $video,
        $email ?: '-'
            ,
        $message ?: '-'
            ,
        $upload['url']
    );

    wp_mail($admin_email, $subject, $body);

    wp_send_json_success(['message' => 'Köszönjük! Hamarosan felvesszük veled a kapcsolatot.']);
}
add_action('wp_ajax_impactshop_card_request', 'impactshop_handle_card_request');
add_action('wp_ajax_nopriv_impactshop_card_request', 'impactshop_handle_card_request');
