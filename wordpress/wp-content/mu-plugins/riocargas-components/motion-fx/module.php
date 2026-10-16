<?php
// Portado do Elementor Pro 3.7.1 (modules/motion-fx/module.php, GPLv3).

namespace RioCargas\Components\MotionFX;

use Elementor\Controls_Manager;
use Elementor\Element_Base;
use Elementor\Element_Column;
use Elementor\Element_Section;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Module {

	public function __construct() {
		add_action( 'elementor/controls/register', [ $this, 'register_controls_group' ] );

		add_action( 'elementor/element/section/section_effects/after_section_start', [ $this, 'add_controls_group_to_element' ] );
		add_action( 'elementor/element/container/section_effects/after_section_start', [ $this, 'add_controls_group_to_element' ] );
		add_action( 'elementor/element/column/section_effects/after_section_start', [ $this, 'add_controls_group_to_element' ] );
		add_action( 'elementor/element/common/section_effects/after_section_start', [ $this, 'add_controls_group_to_element' ] );

		add_action( 'elementor/element/section/section_background/before_section_end', [ $this, 'add_controls_group_to_element_background' ] );
		add_action( 'elementor/element/container/section_background/before_section_end', [ $this, 'add_controls_group_to_element_background' ] );
		add_action( 'elementor/element/column/section_style/before_section_end', [ $this, 'add_controls_group_to_element_background' ] );

		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_assets' ] );
		add_action( 'elementor/frontend/after_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_assets() {
		wp_register_style( 'rc-motion-fx', RC_COMPONENTS_URL . 'assets/motion-fx/motion-fx.css', [], RC_COMPONENTS_VERSION );
		wp_register_script( 'rc-motion-fx', RC_COMPONENTS_URL . 'assets/motion-fx/motion-fx.js', [ 'elementor-frontend' ], RC_COMPONENTS_VERSION, true );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'rc-motion-fx' );
		wp_enqueue_script( 'rc-motion-fx' );
	}

	public function register_controls_group( Controls_Manager $controls_manager ) {
		$controls_manager->add_group_control( Controls_Group::get_type(), new Controls_Group() );
	}

	public function add_controls_group_to_element( Element_Base $element ) {
		$exclude = [];

		$selector = '{{WRAPPER}}';

		if ( $element instanceof Element_Section ) {
			$exclude[] = 'motion_fx_mouse';
		} elseif ( $element instanceof Element_Column ) {
			$selector .= ' > .elementor-widget-wrap';
		} elseif ( $element instanceof Widget_Base ) {
			$selector .= ' > .elementor-widget-container';
		}

		$element->add_group_control(
			Controls_Group::get_type(),
			[
				'name' => 'motion_fx',
				'selector' => $selector,
				'exclude' => $exclude,
			]
		);
	}

	public function add_controls_group_to_element_background( Element_Base $element ) {
		$element->start_injection( [
			'of' => 'background_bg_width_mobile',
		] );

		$element->add_group_control(
			Controls_Group::get_type(),
			[
				'name' => 'background_motion_fx',
				'exclude' => [
					'rotateZ_effect',
					'tilt_effect',
					'transform_origin_x',
					'transform_origin_y',
				],
			]
		);

		$options = [
			'separator' => 'before',
			'conditions' => [
				'relation' => 'or',
				'terms' => [
					[
						'name' => 'background_background',
						'value' => 'classic',
					],
					[
						'terms' => [
							[
								'name' => 'background_background',
								'value' => 'gradient',
							],
							[
								'name' => 'background_color',
								'operator' => '!==',
								'value' => '',
							],
							[
								'name' => 'background_color_b',
								'operator' => '!==',
								'value' => '',
							],
						],
					],
				],
			],
		];

		$element->update_control( 'background_motion_fx_motion_fx_scrolling', $options );

		$element->update_control( 'background_motion_fx_motion_fx_mouse', $options );

		$element->end_injection();
	}
}
