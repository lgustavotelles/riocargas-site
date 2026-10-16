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

function rc_components_replace_powerpack() {
	return did_action( 'elementor/loaded' ) && ! defined( 'POWERPACK_ELEMENTS_VER' );
}

function rc_components_replace_master_addons() {
	return did_action( 'elementor/loaded' ) && ! defined( 'JLTMA_PLUGIN_VERSION' ) && ! defined( 'MASTER_ADDONS_VERSION' ) && ! defined( 'MA_EL_VERSION' );
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
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		if ( ! rc_components_replace_powerpack() ) {
			return;
		}
		require_once RC_COMPONENTS_DIR . 'compat/elementor-schemes.php';
		require_once RC_COMPONENTS_DIR . 'compat/pp-runtime.php';
		require_once RC_COMPONENTS_DIR . 'widgets/pp-advanced-menu.php';
		require_once RC_COMPONENTS_DIR . 'widgets/pp-buttons.php';
		$widgets_manager->register( new \PowerpackElements\Modules\AdvancedMenu\Widgets\Advanced_Menu() );
		$widgets_manager->register( new \PowerpackElements\Modules\Buttons\Widgets\Buttons() );
	},
	20
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		if ( ! rc_components_replace_master_addons() ) {
			return;
		}
		require_once RC_COMPONENTS_DIR . 'compat/ma-runtime.php';
		require_once RC_COMPONENTS_DIR . 'widgets/ma-tabs.php';
		require_once RC_COMPONENTS_DIR . 'widgets/ma-advanced-accordion.php';
		require_once RC_COMPONENTS_DIR . 'widgets/ma-creative-links.php';
		$widgets_manager->register( new \MasterAddons\Addons\Tabs() );
		$widgets_manager->register( new \MasterAddons\Addons\Advanced_Accordion() );
		$widgets_manager->register( new \MasterAddons\Addons\Creative_Links() );
	},
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

add_action(
	'wp_enqueue_scripts',
	function () {
		// Helpers removidos do elementorCommon no Elementor 4 (softDeprecated etc.)
		// ainda sao chamados por plugins antigos; sem eles a excecao interrompe a
		// cadeia elementor/frontend/init e handlers posteriores nao registram.
		wp_enqueue_script(
			'rc-elementor-helpers-polyfill',
			RC_COMPONENTS_URL . 'assets/compat/elementor-helpers-polyfill.js',
			[ 'elementor-common' ],
			RC_COMPONENTS_VERSION,
			true
		);

		// PowerPack Elements: o plugin enfileirava frontend.css em todas as páginas
		// (enqueue_frontend_styles) e registrava os scripts abaixo globalmente.
		if ( rc_components_replace_powerpack() ) {
			wp_enqueue_style( 'powerpack-frontend', RC_COMPONENTS_URL . 'assets/pp/frontend.min.css', [], '2.4.0' );

			wp_register_script( 'jquery-smartmenu', RC_COMPONENTS_URL . 'assets/pp/jquery-smartmenu.js', [ 'jquery' ], '1.1.1', true );
			wp_register_script( 'pp-advanced-menu', RC_COMPONENTS_URL . 'assets/pp/frontend-advanced-menu.js', [ 'jquery' ], '2.4.0', true );
			wp_register_script( 'pp-tooltipster', RC_COMPONENTS_URL . 'assets/pp/tooltipster.min.js', [ 'jquery' ], '2.4.0', true );
			wp_register_script( 'powerpack-frontend', RC_COMPONENTS_URL . 'assets/pp/frontend.js', [ 'jquery' ], '2.4.0', true );

			$pp_localize = apply_filters(
				'pp_elements_js_localize',
				[ 'ajax_url' => admin_url( 'admin-ajax.php' ) ]
			);
			wp_localize_script( 'jquery', 'pp', $pp_localize );

			if ( function_exists( 'wpforms' ) ) {
				wpforms()->frontend->assets_css();
			}
		}

		// Master Addons: jltma_enqueue_scripts enfileirava globalmente bootstrap + styles + plugins.js + scripts.js.
		if ( rc_components_replace_master_addons() ) {
			wp_enqueue_style( 'jltma-bootstrap', RC_COMPONENTS_URL . 'assets/ma/bootstrap.min.css' );
			wp_enqueue_style( 'master-addons-main-style', RC_COMPONENTS_URL . 'assets/ma/master-addons-styles.css' );

			wp_enqueue_script( 'master-addons-plugins', RC_COMPONENTS_URL . 'assets/ma/plugins.js', [ 'jquery' ], '7.1.3', true );
			wp_enqueue_script( 'master-addons-scripts', RC_COMPONENTS_URL . 'assets/ma/master-addons-scripts.js', [ 'jquery' ], '7.1.3', true );

			wp_localize_script(
				'master-addons-scripts',
				'jltma_scripts',
				[
					'plugin_url' => RC_COMPONENTS_URL . 'assets/ma/',
					'ajaxurl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => 'master-addons-elementor',
				]
			);

			// Inline script que o plugin injetava antes do jquery-core.
			$jltma_header_inline_scripts = 'function jltmaNS(n){for(var e=n.split("."),a=window,i="",r=e.length,t=0;r>t;t++)"window"!=e[t]&&(i=e[t],a[i]=a[i]||{},a=a[i]);return a;}';
			if ( $jltma_header_inline_scripts = apply_filters( 'jltma_header_inline_scripts', $jltma_header_inline_scripts ) ) {
				wp_add_inline_script( 'jquery-core', "/* < ![CDATA[ */\n" . $jltma_header_inline_scripts . "\n/* ]]> */", 'before' );
			}
		}
	},
	5
);

// HFE (header-footer-elementor) enfileira CSS de widgets que não existem no
// Elementor Pro 3.7.1 instalado (mega-menu/blockquote são de versões novas) —
// dequeue evita 404 na fila de estilos.
add_action(
	'wp_enqueue_scripts',
	function () {
		foreach ( [ 'hfe-mega-menu', 'hfe-widget-blockquote' ] as $hfe_orphan_handle ) {
			if ( wp_style_is( $hfe_orphan_handle, 'enqueued' ) && ! file_exists( WP_CONTENT_DIR . '/plugins/elementor-pro/assets/css/' . ( 'hfe-mega-menu' === $hfe_orphan_handle ? 'widget-mega-menu' : 'widget-blockquote' ) . '.min.css' ) ) {
				wp_dequeue_style( $hfe_orphan_handle );
			}
		}
	},
	100
);

// WPForms: substituto próprio — carrega classes agora e só inicializa em
// plugins_loaded se o plugin original estiver ausente (evita redeclare).
require_once RC_COMPONENTS_DIR . 'forms/wpforms-compat.php';

// Hardening global (bloqueios que não dependem dos substitutos).
require_once RC_COMPONENTS_DIR . 'security.php';

// Extensões do Master Addons sobre elementos: Wrapper Link, BG Slider e Glassmorphism.
add_action(
	'elementor/init',
	function () {
		if ( ! rc_components_replace_master_addons() ) {
			return;
		}
		require_once RC_COMPONENTS_DIR . 'extensions/ma-extensions.php';
		\RioCargas\Components\Extensions\MA_Extensions::init();
	}
);
