<?php
/**
 * Endpoint AJAX público: cálculo + registro de leads.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Ajax {

	const NONCE = 'ccr_public';

	/** Máximo de cálculos por IP cada 10 minutos. */
	const RATE_LIMIT = 60;

	public function __construct() {
		add_action( 'wp_ajax_ccr_calculate', array( $this, 'calculate' ) );
		add_action( 'wp_ajax_nopriv_ccr_calculate', array( $this, 'calculate' ) );
		add_action( 'wp_ajax_ccr_add_to_cart', array( $this, 'add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_ccr_add_to_cart', array( $this, 'add_to_cart' ) );
	}

	/** El botón del carrito solo funciona con WooCommerce activo y la opción habilitada. */
	public static function cart_available() {
		return CCR_Settings::get( 'cart_enabled' ) && function_exists( 'WC' ) && function_exists( 'wc_get_product_id_by_sku' );
	}

	public function calculate() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La sesión expiró. Recargá la página e intentá nuevamente.', 'calculadora-cielorraso-pvc' ) ), 403 );
		}

		if ( ! $this->within_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'Demasiadas solicitudes. Esperá unos minutos.', 'calculadora-cielorraso-pvc' ) ), 429 );
		}

		// Honeypot anti-spam.
		if ( ! empty( $_POST['ccr_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado arriba.
			wp_send_json_error( array( 'message' => __( 'Solicitud inválida.', 'calculadora-cielorraso-pvc' ) ), 400 );
		}

		$data = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, se sanitiza campo por campo en CCR_Calculator::normalize().
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'calculadora-cielorraso-pvc' ) ), 400 );
		}

		$settings = CCR_Settings::all();
		$lead_id  = 0;
		$token    = '';

		if ( $settings['leads_enabled'] ) {
			$lead = $this->resolve_lead( isset( $data['lead'] ) && is_array( $data['lead'] ) ? $data['lead'] : array(), isset( $data['lead_token'] ) ? (string) $data['lead_token'] : '' );
			if ( is_wp_error( $lead ) ) {
				wp_send_json_error(
					array(
						'message'    => $lead->get_error_message(),
						'lead_error' => true,
					),
					422
				);
			}
			$lead_id = (int) $lead['id'];
			$token   = $lead['token'];
		}

		$calculator = new CCR_Calculator();
		$result     = $calculator->calculate( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		$input = $result['_input'];
		unset( $result['_input'], $result['_cart'] );

		if ( $lead_id ) {
			CCR_Repository::get( 'calculations' )->insert(
				array(
					'lead_id'      => $lead_id,
					'inputs'       => wp_json_encode( $input ),
					'results'      => wp_json_encode( $result ),
					'area'         => $result['summary']['area'],
					'total'        => isset( $result['total'] ) ? $result['total'] : 0,
					'observations' => $input['observations'],
				)
			);
		}

		$result['lead_token'] = $token;
		wp_send_json_success( $result );
	}

	/**
	 * Agrega al carrito de WooCommerce los materiales del presupuesto.
	 *
	 * No confía en las cantidades del navegador: recibe los mismos datos del
	 * formulario, vuelve a calcular en el servidor y vincula cada material con
	 * el producto de la tienda por SKU. El precio cobrado es el de WooCommerce.
	 */
	public function add_to_cart() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'La sesión expiró. Recargá la página e intentá nuevamente.', 'calculadora-cielorraso-pvc' ) ), 403 );
		}
		if ( ! self::cart_available() ) {
			wp_send_json_error( array( 'message' => __( 'La compra en línea no está disponible.', 'calculadora-cielorraso-pvc' ) ), 400 );
		}
		if ( ! $this->within_rate_limit() ) {
			wp_send_json_error( array( 'message' => __( 'Demasiadas solicitudes. Esperá unos minutos.', 'calculadora-cielorraso-pvc' ) ), 429 );
		}

		$data = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, se sanitiza campo por campo en CCR_Calculator::normalize().
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Datos inválidos.', 'calculadora-cielorraso-pvc' ) ), 400 );
		}

		// Con leads activados, solo quien ya dejó sus datos puede usar el carrito.
		if ( CCR_Settings::get( 'leads_enabled' ) ) {
			$token = isset( $data['lead_token'] ) ? (string) $data['lead_token'] : '';
			if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) || ! CCR_Repository::get( 'leads' )->find_by( 'token', $token ) ) {
				wp_send_json_error( array( 'message' => __( 'Volvé a calcular para agregar los materiales al carrito.', 'calculadora-cielorraso-pvc' ) ), 422 );
			}
		}

		$calculator = new CCR_Calculator();
		$result     = $calculator->calculate( $data );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo abrir el carrito.', 'calculadora-cielorraso-pvc' ) ), 500 );
		}

		$added   = 0;
		$missing = array();
		foreach ( $result['_cart'] as $item ) {
			$product_id = '' !== $item['sku'] ? wc_get_product_id_by_sku( $item['sku'] ) : 0;
			$product_id = (int) apply_filters( 'ccr_cart_product_id', $product_id, $item );
			$product    = $product_id ? wc_get_product( $product_id ) : null;
			if ( ! $product || ! $product->is_purchasable() ) {
				$missing[] = $item['name'];
				continue;
			}

			// La cantidad del presupuesto se expresa en unidades de venta (ej. cajas de 100 tornillos).
			$qty = (int) ceil( $item['qty'] / ( $item['price_qty'] > 0 ? $item['price_qty'] : 1 ) - 1e-9 );
			$qty = (int) apply_filters( 'ccr_cart_quantity', max( 1, $qty ), $item, $product );

			if ( $product->is_type( 'variation' ) ) {
				$key = WC()->cart->add_to_cart( $product->get_parent_id(), $qty, $product->get_id(), $product->get_variation_attributes() );
			} else {
				$key = WC()->cart->add_to_cart( $product->get_id(), $qty );
			}
			if ( $key ) {
				$added++;
			} else {
				$missing[] = $item['name'];
			}
		}

		// Los errores de stock de WooCommerce no se muestran en otra página: ya se informan acá.
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Ninguno de los materiales está disponible en la tienda en este momento. Consultanos por WhatsApp o teléfono.', 'calculadora-cielorraso-pvc' ) ), 422 );
		}

		WC()->cart->calculate_totals();
		if ( WC()->session && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
			WC()->session->set_customer_session_cookie( true );
		}

		$redirect = CCR_Settings::get( 'cart_redirect' );
		$url      = 'checkout' === $redirect ? wc_get_checkout_url() : wc_get_cart_url();

		$message = sprintf(
			/* translators: %d: number of products added */
			_n( 'Se agregó %d material al carrito.', 'Se agregaron %d materiales al carrito.', $added, 'calculadora-cielorraso-pvc' ),
			$added
		);
		if ( $missing ) {
			$message .= ' ' . sprintf(
				/* translators: %s: list of materials */
				__( 'No están disponibles en la tienda: %s.', 'calculadora-cielorraso-pvc' ),
				implode( ', ', $missing )
			);
		}

		do_action( 'ccr_added_to_cart', $added, $missing, $result );

		wp_send_json_success(
			array(
				'added'    => $added,
				'missing'  => $missing,
				'message'  => $message,
				'url'      => $url,
				'redirect' => 'stay' !== $redirect && ! $missing,
			)
		);
	}

	/**
	 * Devuelve el lead existente (por token) o crea uno nuevo validando los campos.
	 *
	 * @return array|WP_Error
	 */
	private function resolve_lead( array $raw, $token ) {
		$repo = CCR_Repository::get( 'leads' );

		if ( preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			$existing = $repo->find_by( 'token', $token );
			if ( $existing ) {
				return $existing;
			}
		}

		$s     = CCR_Settings::all();
		$lead  = array(
			'name'    => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
			'company' => isset( $raw['company'] ) ? sanitize_text_field( $raw['company'] ) : '',
			'phone'   => isset( $raw['phone'] ) ? preg_replace( '/[^0-9+\-\s().]/', '', sanitize_text_field( $raw['phone'] ) ) : '',
			'email'   => isset( $raw['email'] ) ? sanitize_email( $raw['email'] ) : '',
			'consent' => ! empty( $raw['consent'] ) ? 1 : 0,
		);
		$lead['name']    = mb_substr( $lead['name'], 0, 190 );
		$lead['company'] = mb_substr( $lead['company'], 0, 190 );
		$lead['phone']   = mb_substr( $lead['phone'], 0, 60 );

		$errors = array();
		if ( $s['lead_require_name'] && '' === $lead['name'] ) {
			$errors[] = __( 'Ingresá tu nombre.', 'calculadora-cielorraso-pvc' );
		}
		if ( $s['lead_require_company'] && '' === $lead['company'] ) {
			$errors[] = __( 'Ingresá tu empresa.', 'calculadora-cielorraso-pvc' );
		}
		if ( $s['lead_require_phone'] && strlen( preg_replace( '/\D/', '', $lead['phone'] ) ) < 6 ) {
			$errors[] = __( 'Ingresá un teléfono válido.', 'calculadora-cielorraso-pvc' );
		}
		if ( ( $s['lead_require_email'] || '' !== $lead['email'] ) && ! is_email( $lead['email'] ) ) {
			$errors[] = __( 'Ingresá un email válido.', 'calculadora-cielorraso-pvc' );
		}
		if ( '' !== trim( $s['lead_consent_text'] ) && ! $lead['consent'] ) {
			$errors[] = __( 'Debés aceptar el uso de tus datos.', 'calculadora-cielorraso-pvc' );
		}
		if ( $errors ) {
			return new WP_Error( 'ccr_lead', implode( ' ', $errors ) );
		}

		$lead['token']      = md5( wp_generate_password( 32, true, true ) . microtime() );
		$lead['source_url'] = esc_url_raw( wp_get_referer() ? wp_get_referer() : '' );
		$lead['source_url'] = mb_substr( $lead['source_url'], 0, 255 );

		$id = $repo->insert( $lead );
		if ( ! $id ) {
			return new WP_Error( 'ccr_lead_db', __( 'No se pudieron guardar tus datos. Intentá nuevamente.', 'calculadora-cielorraso-pvc' ) );
		}
		$lead['id'] = $id;

		$this->notify( $lead );
		do_action( 'ccr_lead_created', $id, $lead );

		return $lead;
	}

	private function notify( array $lead ) {
		$s = CCR_Settings::all();
		if ( ! $s['lead_notify'] ) {
			return;
		}
		$to = $s['lead_notify_email'] ? $s['lead_notify_email'] : get_option( 'admin_email' );
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] Nuevo lead de la calculadora de cielorraso', 'calculadora-cielorraso-pvc' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$body    = sprintf(
			"Nombre: %s\nEmpresa: %s\nTeléfono: %s\nEmail: %s\nOrigen: %s\n\n%s",
			$lead['name'],
			$lead['company'],
			$lead['phone'],
			$lead['email'],
			$lead['source_url'],
			admin_url( 'admin.php?page=ccr-leads&action=view&id=' . (int) $lead['id'] )
		);
		wp_mail( $to, $subject, $body );
	}

	/**
	 * Límite simple por IP (la IP solo se usa hasheada y no se guarda).
	 */
	private function within_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'ccr_rl_' . md5( $ip . wp_salt( 'nonce' ) );
		$hit = (int) get_transient( $key );
		if ( $hit >= (int) apply_filters( 'ccr_rate_limit', self::RATE_LIMIT ) ) {
			return false;
		}
		set_transient( $key, $hit + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}
}
