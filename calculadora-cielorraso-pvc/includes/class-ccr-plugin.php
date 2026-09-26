<?php
/**
 * Clase principal: arranca todos los módulos del plugin.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

final class CCR_Plugin {

	/** @var CCR_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'calculadora-cielorraso-pvc', false, dirname( CCR_BASENAME ) . '/languages' );

		// Migraciones automáticas al actualizar el plugin (sin reactivar).
		if ( get_option( 'ccr_db_version' ) !== CCR_DB_VERSION ) {
			CCR_Activator::activate();
		}

		new CCR_Shortcode();
		new CCR_Ajax();
		new CCR_Block();
		CCR_Elementor::init();

		if ( is_admin() ) {
			new CCR_Admin();
		}
	}
}
