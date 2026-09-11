<?php

declare(strict_types=1);

namespace Google\Site_Kit\Modules {
    final class AdSense
    {
        public function register_tag(): void
        {
            $GLOBALS['site_kit_register_calls'] = ($GLOBALS['site_kit_register_calls'] ?? 0) + 1;
        }
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
        $registered_hook = $GLOBALS['wp_filter'][$hook] ?? null;
        if (is_object($registered_hook) && is_array($registered_hook->callbacks ?? null)) {
            foreach ($registered_hook->callbacks as $registered_priority => &$callbacks) {
                foreach ($callbacks as $key => $callback_data) {
                    if (($callback_data['function'] ?? null) === $callback && (int) $registered_priority === $priority) {
                        unset($callbacks[$key]);
                    }
                }
            }
            unset($callbacks);
        }
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
    assert_true(!impactshop_identity_filter_profile_adsense_widget_render(true, $adsense_widget), 'exact AdSense widget is suppressed');

    $html_host = new FakeWidget('html', ['content' => ['nested' => 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js']]);
    assert_true(!impactshop_identity_filter_profile_adsense_widget_render(true, $html_host), 'generic HTML widget with AdSense host is suppressed');

    $html_marker = new FakeWidget('html', ['content' => ['nested' => '<ins class="adsbygoogle" data-ad-client="ca-pub-example"></ins>']]);
    assert_true(!impactshop_identity_filter_profile_adsense_widget_render(true, $html_marker), 'generic HTML widget with AdSense marker is suppressed');

    $text_editor_host = new FakeWidget('text-editor', ['editor' => '<script src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>']);
    assert_true(!impactshop_identity_filter_profile_adsense_widget_render(true, $text_editor_host), 'text-editor AdSense producer is suppressed');

    $code_sample = new FakeWidget('html', ['content' => 'Example code: adsbygoogle = window.adsbygoogle || [];']);
    assert_true(impactshop_identity_filter_profile_adsense_widget_render(true, $code_sample), 'explanatory AdSense code sample remains visible');

    $benign_html = new FakeWidget('html', ['content' => '<p>Human Touch</p>']);
    assert_true(impactshop_identity_filter_profile_adsense_widget_render(true, $benign_html), 'benign generic HTML widget remains visible');

    $benign_text_editor = new FakeWidget('text-editor', ['editor' => '<p>Human Touch</p>']);
    assert_true(impactshop_identity_filter_profile_adsense_widget_render(true, $benign_text_editor), 'benign text-editor remains visible');

    $near_miss_widget = new FakeWidget('text-editor', ['content' => 'adsbygoogle']);
    assert_true(impactshop_identity_filter_profile_adsense_widget_render(true, $near_miss_widget), 'text-editor near miss remains visible');

    $_SERVER['REQUEST_URI'] = '/control/?tab=account';
    $removed_before_control = count($GLOBALS['removed_actions']);
    impactshop_identity_profile_remove_site_kit_adsense_tag();
    assert_same($removed_before_control, count($GLOBALS['removed_actions']), 'control route keeps Site Kit callback');

    $control_html = new FakeWidget('html', ['content' => 'adsbygoogle']);
    assert_true(impactshop_identity_filter_profile_adsense_widget_render(true, $control_html), 'control route remains untouched');

    $_SERVER['REQUEST_URI'] = '/profil/?tab=account';
    $registering = new \Google\Site_Kit\Modules\AdSense();
    $GLOBALS['wp_filter'] = [
        'template_redirect' => (object) [
            'callbacks' => [
                10 => [
                    'site-kit-register-tag' => ['function' => [$registering, 'register_tag']],
                ],
            ],
        ],
    ];
    $GLOBALS['site_kit_register_calls'] = 0;
    impactshop_identity_profile_suppress_ads();
    foreach ($GLOBALS['wp_filter']['template_redirect']->callbacks as $callbacks) {
        foreach ($callbacks as $callback_data) {
            ($callback_data['function'])();
        }
    }
    assert_same(0, $GLOBALS['site_kit_register_calls'], 'immediate suppression removes Site Kit before hook execution');

    echo "impactshop profile AdSense suppression: PASS\n";
}
