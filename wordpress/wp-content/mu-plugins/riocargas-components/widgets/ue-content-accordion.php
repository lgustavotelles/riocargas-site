<?php
/**
 * Substituto do widget "Content Accordion" do Unlimited Elements Pro (ucaddon_content_accordion).
 * Mesmo nome, mesmas configurações salvas, mesmo HTML/CSS/JS.
 */

namespace RioCargas\Components\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UE_Content_Accordion extends Widget_Base {

	public function get_name() {
		return 'ucaddon_content_accordion';
	}

	public function get_title() {
		return esc_html__( 'Content Accordion', 'riocargas' );
	}

	public function get_icon() {
		return 'eicon-accordion';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_style_depends(): array {
		return [ 'rc-fa-solid' ];
	}

	public function get_script_depends(): array {
		return [ 'jquery' ];
	}

	private function control( $name, $type, $label, $default, $selector = null, $value = null ) {
		$args = [ 'label' => $label, 'type' => $type ];
		if ( null !== $default ) {
			$args['default'] = $default;
		}
		if ( $selector ) {
			$args['selectors'] = [ '{{WRAPPER}} ' . $selector => $value ];
		}
		if ( Controls_Manager::SLIDER === $type ) {
			$args['range'] = [ 'px' => [ 'min' => 0, 'max' => 200 ] ];
			$args['default'] = [ 'unit' => 'px', 'size' => $default ];
		}
		if ( Controls_Manager::DIMENSIONS === $type ) {
			$args['size_units'] = [ 'px', '%', 'em' ];
			$args['default'] = $default ? array_combine( [ 'top', 'right', 'bottom', 'left' ], array_map( 'strval', $default ) ) + [ 'unit' => 'px', 'isLinked' => false ] : [];
		}
		$this->add_control( $name, $args );
	}

	protected function register_controls() {
		$box = 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';
		$margin = 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};';

		$this->start_controls_section( 'section_items', [ 'label' => esc_html__( 'Items', 'riocargas' ) ] );
		$repeater = new Repeater();
		$repeater->add_control( 'title', [ 'label' => 'Title', 'type' => Controls_Manager::TEXT, 'default' => 'Title' ] );
		$repeater->add_control( 'content_type', [ 'label' => 'Content Type', 'type' => Controls_Manager::SELECT, 'default' => 'text', 'options' => [ 'text' => 'Text', 'template' => 'Elementor Template' ] ] );
		$repeater->add_control( 'heading', [ 'label' => 'Heading', 'type' => Controls_Manager::TEXT, 'default' => '' ] );
		$repeater->add_control( 'content', [ 'label' => 'Content', 'type' => Controls_Manager::WYSIWYG, 'default' => '' ] );
		$repeater->add_control( 'link', [ 'label' => 'Link', 'type' => Controls_Manager::URL ] );
		$repeater->add_control( 'button_text', [ 'label' => 'Button Text', 'type' => Controls_Manager::TEXT, 'default' => 'Click Here' ] );
		$repeater->add_control( 'template_templateid', [ 'label' => 'Template ID', 'type' => Controls_Manager::NUMBER, 'condition' => [ 'content_type' => 'template' ] ] );
		$this->add_control( 'uc_items', [ 'type' => Controls_Manager::REPEATER, 'fields' => $repeater->get_controls(), 'title_field' => '{{{ title }}}' ] );
		foreach ( [ 'show_first_item' => 'Open First Item', 'show_title' => 'Show Title', 'show_content' => 'Show Content', 'show_button' => 'Show Button' ] as $name => $label ) {
			$this->add_control( $name, [ 'label' => $label, 'type' => Controls_Manager::SWITCHER, 'return_value' => 'true', 'default' => 'true' ] );
		}
		$this->add_control( 'content_alignment', [ 'label' => 'Content Alignment', 'type' => Controls_Manager::SELECT, 'default' => 'left', 'options' => [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] ] );
		$this->add_control( 'item_background_color', [ 'label' => 'Item Background', 'type' => Controls_Manager::COLOR, 'default' => '' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', [ 'label' => esc_html__( 'Style', 'riocargas' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$c = Controls_Manager::COLOR;
		$sl = Controls_Manager::SLIDER;
		$dm = Controls_Manager::DIMENSIONS;
		$this->control( 'heading_bg_color', $c, 'Heading Background', '#000000', '.uc-heading', 'background-color:{{VALUE}};' );
		$this->control( 'heading_bg_color_active', $c, 'Heading Background Active', '#3e3e3e', '.uc-heading.active', 'background-color:{{VALUE}};' );
		$this->control( 'heading_border_radius', $sl, 'Heading Radius', 0, '.uc-heading', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->control( 'accordion_heading_padding', $dm, 'Heading Padding', null, '.uc-heading', $box );
		$this->control( 'heading_margin', $dm, 'Heading Margin', null, '.uc-heading', $margin );
		$this->control( 'icon_bg', $c, 'Icon Background', null, 'span.uc_dactive', 'background-color:{{VALUE}};' );
		$this->control( 'icon_bg_active', $c, 'Icon Background Active', null, 'span.uc_active', 'background-color:{{VALUE}};' );
		$this->control( 'icon_background_size', $sl, 'Icon Background Size', 50, 'span.ue_accordion_icon', 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' );
		$this->control( 'border_radius', $sl, 'Icon Radius', 0, 'span.ue_accordion_icon', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->control( 'icon_color', $c, 'Icon Color', '#ffffff', 'span.uc_dactive', 'color:{{VALUE}};' );
		$this->control( 'icon_color_active', $c, 'Icon Color Active', '#ffffff', 'span.uc_active', 'color:{{VALUE}};' );
		$this->control( 'icon_size', $sl, 'Icon Size', 10, 'span.ue_accordion_icon', 'font-size: {{SIZE}}{{UNIT}};' );
		$this->control( 'heading_title_padding', $dm, 'Title Padding', [ 10, 10, 10, 10 ], '.ue_heading_title', $box );
		$this->control( 'heading_title_color', $c, 'Title Color', '#ffffff', '.ue_heading_title', 'color:{{VALUE}};' );
		$this->control( 'heading_title_color_active', $c, 'Title Color Active', '#ffffff', '.uc-heading.active .ue_heading_title', 'color:{{VALUE}};' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'heading_title_typography', 'selector' => '{{WRAPPER}} .ue_heading_title' ] );
		$this->control( 'content_padding', $dm, 'Content Padding', [ 30, 30, 30, 30 ], '.uc_content', $box );
		$this->control( 'content_margin', $dm, 'Content Margin', null, '.uc_content', $margin );
		$this->control( 'content_background_color', $c, 'Content Background', null, '.uc_content', 'background-color:{{VALUE}};' );
		$this->control( 'content_radius', $sl, 'Content Radius', 0, '.uc_content', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->control( 'title_color', $c, 'Item Title Color', '#000000', '.ue-item-title', 'color:{{VALUE}};' );
		$this->control( 'title_spacing', $sl, 'Item Title Spacing', 0, '.ue-item-title', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->control( 'text_color', $c, 'Text Color', '#1e1e1e', '.ue-item-text', 'color:{{VALUE}};' );
		$this->control( 'text_spacing', $sl, 'Text Spacing', 15, '.ue-item-text', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->control( 'button_padding', $dm, 'Button Padding', [ 10, 20, 10, 20 ], '.uc_more_btn', $box );
		$this->control( 'button_radius', $sl, 'Button Radius', 0, '.uc_more_btn', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->control( 'button_bg_color', $c, 'Button Background', '#000000', '.uc_more_btn', 'background-color:{{VALUE}};' );
		$this->control( 'btn_txt_clr', $c, 'Button Text', '#ffffff', '.uc_more_btn', 'color:{{VALUE}};' );
		$this->control( 'button_spacing', $sl, 'Button Spacing', 20, '.uc_more_btn', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->control( 'button_background_color_hover', $c, 'Button Background Hover', '#000000', '.uc_more_btn:hover', 'background-color:{{VALUE}};' );
		$this->control( 'btn_txt_clr_hvr', $c, 'Button Text Hover', '#ffffff', '.uc_more_btn:hover', 'color:{{VALUE}};' );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$id = 'uc_content_accordion_elementor' . $this->get_id();
		$on = function ( $key ) use ( $s ) {
			return isset( $s[ $key ] ) && 'true' === $s[ $key ];
		};
		?>
<style type="text/css">/* widget: Content Accordion */
#<?php echo $id; ?> *{box-sizing:border-box;}
#<?php echo $id; ?> em{font-style:normal;}
#<?php echo $id; ?> .uc_container .uc-heading{display:flex;align-items:center;overflow:hidden;cursor:pointer;}
#<?php echo $id; ?> .uc_container .uc_content{display:none;text-align:<?php echo esc_attr( $s['content_alignment'] ); ?>;}
#<?php echo $id; ?> .uc_container .uc-heading span.ue_accordion_icon{display:flex;align-items:center;justify-content:center;}
#<?php echo $id; ?> .uc_container .uc-heading span.uc_active{display:none;}
#<?php echo $id; ?> .uc_container .uc-heading span.uc_dactive{display:flex;}
#<?php echo $id; ?> .uc_container .uc-heading.active span.uc_active{display:flex;}
#<?php echo $id; ?> .uc_container .uc-heading.active span.uc_dactive{display:none;}
#<?php echo $id; ?> .uc_container .uc_btn{text-align:center;display:inline-block;text-decoration:none;transition:ease-in-out all 0.25s;}
.ue-item-title{font-size:21px;}
</style>
<div class="uc_material_accordion" id="<?php echo esc_attr( $id ); ?>"><div class="uc_container">
		<?php
		foreach ( array_values( (array) $s['uc_items'] ) as $index => $item ) {
			$visible = $on( 'show_first_item' ) && 0 === $index ? 'uc-visible' : '';
			echo '<div class="uc_ac_box" style="background-color:' . esc_attr( $s['item_background_color'] ) . ';">';
			echo '<div class="uc-heading uc_trigger-' . esc_attr( $id ) . '">';
			echo '<span class="uc_dactive bbb heading-padding ue_accordion_icon"><i class="fas fa-plus"></i></span> ';
			echo '<span class="uc_active bbb heading-padding ue_accordion_icon"><i class="fas fa-minus"></i></span>';
			echo '<div class="ue_heading_title">' . wp_kses_post( $item['title'] ) . '</div></div>';
			echo '<div class="uc_content ' . esc_attr( $visible ) . '">';
			if ( 'template' === ( $item['content_type'] ?? 'text' ) && ! empty( $item['template_templateid'] ) ) {
				echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $item['template_templateid'] ); // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				if ( $on( 'show_title' ) ) {
					echo '<div class="ue-item-title">' . wp_kses_post( $item['heading'] ) . '</div>';
				}
				if ( $on( 'show_content' ) ) {
					echo '<div class="ue-item-text">' . wp_kses_post( $item['content'] ) . '</div>';
				}
				if ( $on( 'show_button' ) ) {
					$url = ! empty( $item['link']['url'] ) ? $item['link']['url'] : '#';
					$attrs = ! empty( $item['link']['is_external'] ) ? ' target="_blank"' : '';
					$attrs .= ! empty( $item['link']['nofollow'] ) ? ' rel="nofollow"' : '';
					echo '<a href="' . esc_url( $url ) . '" class="uc_btn uc_more_btn"' . $attrs . '>' . wp_kses_post( $item['button_text'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}
			echo '</div></div>';
		}
		?>
</div></div>
<script>
jQuery(function () {
	var triggers = '.uc_material_accordion .uc_trigger-<?php echo $id; ?>';
	<?php if ( $on( 'show_first_item' ) ) : ?>
	jQuery(triggers + ':first').addClass('active').next().show();
	<?php endif; ?>
	jQuery(triggers).on('click', function () {
		if (jQuery(this).next().is(':hidden')) {
			jQuery(triggers).removeClass('active').next().slideUp();
			jQuery(this).toggleClass('active').next().slideDown();
		} else {
			jQuery(triggers).removeClass('active').next().slideUp();
		}
		return false;
	});
});
</script>
		<?php
	}
}
