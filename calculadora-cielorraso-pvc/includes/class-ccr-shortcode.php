<?php
/**
 * Shortcode [calculadora_cielorraso] y carga de assets públicos.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Shortcode {

	const TAG = 'calculadora_cielorraso';

	/** @var int Contador de instancias en la página. */
	private static $instance = 0;

	public function __construct() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style( 'ccr-public', CCR_URL . 'assets/css/ccr-public.css', array(), CCR_VERSION );
		wp_register_script( 'ccr-export', CCR_URL . 'assets/js/ccr-export.js', array(), CCR_VERSION, true );
		wp_register_script( 'ccr-public', CCR_URL . 'assets/js/ccr-public.js', array( 'ccr-export' ), CCR_VERSION, true );

		// Carga temprana del CSS si la página contiene el shortcode o el bloque (evita parpadeo).
		$post = get_post();
		if ( $post && ( has_shortcode( $post->post_content, self::TAG ) || has_block( 'ccr/calculadora', $post ) ) ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		static $done = false;
		if ( $done ) {
			return;
		}
		if ( ! wp_script_is( 'ccr-public', 'registered' ) ) {
			// Llamado fuera de wp_enqueue_scripts (p. ej. vista previa de Elementor).
			wp_register_style( 'ccr-public', CCR_URL . 'assets/css/ccr-public.css', array(), CCR_VERSION );
			wp_register_script( 'ccr-export', CCR_URL . 'assets/js/ccr-export.js', array(), CCR_VERSION, true );
			wp_register_script( 'ccr-public', CCR_URL . 'assets/js/ccr-public.js', array( 'ccr-export' ), CCR_VERSION, true );
		}
		$done = true;
		$s    = CCR_Settings::all();

		if ( $s['load_fonts'] ) {
			wp_enqueue_style( 'ccr-fonts', 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_style( 'ccr-public' );
		wp_enqueue_script( 'ccr-public' );
		wp_localize_script(
			'ccr-public',
			'CCR_PUBLIC',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( CCR_Ajax::NONCE ),
				'leads'    => (bool) $s['leads_enabled'],
				'export'   => array(
					'pdf'   => (bool) $s['enable_pdf'],
					'print' => (bool) $s['enable_print'],
					'excel' => (bool) $s['enable_excel'],
				),
				'cart'     => array(
					'enabled' => CCR_Ajax::cart_available(),
					'label'   => $s['cart_button_text'],
				),
				'whatsapp' => array(
					'enabled' => $s['whatsapp_enabled'] && '' !== $s['whatsapp_number'],
					'number'  => $s['whatsapp_number'],
					'label'   => $s['whatsapp_button_text'],
					'intro'   => $s['whatsapp_intro'],
				),
				'company'  => array(
					'name'    => $s['company_name'],
					'phone'   => $s['company_phone'],
					'email'   => $s['company_email'],
					'address' => $s['company_address'],
					'footer'  => $s['pdf_footer'],
				),
				'currency' => array(
					'symbol'    => $s['currency_symbol'],
					'position'  => $s['currency_position'],
					'decimals'  => (int) $s['price_decimals'],
					'decimal'   => $s['decimal_sep'],
					'thousands' => $s['thousands_sep'],
				),
				'primary'  => $s['primary_color'],
				'brand'    => array(
					'logos' => self::brand_logos(),
				),
				'i18n'     => array(
					'area'         => __( 'Área total', 'calculadora-cielorraso-pvc' ),
					'perimeter'    => __( 'Perímetro', 'calculadora-cielorraso-pvc' ),
					'room'         => __( 'Ambiente', 'calculadora-cielorraso-pvc' ),
					'installType'  => __( 'Instalación', 'calculadora-cielorraso-pvc' ),
					'direction'    => __( 'Sentido de las láminas', 'calculadora-cielorraso-pvc' ),
					'height'       => __( 'Descuelgue', 'calculadora-cielorraso-pvc' ),
					'material'     => __( 'Material', 'calculadora-cielorraso-pvc' ),
					'code'         => __( 'Código', 'calculadora-cielorraso-pvc' ),
					'unit'         => __( 'Unidad', 'calculadora-cielorraso-pvc' ),
					'qty'          => __( 'Cantidad', 'calculadora-cielorraso-pvc' ),
					'waste'        => __( 'Desperdicio', 'calculadora-cielorraso-pvc' ),
					'unitPrice'    => __( 'Precio unit.', 'calculadora-cielorraso-pvc' ),
					'subtotal'     => __( 'Subtotal', 'calculadora-cielorraso-pvc' ),
					'total'        => __( 'Total', 'calculadora-cielorraso-pvc' ),
					'materials'    => __( 'Materiales requeridos', 'calculadora-cielorraso-pvc' ),
					'summary'      => __( 'Resumen final', 'calculadora-cielorraso-pvc' ),
					'items'        => __( 'Ítems', 'calculadora-cielorraso-pvc' ),
					'observations' => __( 'Observaciones', 'calculadora-cielorraso-pvc' ),
					'alongSide'    => __( 'paralelas al lado de', 'calculadora-cielorraso-pvc' ),
					'auto'         => __( 'elegido automáticamente', 'calculadora-cielorraso-pvc' ),
					'pdf'          => __( 'Exportar PDF', 'calculadora-cielorraso-pvc' ),
					'print'        => __( 'Imprimir', 'calculadora-cielorraso-pvc' ),
					'excel'        => __( 'Exportar Excel', 'calculadora-cielorraso-pvc' ),
					'recalc'       => __( 'Modificar datos', 'calculadora-cielorraso-pvc' ),
					'calculating'  => __( 'Calculando…', 'calculadora-cielorraso-pvc' ),
					'error'        => __( 'No se pudo calcular. Intentá nuevamente.', 'calculadora-cielorraso-pvc' ),
					'required'     => __( 'Completá este campo.', 'calculadora-cielorraso-pvc' ),
					'invalidNum'   => __( 'Ingresá un número válido.', 'calculadora-cielorraso-pvc' ),
					'none'         => __( 'Ninguno', 'calculadora-cielorraso-pvc' ),
					'quoteTitle'   => __( 'Presupuesto de materiales – Cielorraso PVC', 'calculadora-cielorraso-pvc' ),
					'date'         => __( 'Fecha', 'calculadora-cielorraso-pvc' ),
					'wasteApplied' => __( 'Desperdicio aplicado', 'calculadora-cielorraso-pvc' ),
					'noItems'      => __( 'No se requieren materiales para estos datos.', 'calculadora-cielorraso-pvc' ),
					'listTitle'    => __( 'Lista de materiales', 'calculadora-cielorraso-pvc' ),
					'qtyShort'     => __( 'Cant.', 'calculadora-cielorraso-pvc' ),
					'unitShort'    => __( 'Unitario', 'calculadora-cielorraso-pvc' ),
					'costPerM2'    => __( 'Costo por m²', 'calculadora-cielorraso-pvc' ),
					'plan'         => __( 'Plano de colocación', 'calculadora-cielorraso-pvc' ),
					'roomOf'       => __( 'Ambiente de', 'calculadora-cielorraso-pvc' ),
					'boardsAlong'  => __( 'láminas en el sentido de', 'calculadora-cielorraso-pvc' ),
					'cheapest'     => __( 'opción más económica', 'calculadora-cielorraso-pvc' ),
					'addingCart'   => __( 'Agregando al carrito…', 'calculadora-cielorraso-pvc' ),
					'viewCart'     => __( 'Ver carrito', 'calculadora-cielorraso-pvc' ),
				),
			)
		);

		$css = sprintf(
			'.ccr-calc{--ccr-primary:%1$s;--ccr-accent:%2$s;--ccr-radius:%3$dpx;}%4$s',
			esc_attr( $s['primary_color'] ),
			esc_attr( $s['accent_color'] ),
			(int) $s['border_radius'],
			$s['load_fonts'] ? '' : '.ccr-calc{--ccr-font-display:inherit;--ccr-font-body:inherit;}'
		);
		wp_add_inline_style( 'ccr-public', $css );
	}

	/**
	 * Renderiza la calculadora.
	 *
	 * @param array|string $atts Atributos: title, subtitle, class.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		$s    = CCR_Settings::all();
		$atts = shortcode_atts(
			array(
				'eyebrow'  => $s['eyebrow'],
				'title'    => $s['title'],
				'subtitle' => $s['subtitle'],
				'class'    => '',
			),
			$atts,
			self::TAG
		);

		self::enqueue();
		self::$instance++;

		$calculator = new CCR_Calculator();
		$config     = $calculator->form_config();
		$uid        = 'ccr-' . self::$instance;

		$template = locate_template( array( 'calculadora-cielorraso/calculator.php' ) );
		if ( ! $template ) {
			$template = CCR_PATH . 'templates/calculator.php';
		}

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Logos de Konex que se imprimen arriba del PDF.
	 *
	 * Se leen de la carpeta fija assets/img/logos/ del plugin (orden alfabético) y no
	 * dependen de ningún ajuste del panel, para que no puedan quitarse desde WordPress.
	 *
	 * @return string[] URLs de las imágenes.
	 */
	private static function brand_logos() {
		$files = glob( CCR_PATH . 'assets/img/logos/*' );
		if ( ! $files ) {
			return array();
		}
		$files = array_filter(
			$files,
			function ( $file ) {
				return is_file( $file ) && in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), array( 'png', 'jpg', 'jpeg', 'webp', 'gif' ), true );
			}
		);
		sort( $files );
		$urls = array();
		foreach ( $files as $file ) {
			$urls[] = CCR_URL . 'assets/img/logos/' . rawurlencode( basename( $file ) ) . '?v=' . filemtime( $file );
		}
		return $urls;
	}
}
