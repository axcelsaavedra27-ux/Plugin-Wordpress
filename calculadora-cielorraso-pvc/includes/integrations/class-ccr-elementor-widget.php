<?php
/**
 * Widget de Elementor. Solo se carga cuando Elementor está activo.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'ccr_calculadora';
	}

	public function get_title() {
		return __( 'Calculadora cielorraso PVC', 'calculadora-cielorraso-pvc' );
	}

	public function get_icon() {
		return 'eicon-calculator';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'cielorraso', 'pvc', 'calculadora', 'materiales' );
	}

	public function get_style_depends() {
		return array( 'ccr-public' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Calculadora', 'calculadora-cielorraso-pvc' ) ) );
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Título', 'calculadora-cielorraso-pvc' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Vacío = título de Ajustes.', 'calculadora-cielorraso-pvc' ),
			)
		);
		$this->add_control(
			'subtitle',
			array(
				'label' => __( 'Subtítulo', 'calculadora-cielorraso-pvc' ),
				'type'  => \Elementor\Controls_Manager::TEXTAREA,
			)
		);
		$this->add_control(
			'hide_header',
			array(
				'label' => __( 'Ocultar encabezado', 'calculadora-cielorraso-pvc' ),
				'type'  => \Elementor\Controls_Manager::SWITCHER,
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$atts     = array();
		if ( ! empty( $settings['hide_header'] ) && 'yes' === $settings['hide_header'] ) {
			$atts['title']    = '';
			$atts['subtitle'] = '';
		} else {
			if ( ! empty( $settings['title'] ) ) {
				$atts['title'] = sanitize_text_field( $settings['title'] );
			}
			if ( ! empty( $settings['subtitle'] ) ) {
				$atts['subtitle'] = sanitize_textarea_field( $settings['subtitle'] );
			}
		}
		echo CCR_Shortcode::render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- la plantilla escapa cada valor.
	}
}
