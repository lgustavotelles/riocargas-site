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

function rc_components_replace_unlimited_elements() {
	return did_action( 'elementor/loaded' ) && ! defined( 'UNLIMITED_ELEMENTS_INC' );
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
	'wp_enqueue_scripts',
	function () {
		if ( ! rc_components_replace_unlimited_elements() ) {
			return;
		}
		wp_register_style( 'rc-owl-carousel', RC_COMPONENTS_URL . 'assets/owl-carousel/owl.carousel.css', [], '2.0.0' );
		wp_register_script( 'rc-owl-carousel', RC_COMPONENTS_URL . 'assets/owl-carousel/owl.carousel.min.js', [ 'jquery' ], '2.0.0', true );
		$fa = plugins_url( 'elementor/assets/lib/font-awesome/css/' );
		wp_register_style( 'rc-fa', $fa . 'fontawesome.min.css', [], '5.15.3' );
		wp_register_style( 'rc-fa-solid', $fa . 'solid.min.css', [ 'rc-fa' ], '5.15.3' );
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
	20
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		if ( ! rc_components_replace_unlimited_elements() ) {
			return;
		}
		require_once RC_COMPONENTS_DIR . 'widgets/ue-card-carousel.php';
		require_once RC_COMPONENTS_DIR . 'widgets/ue-content-accordion.php';
		$widgets_manager->register( new \RioCargas\Components\Widgets\UE_Card_Carousel() );
		$widgets_manager->register( new \RioCargas\Components\Widgets\UE_Content_Accordion() );
	},
	// Depois do módulo Promotions do Elementor, que registra placeholders com os mesmos nomes.
	20
);

add_action(
	'elementor/init',
	function () {
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return;
		}

		require_once RC_COMPONENTS_DIR . 'motion-fx/controls-group.php';
		require_once RC_COMPONENTS_DIR . 'motion-fx/module.php';

		new \RioCargas\Components\MotionFX\Module();
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! rc_components_replace_elementor_pro() ) {
			return;
		}
		wp_enqueue_style( 'rc-pro-compat', RC_COMPONENTS_URL . 'assets/pro-compat.css', [], RC_COMPONENTS_VERSION );
	},
	20
);
