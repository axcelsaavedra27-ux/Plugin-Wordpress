<?php
/**
 * Motor de cálculo.
 *
 * Flujo:
 *  1. Normaliza y valida la entrada del cliente.
 *  2. Arma el contexto base (medidas, tipo de instalación, sentido, datos de materiales).
 *  3. Evalúa las "reglas de cálculo" (variables) en orden.
 *  4. Evalúa la fórmula de cada material en uso y aplica factor, desperdicio, mínimo y redondeo.
 *  5. Si el sentido o el largo de las piezas están en "automático", prueba todas las combinaciones
 *     y se queda con la de menor costo total (igual que la opción "Más económica" de CPR).
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Calculator {

	/** Nombres reservados que no pueden usarse como clave de variable. */
	const RESERVED = array( 'largo', 'ancho', 'alto', 'area', 'perimetro', 'lado_mayor', 'lado_menor', 'lado_paralelo', 'lado_perpendicular', 'sentido_largo', 'sentido_ancho', 'desperdicio_general', 'm_largo', 'm_ancho', 'm_rend', 'm_precio' );

	/** @var array */
	private $settings;
	/** @var array[] */
	private $install_types;
	/** @var array[] id => categoría */
	private $categories;
	/** @var array[] Materiales activos, en orden de evaluación. */
	private $materials;
	/** @var array[] Todos los materiales (incluye inactivos). */
	private $all_materials;
	/** @var array[] */
	private $variables;

	public function __construct() {
		$this->settings      = CCR_Settings::all();
		$this->install_types = CCR_Repository::get( 'install_types' )->all( array( 'active_only' => true ) );
		$this->variables     = CCR_Repository::get( 'variables' )->all( array( 'active_only' => true ) );

		$this->categories = array();
		foreach ( CCR_Repository::get( 'categories' )->all() as $cat ) {
			$this->categories[ (int) $cat['id'] ] = $cat;
		}

		$this->all_materials = CCR_Repository::get( 'materials' )->all();
		$active              = array();
		foreach ( $this->all_materials as $m ) {
			$cat = isset( $this->categories[ (int) $m['category_id'] ] ) ? $this->categories[ (int) $m['category_id'] ] : null;
			if ( ! (int) $m['active'] || ( $cat && ! (int) $cat['active'] ) ) {
				continue;
			}
			$m['_variants']  = self::decode_variants( $m['variants'] );
			$m['_types']     = self::decode_ids( $m['install_types'] );
			$m['_cat_order'] = $cat ? (int) $cat['sort_order'] : 9999;
			$active[]        = $m;
		}
		usort(
			$active,
			static function ( $a, $b ) {
				if ( $a['_cat_order'] !== $b['_cat_order'] ) {
					return $a['_cat_order'] - $b['_cat_order'];
				}
				if ( (int) $a['sort_order'] !== (int) $b['sort_order'] ) {
					return (int) $a['sort_order'] - (int) $b['sort_order'];
				}
				return (int) $a['id'] - (int) $b['id'];
			}
		);
		$this->materials = $active;
	}

	/* --------------------------------------------------------------------
	 * Utilidades estáticas
	 * ------------------------------------------------------------------ */

	/**
	 * @param string|null $json Variantes guardadas.
	 * @return array[] Lista de array( label, length, price ).
	 */
	public static function decode_variants( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $v ) {
			if ( ! is_array( $v ) ) {
				continue;
			}
			$out[] = array(
				'label'  => isset( $v['label'] ) ? (string) $v['label'] : '',
				'length' => isset( $v['length'] ) ? (float) $v['length'] : 0,
				'width'  => isset( $v['width'] ) ? (float) $v['width'] : 0,
				'price'  => isset( $v['price'] ) ? (float) $v['price'] : 0,
			);
		}
		return $out;
	}

	public static function decode_ids( $csv ) {
		return array_values( array_filter( array_map( 'intval', explode( ',', (string) $csv ) ) ) );
	}

	/** Convierte un código/slug en un nombre de variable válido. */
	public static function var_name( $s ) {
		$s = strtolower( preg_replace( '/[^A-Za-z0-9_]+/', '_', (string) $s ) );
		return trim( $s, '_' );
	}

	/* --------------------------------------------------------------------
	 * Configuración para el formulario público
	 * ------------------------------------------------------------------ */

	/**
	 * Datos que necesita el formulario (sin fórmulas ni datos internos).
	 */
	public function form_config() {
		$show_prices = (bool) $this->settings['show_prices'];

		$types = array();
		foreach ( $this->install_types as $t ) {
			$types[] = array(
				'id'          => (int) $t['id'],
				'slug'        => $t['slug'],
				'name'        => $t['name'],
				'description' => (string) $t['description'],
				'is_default'  => (int) $t['is_default'],
			);
		}

		$cats = array();
		foreach ( $this->categories as $cat ) {
			if ( ! (int) $cat['active'] || ! in_array( $cat['selection_mode'], array( 'single', 'optional' ), true ) ) {
				continue;
			}
			$items = array();
			foreach ( $this->materials as $m ) {
				if ( (int) $m['category_id'] !== (int) $cat['id'] ) {
					continue;
				}
				$variants = array();
				foreach ( $m['_variants'] as $i => $v ) {
					$variants[] = array(
						'index' => $i,
						'label' => $v['label'] ? $v['label'] : self::number( $v['length'] ) . ' m',
					);
				}
				$items[] = array(
					'id'       => (int) $m['id'],
					'name'     => $m['name'],
					'types'    => $m['_types'],
					'variants' => $variants,
				);
			}
			if ( ! $items ) {
				continue;
			}
			$cats[] = array(
				'id'       => (int) $cat['id'],
				'slug'     => $cat['slug'],
				'label'    => $cat['customer_label'] ? $cat['customer_label'] : $cat['name'],
				'optional' => 'optional' === $cat['selection_mode'],
				'items'    => $items,
			);
		}

		return array(
			'install_types' => $types,
			'categories'    => $cats,
			'show_prices'        => $show_prices,
			'variant_auto_label' => $this->settings['label_variant_auto'],
		);
	}

	private static function number( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 3, ',', '' ), '0' ), ',' );
	}

	/* --------------------------------------------------------------------
	 * Cálculo
	 * ------------------------------------------------------------------ */

	/**
	 * Calcula los materiales.
	 *
	 * @param array $raw   Entrada sin sanitizar.
	 * @param bool  $debug Incluir variables y advertencias (probador de administración).
	 * @return array|WP_Error
	 */
	public function calculate( array $raw, $debug = false ) {
		$input = $this->normalize( $raw );
		if ( is_wp_error( $input ) ) {
			return $input;
		}

		$type_id = (int) $input['install_type']['id'];

		// Materiales en uso y selección de variantes.
		$in_use       = array();
		$variant_sets = array(); // material_id => índices candidatos.
		foreach ( $this->materials as $m ) {
			if ( $m['_types'] && ! in_array( $type_id, $m['_types'], true ) ) {
				continue;
			}
			$cat  = isset( $this->categories[ (int) $m['category_id'] ] ) ? $this->categories[ (int) $m['category_id'] ] : null;
			$mode = $cat ? $cat['selection_mode'] : 'all';
			if ( in_array( $mode, array( 'single', 'optional' ), true ) ) {
				$chosen = isset( $input['selections'][ $cat['slug'] ] ) ? $input['selections'][ $cat['slug'] ] : 0;
				if ( (int) $m['id'] !== $chosen ) {
					continue;
				}
			}
			$in_use[ (int) $m['id'] ] = $m;

			$count = count( $m['_variants'] );
			if ( $count > 0 ) {
				$pick = isset( $input['variants'][ (int) $m['id'] ] ) ? $input['variants'][ (int) $m['id'] ] : 'auto';
				if ( 'auto' !== $pick && $pick >= 0 && $pick < $count ) {
					$variant_sets[ (int) $m['id'] ] = array( (int) $pick );
				} else {
					$variant_sets[ (int) $m['id'] ] = range( 0, $count - 1 );
				}
			}
		}

		$directions = 'auto' === $input['direction'] ? array( 'largo', 'ancho' ) : array( $input['direction'] );

		// Combinaciones (sentido x variantes), con tope configurable.
		$warnings = array();
		$combos   = array();
		foreach ( $directions as $d ) {
			$combos[] = array(
				'direction' => $d,
				'variants'  => array(),
			);
		}
		$cap = max( 1, (int) $this->settings['max_combinations'] );
		foreach ( $variant_sets as $mid => $indices ) {
			if ( count( $combos ) * count( $indices ) > $cap ) {
				$indices    = array( $indices[0] );
				$warnings[] = sprintf( 'Se alcanzó el máximo de combinaciones; el material #%d usa su primera variante.', $mid );
			}
			$next = array();
			foreach ( $combos as $c ) {
				foreach ( $indices as $i ) {
					$c2                   = $c;
					$c2['variants'][ $mid ] = $i;
					$next[]               = $c2;
				}
			}
			$combos = $next;
		}

		$best = null;
		foreach ( $combos as $combo ) {
			$run = $this->run( $input, $in_use, $combo );
			if ( null === $best || $this->is_better( $run, $best ) ) {
				$best = $run;
			}
		}

		$best['warnings'] = array_merge( $warnings, $best['warnings'] );
		if ( $best['warnings'] && defined( 'WP_DEBUG' ) && WP_DEBUG && ! $debug ) {
			error_log( '[Calculadora Cielorraso] ' . implode( ' | ', $best['warnings'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $this->build_result( $input, $best, $debug, count( $combos ) );
	}

	private function is_better( $run, $best ) {
		if ( abs( $run['total'] - $best['total'] ) > 0.0001 ) {
			return $run['total'] < $best['total'];
		}
		return $run['qty_sum'] < $best['qty_sum'] - 0.0001;
	}

	/**
	 * Ejecuta una pasada completa del cálculo para una combinación.
	 */
	private function run( array $input, array $in_use, array $combo ) {
		$warnings = array();
		$vars     = $this->base_vars( $input, $combo['direction'] );

		// Datos de cada material activo (usando la variante de esta combinación).
		$resolved = array();
		foreach ( $this->materials as $m ) {
			$mid = (int) $m['id'];
			$len = (float) $m['length'];
			$wid = (float) $m['width'];
			$prc = (float) $m['price'];
			$lbl = '';
			if ( $m['_variants'] ) {
				$idx = isset( $combo['variants'][ $mid ] ) ? $combo['variants'][ $mid ] : 0;
				$v   = $m['_variants'][ $idx ];
				$len = $v['length'] > 0 ? $v['length'] : $len;
				$wid = $v['width'] > 0 ? $v['width'] : $wid;
				$prc = $v['price'];
				$lbl = $v['label'] ? $v['label'] : self::number( $len ) . ' m';
			}
			$resolved[ $mid ] = array(
				'length'        => $len,
				'width'         => $wid,
				'price'         => $prc,
				'variant_label' => $lbl,
			);
			$code                      = self::var_name( $m['code'] );
			$vars[ $code . '_largo' ]  = $len;
			$vars[ $code . '_ancho' ]  = $wid;
			$vars[ $code . '_rend' ]   = (float) $m['yield'];
			$vars[ $code . '_precio' ] = $prc;
			$vars[ 'sel_' . $code ]    = isset( $in_use[ $mid ] ) ? 1 : 0;
		}

		// Datos del material elegido en cada categoría.
		foreach ( $this->categories as $cat ) {
			$slug                               = self::var_name( $cat['slug'] );
			$vars[ 'cat_' . $slug . '_largo' ]  = 0;
			$vars[ 'cat_' . $slug . '_ancho' ]  = 0;
			$vars[ 'cat_' . $slug . '_rend' ]   = 0;
			$vars[ 'sel_cat_' . $slug ]         = 0;
			$vars[ 'q_cat_' . $slug ]           = 0;
			foreach ( $in_use as $mid => $m ) {
				if ( (int) $m['category_id'] === (int) $cat['id'] ) {
					$vars[ 'cat_' . $slug . '_largo' ] = $resolved[ $mid ]['length'];
					$vars[ 'cat_' . $slug . '_ancho' ] = $resolved[ $mid ]['width'];
					$vars[ 'cat_' . $slug . '_rend' ]  = (float) $m['yield'];
					$vars[ 'sel_cat_' . $slug ]        = 1;
					break;
				}
			}
		}

		// Materiales que no se van a calcular valen 0 (se pueden referenciar sin error).
		$in_use_codes = array();
		foreach ( $in_use as $m ) {
			$in_use_codes[ self::var_name( $m['code'] ) ] = true;
		}
		foreach ( $this->all_materials as $m ) {
			$code = self::var_name( $m['code'] );
			if ( ! isset( $in_use_codes[ $code ] ) ) {
				$vars[ 'q_' . $code ] = 0;
			}
		}

		// Reglas de cálculo (variables).
		foreach ( $this->variables as $var ) {
			$key = self::var_name( $var['var_key'] );
			try {
				$vars[ $key ] = CCR_Expression::evaluate( $var['formula'], $vars );
			} catch ( CCR_Expression_Exception $e ) {
				$vars[ $key ] = 0;
				$warnings[]   = sprintf( 'Regla "%s": %s', $key, $e->getMessage() );
			}
		}

		// Materiales.
		$lines   = array();
		$total   = 0.0;
		$qty_sum = 0.0;
		$general = (float) $this->settings['general_waste_pct'];

		foreach ( $this->materials as $m ) {
			$mid = (int) $m['id'];
			if ( ! isset( $in_use[ $mid ] ) ) {
				continue;
			}
			$code = self::var_name( $m['code'] );
			$r    = $resolved[ $mid ];

			$ctx             = $vars;
			$ctx['m_largo']  = $r['length'];
			$ctx['m_ancho']  = $r['width'];
			$ctx['m_rend']   = (float) $m['yield'];
			$ctx['m_precio'] = $r['price'];

			$base = 0.0;
			if ( '' !== trim( (string) $m['formula'] ) ) {
				try {
					$base = CCR_Expression::evaluate( $m['formula'], $ctx );
				} catch ( CCR_Expression_Exception $e ) {
					$warnings[] = sprintf( 'Material "%s": %s', $m['name'], $e->getMessage() );
				}
			} else {
				$warnings[] = sprintf( 'Material "%s" no tiene fórmula.', $m['name'] );
			}
			$base = max( 0.0, $base );

			$factor    = (float) $m['correction_factor'];
			$factor    = $factor > 0 ? $factor : 1.0;
			$waste_pct = ( null === $m['waste_pct'] || '' === $m['waste_pct'] ) ? $general : (float) $m['waste_pct'];
			$adjusted  = $base * $factor;
			$with_w    = $adjusted * ( 1 + $waste_pct / 100 );

			if ( $with_w > 0 && $with_w < (float) $m['min_qty'] ) {
				$with_w = (float) $m['min_qty'];
			}

			$qty = $this->apply_rounding( $with_w, $m );

			$vars[ 'q_' . $code ]     = $qty;
			$vars[ 'qbase_' . $code ] = $base;
			$cat_slug                 = isset( $this->categories[ (int) $m['category_id'] ] ) ? self::var_name( $this->categories[ (int) $m['category_id'] ]['slug'] ) : '';
			if ( $cat_slug ) {
				$vars[ 'q_cat_' . $cat_slug ] += $qty;
			}

			$price_qty = (float) $m['price_qty'] > 0 ? (float) $m['price_qty'] : 1.0;
			$subtotal  = $qty / $price_qty * $r['price'];
			$total    += $subtotal;
			$qty_sum  += $qty;

			$lines[] = array(
				'material_id'   => $mid,
				'code'          => $m['code'],
				'sku'           => $m['sku'],
				'name'          => $m['name'],
				'variant_label' => $r['variant_label'],
				'description'   => (string) $m['description'],
				'category'      => isset( $this->categories[ (int) $m['category_id'] ] ) ? $this->categories[ (int) $m['category_id'] ]['name'] : '',
				'unit'          => $m['unit'],
				'base_qty'      => round( $base, 4 ),
				'factor'        => $factor,
				'waste_pct'     => $waste_pct,
				'waste_qty'     => round( $adjusted * $waste_pct / 100, 4 ),
				'qty'           => $qty,
				'decimals'      => (int) $m['decimals'],
				'unit_price'    => $r['price'],
				'price_qty'     => $price_qty,
				'subtotal'      => $subtotal,
			);
		}

		return array(
			'direction' => $combo['direction'],
			'lines'     => $lines,
			'total'     => $total,
			'qty_sum'   => $qty_sum,
			'vars'      => $vars,
			'warnings'  => $warnings,
		);
	}

	private function apply_rounding( $value, array $m ) {
		$multiple = (float) $m['round_multiple'];
		switch ( $m['rounding'] ) {
			case 'none':
				return round( $value, (int) $m['decimals'] );
			case 'floor':
			case 'round':
			case 'ceil':
				return round( CCR_Math::to_multiple( $value, $multiple, $m['rounding'] ), 4 );
		}
		return round( CCR_Math::to_multiple( $value, $multiple, 'ceil' ), 4 );
	}

	/**
	 * Variables base disponibles en todas las fórmulas.
	 */
	private function base_vars( array $input, $direction ) {
		$largo = $input['largo'];
		$ancho = $input['ancho'];
		$vars  = array(
			'largo'               => $largo,
			'ancho'               => $ancho,
			'alto'                => $input['alto'],
			'area'                => $largo * $ancho,
			'perimetro'           => 2 * ( $largo + $ancho ),
			'lado_mayor'          => max( $largo, $ancho ),
			'lado_menor'          => min( $largo, $ancho ),
			'sentido_largo'       => 'largo' === $direction ? 1 : 0,
			'sentido_ancho'       => 'ancho' === $direction ? 1 : 0,
			'lado_paralelo'       => 'largo' === $direction ? $largo : $ancho,
			'lado_perpendicular'  => 'largo' === $direction ? $ancho : $largo,
			'desperdicio_general' => (float) $this->settings['general_waste_pct'],
		);
		foreach ( $this->install_types as $t ) {
			$vars[ 'inst_' . self::var_name( $t['slug'] ) ] = (int) $t['id'] === (int) $input['install_type']['id'] ? 1 : 0;
		}
		return $vars;
	}

	/**
	 * Normaliza y valida la entrada.
	 *
	 * @return array|WP_Error
	 */
	private function normalize( array $raw ) {
		$s    = $this->settings;
		$num  = static function ( $v ) {
			return (float) str_replace( ',', '.', trim( (string) $v ) );
		};
		$min  = (float) $s['min_dim'];
		$max  = (float) $s['max_dim'];
		$in   = array();

		foreach ( array( 'largo', 'ancho' ) as $k ) {
			$v = isset( $raw[ $k ] ) ? $num( $raw[ $k ] ) : 0;
			if ( $v < $min || $v > $max ) {
				return new WP_Error(
					'ccr_invalid_' . $k,
					/* translators: 1: field, 2: min, 3: max */
					sprintf( __( 'El %1$s debe estar entre %2$s y %3$s m.', 'calculadora-cielorraso-pvc' ), $k, self::number( $min ), self::number( $max ) )
				);
			}
			$in[ $k ] = $v;
		}

		$alto = ( isset( $raw['alto'] ) && '' !== trim( (string) $raw['alto'] ) ) ? $num( $raw['alto'] ) : (float) $s['default_alto'];
		if ( $alto < 0 || $alto > (float) $s['max_alto'] ) {
			return new WP_Error(
				'ccr_invalid_alto',
				/* translators: %s: max height */
				sprintf( __( 'La altura debe estar entre 0 y %s m.', 'calculadora-cielorraso-pvc' ), self::number( $s['max_alto'] ) )
			);
		}
		$in['alto'] = $alto;

		// Tipo de instalación.
		if ( ! $this->install_types ) {
			return new WP_Error( 'ccr_no_types', __( 'No hay tipos de instalación activos.', 'calculadora-cielorraso-pvc' ) );
		}
		$wanted  = isset( $raw['install_type'] ) ? sanitize_key( $raw['install_type'] ) : '';
		$type    = null;
		$default = $this->install_types[0];
		foreach ( $this->install_types as $t ) {
			if ( $t['slug'] === $wanted ) {
				$type = $t;
			}
			if ( (int) $t['is_default'] ) {
				$default = $t;
			}
		}
		$in['install_type'] = $type ? $type : $default;

		// Sentido.
		$dir = isset( $raw['direction'] ) ? sanitize_key( $raw['direction'] ) : $s['default_direction'];
		if ( ! in_array( $dir, array( 'largo', 'ancho', 'auto' ), true ) || ( 'auto' === $dir && ! $s['enable_auto_direction'] ) ) {
			$dir = 'auto' === $s['default_direction'] && ! $s['enable_auto_direction'] ? 'largo' : $s['default_direction'];
		}
		$in['direction'] = $dir;

		// Selección por categoría (single / optional).
		$type_id           = (int) $in['install_type']['id'];
		$raw_sel           = isset( $raw['selections'] ) && is_array( $raw['selections'] ) ? $raw['selections'] : array();
		$in['selections']  = array();
		foreach ( $this->categories as $cat ) {
			if ( ! (int) $cat['active'] || ! in_array( $cat['selection_mode'], array( 'single', 'optional' ), true ) ) {
				continue;
			}
			$eligible = array();
			foreach ( $this->materials as $m ) {
				if ( (int) $m['category_id'] === (int) $cat['id'] && ( ! $m['_types'] || in_array( $type_id, $m['_types'], true ) ) ) {
					$eligible[] = (int) $m['id'];
				}
			}
			$want = isset( $raw_sel[ $cat['slug'] ] ) ? absint( $raw_sel[ $cat['slug'] ] ) : -1;
			if ( in_array( $want, $eligible, true ) ) {
				$in['selections'][ $cat['slug'] ] = $want;
			} elseif ( 'optional' === $cat['selection_mode'] ) {
				$in['selections'][ $cat['slug'] ] = 0;
			} else {
				$in['selections'][ $cat['slug'] ] = $eligible ? $eligible[0] : 0;
			}
		}

		// Variantes elegidas.
		$in['variants'] = array();
		if ( isset( $raw['variants'] ) && is_array( $raw['variants'] ) ) {
			foreach ( $raw['variants'] as $mid => $idx ) {
				$in['variants'][ absint( $mid ) ] = ( 'auto' === $idx || '' === $idx ) ? 'auto' : absint( $idx );
			}
		}

		$obs                = isset( $raw['observations'] ) ? sanitize_textarea_field( (string) $raw['observations'] ) : '';
		$in['observations'] = function_exists( 'mb_substr' ) ? mb_substr( $obs, 0, 2000 ) : substr( $obs, 0, 2000 );

		return $in;
	}

	/**
	 * Arma la respuesta final (lo que ve el cliente).
	 */
	private function build_result( array $input, array $run, $debug, $combos ) {
		$s           = $this->settings;
		$show_prices = (bool) $s['show_prices'];
		$dir_labels  = array(
			'largo' => $s['label_dir_largo'],
			'ancho' => $s['label_dir_ancho'],
			'auto'  => $s['label_dir_auto'],
		);

		$lines = array();
		foreach ( $run['lines'] as $l ) {
			if ( $l['qty'] <= 0 ) {
				continue;
			}
			$line = array(
				'code'          => $l['code'],
				'sku'           => $l['sku'],
				'name'          => $l['name'] . ( $l['variant_label'] ? ' ' . $l['variant_label'] : '' ),
				'description'   => $l['description'],
				'category'      => $l['category'],
				'unit'          => $l['unit'],
				'qty'           => $l['qty'],
				'qty_display'   => self::format_qty( $l['qty'], $l['decimals'] ),
				'base_qty'      => $l['base_qty'],
				'waste_pct'     => $l['waste_pct'],
				'waste_qty'     => $l['waste_qty'],
			);
			if ( $show_prices ) {
				$line['unit_price']         = $l['unit_price'];
				$line['price_qty']          = $l['price_qty'];
				$line['unit_price_display'] = CCR_Settings::format_price( $l['unit_price'] ) . ( $l['price_qty'] > 1 ? ' / ' . self::format_qty( $l['price_qty'], 0 ) : '' );
				$line['subtotal']           = round( $l['subtotal'], 4 );
				$line['subtotal_display']   = CCR_Settings::format_price( $l['subtotal'] );
			}
			$lines[] = $line;
		}

		$waste_units = 0;
		foreach ( $lines as $l ) {
			$waste_units += $l['waste_qty'];
		}

		$result = array(
			'summary'     => array(
				'largo'              => $input['largo'],
				'ancho'              => $input['ancho'],
				'alto'               => $input['alto'],
				'area'               => round( $input['largo'] * $input['ancho'], 4 ),
				'perimetro'          => round( 2 * ( $input['largo'] + $input['ancho'] ), 4 ),
				'install_type'       => $input['install_type']['name'],
				'install_type_slug'  => $input['install_type']['slug'],
				'direction'          => $run['direction'],
				'direction_label'    => $dir_labels[ $run['direction'] ],
				'direction_side'     => 'largo' === $run['direction'] ? $input['largo'] : $input['ancho'],
				'direction_auto'     => 'auto' === $input['direction'],
				'general_waste_pct'  => (float) $s['general_waste_pct'],
				'observations'       => $input['observations'],
				'items_count'        => count( $lines ),
				'waste_units'        => round( $waste_units, 2 ),
			),
			'lines'       => $lines,
			'show_prices' => $show_prices,
			'date'        => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
		);

		if ( $show_prices ) {
			$result['total']         = round( $run['total'], 4 );
			$result['total_display'] = CCR_Settings::format_price( $run['total'] );
			$result['total_label']   = $s['total_label'];
			$result['prices_note']   = $s['prices_note'];
		}

		if ( $debug ) {
			ksort( $run['vars'] );
			$result['debug'] = array(
				'variables'  => $run['vars'],
				'warnings'   => $run['warnings'],
				'combos'     => $combos,
				'raw_lines'  => $run['lines'],
				'total'      => $run['total'],
			);
		}

		$result['_input'] = $input; // Uso interno (se elimina antes de responder).
		return $result;
	}

	public static function format_qty( $qty, $decimals ) {
		$decimals = (int) $decimals;
		if ( 0 === $decimals && abs( $qty - round( $qty ) ) > 0.00001 ) {
			$decimals = 2;
		}
		return number_format_i18n( $qty, $decimals );
	}
}
