<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * TODO: amikor a device-registration és updated-pass REST végpontok bekerülnek az
 * impactshop-wallet pluginba, bővítsük a teszteket a REST válaszokra is.
 */
final class WalletPassTest extends TestCase
{
    private function requirePlugin(): void
    {
        if (!impactshop_wallet_plugin_loaded()) {
            $this->markTestSkipped('impactshop-wallet.php is not available in this workspace.');
        }
    }

    public function testBuildPassJsonProducesExpectedPayload(): void
    {
        $this->requirePlugin();

        $item = [
            'amount'    => ['formatted' => '128 000 Ft'],
            'rank'      => 2,
            'share_url' => 'https://example.org/share/demo',
            'cta_url'   => 'https://example.org/cta/demo',
            'pass_type' => 'membership',
            'latitude'  => 47.4925,
            'longitude' => 19.0513,
        ];

        $config = [
            'pass_type_id' => 'pass.com.sharity.demo',
            'team_id'      => 'TEAMID1234',
            'org_name'     => 'Sharity Demo',
        ];

        $ref = new ReflectionMethod(ImpactShop_Wallet_Passes::class, 'build_pass_json');
        $ref->setAccessible(true);
        $payload = $ref->invoke(null, 'bator-tabor', $item, $config);

        $this->assertSame('ngo:bator-tabor', $payload['serialNumber']);
        $this->assertSame('Impact Shop', $payload['logoText']);
        $this->assertSame(
            'https://app.sharity.hu/impactshop/?d1=bator-tabor&ngo=bator-tabor&src=wallet-pass',
            $payload['appLaunchURL']
        );
        $this->assertSame('PKBarcodeFormatQR', $payload['barcode']['format']);
        $this->assertSame('iso-8859-1', $payload['barcode']['messageEncoding']);
        $this->assertSame('Összegyűjtve', $payload['storeCard']['primaryFields'][0]['label']);
    }

    public function testRegisterRoutesRegistersWalletEndpoint(): void
    {
        $this->requirePlugin();
        impactshop_tests_reset_routes();

        ImpactShop_Wallet_Passes::bootstrap();
        do_action('rest_api_init');
        $routes = impactshop_tests_registered_routes();

        $this->assertNotEmpty($routes, 'No REST routes were registered.');

        $match = null;
        foreach ($routes as $route) {
            if (str_contains($route['route'], '/impact/v1/ngo-card/')
                && str_contains($route['route'], 'wallet-pass')) {
                $match = $route;
                break;
            }
        }

        $this->assertNotNull($match, 'Wallet endpoint route was not registered.');
        $this->assertNotEmpty($match['permission_callback']);
        $this->assertSame('__return_true', $match['permission_callback']);
    }

    public function testHandleWalletPassValidatesSlug(): void
    {
        $this->requirePlugin();

        $request = new WP_REST_Request('GET', '/impact/v1/ngo-card//wallet-pass');
        $request->set_param('slug', '');

        $response = ImpactShop_Wallet_Passes::handle_wallet_pass($request);
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame('impactshop_wallet_invalid_slug', $response->get_error_code());
    }
}
