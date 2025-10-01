<?php
// SHARITY – Impact Bridge (local config)
// Hely: wp-content/impact-bridge-local.php
// Csak privát beállítások. NE írjon ki semmit.

if (!defined('ABSPATH')) { exit; }

/** Dognet Publisher account (ide jön az e-mail/jelszó) */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');

/** Fix Ad Channel – Sharity: 26081 */
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);

/** Alapok / finomhangolás */
if (!defined('DOGNET_API_BASE'))         define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))        define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))   define('IMPACT_BRIDGE_TIMEOUT', 25);

/** Kompat aliasok (ha másik plugin mást vár) */
if (!defined('DOGNET_EMAIL'))    define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

// NINCS záró "?>"