<?php

declare(strict_types=1);

namespace Google\Site_Kit\Modules {
    final class AdSense
    {
        public function register_tag(): void {}
    }
}

namespace {
    define('ABSPATH', __DIR__ . '/');

    $GLOBALS['added_actions'] = [];
    $GLOBALS['removed_actions'] = [];
    $GLOBALS['test_home_path'] = '';

    function add_action(...$args): void
    {
        $GLOBALS['added_actions'][] = $args;
    }

    function add_filter(...$args): void {}
    function add_shortcode(...$args): void {}

    function remove_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['removed_actions'][] = [$hook, $callback, $priority, $accepted_args];
        return true;
    }

    function wp_parse_url(string $url, int $component = -1)
    {
        return parse_url($url, $component);
    }

    function home_url(string $path = '/'): string
    {
        return 'https://app.sharity.hu' . $GLOBALS['test_home_path'] . ltrim($path, '/');
    }

    function untrailingslashit(string $value): string
    {
        return rtrim($value, '/');
    }

    final class OtherAdsenseLike
    {
        public function register_tag(): void {}
    }

    final class FakeWidget
    {
        private string $name;
        private array $settings;
        public bool $should_render = true;

        public function __construct(string $name, array $settings = [])
        {
            $this->name = $name;
            $this->settings = $settings;
        }

        public function get_name(): string
        {
            return $this->name;
        }

        public function get_settings_for_display(): array
        {
            return $this->settings;
        }

        public function set_should_render(bool $should_render): void
        {
            $this->should_render = $should_render;
        }
    }

    require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-panel.php';

    function assert_true(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function assert_same(int $expected, int $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message . " expected={$expected} actual={$actual}");
        }
    }

    $_SERVER['REQUEST_URI'] = '/profil/?tab=account';
    impactshop_identity_profile_suppress_ads();
    $late_removal_queued = array_filter(
        $GLOBALS['added_actions'],
        static fn (array $args): bool => ($args[0] ?? null) === 'template_redirect'
            && ($args[1] ?? null) === 'impactshop_identity_profile_remove_site_kit_adsense_tag'
            && ($args[2] ?? null) === PHP_INT_MAX
    );
    assert_true($late_removal_queued !== [], 'profile suppression queues late Site Kit producer removal');

    $exact = new \Google\Site_Kit\Modules\AdSense();
    $other = new OtherAdsenseLike();
    $GLOBALS['wp_filter'] = [
        'template_redirect' => (object) [
            'callbacks' => [
                10 => [
                    'exact' => ['function' => [$exact, 'register_tag']],
                    'other' => ['function' => [$other, 'register_tag']],
                    'static' => ['function' => ['Google\\Site_Kit\\Modules\\AdSense', 'register_tag']],
                ],
            ],
        ],
    ];
    $GLOBALS['removed_actions'] = [];
    impactshop_identity_profile_remove_site_kit_adsense_tag();
    assert_same(1, count($GLOBALS['removed_actions']), 'only exact Site Kit callback is removed');
    assert_same(10, $GLOBALS['removed_actions'][0][2], 'Site Kit callback priority is preserved');
    assert_true($GLOBALS['removed_actions'][0][1][0] === $exact, 'removed callback is exact Site Kit object');

    $adsense_widget = new FakeWidget('adsense');
    impactshop_identity_suppress_profile_adsense_widget($adsense_widget);
    assert_true(!$adsense_widget->should_render, 'exact AdSense widget is suppressed');

    $html_host = new FakeWidget('html', ['content' => ['nested' => 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js']]);
    impactshop_identity_suppress_profile_adsense_widget($html_host);
    assert_true(!$html_host->should_render, 'generic HTML widget with AdSense host is suppressed');

    $html_marker = new FakeWidget('html', ['content' => ['nested' => '(adsbygoogle = window.adsbygoogle || [])']]);
    impactshop_identity_suppress_profile_adsense_widget($html_marker);
    assert_true(!$html_marker->should_render, 'generic HTML widget with AdSense marker is suppressed');

    $benign_html = new FakeWidget('html', ['content' => '<p>Human Touch</p>']);
    impactshop_identity_suppress_profile_adsense_widget($benign_html);
    assert_true($benign_html->should_render, 'benign generic HTML widget remains visible');

    $near_miss_widget = new FakeWidget('text-editor', ['content' => 'adsbygoogle']);
    impactshop_identity_suppress_profile_adsense_widget($near_miss_widget);
    assert_true($near_miss_widget->should_render, 'non-HTML widget near miss remains visible');

    $_SERVER['REQUEST_URI'] = '/control/?tab=account';
    $removed_before_control = count($GLOBALS['removed_actions']);
    impactshop_identity_profile_remove_site_kit_adsense_tag();
    assert_same($removed_before_control, count($GLOBALS['removed_actions']), 'control route keeps Site Kit callback');

    $control_html = new FakeWidget('html', ['content' => 'adsbygoogle']);
    impactshop_identity_suppress_profile_adsense_widget($control_html);
    assert_true($control_html->should_render, 'control route remains untouched');

    echo "impactshop profile AdSense suppression: PASS\n";
}
