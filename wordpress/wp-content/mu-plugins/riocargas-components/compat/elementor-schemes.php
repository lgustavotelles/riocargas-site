<?php
// Classes Elementor\Core\Schemes removidas no Elementor 4, ainda referenciadas
// pelos controles portados dos plugins originais.
namespace Elementor\Core\Schemes {
	if ( ! class_exists( 'Elementor\Core\Schemes\Color' ) ) {
		class Color {
			const COLOR_1 = '1';
			const COLOR_2 = '2';
			const COLOR_3 = '3';
			const COLOR_4 = '4';
			public static function get_type() {
				return 'color';
			}
		}
	}
	if ( ! class_exists( 'Elementor\Core\Schemes\Typography' ) ) {
		class Typography {
			const TYPOGRAPHY_1 = '1';
			const TYPOGRAPHY_2 = '2';
			const TYPOGRAPHY_3 = '3';
			const TYPOGRAPHY_4 = '4';
			public static function get_type() {
				return 'typography';
			}
		}
	}
}
