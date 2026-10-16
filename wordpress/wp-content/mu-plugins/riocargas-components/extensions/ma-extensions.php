<?php
/**
 * Extensões do Master Addons Pro aplicadas a elementos Elementor:
 * - Wrapper Link (data-jltma-wrapper-link + cursor:pointer)
 * - BG Slider (classe has_ma_el_bg_slider em sections/columns)
 * - Glassmorphism (classe jltma-glass-effect-{yes|no})
 * Reproduzem os atributos que o plugin injeta no markup.
 */

namespace RioCargas\Components\Extensions;

use Elementor\Element_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MA_Extensions {

	public static function init() {
		// before_render genérico cobre sections, columns e widgets.
		add_action( 'elementor/frontend/before_render', [ self::class, 'before_render' ], 10, 1 );
	}

	public static function before_render( Element_Base $element ) {
		$settings = $element->get_settings_for_display();

		// Wrapper Link (inc/modules/wrapper-link/wrapper-link.php).
		$link = $settings['jltma_section_element_link'] ?? null;
		if ( $link && ! empty( $link['url'] ) ) {
			$element->add_render_attribute(
				'_wrapper',
				[
					'data-jltma-wrapper-link' => wp_json_encode( $link ),
					'style'                   => 'cursor: pointer',
				]
			);
		}

		// BG Slider: classe sempre presente em sections/columns (bg-slider.php:257).
		$name = $element->get_name();
		if ( 'section' === $name || 'column' === $name ) {
			$element->add_render_attribute( '_wrapper', 'class', 'has_ma_el_bg_slider' );
		}

		// Glassmorphism: prefix_class jltma-glass-effect- com default 'no' (glassmorphism.php:66).
		$glass = $settings['jltma_enable_glassmorphism_effect'] ?? 'no';
		if ( 'yes' !== $glass ) {
			$glass = 'no';
		}
		$element->add_render_attribute( '_wrapper', 'class', 'jltma-glass-effect-' . $glass );
	}
}
