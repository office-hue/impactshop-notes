<?php
/**
 * Plugin Name: ImpactShop Static Pages
 * Description: Serves static HTML pages for NGO guides and partner landing pages.
 * Version: 1.1.2
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Static Pages Server
 * 
 * URLs:
 * - /ngo-guides/              → index (NGO összefoglaló landing page)
 * - /ngo-guides/impact-shop   → Impact Shop útmutató
 * - /ngo-guides/impact-challenge → Impact Challenge útmutató  
 * - /ngo-guides/ngo-card      → NGO Card beágyazás útmutató
 * - /cegeknek                 → Partneri csatlakozás cégeknek
 * - /ngo-guides/jogi-dokumentumok → Jogi dokumentumok
 */

add_action('init', 'impactshop_ngo_guides_rewrite_rules');
add_filter('query_vars', 'impactshop_ngo_guides_query_vars');
add_action('template_redirect', 'impactshop_ngo_guides_template_redirect');

function impactshop_hatas_korok_handled_by_community(): bool
{
    return defined('IMPACT_COMMUNITY_ENABLED') && IMPACT_COMMUNITY_ENABLED;
}

/**
 * Add rewrite rules for /ngo-guides/ and /cegeknek paths
 */
function impactshop_ngo_guides_rewrite_rules(): void
{
    // NGO guides
    add_rewrite_rule(
        '^ngo-guides/?$',
        'index.php?ngo_guide_page=index',
        'top'
    );
    add_rewrite_rule(
        '^ngo-guides/([a-z0-9-]+)/?$',
        'index.php?ngo_guide_page=$matches[1]',
        'top'
    );
    
    // Partner landing page for companies
    add_rewrite_rule(
        '^cegeknek/?$',
        'index.php?ngo_guide_page=cegeknek',
        'top'
    );
    
    // User landing page
    add_rewrite_rule(
        '^rolunk/?$',
        'index.php?ngo_guide_page=rolunk',
        'top'
    );

    if (!impactshop_hatas_korok_handled_by_community()) {
        add_rewrite_rule(
            '^hatas-korok/?$',
            'index.php?ngo_guide_page=hatas-korok',
            'top'
        );
    }
}

/**
 * Register query var
 */
function impactshop_ngo_guides_query_vars(array $vars): array
{
    $vars[] = 'ngo_guide_page';
    return $vars;
}

/**
 * Serve the appropriate guide page
 */
function impactshop_ngo_guides_template_redirect(): void
{
    $page = get_query_var('ngo_guide_page', '');
    
    if (empty($page)) {
        return;
    }

    if ($page === 'impact-activity') {
        wp_redirect(site_url('/ngo-guides/impact-challenge/'), 301);
        exit;
    }

    if ($page === 'hatas-korok' && impactshop_hatas_korok_handled_by_community()) {
        return;
    }
    
    // Map slugs to HTML files
    $pages = [
        'index'           => 'ngo-guides-summary.html', // Summary landing page
        'impact-shop'     => 'impact-shop-ngo.html',
        'impact-challenge' => 'impact-activity-ngo.html',
        'ngo-card'        => 'ngo-card.html',
        'cegeknek'        => 'cegeknek.html',
        'rolunk'          => 'rolunk.html',
        'hatas-korok'     => 'hatas-korok.html',
        'jogi-dokumentumok' => 'jogi-dokumentumok.html',
    ];
    
    // Validate page exists
    if (!isset($pages[$page])) {
        // 404 for unknown pages
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    
    $file = __DIR__ . '/impactshop-ngo-guides/' . $pages[$page];
    
    if (!file_exists($file)) {
        // File not found
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    
    // Serve the HTML file
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: public, max-age=3600'); // 1 hour cache
    
    // Read and output the file
    readfile($file);
    exit;
}

/**
 * Flush rewrite rules on activation
 * (Run once after uploading this plugin)
 */
function impactshop_ngo_guides_activate(): void
{
    impactshop_ngo_guides_rewrite_rules();
    flush_rewrite_rules();
}

// Check if rewrite rules need flushing (first run detection)
add_action('admin_init', function() {
    if (get_option('impactshop_ngo_guides_rules_flushed') !== '1.1.2') {
        impactshop_ngo_guides_activate();
        update_option('impactshop_ngo_guides_rules_flushed', '1.1.2');
    }
});
