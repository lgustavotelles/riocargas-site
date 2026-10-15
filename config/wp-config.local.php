<?php
// wp-config LOCAL (Docker). Copiar para wordpress/wp-config.php. Não usar em produção.
define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'riocargas' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'riocargas' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'riocargas_local' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Chaves só para uso local: gere novas em https://api.wordpress.org/secret-key/1.1/salt/
define( 'AUTH_KEY', 'local' );
define( 'SECURE_AUTH_KEY', 'local' );
define( 'LOGGED_IN_KEY', 'local' );
define( 'NONCE_KEY', 'local' );
define( 'AUTH_SALT', 'local' );
define( 'SECURE_AUTH_SALT', 'local' );
define( 'LOGGED_IN_SALT', 'local' );
define( 'NONCE_SALT', 'local' );

$table_prefix = 'wpg8_';

define( 'WP_ENVIRONMENT_TYPE', 'local' );

// Usa o host da requisição (localhost ou URL de preview) e reescreve os links salvos no banco.
if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$rc_scheme = ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' ) ? 'https' : 'http';
	if ( 'https' === $rc_scheme ) {
		$_SERVER['HTTPS'] = 'on';
	}
	$rc_origin = $rc_scheme . '://' . $_SERVER['HTTP_HOST'];
	define( 'WP_HOME', $rc_origin );
	define( 'WP_SITEURL', $rc_origin );
	if ( 'http://localhost:8080' !== $rc_origin ) {
		ob_start(
			static function ( $html ) use ( $rc_origin ) {
				return str_replace(
					array( 'http://localhost:8080', 'http:\/\/localhost:8080' ),
					array( $rc_origin, str_replace( '/', '\/', $rc_origin ) ),
					$html
				);
			}
		);
	}
}

define( 'WP_CACHE', false );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_MEMORY_LIMIT', '512M' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
