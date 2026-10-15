<?php
/**
 * Substituto do widget "Card Carousel" do Unlimited Elements Pro (ucaddon_card_carousel).
 * Mesmo nome, mesmas configurações salvas, mesmo HTML/CSS/JS (Owl Carousel).
 */

namespace RioCargas\Components\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UE_Card_Carousel extends Widget_Base {

	public function get_name() {
		return 'ucaddon_card_carousel';
	}

	public function get_title() {
		return esc_html__( 'Card Carousel', 'riocargas' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_style_depends(): array {
		return [ 'rc-owl-carousel' ];
	}

	public function get_script_depends(): array {
		return [ 'rc-owl-carousel' ];
	}

	private function switcher( $name, $label, $default ) {
		$this->add_control(
			$name,
			[
				'label' => $label,
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'default' => $default ? 'true' : '',
			]
		);
	}

	private function slider( $name, $label, $default, $selector, $value, $max = 500 ) {
		$this->add_control(
			$name,
			[
				'label' => $label,
				'type' => Controls_Manager::SLIDER,
				'range' => [ 'px' => [ 'min' => 0, 'max' => $max ] ],
				'default' => [ 'unit' => 'px', 'size' => $default ],
				'selectors' => [ '{{WRAPPER}} ' . $selector => $value ],
			]
		);
	}

	private function color( $name, $label, $default, $selector, $property ) {
		$this->add_control(
			$name,
			[
				'label' => $label,
				'type' => Controls_Manager::COLOR,
				'default' => $default,
				'selectors' => [ '{{WRAPPER}} ' . $selector => $property . ':{{VALUE}};' ],
			]
		);
	}

	private function dimensions( $name, $label, $default, $selector, $property ) {
		$args = [
			'label' => $label,
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%', 'em' ],
			'selectors' => [ '{{WRAPPER}} ' . $selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
		];
		if ( $default ) {
			$args['default'] = array_combine( [ 'top', 'right', 'bottom', 'left' ], array_map( 'strval', $default ) ) + [ 'unit' => 'px', 'isLinked' => false ];
		}
		$this->add_control( $name, $args );
	}

	protected function register_controls() {
		$arrows = '.owl-carousel .owl-nav .owl-next, {{WRAPPER}} .owl-carousel .owl-nav .owl-prev';
		$arrows_hover = '.owl-carousel .owl-nav .owl-next:hover, {{WRAPPER}} .owl-carousel .owl-nav .owl-prev:hover';

		$this->start_controls_section( 'section_items', [ 'label' => esc_html__( 'Items', 'riocargas' ) ] );
		$repeater = new Repeater();
		$repeater->add_control( 'title', [ 'label' => 'Title', 'type' => Controls_Manager::TEXT, 'default' => 'Title' ] );
		$repeater->add_control( 'content', [ 'label' => 'Text', 'type' => Controls_Manager::TEXTAREA, 'default' => '' ] );
		$repeater->add_control( 'btn_text', [ 'label' => 'Button Text', 'type' => Controls_Manager::TEXT, 'default' => 'Show More' ] );
		$repeater->add_control( 'image', [ 'label' => 'Image', 'type' => Controls_Manager::MEDIA ] );
		$repeater->add_control( 'link', [ 'label' => 'Link', 'type' => Controls_Manager::URL ] );
		$this->add_control(
			'uc_items',
			[
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'title_field' => '{{{ title }}}',
			]
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_options', [ 'label' => esc_html__( 'Carousel', 'riocargas' ) ] );
		$this->switcher( 'show_top_decoration', 'Show Top Decoration', true );
		$this->switcher( 'show_image', 'Show Image', true );
		$this->switcher( 'image_link', 'Image Link', true );
		$this->switcher( 'show_title', 'Show Title', true );
		$this->switcher( 'show_text', 'Show Text', true );
		$this->switcher( 'show_button', 'Show Button', true );
		$this->add_control( 'align', [ 'label' => 'Align', 'type' => Controls_Manager::SELECT, 'default' => 'center', 'options' => [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] ] );
		$this->add_control( 'direction', [ 'label' => 'Direction', 'type' => Controls_Manager::SELECT, 'default' => 'ltr', 'options' => [ 'ltr' => 'LTR', 'rtl' => 'RTL' ] ] );
		$this->switcher( 'loop', 'Loop', true );
		$this->switcher( 'autoplay', 'Autoplay', true );
		$this->switcher( 'autoplayhoverpause', 'Pause On Hover', true );
		$this->switcher( 'show_arrows', 'Show Arrows', true );
		$this->switcher( 'show_bullets', 'Show Bullets', true );
		$this->switcher( 'transition_easing', 'Easing', true );
		foreach ( [
			'margin_between_slides' => 30,
			'autoplay_interval' => 3000,
			'transition_speed' => 1000,
			'number_of_items' => 3,
			'number_of_items_tablet' => 2,
			'number_of_items_mobile' => 1,
			'slides_to_scroll' => 1,
			'slides_to_scroll_tablet' => 1,
			'slides_to_scroll_mobile' => 1,
			'nav_arrow_spacing' => 20,
		] as $name => $default ) {
			$this->add_control( $name, [ 'label' => ucwords( str_replace( '_', ' ', $name ) ), 'type' => Controls_Manager::NUMBER, 'default' => $default ] );
		}
		$this->add_control( 'dot_alignment', [ 'label' => 'Dots Alignment', 'type' => Controls_Manager::SELECT, 'default' => 'center', 'options' => [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style', [ 'label' => esc_html__( 'Style', 'riocargas' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->dimensions( 'content_padding', 'Content Padding', [ 0, 20, 20, 20 ], '.uc_classic_carousel_content', 'padding' );
		$this->slider( 'content_minimum_height', 'Content Min Height', 100, '.uc_classic_carousel_content', 'min-height: {{SIZE}}{{UNIT}};' );
		$this->color( 'background_color', 'Item Background', '#ffffff', '.ue-carousel-item', 'background-color' );
		$this->slider( 'slide_radius', 'Item Radius', 0, '.ue-carousel-item', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'item_border',
				'selector' => '{{WRAPPER}} .ue-carousel-item',
				'fields_options' => [
					'border' => [ 'default' => 'solid' ],
					'width' => [ 'default' => [ 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => true ] ],
				],
			]
		);
		$this->color( 'top_decoration_color', 'Top Decoration Color', '#000000', '.uc_classic_carousel_border', 'background-color' );
		$this->slider( 'top_decoration_width', 'Top Decoration Width', 50, '.uc_classic_carousel_border', 'width: {{SIZE}}{{UNIT}};' );
		$this->slider( 'top_decoration_height', 'Top Decoration Height', 3, '.uc_classic_carousel_border', 'height: {{SIZE}}{{UNIT}};' );
		$this->slider( 'image_radius', 'Image Radius', 0, '.uc_classic_carousel_placeholder img', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->slider( 'image_height', 'Image Height', 200, '.uc_classic_carousel_placeholder img', 'height: {{SIZE}}{{UNIT}};', 1000 );
		$this->dimensions( 'image_padding', 'Image Padding', null, '.uc_classic_carousel_placeholder', 'padding' );
		$this->slider( 'title_spacing', 'Title Spacing', 0, '.card_carousel_title', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->color( 'title_color', 'Title Color', '#000000', '.card_carousel_title', 'color' );
		$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .card_carousel_title' ] );
		$this->slider( 'text_spacing', 'Text Spacing', 10, '.card_carousel_text', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->color( 'text_color', 'Text Color', '#8a8a8a', '.card_carousel_text', 'color' );
		$this->dimensions( 'button_padding', 'Button Padding', [ 10, 20, 10, 20 ], '.uc_more_btn', 'padding' );
		$this->slider( 'button_radius', 'Button Radius', 0, '.uc_more_btn', 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->color( 'button_bg_color', 'Button Background', '#d3d3d3', '.uc_more_btn', 'background-color' );
		$this->color( 'btn_txt_clr', 'Button Text', '#000000', '.uc_more_btn', 'color' );
		$this->slider( 'button_spacing', 'Button Spacing', 10, '.uc_more_btn', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->color( 'button_background_color_hover', 'Button Background Hover', '#000000', '.uc_more_btn:hover', 'background-color' );
		$this->color( 'btn_txt_clr_hvr', 'Button Text Hover', '#ffffff', '.uc_more_btn:hover', 'color' );
		$this->slider( 'nav_arrow_position', 'Arrows Top', 94, $arrows, 'top: {{SIZE}}{{UNIT}};', 1000 );
		$this->slider( 'nav_arrow_radius', 'Arrows Radius', 0, $arrows, 'border-radius: {{SIZE}}{{UNIT}};' );
		$this->slider( 'nav_arrow_background_size', 'Arrows Size', 62, $arrows, 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; line-height: {{SIZE}}{{UNIT}};' );
		$this->slider( 'nav_arrow_icon_size', 'Arrows Icon Size', 24, $arrows, 'font-size: {{SIZE}}{{UNIT}};' );
		$this->color( 'nav_arrow_background', 'Arrows Background', '#ffffff', $arrows, 'background-color' );
		$this->color( 'nav_arrow_icon_color', 'Arrows Color', '#000000', $arrows, 'color' );
		$this->color( 'nav_arrow_background_hover', 'Arrows Background Hover', '#000000', $arrows_hover, 'background-color' );
		$this->color( 'nav_arrow_icon_color_hover', 'Arrows Color Hover', '#ffffff', $arrows_hover, 'color' );
		$this->slider( 'nav_dots_size', 'Dots Size', 8, '.owl-dot', 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' );
		$this->color( 'dot_color', 'Dots Color', '#c6c6c6', '.owl-dot', 'background-color' );
		$this->slider( 'dot_space_between', 'Dots Space Between', 5, '.owl-dot', 'margin:0px {{SIZE}}{{UNIT}};' );
		$this->color( 'dot_active_color', 'Dots Active Color', '#000000', '.owl-dot.active', 'background-color' );
		$this->slider( 'dot_spacing', 'Dots Spacing', 20, '.owl-dots', 'margin-top: {{SIZE}}{{UNIT}};' );
		$this->end_controls_section();
	}

	private static function bool( $value ) {
		return 'true' === $value ? 'true' : 'false';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$id = 'uc_card_carousel_elementor' . $this->get_id();
		$on = function ( $key ) use ( $s ) {
			return isset( $s[ $key ] ) && 'true' === $s[ $key ];
		};
		$int = function ( $key, $default ) use ( $s ) {
			return isset( $s[ $key ] ) && '' !== $s[ $key ] ? (int) $s[ $key ] : $default;
		};
		?>
<style type="text/css">/* widget: Card Carousel */
#<?php echo $id; ?> *{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;}
#<?php echo $id; ?> .uc_classic_carousel_container_holder{display:block;position:relative;width:100%;box-sizing:border-box;}
.uc_classic_carousel_content h2{font-size:21px;}
.uc_classic_carousel .uc_classic_carousel_border{height:4px;display:block;width:100px;margin:0 auto;}
.uc_classic_carousel .uc_classic_carousel_placeholder img{width:100%;object-fit:cover;}
.uc_classic_carousel .uc_classic_carousel_content{display:flex;flex-flow:column nowrap;}
.uc_classic_carousel .ue_button{margin-top:auto;}
.uc_classic_carousel .uc_classic_carousel_content .uc_more_btn{display:inline-block;text-decoration:none;transition:all 0.5s ease;}
#<?php echo $id; ?> .owl-dots{overflow:hidden;display:<?php echo $on( 'show_bullets' ) ? 'block' : 'none'; ?> !important;text-align:<?php echo esc_attr( $s['dot_alignment'] ); ?>;}
#<?php echo $id; ?> .owl-dot{border-radius:50%;display:inline-block;}
#<?php echo $id; ?> .owl-nav .owl-prev{position:absolute;left:<?php echo $int( 'nav_arrow_spacing', 20 ); ?>px;display:inline-block;text-align:center;}
#<?php echo $id; ?> .owl-nav .owl-next{position:absolute;right:<?php echo $int( 'nav_arrow_spacing', 20 ); ?>px;display:inline-block;text-align:center;}
#<?php echo $id; ?> .owl-nav .owl-prev:after{content:"←";}
#<?php echo $id; ?> .owl-nav .owl-next:after{content:"→";}
<?php if ( ! $on( 'transition_easing' ) ) : ?>#<?php echo $id; ?> .owl-stage{transition-timing-function:linear!important;}<?php endif; ?>
</style>
<div class="uc_classic_carousel" id="<?php echo esc_attr( $id ); ?>" style="direction:ltr;"><div class="uc_carousel owl-carousel">
		<?php
		foreach ( (array) $s['uc_items'] as $item ) {
			$link = ! empty( $item['link']['url'] ) ? $item['link']['url'] : '#';
			$image_id = ! empty( $item['image']['id'] ) ? (int) $item['image']['id'] : 0;
			$image_url = $image_id ? wp_get_attachment_url( $image_id ) : ( $item['image']['url'] ?? '' );
			$alt = $image_id ? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '';
			if ( '' === $alt && $image_id ) {
				$alt = get_the_title( $image_id );
			}
			echo '<div class="uc_classic_carousel_container_holder"><div class="ue-carousel-item"> ';
			if ( $on( 'show_top_decoration' ) ) {
				echo '<span class="uc_classic_carousel_border" style=""></span>';
			}
			if ( $on( 'show_image' ) ) {
				echo '<div class="uc_classic_carousel_placeholder"> ';
				if ( $on( 'image_link' ) ) {
					echo '<a href="' . esc_url( $link ) . '" style="display:block">';
				}
				echo '<img decoding="async" src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $alt ) . '">';
				if ( $on( 'image_link' ) ) {
					echo '</a>';
				}
				echo '</div>';
			}
			echo '<div class="uc_classic_carousel_content" style="text-align:' . esc_attr( $s['align'] ) . '; direction:' . esc_attr( $s['direction'] ) . ';">';
			if ( $on( 'show_title' ) ) {
				echo '<h2  class="card_carousel_title">' . wp_kses_post( $item['title'] ) . '</h2>';
			}
			if ( $on( 'show_text' ) ) {
				echo '<p class="card_carousel_text">' . wp_kses_post( $item['content'] ) . '</p>';
			}
			if ( $on( 'show_button' ) ) {
				echo '<div class="ue_button"><a class="uc_more_btn" href="' . esc_url( $link ) . '">' . wp_kses_post( $item['btn_text'] ) . '</a></div>';
			}
			echo '</div></div></div>';
		}
		?>
</div></div>
<script>
jQuery(function () {
	jQuery('#<?php echo $id; ?> > .owl-carousel').owlCarousel({
		loop: <?php echo self::bool( $s['loop'] ); ?>,
		autoplay: <?php echo self::bool( $s['autoplay'] ); ?>,
		autoplayHoverPause: <?php echo self::bool( $s['autoplayhoverpause'] ); ?>,
		margin: <?php echo $int( 'margin_between_slides', 30 ); ?>,
		navText: ['', ''],
		nav: <?php echo self::bool( $s['show_arrows'] ); ?>,
		autoplayTimeout: <?php echo $int( 'autoplay_interval', 3000 ); ?>,
		smartSpeed: <?php echo $int( 'transition_speed', 1000 ); ?>,
		responsive: {
			0: { items: <?php echo $int( 'number_of_items_mobile', 1 ); ?>, slideBy: <?php echo $int( 'slides_to_scroll', 1 ); ?> },
			768: { items: <?php echo $int( 'number_of_items_tablet', 2 ); ?>, slideBy: <?php echo $int( 'slides_to_scroll_tablet', 1 ); ?> },
			980: { items: <?php echo $int( 'number_of_items', 3 ); ?>, slideBy: <?php echo $int( 'slides_to_scroll_mobile', 1 ); ?> }
		}
	});
});
</script>
		<?php
	}
}
