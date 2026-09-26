<?php
/**
 * Integración opcional con Elementor: widget nativo "Calculadora de cielorraso PVC".
 * (El shortcode también funciona en el widget "Shortcode" de Elementor.)
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Elementor {

	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager Gestor de widgets.
	 */
	public static function register_widget( $manager ) {
		require_once CCR_PATH . 'includes/integrations/class-ccr-elementor-widget.php';
		$manager->register( new CCR_Elementor_Widget() );
	}
}
