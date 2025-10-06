<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'sharityh_wp1' );

/** MySQL database username */
define( 'DB_USER', 'sharityh_wp1' );

/** MySQL database password */
define( 'DB_PASSWORD', 'D.TQ4BuREbirMe8MQJF59' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'QIelDLqqllPOiJthXsa3I3914NrJaQhOsHFzNVuTl4OJGXX94Jx3PgklAmEktsU6');
define('SECURE_AUTH_KEY',  'iM4osrrOKHF0mu5P8W958ZuJ4rf4ZE2NVQCimlw2Uv5TlGmFxdulFOZXPs2LFKWk');
define('LOGGED_IN_KEY',    'Y5G6QJfFZxLlh2cCq24iIVk8EpatBZD7RlUHhT6mlLFN85lKO056XFwhKPD4j4RV');
define('NONCE_KEY',        'f6z8dOXj6yDbBJ6sNQOzBsjNLGe5YjqQf4PJSmxbDUEyQx8V3GByBBxlgiEolI8w');
define('AUTH_SALT',        'nZMk6PtVAtQt7Rd16KbwsJZJUwjdcucYOpbhGCM5kRBlPGU3R1SF8S1HeyrCRqPI');
define('SECURE_AUTH_SALT', 'cnP8dMT9wGXYVxbr6boanILd9ogQhH2tAojoWyYj28JcTIpfzSQBnms6KXtV1TeV');
define('LOGGED_IN_SALT',   'ZnGve4xQvQ3tDCwWPukLbfzXGEBLADrkxe91wt07Pe2lXyzwCvK1gaKfVC2O6DXZ');
define('NONCE_SALT',       'B7vIOR6IcIm4BSCkOGc7fQKqHNEn7Da5dfiveY1kJJYzT1A2mY0yj0Rlwleq1ma8');

/**
 * Other customizations.
 */
define('FS_METHOD','direct');
define('FS_CHMOD_DIR',0755);
define('FS_CHMOD_FILE',0644);
define('WP_TEMP_DIR',dirname(__FILE__).'/wp-content/uploads');

/**
 * Turn off automatic updates since these are managed externally by Installatron.
 * If you remove this define() to re-enable WordPress's automatic background updating
 * then it's advised to disable auto-updating in Installatron.
 */
define('AUTOMATIC_UPDATER_DISABLED', true);

/** WordPress Database Table prefix. */
$table_prefix = 'wp_';

/** For developers: WordPress debugging mode. */
define( 'WP_DEBUG', true );

/** ==============================
 * Impact Shop / Dognet beállítások
 * (Ezt a blokkot a "That's all..." sor ELÉ tesszük!)
 * =============================== */
define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
define('DOGNET_API_TOKEN', ''); // üres = auto-login
define('DOGNET_AD_CHANNEL_ID', 0);
// define('WP_HTTP_BLOCK_EXTERNAL', true);
// define('WP_ACCESSIBLE_HOSTS', 'api.app.dognet.com');
if (!defined('DISALLOW_FILE_EDIT')) { define('DISALLOW_FILE_EDIT', true); }
/** ============================== */
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);   // vagy hagyhatod true-n, ha szereted a logot
define('WP_DEBUG_DISPLAY', false);
define('DISABLE_WP_CRON', true);
// opcionális, csak ha kell:
// define('WP_MEMORY_LIMIT', '256M');
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
