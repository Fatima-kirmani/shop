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
define( 'DB_NAME', 'shop' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'Hznp==./If.`-SLP6frI@[#IO!@32mTYkDD_n,gu1#^;@jB6&m3%8s9-nT0RJbH`' );
define( 'SECURE_AUTH_KEY',  'bUo8kcCs4&e(_i!NL&a*9qjY{H#X%50xbi(,P_T/5n+jarD/E}5y{o)[0TY<9nbY' );
define( 'LOGGED_IN_KEY',    'dG};;yNYDY,-pb{=X%$7PJ51eI*,r,j8nrJKE--_u>iUAka#c=VGmSJSld->|D5?' );
define( 'NONCE_KEY',        '`;6N1J],>:w1_!4}tnnbzu>S-K,3akpdZBU?GfD01 o7]|Z-c!WX]1AS;mY{`Y]4' );
define( 'AUTH_SALT',        'x7uy;NjlCB(6u$T61=t<&tG(Cn6?67x*dG&]c[pm=[@>tK<e+Nz$_FDv%$f[NC79' );
define( 'SECURE_AUTH_SALT', 'qUM}=1^0~UW2o,9^N~e~Yqfq*LhkI:z|I!9$)3uH]q|jjLc._ph8Yo}X/c:bl~nS' );
define( 'LOGGED_IN_SALT',   '&m(NM>u]2fUFi_EfT:;yL^!J-T^1;ee(<zvL=S;rPSbuHk6l8*/_fZuShuI$8osZ' );
define( 'NONCE_SALT',       '_|8Vo1>cCA7#E{_@I<@W1nNMK8VRq)T&}(zy!}%V#VGq]= ,K%:_:];i[%C.I*+=' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
