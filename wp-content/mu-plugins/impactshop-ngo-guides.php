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
 * - /befektetoknek            → Befektetői landing page
 * - /ngo-guides/jogi-dokumentumok → Jogi dokumentumok
 */

add_action('init', 'impactshop_ngo_guides_rewrite_rules');
add_action('init', 'impactshop_ngo_guides_register_sitemap', 20);
add_filter('query_vars', 'impactshop_ngo_guides_query_vars');
add_action('template_redirect', 'impactshop_ngo_guides_template_redirect');
add_filter('wpseo_sitemap_index', 'impactshop_ngo_guides_add_sitemap_to_index');

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

    add_rewrite_rule(
        '^befektetoknek/?$',
        'index.php?ngo_guide_page=befektetoknek',
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
    $vars[] = 'lang';
    return $vars;
}

function impactshop_ngo_guides_current_lang(): string
{
    $lang = sanitize_key((string) get_query_var('lang', ''));

    return $lang === 'en' ? 'en' : 'hu';
}

function impactshop_ngo_guides_public_path(string $page): string
{
    $paths = [
        'index' => '/ngo-guides/',
        'impact-shop' => '/ngo-guides/impact-shop/',
        'impact-challenge' => '/ngo-guides/impact-challenge/',
        'ngo-card' => '/ngo-guides/ngo-card/',
        'cegeknek' => '/cegeknek/',
        'befektetoknek' => '/befektetoknek/',
        'partner-api' => '/partner-api/',
        'rolunk' => '/rolunk/',
        'hatas-korok' => '/hatas-korok/',
        'jogi-dokumentumok' => '/ngo-guides/jogi-dokumentumok/',
    ];

    return $paths[$page] ?? '/';
}

function impactshop_ngo_guides_public_url(string $page, string $lang = 'hu'): string
{
    $path = impactshop_ngo_guides_public_path($page);
    $url = site_url($path);

    if ($lang === 'en') {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'lang=en';
    }

    return $url;
}

function impactshop_ngo_guides_resolve_file(string $filename, string $lang): string
{
    if ($lang !== 'en') {
        return __DIR__ . '/impactshop-ngo-guides/' . $filename;
    }

    $localized = preg_replace('/\.html$/', '-en.html', $filename);
    $localizedFile = __DIR__ . '/impactshop-ngo-guides/' . $localized;

    if (is_string($localized) && file_exists($localizedFile)) {
        return $localizedFile;
    }

    return __DIR__ . '/impactshop-ngo-guides/' . $filename;
}

function impactshop_ngo_guides_inject_head_links(string $html, string $page, string $lang): string
{
    $canonical = esc_url(impactshop_ngo_guides_public_url($page, $lang));
    $hu = esc_url(impactshop_ngo_guides_public_url($page, 'hu'));
    $en = esc_url(impactshop_ngo_guides_public_url($page, 'en'));

    $html = preg_replace(
        '~<meta\s+property=["\']og:url["\']\s+content=["\'][^"\']*["\']\s*/?>~i',
        '<meta property="og:url" content="' . $canonical . '">',
        $html,
        1
    ) ?? $html;

    $headLinks = "\n"
        . '  <link rel="canonical" href="' . $canonical . '">' . "\n"
        . '  <link rel="alternate" hreflang="hu" href="' . $hu . '">' . "\n"
        . '  <link rel="alternate" hreflang="en" href="' . $en . '">' . "\n"
        . '  <link rel="alternate" hreflang="x-default" href="' . $hu . '">' . "\n";

    if (str_contains($html, '</head>')) {
        return str_replace('</head>', $headLinks . '</head>', $html);
    }

    return $html;
}

function impactshop_ngo_guides_sitemap_pages(): array
{
    $pages = [
        'index',
        'impact-shop',
        'impact-challenge',
        'ngo-card',
        'cegeknek',
        'befektetoknek',
        'partner-api',
        'rolunk',
        'jogi-dokumentumok',
    ];

    if (!impactshop_hatas_korok_handled_by_community()) {
        $pages[] = 'hatas-korok';
    }

    return $pages;
}

