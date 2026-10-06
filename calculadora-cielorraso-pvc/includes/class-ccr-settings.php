<?php
/**
 * Ajustes generales (wp_options: ccr_settings).
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Settings {

	const OPTION = 'ccr_settings';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Esquema: clave => array( tipo, valor por defecto, [opciones] ).
	 */
	public static function schema() {
		return array(
			// Cálculo.
			'general_waste_pct'      => array( 'float', 0 ),
			'default_alto'           => array( 'float', 0.30 ),
			'min_dim'                => array( 'float', 0.20 ),
			'max_dim'                => array( 'float', 100 ),
			'max_alto'               => array( 'float', 15 ),
			'max_combinations'       => array( 'int', 300 ),

			// Precios / cotización.
			'show_prices'            => array( 'bool', 1 ),
			'currency_symbol'        => array( 'text', '$' ),
			'currency_position'      => array( 'select', 'after', array( 'before', 'after' ) ),
			'price_decimals'         => array( 'int', 0 ),
			'decimal_sep'            => array( 'text', ',' ),
			'thousands_sep'          => array( 'text', '.' ),
			'total_label'            => array( 'text', 'TOTAL (IVA incluido)' ),
			'prices_note'            => array( 'textarea', 'Los precios son aproximados y dependen de la disponibilidad de la mercadería, de la modalidad de pago y de posibles variaciones de precio.' ),

			// Formulario público.
			'eyebrow'                => array( 'text', 'Herramienta para instaladores y comercios' ),
			'title'                  => array( 'text', 'Calculadora de cielorraso PVC' ),
			'subtitle'               => array( 'textarea', 'Ingresá las medidas y obtené al instante la lista de materiales, el plano de colocación y una cotización estimada.' ),
			'label_largo'            => array( 'text', 'Largo del ambiente (m)' ),
			'label_ancho'            => array( 'text', 'Ancho del ambiente (m)' ),
			'label_alto'             => array( 'text', 'Altura de descuelgue / cámara de aire (m)' ),
			'label_install'          => array( 'text', 'Tipo de instalación' ),
			'label_direction'        => array( 'text', 'Sentido de colocación de las láminas' ),
			'label_observations'     => array( 'text', 'Observaciones' ),
			'label_dir_largo'        => array( 'text', 'A lo largo' ),
			'label_dir_ancho'        => array( 'text', 'A lo ancho' ),
			'label_dir_auto'         => array( 'text', 'Más económica (automático)' ),
			'label_variant_auto'     => array( 'text', 'Automático (más conveniente)' ),
			'button_text'            => array( 'text', 'Calcular materiales' ),
			'show_alto'              => array( 'bool', 1 ),
			'show_observations'      => array( 'bool', 1 ),
			'enable_auto_direction'  => array( 'bool', 1 ),
			'default_direction'      => array( 'select', 'auto', array( 'largo', 'ancho', 'auto' ) ),

			// Leads.
			'leads_enabled'          => array( 'bool', 0 ),
			'lead_title'             => array( 'text', 'Completá tus datos para ver el resultado' ),
			'lead_require_name'      => array( 'bool', 1 ),
			'lead_require_company'   => array( 'bool', 0 ),
			'lead_require_phone'     => array( 'bool', 1 ),
			'lead_require_email'     => array( 'bool', 1 ),
			'lead_consent_text'      => array( 'textarea', 'Acepto que mis datos sean utilizados para contactarme en relación a esta cotización.' ),
			'lead_notify'            => array( 'bool', 0 ),
			'lead_notify_email'      => array( 'email', '' ),

			// Exportación.
			'enable_pdf'             => array( 'bool', 1 ),
			'enable_print'           => array( 'bool', 1 ),
			'enable_excel'           => array( 'bool', 1 ),

			// Carrito de WooCommerce y WhatsApp.
			'cart_enabled'           => array( 'bool', 0 ),
			'cart_button_text'       => array( 'text', 'Agregar materiales al carrito' ),
			'cart_redirect'          => array( 'select', 'cart', array( 'cart', 'checkout', 'stay' ) ),
			'whatsapp_enabled'       => array( 'bool', 0 ),
			'whatsapp_number'        => array( 'text', '' ),
			'whatsapp_button_text'   => array( 'text', 'Enviar presupuesto por WhatsApp' ),
			'whatsapp_intro'         => array( 'textarea', 'Hola, quiero consultar por este presupuesto de cielorraso PVC:' ),

			// Empresa (encabezado PDF / impresión).
			'company_name'           => array( 'text', '' ),
			'company_phone'          => array( 'text', '' ),
			'company_email'          => array( 'email', '' ),
			'company_address'        => array( 'text', '' ),
			'pdf_footer'             => array( 'textarea', 'Presupuesto orientativo generado automáticamente.' ),

			// Diseño.
			'primary_color'          => array( 'color', '#292A87' ),
			'accent_color'           => array( 'color', '#AE2F45' ),
			'border_radius'          => array( 'int', 24 ),
			'load_fonts'             => array( 'bool', 1 ),

			// Mantenimiento.
			'delete_data_on_uninstall' => array( 'bool', 0 ),
		);
	}

	public static function defaults() {
		$out = array();
		foreach ( self::schema() as $key => $def ) {
			$out[ $key ] = $def[1];
		}
		$out['company_name'] = get_bloginfo( 'name' );
		return $out;
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Sanitiza un array de entrada (normalmente $_POST) según el esquema.
	 * Las claves booleanas ausentes se guardan como 0 (checkbox desmarcado).
	 *
	 * @param array $input Datos sin sanitizar.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = self::all();
		$out     = array();

		foreach ( self::schema() as $key => $def ) {
			$type = $def[0];
			$raw  = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : null;

			switch ( $type ) {
				case 'bool':
					$out[ $key ] = empty( $raw ) ? 0 : 1;
					break;
				case 'float':
					$out[ $key ] = null === $raw ? $current[ $key ] : (float) str_replace( ',', '.', (string) $raw );
					break;
				case 'int':
					$out[ $key ] = null === $raw ? $current[ $key ] : (int) $raw;
					break;
				case 'textarea':
					$out[ $key ] = null === $raw ? $current[ $key ] : sanitize_textarea_field( $raw );
					break;
				case 'email':
					$out[ $key ] = null === $raw ? $current[ $key ] : sanitize_email( $raw );
					break;
				case 'color':
					$color       = null === $raw ? '' : sanitize_hex_color( $raw );
					$out[ $key ] = $color ? $color : $current[ $key ];
					break;
				case 'select':
					$out[ $key ] = ( null !== $raw && in_array( $raw, $def[2], true ) ) ? $raw : $current[ $key ];
					break;
				default:
					$out[ $key ] = null === $raw ? $current[ $key ] : sanitize_text_field( $raw );
			}
		}

		// Límites razonables.
		$out['general_waste_pct'] = max( 0, min( 500, $out['general_waste_pct'] ) );
		$out['price_decimals']    = max( 0, min( 4, $out['price_decimals'] ) );
		$out['border_radius']     = max( 0, min( 40, $out['border_radius'] ) );
		$out['max_combinations']  = max( 1, min( 5000, $out['max_combinations'] ) );
		$out['whatsapp_number']   = preg_replace( '/\D/', '', (string) $out['whatsapp_number'] );
		$out['min_dim']           = max( 0.01, $out['min_dim'] );
		$out['max_dim']           = max( $out['min_dim'], $out['max_dim'] );

		return $out;
	}

	public static function update( array $values ) {
		update_option( self::OPTION, $values );
		self::$cache = null;
	}

	/**
	 * Formatea un importe según los ajustes de moneda.
	 *
	 * @param float $amount Importe.
	 * @return string
	 */
	public static function format_price( $amount ) {
		$s   = self::all();
		$num = number_format( (float) $amount, (int) $s['price_decimals'], $s['decimal_sep'], $s['thousands_sep'] );
		return 'before' === $s['currency_position'] ? $s['currency_symbol'] . ' ' . $num : $num . ' ' . $s['currency_symbol'];
	}
}
