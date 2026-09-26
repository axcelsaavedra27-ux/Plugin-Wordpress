<?php
/**
 * Plugin Name:       Calculadora de Cielorraso PVC
 * Plugin URI:        https://example.com/calculadora-cielorraso-pvc
 * Description:       Calculadora de materiales para cielorrasos PVC totalmente configurable: materiales, fórmulas, desperdicios, redondeos, cotización, leads y exportación (PDF / Excel / impresión). Shortcode [calculadora_cielorraso] y bloque Gutenberg.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Konex
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       calculadora-cielorraso-pvc
 * Domain Path:       /languages
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

define( 'CCR_VERSION', '1.2.0' );
define( 'CCR_DB_VERSION', '1.1.0' );
define( 'CCR_FILE', __FILE__ );
define( 'CCR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CCR_URL', plugin_dir_url( __FILE__ ) );
define( 'CCR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader simple para las clases CCR_*.
 * CCR_Admin_Crud -> includes/admin/class-ccr-admin-crud.php o includes/class-ccr-admin-crud.php
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'CCR_' ) ) {
			return;
		}
		$file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		foreach ( array( 'includes/', 'admin/', 'includes/integrations/' ) as $dir ) {
			$path = CCR_PATH . $dir . $file;
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

register_activation_hook( __FILE__, array( 'CCR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CCR_Activator', 'deactivate' ) );

// Se inicia en "init" (prioridad 0) para que las traducciones estén disponibles (WP 6.7+).
add_action( 'init', array( 'CCR_Plugin', 'instance' ), 0 );
