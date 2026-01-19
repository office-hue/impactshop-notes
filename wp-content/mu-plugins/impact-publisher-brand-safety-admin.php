<?php
/**
 * WHAT: Egyszerű admin UI a brand safety szabályok szerkesztéséhez.
 * WHY: A tiltólisták és whitelist karbantarthatók legyenek DNS/proxy függés nélkül.
 * HOW: Settings API wrapper a wp_options (impact_brand_safety_rules) JSON szerkesztéséhez.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_options_page(
        'Impact Publisher Brand Safety',
        'Impact Brand Safety',
        'manage_options',
        'impact-brand-safety',
        'impact_brand_safety_admin_page'
    );
});

add_action('admin_init', function () {
    register_setting('impact_brand_safety_group', IMPACT_BRAND_SAFETY_OPTION);
});

function impact_brand_safety_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $rules = get_option(IMPACT_BRAND_SAFETY_OPTION, impact_brand_safety_default_rules());
    ?>
    <div class="wrap">
        <h1>Impact Publisher – Brand Safety</h1>
        <p>Locale-alapú tiltólista és CTA domain whitelist. A JSON formátumot módosíthatod, majd mentheted.</p>
        <form method="post" action="options.php">
            <?php settings_fields('impact_brand_safety_group'); ?>
            <?php do_settings_sections('impact_brand_safety_group'); ?>
            <textarea name="<?php echo esc_attr(IMPACT_BRAND_SAFETY_OPTION); ?>" rows="20" style="width:100%;font-family:monospace;"><?php echo esc_textarea(wp_json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
            <?php submit_button('Mentés'); ?>
        </form>
    </div>
    <?php
}
