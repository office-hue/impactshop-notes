<?php
/**
 * Plugin Name: ImpactShop Static Pages
 * Description: Serves static HTML pages for NGO guides and partner landing pages.
 * Version: 1.1.3
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
 * - /jysk-riport              → JYSK kampányriport
 * - /jysk-riport.data.json    → JYSK riport nyers adatcsomag
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

    // JYSK campaign report
    add_rewrite_rule(
        '^jysk-riport/?$',
        'index.php?ngo_guide_page=jysk-riport',
        'top'
    );
    add_rewrite_rule(
        '^jysk-riport\\.data\\.json/?$',
        'index.php?ngo_guide_page=jysk-riport-data',
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
 * Resolve static page metadata for known routes.
 *
 * @return array{file:string,content_type:string,cache_control:string}|null
 */
function impactshop_ngo_guides_page_meta(string $page): ?array
{
    $pages = [
        'index' => [
            'file' => 'ngo-guides-summary.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'impact-shop' => [
            'file' => 'impact-shop-ngo.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'impact-challenge' => [
            'file' => 'impact-activity-ngo.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'ngo-card' => [
            'file' => 'ngo-card.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'cegeknek' => [
            'file' => 'cegeknek.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'rolunk' => [
            'file' => 'rolunk.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'hatas-korok' => [
            'file' => 'hatas-korok.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'jogi-dokumentumok' => [
            'file' => 'jogi-dokumentumok.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=3600',
        ],
        'jysk-riport' => [
            'file' => 'jysk-riport.html',
            'content_type' => 'text/html; charset=UTF-8',
            'cache_control' => 'public, max-age=900',
        ],
        'jysk-riport-data' => [
            'file' => 'jysk-riport.data.json',
            'content_type' => 'application/json; charset=UTF-8',
            'cache_control' => 'public, max-age=900',
        ],
    ];

    return $pages[$page] ?? null;
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
    
    $page_meta = impactshop_ngo_guides_page_meta($page);

    if ($page_meta === null) {
        // 404 for unknown pages
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }

    $file = __DIR__ . '/impactshop-ngo-guides/' . $page_meta['file'];
    
    if (!file_exists($file)) {
        // File not found
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    
    global $wp_query;
    if (isset($wp_query) && is_object($wp_query)) {
        $wp_query->is_404 = false;
    }

    status_header(200);
    header('Content-Type: ' . $page_meta['content_type']);
    header('Cache-Control: ' . $page_meta['cache_control']);
    
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
    if (get_option('impactshop_ngo_guides_rules_flushed') !== '1.1.3') {
        impactshop_ngo_guides_activate();
        update_option('impactshop_ngo_guides_rules_flushed', '1.1.3');
    }
});
