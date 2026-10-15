<?php
/**
 * Bootstrap do substituto WPForms.
 *
 * Mu-plugins carregam ANTES dos plugins regulares, então este arquivo não pode
 * definir wpforms_display() diretamente — o plugin original a declara sem
 * guard e geraria "cannot redeclare". Por isso a inicialização é adiada para
 * plugins_loaded, quando já sabemos se o WPForms está ativo.
 *
 * @package RioCargas\Components\Forms
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-facade.php';
require_once __DIR__ . '/class-renderer.php';
require_once __DIR__ . '/class-uploads.php';
require_once __DIR__ . '/class-process.php';

// O stub WPForms\WPForms (para class_exists do Essential Addons) já é
// declarado aqui — é seguro porque o plugin original declara a classe real
// sem guard também... mas como nós carregamos ANTES, o plugin tentaria
// redeclarar a classe completa e fatalizaria. Por isso o stub fica condicional:
// só é declarado em plugins_loaded se o plugin estiver ausente.

add_action(
	'plugins_loaded',
	function () {
		if ( defined( 'WPFORMS_VERSION' ) || function_exists( 'wpforms' ) ) {
			return; // Plugin original ativo — substituto não interfere.
		}

		// Stub para integrações que checam class_exists.
		if ( ! class_exists( 'WPForms\\WPForms' ) ) {
			// phpcs:ignore Squiz.Commenting
			eval( 'namespace WPForms; class WPForms {}' );
		}

		if ( ! function_exists( 'wpforms_display' ) ) {
			function wpforms_display( $form_id, $title = false, $description = false ) {
				\RioCargas\Components\Forms\Renderer::output( absint( $form_id ), $title, $description );
			}
		}

		// Objeto global mínimo usado por alguns integradores.
		if ( ! function_exists( 'wpforms' ) ) {
			function wpforms() {
				static $inst = null;
				if ( null === $inst ) {
					$inst = new \RioCargas\Components\Forms\Facade();
				}
				return $inst;
			}
		}

		if ( ! function_exists( 'wpforms_setting' ) ) {
			function wpforms_setting( $key, $default = false, $option = 'wpforms_settings' ) {
				$key     = sanitize_key( $key );
				$options = get_option( $option, false );
				$value   = is_array( $options ) && ! empty( $options[ $key ] ) ? wp_unslash( $options[ $key ] ) : $default;
				return $value;
			}
		}

		if ( ! function_exists( 'wpforms_is_collecting_cookies_allowed' ) ) {
			function wpforms_is_collecting_cookies_allowed() {
				return ! ( wpforms_setting( 'gdpr', false ) && wpforms_setting( 'gdpr-disable-uuid', false ) );
			}
		}

		if ( ! function_exists( 'wpforms_get_capability_manage_options' ) ) {
			function wpforms_get_capability_manage_options() {
				return apply_filters( 'wpforms_manage_cap', 'manage_options' );
			}
		}

		if ( ! function_exists( 'wpforms_current_user_can' ) ) {
			function wpforms_current_user_can( $caps = [], $id = 0 ) {
				$user_can = current_user_can( wpforms_get_capability_manage_options() );
				return apply_filters( 'wpforms_current_user_can', $user_can, $caps, $id );
			}
		}

		if ( ! function_exists( 'wpforms_is_email' ) ) {
			function wpforms_is_email( $email ) {
				if ( ! is_scalar( $email ) ) {
					return false;
				}
				if ( preg_match( '/{.+?}/', $email ) ) {
					return $email;
				}
				if ( strlen( $email ) > 254 ) {
					return false;
				}
				$parts = explode( '@', $email );
				if ( count( $parts ) !== 2 ) {
					return false;
				}
				list( $local, $domain ) = $parts;
				if ( strlen( $local ) > 63 ) {
					return false;
				}
				foreach ( explode( '.', $domain ) as $label ) {
					if ( '' === trim( $label ) || strlen( $label ) > 63 ) {
						return false;
					}
				}
				return $email;
			}
		}

		add_shortcode(
			'wpforms',
			function ( $atts ) {
				$id = ! empty( $atts['id'] ) ? absint( $atts['id'] ) : 0;
				if ( ! $id ) {
					return '';
				}
				$title = isset( $atts['title'] ) ? filter_var( $atts['title'], FILTER_VALIDATE_BOOLEAN ) : false;
				$desc  = isset( $atts['description'] ) ? filter_var( $atts['description'], FILTER_VALIDATE_BOOLEAN ) : false;
				ob_start();
				\RioCargas\Components\Forms\Renderer::output( $id, $title, $desc );
				return ob_get_clean();
			}
		);

		// Submit.
		add_action( 'wp_ajax_wpforms_submit', [ \RioCargas\Components\Forms\Process::class, 'ajax_submit' ] );
		add_action( 'wp_ajax_nopriv_wpforms_submit', [ \RioCargas\Components\Forms\Process::class, 'ajax_submit' ] );
		add_action( 'wp', [ \RioCargas\Components\Forms\Process::class, 'listen' ] );

		// Upload moderno (Dropzone).
		add_action( 'wp_ajax_wpforms_file_upload_speed_test', 'wp_send_json_success' );
		add_action( 'wp_ajax_nopriv_wpforms_file_upload_speed_test', 'wp_send_json_success' );
		add_action( 'wp_ajax_wpforms_upload_file', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_modern_upload' ] );
		add_action( 'wp_ajax_nopriv_wpforms_upload_file', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_modern_upload' ] );
		add_action( 'wp_ajax_wpforms_upload_chunk_init', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_chunk_init' ] );
		add_action( 'wp_ajax_nopriv_wpforms_upload_chunk_init', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_chunk_init' ] );
		add_action( 'wp_ajax_wpforms_upload_chunk', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_chunk' ] );
		add_action( 'wp_ajax_nopriv_wpforms_upload_chunk', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_chunk' ] );
		add_action( 'wp_ajax_wpforms_file_chunks_uploaded', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_chunks_uploaded' ] );
		add_action( 'wp_ajax_nopriv_wpforms_file_chunks_uploaded', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_chunks_uploaded' ] );
		add_action( 'wp_ajax_wpforms_remove_file', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_remove' ] );
		add_action( 'wp_ajax_nopriv_wpforms_remove_file', [ \RioCargas\Components\Forms\Uploads::class, 'ajax_remove' ] );

		// Assets + settings.
		add_action( 'wp_enqueue_scripts', [ \RioCargas\Components\Forms\Renderer::class, 'enqueue_marker' ], 5 );
		add_action( 'wp_footer', [ \RioCargas\Components\Forms\Renderer::class, 'enqueue_assets' ], 5 );
		add_action( 'wp_footer', [ \RioCargas\Components\Forms\Renderer::class, 'print_settings' ], 99 );
	},
	20
);
