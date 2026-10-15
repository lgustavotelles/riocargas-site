<?php
// Runtime mínimo para os widgets portados do PowerPack Elements:
// classe base copiada do plugin + stubs de PP_Helper/PP_Config usados pelos widgets.

namespace PowerpackElements\Classes {

	if ( ! class_exists( 'PowerpackElements\Classes\PP_Helper' ) ) {
		class PP_Helper {
			protected static $widgets_list = [
				'Advanced_Menu' => [
					'name'       => 'pp-advanced-menu',
					'title'      => 'Advanced Menu',
					'categories' => [ 'powerpack-elements' ],
					'keywords'   => [ 'powerpack', 'menu', 'navigation' ],
					'icon'       => 'ppicon-advanced-menu power-pack-admin-icon',
				],
				'Buttons'       => [
					'name'       => 'pp-buttons',
					'title'      => 'Buttons',
					'categories' => [ 'powerpack-elements' ],
					'keywords'   => [ 'powerpack', 'buttons' ],
					'icon'       => 'ppicon-multi-buttons power-pack-admin-icon',
				],
			];

			public static function get_widget_name( $slug = '' ) {
				$name = isset( self::$widgets_list[ $slug ] ) ? self::$widgets_list[ $slug ]['name'] : '';
				return apply_filters( 'pp_elements_widget_name', $name );
			}

			public static function get_widget_title( $slug = '' ) {
				$title = isset( self::$widgets_list[ $slug ] ) ? self::$widgets_list[ $slug ]['title'] : '';
				return apply_filters( 'pp_elements_widget_title', $title );
			}

			public static function get_widget_categories( $slug = '' ) {
				$categories = isset( self::$widgets_list[ $slug ] ) ? self::$widgets_list[ $slug ]['categories'] : '';
				return apply_filters( 'pp_elements_widget_categories', $categories );
			}

			public static function get_widget_icon( $slug = '' ) {
				$icon = isset( self::$widgets_list[ $slug ] ) ? self::$widgets_list[ $slug ]['icon'] : '';
				return apply_filters( 'pp_elements_widget_icon', $icon );
			}

			public static function get_widget_keywords( $slug = '' ) {
				$keywords = isset( self::$widgets_list[ $slug ] ) ? self::$widgets_list[ $slug ]['keywords'] : '';
				return apply_filters( 'pp_elements_widget_keywords', $keywords );
			}

			public static function is_widget_active( $widget = '' ) {
				return true;
			}
		}
	}

	if ( ! class_exists( 'PowerpackElements\Classes\PP_Config' ) ) {
		class PP_Config {
			public static function get_widget_help_links( $widget ) {
				return [];
			}
			public static function get_widget_style() {
				return [];
			}
			public static function __callStatic( $name, $args ) {
				return [];
			}
		}
	}
}

namespace {
	require_once __DIR__ . '/pp-widget-base.php';
}
