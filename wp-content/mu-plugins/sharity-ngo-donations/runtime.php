<?php
/** Lazy, pinned donation SDK. No provider call or credential read on load/status.
 * Release installs the lock-built artifact at sdk/stripe-22.1.0/vendor before
 * this loader. Never install Composer on production or borrow another vendor.
 */
declare(strict_types=1);

function sharity_donation_runtime_sdk(): void
{
    try {
        $vendor = __DIR__ . '/sdk/stripe-22.1.0/vendor';
        $root = realpath($vendor . '/stripe/stripe-php/lib');
        if ($root === false || !is_file($vendor . '/autoload.php')) {
            throw new RuntimeException();
        }
        // WordPress plugins may already have loaded another Stripe version.
        // Do not replace it or mix classes from two vendors in one request.
        $checkClasses = static function () use ($root): void {
            foreach (array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()) as $class) {
                if (strncasecmp($class, 'Stripe\\', 7) !== 0) { continue; }
                $file = (new ReflectionClass($class))->getFileName();
                $path = is_string($file) ? realpath($file) : false;
                if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) { throw new RuntimeException(); }
            }
        };
        $checkClasses();
        require_once $vendor . '/autoload.php';
        if (!class_exists(Stripe\StripeClient::class) || !class_exists(Stripe\Stripe::class)
            || Stripe\Stripe::VERSION !== Sharity_Stripe_Checkout_Adapter::SDK_VERSION) { throw new RuntimeException(); }
        if (!class_exists(Stripe\Webhook::class) || !class_exists(Stripe\WebhookSignature::class)) { throw new RuntimeException(); }
        $checkClasses();
    } catch (Throwable $error) {
        // Never retain the original exception: it may include credentials/paths.
        throw new RuntimeException('DONATION_RUNTIME_UNAVAILABLE');
    }
}

function sharity_donation_runtime_adapter(): Sharity_Stripe_Checkout_Adapter
{
    sharity_donation_runtime_sdk();
    try {
        if (!defined('SHARITY_DONATIONS_STRIPE_KEY') || !is_string(SHARITY_DONATIONS_STRIPE_KEY)) { throw new RuntimeException(); }
        return new Sharity_Stripe_Checkout_Adapter(Sharity_Stripe_Checkout_Adapter::client(SHARITY_DONATIONS_STRIPE_KEY));
    } catch (Throwable $error) { throw new RuntimeException('DONATION_RUNTIME_UNAVAILABLE'); }
}
