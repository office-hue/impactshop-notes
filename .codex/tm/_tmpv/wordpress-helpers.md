# WordPress Development Helpers

## Gyors kód generálás

### Plugin fejléc template
```php
<?php
/**
 * Plugin Name: [Név]
 * Description: [Leírás]
 * Version: 1.0
 * Author: [Te]
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
```

### Shortcode template
```php
function my_shortcode_function($atts) {
    $atts = shortcode_atts(array(
        'param1' => 'default_value',
        'param2' => 'default_value'
    ), $atts);
    
    // Kód logic
    
    return $output;
}
add_shortcode('my_shortcode', 'my_shortcode_function');
```

### Hook template
```php
// Action hook
function my_custom_function() {
    // Kód
}
add_action('hook_name', 'my_custom_function');

// Filter hook
function my_filter_function($value) {
    // Módosítások
    return $value;
}
add_filter('filter_name', 'my_filter_function');
```

## WordPress debugging
```php
// Debug log írás
error_log('Debug info: ' . print_r($data, true));

// WordPress debug
if (WP_DEBUG) {
    error_log('Debug mode active');
}
```

## Gyakori WordPress függvények
- `get_option()` / `update_option()` - beállítások
- `wp_enqueue_script()` / `wp_enqueue_style()` - JS/CSS betöltés
- `wp_remote_get()` / `wp_remote_post()` - HTTP kérések
- `sanitize_text_field()` - input tisztítás
- `wp_nonce_field()` / `wp_verify_nonce()` - biztonság