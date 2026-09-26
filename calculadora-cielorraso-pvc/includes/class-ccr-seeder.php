<?php
/**
 * Datos iniciales de ejemplo.
 *
 * Con esta configuración, un ambiente de 4,80 x 3,60 m, instalación suspendida, sentido
 * "más económica", lámina blanca 7 mm y terminación U da exactamente el mismo resultado que
 * la calculadora de referencia: 18 láminas de 5 m, 3 U, 11 montantes, 10 soleras,
 * 100 fijaciones y 300 tornillos T1.
 *
 * Los precios son solo de ejemplo: reemplácelos por los suyos desde el panel.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Seeder {

	public static function seed() {
		$types = self::seed_install_types();
		$cats  = self::seed_categories();
		self::seed_variables();
		self::seed_materials( $cats, $types );
	}

	/**
	 * Vacía catálogo y reglas y vuelve a cargar los datos de ejemplo (no toca leads).
	 */
	public static function reset() {
		foreach ( array( 'materials', 'categories', 'install_types', 'variables' ) as $repo ) {
			CCR_Repository::get( $repo )->truncate();
		}
		self::seed();
	}

	public static function install_types_data() {
		return array(
			array(
				'name'        => 'Suspendido con perfilería metálica',
				'slug'        => 'suspendido',
				'description' => 'Estructura de montantes y soleras colgada de la losa, con cámara de aire.',
				'is_default'  => 1,
				'sort_order'  => 10,
				'active'      => 1,
			),
			array(
				'name'        => 'Directo a losa con omegas',
				'slug'        => 'directo',
				'description' => 'Perfiles omega fijados directamente a la losa o al techo existente.',
				'is_default'  => 0,
				'sort_order'  => 20,
				'active'      => 1,
			),
		);
	}

	private static function seed_install_types() {
		$repo = CCR_Repository::get( 'install_types' );
		$ids  = array();
		foreach ( self::install_types_data() as $t ) {
			$ids[ $t['slug'] ] = $repo->insert( $t );
		}
		return $ids;
	}

	private static function seed_categories() {
		$repo = CCR_Repository::get( 'categories' );
		$data = array(
			array( 'Láminas PVC', 'laminas', 'single', 'Modelo de lámina', 10, 'Láminas / tablillas de PVC. El cliente elige un modelo.' ),
			array( 'Terminaciones', 'terminaciones', 'single', 'Terminación perimetral', 20, 'Perfil de terminación contra las paredes.' ),
			array( 'Uniones y accesorios', 'uniones', 'all', '', 30, 'Uniones H, esquineros y uniones de moldura.' ),
			array( 'Perfiles y estructura', 'perfiles', 'all', '', 40, 'Montantes, soleras, omegas y ángulos.' ),
			array( 'Aislantes', 'aislantes', 'optional', 'Aislante térmico', 50, 'Opcional: lana de vidrio, espumas, etc.' ),
			array( 'Tornillos y tarugos', 'fijaciones', 'all', '', 60, 'Fijaciones a losa, pared y tornillería.' ),
		);
		$ids  = array();
		foreach ( $data as $c ) {
			$ids[ $c[1] ] = $repo->insert(
				array(
					'name'           => $c[0],
					'slug'           => $c[1],
					'selection_mode' => $c[2],
					'customer_label' => $c[3],
					'sort_order'     => $c[4],
					'description'    => $c[5],
					'active'         => 1,
				)
			);
		}
		return $ids;
	}

	private static function seed_variables() {
		$repo  = CCR_Repository::get( 'variables' );
		$order = 0;
		$data  = array(
			// Parámetros editables.
			array( 'tolerancia_largo', 'Tolerancia de largo de pieza (m)', '0.02', 'Se suma al largo nominal de láminas y terminaciones (una lámina "de 5 m" mide 5,02 m).' ),
			array( 'solape', 'Solape en empalmes de perfiles (m)', '0.30', 'Superposición al empalmar dos perfiles en una misma línea.' ),
			array( 'tolerancia_perfil', 'Tolerancia para no agregar una línea extra (m)', '0.05', '' ),
			array( 'sep_colgantes', 'Separación entre colgantes (m)', '1.20', '' ),
			array( 'sep_principales', 'Separación entre perfiles principales (m)', '1.40', '' ),
			array( 'sep_red', 'Separación entre perfiles de la red / omegas (m)', '0.60', 'Distancia entre los perfiles donde se atornillan las láminas.' ),
			array( 'sep_fijacion_pared', 'Separación de fijaciones perimetrales (m)', '0.40', '' ),
			array( 'sep_fijacion_omega', 'Separación de fijaciones de omegas a losa (m)', '0.50', '' ),
			array( 'tornillos_reserva', 'Tornillos de reserva', '50', 'Cantidad extra de tornillos por pérdidas.' ),

			// Reglas derivadas.
			array( 'lineas_red', 'Líneas de red (perpendiculares a las láminas)', 'lineas(lado_paralelo, sep_red, tolerancia_perfil)', '' ),
			array( 'lineas_principales', 'Líneas de perfiles principales', 'si(inst_suspendido, lineas(lado_perpendicular, sep_principales, tolerancia_perfil), 0)', 'Solo en instalación suspendida.' ),
			array( 'colgantes_por_linea', 'Colgantes por línea principal', 'floor(lado_paralelo / sep_colgantes)', '' ),
			array( 'total_colgantes', 'Total de colgantes', 'lineas_principales * colgantes_por_linea', '' ),
			array( 'filas_laminas', 'Filas de láminas', 'si(cat_laminas_ancho > 0, floor(lado_perpendicular / cat_laminas_ancho), 0)', '' ),
			array( 'fijaciones_pared', 'Fijaciones perimetrales a pared', 'ceil(perimetro / sep_fijacion_pared) + 4', '' ),
			array( 'fijaciones_techo', 'Fijaciones a losa', 'si(inst_suspendido, total_colgantes * 2, lineas_red * ceil(lado_perpendicular / sep_fijacion_omega))', '' ),
			array( 'tornillos_estructura', 'Tornillos de estructura', 'si(inst_suspendido, total_colgantes * 2 + total_colgantes * 4 + lineas_principales * (lineas_red + 2), 0)', 'Colgante-losa, colgante-principal y principal-red-perímetro.' ),
			array( 'tornillos_laminas', 'Tornillos de láminas', 'lineas_red * filas_laminas', 'Un tornillo por lámina en cada línea de red.' ),
		);
		foreach ( $data as $v ) {
			$order += 10;
			$repo->insert(
				array(
					'var_key'     => $v[0],
					'label'       => $v[1],
					'formula'     => $v[2],
					'description' => $v[3],
					'sort_order'  => $order,
					'active'      => 1,
				)
			);
		}
	}

	private static function variants( array $pairs ) {
		$out = array();
		foreach ( $pairs as $len => $price ) {
			$out[] = array(
				'label'  => $len . ' m',
				'length' => (float) $len,
				'width'  => 0,
				'price'  => (float) $price,
			);
		}
		return wp_json_encode( $out );
	}

	private static function seed_materials( array $cats, array $types ) {
		$repo = CCR_Repository::get( 'materials' );

		$lamina = 'piezas_superficie(lado_paralelo, lado_perpendicular, m_largo + tolerancia_largo, m_ancho)';
		$moldura = 'sel_term_moldura_nobre || sel_term_moldura_premium';

		$defaults = array(
			'sku'               => '',
			'description'       => '',
			'unit'              => 'unidad',
			'length'            => 0,
			'width'             => 0,
			'yield'             => 0,
			'price'             => 0,
			'price_qty'         => 1,
			'waste_pct'         => null,
			'min_qty'           => 0,
			'correction_factor' => 1,
			'rounding'          => 'ceil',
			'round_multiple'    => 1,
			'decimals'          => 0,
			'variants'          => '',
			'install_types'     => '',
			'active'            => 1,
		);

		$items = array(
			// Láminas.
			array( 'laminas', 'lam_blanca_7', 'Lámina PVC blanca lisa 7 mm', array( 'unit' => 'lámina', 'width' => 0.20, 'length' => 6, 'description' => 'Lámina machihembrada de 20 cm de ancho.', 'variants' => self::variants( array( 3 => 132, 4 => 176, 5 => 220, 6 => 264 ) ) ), $lamina ),
			array( 'laminas', 'lam_blanca_frisada_7', 'Lámina PVC blanca frisada 7 mm', array( 'unit' => 'lámina', 'width' => 0.20, 'length' => 6, 'variants' => self::variants( array( 6 => 390 ) ) ), $lamina ),
			array( 'laminas', 'lam_blanca_10', 'Lámina PVC blanca lisa 10 mm', array( 'unit' => 'lámina', 'width' => 0.20, 'length' => 6, 'variants' => self::variants( array( 4 => 330, 6 => 495 ) ) ), $lamina ),
			array( 'laminas', 'lam_roble_10', 'Lámina PVC roble 10 mm', array( 'unit' => 'lámina', 'width' => 0.20, 'length' => 6, 'variants' => self::variants( array( 4 => 370, 5 => 440, 6 => 528 ) ) ), $lamina ),
			array( 'laminas', 'lam_nogal_10', 'Lámina PVC nogal 10 mm', array( 'unit' => 'lámina', 'width' => 0.20, 'length' => 6, 'variants' => self::variants( array( 4 => 370, 5 => 440, 6 => 528 ) ) ), $lamina ),
			array( 'laminas', 'lam_ripado_blanco', 'Lámina PVC ripado blanco', array( 'unit' => 'lámina', 'width' => 0.25, 'length' => 3, 'variants' => self::variants( array( 3 => 940 ) ) ), $lamina ),

			// Terminaciones.
			array( 'terminaciones', 'term_u', 'Terminación U', array( 'unit' => 'pieza', 'length' => 6, 'price' => 264, 'description' => 'Perfil U perimetral de 6 m.' ), 'perimetro / (m_largo + tolerancia_largo)' ),
			array( 'terminaciones', 'term_moldura_nobre', 'Moldura nobre', array( 'unit' => 'pieza', 'length' => 6, 'price' => 400, 'description' => 'Moldura decorativa perimetral de 6 m.' ), 'perimetro / (m_largo + tolerancia_largo)' ),
			array( 'terminaciones', 'term_moldura_premium', 'Moldura premium', array( 'unit' => 'pieza', 'length' => 6, 'price' => 460, 'description' => 'Moldura decorativa perimetral de 6 m.' ), 'perimetro / (m_largo + tolerancia_largo)' ),

			// Uniones y accesorios.
			array( 'uniones', 'union_h', 'Unión H', array( 'unit' => 'pieza', 'length' => 6, 'price' => 460, 'description' => 'Perfil H para empalmar láminas cuando el ambiente es más largo que la lámina.' ), 'si(sel_cat_laminas, piezas_lineales(lado_perpendicular, m_largo, ceil(lado_paralelo / (cat_laminas_largo + tolerancia_largo)) - 1), 0)' ),
			array( 'uniones', 'esquinero_moldura', 'Esquinero para moldura', array( 'unit' => 'unidad', 'price' => 60 ), "si($moldura, 4, 0)" ),
			array( 'uniones', 'union_moldura', 'Unión para moldura', array( 'unit' => 'unidad', 'price' => 60 ), "si($moldura, 2 * (ceil(largo / cat_terminaciones_largo) - 1) + 2 * (ceil(ancho / cat_terminaciones_largo) - 1) + max(0, 4 - q_cat_terminaciones), 0)" ),

			// Perfiles.
			array( 'perfiles', 'montante_35', 'Montante 35 mm', array( 'unit' => 'pieza', 'length' => 3, 'price' => 172, 'install_types' => (string) $types['suspendido'], 'description' => 'Usado como colgante y como red.' ), 'piezas_lineales(alto, m_largo, total_colgantes, solape) + piezas_lineales(lado_perpendicular, m_largo, lineas_red, solape)' ),
			array( 'perfiles', 'solera_35', 'Solera 35 mm', array( 'unit' => 'pieza', 'length' => 3, 'price' => 152, 'install_types' => (string) $types['suspendido'], 'description' => 'Usada como perfil principal y perimetral.' ), 'piezas_lineales(lado_paralelo, m_largo, lineas_principales, solape) + piezas_perimetro(largo, ancho, m_largo)' ),
			array( 'perfiles', 'omega_12', 'Omega 12 mm', array( 'unit' => 'pieza', 'length' => 3, 'price' => 143, 'install_types' => (string) $types['directo'] ), 'piezas_lineales(lado_perpendicular, m_largo, lineas_red, solape) + piezas_perimetro(largo, ancho, m_largo)' ),
			array( 'perfiles', 'angulo_25', 'Ángulo perimetral 25 mm', array( 'unit' => 'pieza', 'length' => 3, 'price' => 140, 'active' => 0, 'description' => 'Ejemplo de material desactivado.' ), 'perimetro / m_largo' ),

			// Aislantes.
			array( 'aislantes', 'lana_vidrio', 'Lana de vidrio (rollo 18 m²)', array( 'unit' => 'rollo', 'yield' => 18, 'price' => 1990, 'waste_pct' => 5 ), 'area / m_rend' ),
			array( 'aislantes', 'lana_vidrio_alu', 'Lana de vidrio con aluminio (rollo 18 m²)', array( 'unit' => 'rollo', 'yield' => 18, 'price' => 2650, 'waste_pct' => 5 ), 'area / m_rend' ),
			array( 'aislantes', 'espuma_pe_5', 'Espuma de polietileno 5 mm (rollo 20 m²)', array( 'unit' => 'rollo', 'yield' => 20, 'price' => 1060, 'waste_pct' => 5 ), 'area / m_rend' ),

			// Fijaciones.
			array( 'fijaciones', 'fijacion_6', 'Fijación 6 mm (tarugo + tornillo)', array( 'unit' => 'unidad', 'price' => 250, 'price_qty' => 100, 'round_multiple' => 50 ), 'fijaciones_techo + fijaciones_pared' ),
			array( 'fijaciones', 'tornillo_t1', 'Tornillo T1 punta aguja', array( 'unit' => 'unidad', 'price' => 70, 'price_qty' => 100, 'round_multiple' => 50 ), 'tornillos_estructura + fijaciones_pared + tornillos_laminas + tornillos_reserva' ),
		);

		$order = 0;
		foreach ( $items as $it ) {
			$order += 10;
			$row    = array_merge(
				$defaults,
				$it[3],
				array(
					'category_id' => isset( $cats[ $it[0] ] ) ? (int) $cats[ $it[0] ] : 0,
					'code'        => $it[1],
					'name'        => $it[2],
					'formula'     => $it[4],
					'sort_order'  => $order,
				)
			);
			$repo->insert( $row );
		}
	}
}
