<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '=n[yWm%1#=6hu&pY3g}:Qi|B0L%LED}gg,uKnntUKO]+f6N*}d^<}fq?FL#%Z(tf' );
define( 'SECURE_AUTH_KEY',   'fC|f;4j,1~b9&/j^f_pk<ufI<rZLtZ9$M}|&bYClV.YHRj$uz]VR3tSNXh[[>K<@' );
define( 'LOGGED_IN_KEY',     'F*X2q2!H3V|k?Zol|g^apy&~X&BZ*O7F<ul=X`!,VkJn0Hs&VF]NAOiTD6f`wu89' );
define( 'NONCE_KEY',         '3FtRU6R;^|ZFs34|nh2D$R09!cMw+8GYr(D!T!]lTAK86rgVJP>km/x70||Xgq>C' );
define( 'AUTH_SALT',         'Ew OG%c~/7M$ZdTZ6`lhrvAT%0!AnPl|g@4iJ|K sP|b@;hx^g--^`SrpJvAofX`' );
define( 'SECURE_AUTH_SALT',  '.]Hukz+QeU%B=KCP@Z5d~QHxvEe^elHFZsS{vt&GWm_YPw2SYiYCxZi2Y+tlcGt)' );
define( 'LOGGED_IN_SALT',    'qtKw<=DsG;<QJq/9QZ:.(g`GWiUIVFk^;F]yeAp+n(m<j}5E*0?}_>W#E~a8N!sP' );
define( 'NONCE_SALT',        '*,e|BAMiH7RnEJjY5;9ppREIusMVTrxtd&P#6p9U/~=+Q{+o ;t$o<fC 5Uu1s!y' );
define( 'WP_CACHE_KEY_SALT', '#}O#cLGTxU8J,rd+:6Nu56/F;j&wS$xDV5&iE@X,{,8;54~Pwm|<#</`VE^}*OUJ' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
