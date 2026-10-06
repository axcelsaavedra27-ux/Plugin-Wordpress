<?php
/**
 * Definición de las entidades administrables (materiales, categorías, tipos de instalación, reglas).
 * Cada configuración alimenta al CRUD genérico CCR_Admin_Crud.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Entities {

	public static function all() {
		return array(
			'materials'     => self::materials(),
			'categories'    => self::categories(),
			'install_types' => self::install_types(),
			'variables'     => self::variables(),
		);
	}

	public static function category_options() {
		$out = array();
		foreach ( CCR_Repository::get( 'categories' )->all() as $c ) {
			$out[ $c['id'] ] = $c['name'] . ( (int) $c['active'] ? '' : ' (' . __( 'inactiva', 'calculadora-cielorraso-pvc' ) . ')' );
		}
		return $out;
	}

	public static function install_type_options() {
		$out = array();
		foreach ( CCR_Repository::get( 'install_types' )->all() as $t ) {
			$out[ $t['id'] ] = $t['name'];
		}
		return $out;
	}

	public static function rounding_options() {
		return array(
			'ceil'  => __( 'Hacia arriba', 'calculadora-cielorraso-pvc' ),
			'round' => __( 'Al más cercano', 'calculadora-cielorraso-pvc' ),
			'floor' => __( 'Hacia abajo', 'calculadora-cielorraso-pvc' ),
			'none'  => __( 'Sin redondeo (usa decimales)', 'calculadora-cielorraso-pvc' ),
		);
	}

	private static function status_column() {
		return static function ( $row ) {
			return (int) $row['active']
				? '<span class="ccr-badge ccr-badge-on">' . esc_html__( 'Activo', 'calculadora-cielorraso-pvc' ) . '</span>'
				: '<span class="ccr-badge ccr-badge-off">' . esc_html__( 'Inactivo', 'calculadora-cielorraso-pvc' ) . '</span>';
		};
	}

	/* ------------------------------------------------------------------ */

	private static function materials() {
		return array(
			'key'      => 'materials',
			'repo'     => 'materials',
			'slug'     => 'ccr-materials',
			'singular' => __( 'material', 'calculadora-cielorraso-pvc' ),
			'plural'   => __( 'Materiales', 'calculadora-cielorraso-pvc' ),
			'intro'    => __( 'Láminas, perfiles, omegas, ángulos, tornillos, tarugos, uniones, terminaciones, aislantes, accesorios… Cada material tiene su propia fórmula de cantidad. Se evalúan en el orden de su categoría y luego de "Orden".', 'calculadora-cielorraso-pvc' ),
			'unique'   => 'code',
			'search'   => array( 'name', 'code', 'sku', 'description' ),
			'filter'   => array(
				'column'  => 'category_id',
				'label'   => __( 'Todas las categorías', 'calculadora-cielorraso-pvc' ),
				'options' => array( __CLASS__, 'category_options' ),
			),
			'formula_help' => true,
			'columns'  => array(
				'name'        => array(
					__( 'Material', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return '<strong>' . esc_html( $row['name'] ) . '</strong><br><code>' . esc_html( $row['code'] ) . '</code>' . ( $row['sku'] ? ' · ' . esc_html( $row['sku'] ) : '' );
					},
				),
				'category_id' => array(
					__( 'Categoría', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						$opts = self::category_options();
						return isset( $opts[ $row['category_id'] ] ) ? esc_html( $opts[ $row['category_id'] ] ) : '—';
					},
				),
				'unit'        => array( __( 'Unidad', 'calculadora-cielorraso-pvc' ) ),
				'price'       => array(
					__( 'Precio', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						$v = CCR_Calculator::decode_variants( $row['variants'] );
						if ( $v ) {
							/* translators: %d: number of variants */
							return esc_html( sprintf( _n( '%d variante', '%d variantes', count( $v ), 'calculadora-cielorraso-pvc' ), count( $v ) ) );
						}
						return esc_html( CCR_Settings::format_price( $row['price'] ) ) . ( (float) $row['price_qty'] > 1 ? ' / ' . esc_html( (float) $row['price_qty'] ) : '' );
					},
				),
				'waste_pct'   => array(
					__( 'Desperdicio', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return null === $row['waste_pct'] ? '<span class="description">' . esc_html__( 'general', 'calculadora-cielorraso-pvc' ) . '</span>' : esc_html( (float) $row['waste_pct'] . ' %' );
					},
				),
				'formula'     => array(
					__( 'Fórmula', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return '<code class="ccr-formula-preview">' . esc_html( $row['formula'] ) . '</code>';
					},
				),
				'sort_order'  => array( __( 'Orden', 'calculadora-cielorraso-pvc' ) ),
				'active'      => array( __( 'Estado', 'calculadora-cielorraso-pvc' ), self::status_column() ),
			),
			'fields'   => array(
				'_s1'               => array( 'type' => 'section', 'label' => __( 'Datos del material', 'calculadora-cielorraso-pvc' ) ),
				'name'              => array( 'label' => __( 'Nombre', 'calculadora-cielorraso-pvc' ), 'type' => 'text', 'required' => true ),
				'code'              => array( 'label' => __( 'Código interno', 'calculadora-cielorraso-pvc' ), 'type' => 'slug', 'required' => true, 'help' => __( 'Letras minúsculas, números y guion bajo. Se usa en fórmulas: q_CODIGO, sel_CODIGO…', 'calculadora-cielorraso-pvc' ) ),
				'sku'               => array( 'label' => __( 'SKU / código comercial', 'calculadora-cielorraso-pvc' ), 'type' => 'text', 'help' => __( 'Para el botón "Agregar al carrito": debe coincidir con el SKU del producto en WooCommerce.', 'calculadora-cielorraso-pvc' ) ),
				'category_id'       => array( 'label' => __( 'Categoría', 'calculadora-cielorraso-pvc' ), 'type' => 'select', 'options' => array( __CLASS__, 'category_options' ), 'int' => true ),
				'description'       => array( 'label' => __( 'Descripción', 'calculadora-cielorraso-pvc' ), 'type' => 'textarea' ),
				'unit'              => array( 'label' => __( 'Unidad', 'calculadora-cielorraso-pvc' ), 'type' => 'text', 'default' => 'unidad', 'help' => __( 'Ej.: lámina, pieza, rollo, unidad, m, m².', 'calculadora-cielorraso-pvc' ) ),
				'length'            => array( 'label' => __( 'Largo (m)', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 0 ),
				'width'             => array( 'label' => __( 'Ancho (m)', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 0 ),
				'yield'             => array( 'label' => __( 'Rendimiento', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 0, 'help' => __( 'Libre: m² por rollo, unidades por m², etc. Disponible como m_rend.', 'calculadora-cielorraso-pvc' ) ),
				'price'             => array( 'label' => __( 'Precio', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 0 ),
				'price_qty'         => array( 'label' => __( 'El precio corresponde a (unidades)', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 1, 'help' => __( 'Ej.: 100 si el precio es por caja de 100 tornillos.', 'calculadora-cielorraso-pvc' ) ),
				'variants'          => array( 'label' => __( 'Variantes / largos disponibles', 'calculadora-cielorraso-pvc' ), 'type' => 'variants', 'help' => __( 'Opcional. Si carga variantes, cada una define su largo y precio (reemplazan a los de arriba). El cliente puede elegir el largo o dejarlo en automático (se elige el más económico).', 'calculadora-cielorraso-pvc' ) ),

				'_s2'               => array( 'type' => 'section', 'label' => __( 'Cálculo', 'calculadora-cielorraso-pvc' ) ),
				'formula'           => array( 'label' => __( 'Fórmula de cantidad', 'calculadora-cielorraso-pvc' ), 'type' => 'formula', 'required' => true, 'help' => __( 'Cantidad base antes de factor, desperdicio y redondeo. Ej.: area / m_rend', 'calculadora-cielorraso-pvc' ) ),
				'correction_factor' => array( 'label' => __( 'Factor de corrección', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 1 ),
				'waste_pct'         => array( 'label' => __( 'Desperdicio (%)', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.001', 'nullable' => true, 'help' => __( 'Vacío = usa el desperdicio general.', 'calculadora-cielorraso-pvc' ) ),
				'min_qty'           => array( 'label' => __( 'Cantidad mínima', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 0, 'help' => __( 'Se aplica solo si el material es necesario (cantidad > 0).', 'calculadora-cielorraso-pvc' ) ),
				'rounding'          => array( 'label' => __( 'Redondeo', 'calculadora-cielorraso-pvc' ), 'type' => 'select', 'options' => array( __CLASS__, 'rounding_options' ), 'default' => 'ceil' ),
				'round_multiple'    => array( 'label' => __( 'Redondear al múltiplo de', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '0.0001', 'default' => 1, 'help' => __( 'Ej.: 50 para vender tornillos de a 50.', 'calculadora-cielorraso-pvc' ) ),
				'decimals'          => array( 'label' => __( 'Decimales a mostrar', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '1', 'default' => 0, 'int' => true ),
				'install_types'     => array( 'label' => __( 'Aplica a tipos de instalación', 'calculadora-cielorraso-pvc' ), 'type' => 'multicheck', 'options' => array( __CLASS__, 'install_type_options' ), 'help' => __( 'Ninguno marcado = aplica a todos.', 'calculadora-cielorraso-pvc' ) ),
				'sort_order'        => array( 'label' => __( 'Orden', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '1', 'default' => 0, 'int' => true ),
				'active'            => array( 'label' => __( 'Activo', 'calculadora-cielorraso-pvc' ), 'type' => 'checkbox', 'default' => 1 ),
			),
		);
	}

	private static function categories() {
		return array(
			'key'           => 'categories',
			'repo'          => 'categories',
			'slug'          => 'ccr-categories',
			'singular'      => __( 'categoría', 'calculadora-cielorraso-pvc' ),
			'plural'        => __( 'Categorías', 'calculadora-cielorraso-pvc' ),
			'intro'         => __( 'Las categorías agrupan materiales y definen cómo se eligen: "Todos" calcula todos los materiales activos; "Uno a elección" muestra un desplegable al cliente; "Opcional" permite elegir uno o ninguno.', 'calculadora-cielorraso-pvc' ),
			'unique'        => 'slug',
			'search'        => array( 'name', 'slug' ),
			'before_delete' => static function ( $id ) {
				$n = CCR_Repository::get( 'materials' )->count( 'WHERE category_id = %d', array( $id ) );
				/* translators: %d: materials */
				return $n ? sprintf( __( 'No se puede eliminar: la categoría tiene %d materiales. Muévalos o desactive la categoría.', 'calculadora-cielorraso-pvc' ), $n ) : true;
			},
			'columns'       => array(
				'name'           => array(
					__( 'Nombre', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return '<strong>' . esc_html( $row['name'] ) . '</strong><br><code>' . esc_html( $row['slug'] ) . '</code>';
					},
				),
				'selection_mode' => array(
					__( 'Selección', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						$o = self::selection_modes();
						return esc_html( isset( $o[ $row['selection_mode'] ] ) ? $o[ $row['selection_mode'] ] : $row['selection_mode'] );
					},
				),
				'customer_label' => array( __( 'Etiqueta para el cliente', 'calculadora-cielorraso-pvc' ) ),
				'materials'      => array(
					__( 'Materiales', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						$n = CCR_Repository::get( 'materials' )->count( 'WHERE category_id = %d', array( $row['id'] ) );
						return '<a href="' . esc_url( CCR_Admin::url( 'ccr-materials', array( 'filter' => $row['id'] ) ) ) . '">' . esc_html( number_format_i18n( $n ) ) . '</a>';
					},
				),
				'sort_order'     => array( __( 'Orden', 'calculadora-cielorraso-pvc' ) ),
				'active'         => array( __( 'Estado', 'calculadora-cielorraso-pvc' ), self::status_column() ),
			),
			'fields'        => array(
				'name'           => array( 'label' => __( 'Nombre', 'calculadora-cielorraso-pvc' ), 'type' => 'text', 'required' => true ),
				'slug'           => array( 'label' => __( 'Identificador', 'calculadora-cielorraso-pvc' ), 'type' => 'slug', 'required' => true, 'help' => __( 'Se usa en fórmulas: cat_IDENTIFICADOR_largo, q_cat_IDENTIFICADOR…', 'calculadora-cielorraso-pvc' ) ),
				'description'    => array( 'label' => __( 'Descripción', 'calculadora-cielorraso-pvc' ), 'type' => 'textarea' ),
				'selection_mode' => array( 'label' => __( 'Modo de selección', 'calculadora-cielorraso-pvc' ), 'type' => 'select', 'options' => array( __CLASS__, 'selection_modes' ), 'default' => 'all' ),
				'customer_label' => array( 'label' => __( 'Etiqueta en el formulario', 'calculadora-cielorraso-pvc' ), 'type' => 'text', 'help' => __( 'Solo para "Uno a elección" y "Opcional". Vacío = nombre.', 'calculadora-cielorraso-pvc' ) ),
				'sort_order'     => array( 'label' => __( 'Orden', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '1', 'default' => 0, 'int' => true, 'help' => __( 'También define el orden de evaluación de las fórmulas.', 'calculadora-cielorraso-pvc' ) ),
				'active'         => array( 'label' => __( 'Activa', 'calculadora-cielorraso-pvc' ), 'type' => 'checkbox', 'default' => 1 ),
			),
		);
	}

	public static function selection_modes() {
		return array(
			'all'      => __( 'Todos (se calculan todos los materiales)', 'calculadora-cielorraso-pvc' ),
			'single'   => __( 'Uno a elección del cliente', 'calculadora-cielorraso-pvc' ),
			'optional' => __( 'Opcional (uno o ninguno)', 'calculadora-cielorraso-pvc' ),
		);
	}

	private static function install_types() {
		return array(
			'key'           => 'install_types',
			'repo'          => 'install_types',
			'slug'          => 'ccr-install-types',
			'singular'      => __( 'tipo de instalación', 'calculadora-cielorraso-pvc' ),
			'plural'        => __( 'Tipos de instalación', 'calculadora-cielorraso-pvc' ),
			'intro'         => __( 'Cada tipo crea la variable inst_IDENTIFICADOR (1 o 0) para usar en fórmulas. En cada material puede indicar a qué tipos aplica.', 'calculadora-cielorraso-pvc' ),
			'unique'        => 'slug',
			'search'        => array( 'name', 'slug' ),
			'before_delete' => static function ( $id ) {
				$repo = CCR_Repository::get( 'materials' );
				$n    = $repo->count( 'WHERE FIND_IN_SET(%d, install_types)', array( $id ) );
				/* translators: %d: materials */
				return $n ? sprintf( __( 'No se puede eliminar: %d materiales están asignados a este tipo. Desactívelo o quite la asignación.', 'calculadora-cielorraso-pvc' ), $n ) : true;
			},
			'after_save'    => static function ( $id, $data ) {
				// Solo puede haber un tipo por defecto.
				if ( ! empty( $data['is_default'] ) ) {
					foreach ( CCR_Repository::get( 'install_types' )->all() as $t ) {
						if ( (int) $t['id'] !== (int) $id && (int) $t['is_default'] ) {
							CCR_Repository::get( 'install_types' )->update( $t['id'], array( 'is_default' => 0 ) );
						}
					}
				}
			},
			'columns'       => array(
				'name'       => array(
					__( 'Nombre', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return '<strong>' . esc_html( $row['name'] ) . '</strong>' . ( (int) $row['is_default'] ? ' <span class="ccr-badge">' . esc_html__( 'predeterminado', 'calculadora-cielorraso-pvc' ) . '</span>' : '' ) . '<br><code>inst_' . esc_html( CCR_Calculator::var_name( $row['slug'] ) ) . '</code>';
					},
				),
				'description' => array( __( 'Descripción', 'calculadora-cielorraso-pvc' ) ),
				'sort_order'  => array( __( 'Orden', 'calculadora-cielorraso-pvc' ) ),
				'active'      => array( __( 'Estado', 'calculadora-cielorraso-pvc' ), self::status_column() ),
			),
			'fields'        => array(
				'name'        => array( 'label' => __( 'Nombre', 'calculadora-cielorraso-pvc' ), 'type' => 'text', 'required' => true ),
				'slug'        => array( 'label' => __( 'Identificador', 'calculadora-cielorraso-pvc' ), 'type' => 'slug', 'required' => true ),
				'description' => array( 'label' => __( 'Descripción', 'calculadora-cielorraso-pvc' ), 'type' => 'textarea' ),
				'is_default'  => array( 'label' => __( 'Predeterminado', 'calculadora-cielorraso-pvc' ), 'type' => 'checkbox', 'default' => 0 ),
				'sort_order'  => array( 'label' => __( 'Orden', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '1', 'default' => 0, 'int' => true ),
				'active'      => array( 'label' => __( 'Activo', 'calculadora-cielorraso-pvc' ), 'type' => 'checkbox', 'default' => 1 ),
			),
		);
	}

	private static function variables() {
		return array(
			'key'          => 'variables',
			'repo'         => 'variables',
			'slug'         => 'ccr-variables',
			'singular'     => __( 'regla', 'calculadora-cielorraso-pvc' ),
			'plural'       => __( 'Reglas de cálculo', 'calculadora-cielorraso-pvc' ),
			'intro'        => __( 'Las reglas son variables intermedias que se calculan en orden antes de los materiales: separaciones, solapes, cantidad de líneas de perfiles, fijaciones, etc. Una regla puede usar las anteriores.', 'calculadora-cielorraso-pvc' ),
			'unique'       => 'var_key',
			'search'       => array( 'var_key', 'label', 'formula' ),
			'formula_help' => true,
			'validate'     => static function ( $data ) {
				$key = $data['var_key'];
				if ( in_array( $key, CCR_Calculator::RESERVED, true ) || preg_match( '/^(q_|qbase_|sel_|cat_|inst_|m_)/', $key ) ) {
					return array( __( 'Ese nombre de variable está reservado. Elija otro.', 'calculadora-cielorraso-pvc' ) );
				}
				if ( isset( CCR_Expression::functions()[ $key ] ) ) {
					return array( __( 'El nombre coincide con una función. Elija otro.', 'calculadora-cielorraso-pvc' ) );
				}
				return array();
			},
			'columns'      => array(
				'var_key'    => array(
					__( 'Variable', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return '<code><strong>' . esc_html( $row['var_key'] ) . '</strong></code><br>' . esc_html( $row['label'] );
					},
				),
				'formula'    => array(
					__( 'Fórmula / valor', 'calculadora-cielorraso-pvc' ),
					static function ( $row ) {
						return '<code class="ccr-formula-preview">' . esc_html( $row['formula'] ) . '</code>';
					},
				),
				'sort_order' => array( __( 'Orden', 'calculadora-cielorraso-pvc' ) ),
				'active'     => array( __( 'Estado', 'calculadora-cielorraso-pvc' ), self::status_column() ),
			),
			'fields'       => array(
				'var_key'     => array( 'label' => __( 'Nombre de variable', 'calculadora-cielorraso-pvc' ), 'type' => 'slug', 'required' => true ),
				'label'       => array( 'label' => __( 'Descripción corta', 'calculadora-cielorraso-pvc' ), 'type' => 'text' ),
				'formula'     => array( 'label' => __( 'Fórmula o valor', 'calculadora-cielorraso-pvc' ), 'type' => 'formula', 'required' => true, 'help' => __( 'Un número (ej. 0.60) o una fórmula (ej. lineas(lado_paralelo, sep_red, tolerancia_perfil)).', 'calculadora-cielorraso-pvc' ) ),
				'description' => array( 'label' => __( 'Notas', 'calculadora-cielorraso-pvc' ), 'type' => 'textarea' ),
				'sort_order'  => array( 'label' => __( 'Orden', 'calculadora-cielorraso-pvc' ), 'type' => 'number', 'step' => '1', 'default' => 0, 'int' => true ),
				'active'      => array( 'label' => __( 'Activa', 'calculadora-cielorraso-pvc' ), 'type' => 'checkbox', 'default' => 1 ),
			),
		);
	}
}
