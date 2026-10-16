<?php
// Tags dinâmicas "site-logo" e "site-url" (mesmos nomes do Elementor Pro) usadas pelo widget Site Logo.
namespace RioCargas\Components\Tags;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use Elementor\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Logo extends Data_Tag {

	public function get_name() {
		return 'site-logo';
	}

	public function get_title() {
		return esc_html__( 'Site Logo', 'riocargas' );
	}

	public function get_group() {
		return 'site';
	}

	public function get_categories() {
		return [ Module::IMAGE_CATEGORY ];
	}

	public function get_value( array $options = [] ) {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$image = $custom_logo_id ? wp_get_attachment_image_src( $custom_logo_id, 'full' ) : false;

		return [
			'id' => $custom_logo_id,
			'url' => $image ? $image[0] : Utils::get_placeholder_image_src(),
		];
	}
}

class Site_URL extends Data_Tag {

	public function get_name() {
		return 'site-url';
	}

	public function get_title() {
		return esc_html__( 'Site URL', 'riocargas' );
	}

	public function get_group() {
		return 'site';
	}

	public function get_categories() {
		return [ Module::URL_CATEGORY ];
	}

	public function get_value( array $options = [] ) {
		return home_url();
	}
}
