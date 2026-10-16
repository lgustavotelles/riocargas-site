<?php
// Substituto do widget Site Logo do Elementor Pro 3.7.1 (GPLv3): mesma saída do widget de imagem com logo/URL do site.
namespace RioCargas\Components\Widgets;

use Elementor\Plugin;
use Elementor\Widget_Image;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Logo extends Widget_Image {

	public function get_name() {
		return 'theme-site-logo';
	}

	public function get_title() {
		return esc_html__( 'Site Logo', 'riocargas' );
	}

	public function get_icon() {
		return 'eicon-site-logo';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'site', 'logo', 'branding' ];
	}

	public function get_inline_css_depends() {
		return [
			[
				'name' => 'image',
				'is_core_dependency' => true,
			],
		];
	}

	protected function register_controls() {
		parent::register_controls();

		$tags = Plugin::$instance->dynamic_tags;

		$this->update_control( 'image', [ 'dynamic' => [ 'default' => $tags->tag_data_to_tag_text( null, 'site-logo' ) ] ], [ 'recursive' => true ] );
		$this->update_control( 'image_size', [ 'default' => 'full' ] );
		$this->update_control( 'link_to', [ 'default' => 'custom' ] );
		$this->update_control( 'link', [ 'dynamic' => [ 'default' => $tags->tag_data_to_tag_text( null, 'site-url' ) ] ], [ 'recursive' => true ] );

		$caption_options = $this->get_controls( 'caption_source' )['options'];
		unset( $caption_options['custom'] );
		$this->update_control( 'caption_source', [ 'options' => $caption_options ] );
		$this->remove_control( 'caption' );
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' elementor-widget-' . parent::get_name();
	}
}
