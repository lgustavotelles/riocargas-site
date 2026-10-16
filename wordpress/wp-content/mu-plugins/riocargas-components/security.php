<?php
/**
 * Hardening independente dos substitutos de plugins.
 *
 * @package RioCargas\Components
 */

defined( 'ABSPATH' ) || exit;

/**
 * S6: esconde /wp-json/wp/v2/users de visitantes — a rota listava logins
 * (name/slug), facilitando ataque de senha. Logados seguem com acesso
 * (editor e admin usam a rota para autocomplete de autores).
 */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( $endpoints as $route => $handlers ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}
);