function impactshop_ngo_guides_page_filename(string $page): ?string
{
    $pages = [
        'index' => 'ngo-guides-summary.html',
        'impact-shop' => 'impact-shop-ngo.html',
        'impact-challenge' => 'impact-activity-ngo.html',
        'ngo-card' => 'ngo-card.html',
        'cegeknek' => 'cegeknek.html',
        'befektetoknek' => 'befektetoknek.html',
        'rolunk' => 'rolunk.html',
        'hatas-korok' => 'hatas-korok.html',
        'jogi-dokumentumok' => 'jogi-dokumentumok.html',
    ];

    return $pages[$page] ?? null;
}

function impactshop_ngo_guides_sitemap_file(string $page, string $lang): ?string
{
    if ($page === 'partner-api') {
        $filename = $lang === 'en' ? 'partner-docs-en.html' : 'partner-docs.html';
        $file = trailingslashit(ABSPATH) . $filename;

        return file_exists($file) ? $file : null;
    }

    $filename = impactshop_ngo_guides_page_filename($page);
    if ($filename === null) {
        return null;
    }

    $file = impactshop_ngo_guides_resolve_file($filename, $lang);

    return file_exists($file) ? $file : null;
}

function impactshop_ngo_guides_sitemap_lastmod(string $page, string $lang): string
{
    $fallback = gmdate('c');
    $file = impactshop_ngo_guides_sitemap_file($page, $lang);
    if ($file === null) {
        return $fallback;
    }

    $mtime = filemtime($file);
    if ($mtime === false) {
        return $fallback;
    }

    return gmdate('c', $mtime);
}

function impactshop_ngo_guides_sitemap_url(): string
{
    return home_url('/impactshop-static-sitemap.xml');
}

function impactshop_ngo_guides_register_sitemap(): void
{
    global $wpseo_sitemaps;

    if (!isset($wpseo_sitemaps) || empty($wpseo_sitemaps) || !method_exists($wpseo_sitemaps, 'register_sitemap')) {
        return;
    }

    $wpseo_sitemaps->register_sitemap('impactshop-static', 'impactshop_ngo_guides_render_sitemap');
}

function impactshop_ngo_guides_add_sitemap_to_index(string $sitemap_index): string
{
    $lastmods = [];
    foreach (impactshop_ngo_guides_sitemap_pages() as $page) {
        $lastmods[] = impactshop_ngo_guides_sitemap_lastmod($page, 'hu');
        $lastmods[] = impactshop_ngo_guides_sitemap_lastmod($page, 'en');
    }

    $lastmod = max($lastmods);
    $entry = "\n\t<sitemap>\n\t\t<loc>" . esc_url(impactshop_ngo_guides_sitemap_url()) . "</loc>\n\t\t<lastmod>"
        . esc_html($lastmod) . "</lastmod>\n\t</sitemap>";

    if (str_contains($sitemap_index, impactshop_ngo_guides_sitemap_url())) {
        return $sitemap_index;
    }

    return $sitemap_index . $entry;
}

function impactshop_ngo_guides_render_sitemap(): void
{
    global $wpseo_sitemaps;

    $items = [];
    foreach (impactshop_ngo_guides_sitemap_pages() as $page) {
        foreach (['hu', 'en'] as $lang) {
            $url = impactshop_ngo_guides_public_url($page, $lang);
            $items[] = "\t<url>\n"
                . "\t\t<loc>" . esc_url($url) . "</loc>\n"
                . "\t\t<lastmod>" . esc_html(impactshop_ngo_guides_sitemap_lastmod($page, $lang)) . "</lastmod>\n"
                . "\t</url>";
        }
    }

    $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $xml .= implode("\n", $items) . "\n";
    $xml .= '</urlset>';

    if (isset($wpseo_sitemaps) && method_exists($wpseo_sitemaps, 'set_sitemap')) {
        $wpseo_sitemaps->set_sitemap($xml);
        return;
    }

    header('Content-Type: application/xml; charset=UTF-8');
    echo $xml;
}

/**
 * Serve the appropriate guide page
 */
function impactshop_ngo_guides_template_redirect(): void
{
    $page = get_query_var('ngo_guide_page', '');
    $lang = impactshop_ngo_guides_current_lang();
    
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
        'befektetoknek'   => 'befektetoknek.html',
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
    
    $file = impactshop_ngo_guides_resolve_file($pages[$page], $lang);
    
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
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=(), usb=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");

    $html = file_get_contents($file);
    if ($html === false) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }

    echo impactshop_ngo_guides_inject_head_links($html, $page, $lang);
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
