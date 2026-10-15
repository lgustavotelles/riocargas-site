<?php
/**
 * Plugin Name: Rio Cargas Components
 * Description: Substitutos próprios dos componentes de plugins pagos usados no site (mesmo HTML/CSS). Só atuam quando o plugin pago correspondente está desativado.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RC_COMPONENTS_VERSION', '1.0.0' );
define( 'RC_COMPONENTS_DIR', __DIR__ . '/riocargas-components/' );
define( 'RC_COMPONENTS_URL', content_url( 'mu-plugins/riocargas-components/' ) );

function rc_components_replace_elementor_pro() {
	return did_action( 'elementor/loaded' ) && ! defined( 'ELEMENTOR_PRO_VERSION' );
}

function rc_components_page_uses( $widget_type ) {
	$id = get_queried_object_id();
	if ( ! $id ) {
		return false;
	}
	$data = get_post_meta( $id, '_elementor_data', true );
	return is_string( $data ) && false !== strpos( $data, '"widgetType":"' . $widget_type . '"' );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! rc_components_replace_elementor_pro() ) {
			return;
		}
		wp_register_style( 'rc-animated-headline', RC_COMPONENTS_URL . 'assets/animated-headline.css', [], RC_COMPONENTS_VERSION );
		wp_register_script( 'rc-animated-headline', RC_COMPONENTS_URL . 'assets/animated-headline.js', [], RC_COMPONENTS_VERSION, true );

		if ( rc_components_page_uses( 'animated-headline' ) ) {
			wp_enqueue_style( 'rc-animated-headline' );
			wp_enqueue_script( 'rc-animated-headline' );
		}
	},
	5
);

add_action(
	'elementor/dynamic_tags/register',
	function ( $dynamic_tags ) {
		if ( ! rc_components_replace_elementor_pro() ) {
			return;
		}
		require_once RC_COMPONENTS_DIR . 'tags/site-tags.php';
		$dynamic_tags->register_group( 'site', [ 'title' => esc_html__( 'Site', 'riocargas' ) ] );
		$dynamic_tags->register( new \RioCargas\Components\Tags\Site_Logo() );
		$dynamic_tags->register( new \RioCargas\Components\Tags\Site_URL() );
	}
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		if ( ! rc_components_replace_elementor_pro() ) {
			return;
		}
		require_once RC_COMPONENTS_DIR . 'widgets/animated-headline.php';
		require_once RC_COMPONENTS_DIR . 'widgets/site-logo.php';
		$widgets_manager->register( new \RioCargas\Components\Widgets\Animated_Headline() );
		$widgets_manager->register( new \RioCargas\Components\Widgets\Site_Logo() );
	},
	// Depois do módulo Promotions do Elementor, que registra placeholders com os mesmos nomes.
	20
);
