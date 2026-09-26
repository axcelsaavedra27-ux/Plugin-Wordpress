<?php
/**
 * Bloque Gutenberg ccr/calculadora (renderizado dinámico en PHP).
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Block {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_style( 'ccr-block-editor', CCR_URL . 'assets/css/ccr-public.css', array(), CCR_VERSION );

		register_block_type(
			CCR_PATH . 'blocks/calculadora',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * @param array $attributes Atributos del bloque.
	 * @return string
	 */
	public function render( $attributes ) {
		$atts = array();
		if ( ! empty( $attributes['hideHeader'] ) ) {
			$atts['title']    = '';
			$atts['subtitle'] = '';
		} else {
			if ( ! empty( $attributes['title'] ) ) {
				$atts['title'] = sanitize_text_field( $attributes['title'] );
			}
			if ( ! empty( $attributes['subtitle'] ) ) {
				$atts['subtitle'] = sanitize_text_field( $attributes['subtitle'] );
			}
		}
		$wrapper = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : '';
		return '<div ' . $wrapper . '>' . CCR_Shortcode::render( $atts ) . '</div>';
	}
}
