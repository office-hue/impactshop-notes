<?php
declare(strict_types=1);
// Fresh PHP processes avoid global class state; copy only our existing isolated
// SDK to disposable fixtures. No install, symlinks, provider requests or secrets.
$source = __DIR__ . '/../wp-content/mu-plugins';
$vendor = getenv('SHARITY_DONATION_SDK_VENDOR') ?: __DIR__ . '/../tmp/sharity-donation-sdk/vendor';
if (!is_file($vendor . '/autoload.php')) { throw new RuntimeException('Dedicated lock-built SDK required'); }
$fixture = sys_get_temp_dir() . '/sharity-donation-runtime-' . bin2hex(random_bytes(6));
mkdir($fixture, 0700); mkdir($fixture . '/sharity-ngo-donations', 0700);
function copyTree(string $src, string $dst): void {
    if (is_link($src)) { throw new RuntimeException('Fixture input link rejected'); }
    if (is_dir($src)) { if (!is_dir($dst)) { mkdir($dst,0700,true); } foreach (scandir($src) as $f) { if ($f!=='.' && $f!=='..') { copyTree($src.'/'.$f,$dst.'/'.$f); } } }
    elseif (!copy($src,$dst)) { throw new RuntimeException('Fixture copy failed'); }
}
function removeTree(string $path): void {
    if (is_dir($path) && !is_link($path)) { foreach (scandir($path) as $f) { if ($f!=='.' && $f!=='..') { removeTree($path.'/'.$f); } } rmdir($path); }
    else { unlink($path); }
}
$checks = 0;
try {
    copy($source . '/sharity-ngo-donations.php',$fixture . '/sharity-ngo-donations.php');
    foreach (['http.php','runtime.php','orders.php','stripe-checkout.php','webhook.php','finance-read.php'] as $f) { copy($source.'/sharity-ngo-donations/'.$f,$fixture.'/sharity-ngo-donations/'.$f); }
    $runner = <<<'CHILD'
<?php
$case=$argv[1];$hooks=[];$routes=[];
function add_action($hook,$cb,...$args):void{$GLOBALS['hooks'][$hook][]=$cb;}
function register_rest_route($ns,$path,$args):void{$GLOBALS['routes'][]=$ns.$path;}
function requireTrue($ok,$why):void{if(!$ok)throw new RuntimeException($why);}
if($case==='missing-http'){rename(__DIR__.'/sharity-ngo-donations/http.php',__DIR__.'/sharity-ngo-donations/http.off');}
if($case==='missing-finance'){rename(__DIR__.'/sharity-ngo-donations/finance-read.php',__DIR__.'/sharity-ngo-donations/finance-read.off');}
if($case==='missing-webhook'){rename(__DIR__.'/sharity-ngo-donations/webhook.php',__DIR__.'/sharity-ngo-donations/webhook.off');}
require __DIR__.'/sharity-ngo-donations.php';
requireTrue(!class_exists('Stripe\\StripeClient',false),'main load must not load SDK');
foreach($hooks['plugins_loaded'] as $cb){$cb();}
foreach($hooks['rest_api_init'] as $cb){$cb();}
requireTrue(!class_exists('Stripe\\StripeClient',false),'registration must not load SDK');
requireTrue(in_array('sharity/v1/donations/quote',$routes,true),'quote still registered');
if($case==='missing-http'){
 requireTrue(!function_exists('sharity_donation_order_handle'),'partial install skips new boundary');
 rename(__DIR__.'/sharity-ngo-donations/http.off',__DIR__.'/sharity-ngo-donations/http.php');echo "OK\n";exit;
}
requireTrue(in_array('sharity/v1/donations/checkout',$routes,true),'complete definitions register checkout');
if($case==='missing-finance' || $case==='missing-webhook'){
 requireTrue(in_array('sharity/v1/donations/status/(?P<id>do_[a-f0-9]{32})',$routes,true),'historical status remains');
 if($case==='missing-finance'){requireTrue(in_array('sharity/v1/donations/webhook',$routes,true),'callback remains');rename(__DIR__.'/sharity-ngo-donations/finance-read.off',__DIR__.'/sharity-ngo-donations/finance-read.php');}
 else{requireTrue(function_exists('sharity_donation_cli_payment_inspect'),'finance definitions load without webhook');rename(__DIR__.'/sharity-ngo-donations/webhook.off',__DIR__.'/sharity-ngo-donations/webhook.php');}
 echo "OK\n";exit;
}
requireTrue(function_exists('sharity_donation_cli_payment_inspect'),'finance definitions loaded without SDK');
if(str_starts_with($case,'webhook-')){
 requireTrue(in_array('sharity/v1/donations/webhook',$routes,true),'webhook registered');
 if($case!=='webhook-no-config'){define('SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET','whsec_SYNTHETICLOCALTESTONLY');define('SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE',$case==='webhook-invalid-mode'?'false':false);}
 if($case==='webhook-no-key'){
  sharity_donation_runtime_sdk();$raw=json_encode(['id'=>'evt_SYNTHETIC','object'=>'event','type'=>'charge.succeeded']);
  $sig=Stripe\WebhookSignature::generateSignatureHeader($raw,SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET);
  requireTrue(sharity_donation_webhook_verify($raw,$sig)['id']==='evt_SYNTHETIC','real signature verification without API key');
  requireTrue(!defined('SHARITY_DONATIONS_STRIPE_KEY'),'no API key needed');
 }else{
  try{sharity_donation_webhook_verify('{}','synthetic');throw new RuntimeException('configuration accepted');}
  catch(RuntimeException $e){requireTrue($e->getMessage()==='WEBHOOK_CONFIG_UNAVAILABLE' && $e->getPrevious()===null,'missing/bad config fails safely');}
 }
 echo "OK\n";exit;
}
if($case!=='missing-key'){define('SHARITY_DONATIONS_STRIPE_KEY',$case==='invalid-key'?'invalid':'rk_test_SYNTHETICNOTACREDENTIAL');}
if($case==='foreign-class'){eval('namespace Stripe; class Stripe {}');}
try{
 $adapter=sharity_donation_runtime_adapter();
 requireTrue($case==='success','unavailable runtime should fail');
 requireTrue($adapter instanceof Sharity_Stripe_Checkout_Adapter,'real SDK adapter created');
 requireTrue(Stripe\Stripe::VERSION==='22.1.0','pinned version');
 requireTrue(Stripe\Stripe::getApiKey()===null,'no global key mutation');
}catch(RuntimeException $e){
 requireTrue($case!=='success','valid runtime failed');
 requireTrue($e->getMessage()==='DONATION_RUNTIME_UNAVAILABLE' && $e->getPrevious()===null,'sanitized error without chain');
}
echo "OK\n";
CHILD;
    file_put_contents($fixture.'/run.php',$runner);
    $run = static function(string $case) use($fixture,&$checks):void {
        $out=[];$code=0;exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($fixture.'/run.php').' '.escapeshellarg($case).' 2>&1',$out,$code);
        if($code!==0 || $out!==['OK']){throw new RuntimeException('Runtime case '.$case.' failed: '.implode("\n",$out));}$checks++;
    };
    $run('missing-sdk');$run('missing-http');$run('missing-finance');$run('missing-webhook');
    $dest=$fixture.'/sharity-ngo-donations/sdk/stripe-22.1.0/vendor';copyTree($vendor,$dest);
    foreach(['webhook-no-config','webhook-invalid-mode','webhook-no-key','missing-key','invalid-key','foreign-class','success'] as $case){$run($case);}
    $stripeFile=$dest.'/stripe/stripe-php/lib/Stripe.php';$original=file_get_contents($stripeFile);
    if(!str_contains($original,"const VERSION = '22.1.0'")){throw new RuntimeException('SDK version fixture not found');}
    file_put_contents($stripeFile,str_replace("const VERSION = '22.1.0'","const VERSION = '0.0.0'",$original));$run('wrong-version');
    echo "PASS donation runtime {$checks} fresh-process scenarios; pinned local SDK, no provider calls\n";
} finally { removeTree($fixture); }
