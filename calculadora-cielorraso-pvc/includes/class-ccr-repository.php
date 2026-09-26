<?php
/**
 * Repositorio genérico sobre las tablas del plugin.
 * Todas las consultas usan $wpdb->prepare / insert / update (protección SQL Injection).
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Repository {

	/** @var string Nombre de tabla sin prefijo. */
	protected $table;

	/** @var array columna => formato (%s, %d, %f). */
	protected $columns;

	/** @var bool La tabla tiene created_at/updated_at. */
	protected $timestamps;

	/** @var array<string,CCR_Repository> */
	private static $instances = array();

	/**
	 * Definición de las tablas. Las columnas listadas son las únicas que se pueden escribir.
	 */
	private static function definitions() {
		return array(
			'categories'    => array(
				'columns'    => array(
					'name'           => '%s',
					'slug'           => '%s',
					'description'    => '%s',
					'selection_mode' => '%s',
					'customer_label' => '%s',
					'sort_order'     => '%d',
					'active'         => '%d',
				),
				'timestamps' => 'both',
			),
			'install_types' => array(
				'columns'    => array(
					'name'        => '%s',
					'slug'        => '%s',
					'description' => '%s',
					'is_default'  => '%d',
					'sort_order'  => '%d',
					'active'      => '%d',
				),
				'timestamps' => 'both',
			),
			'materials'     => array(
				'columns'    => array(
					'category_id'       => '%d',
					'code'              => '%s',
					'sku'               => '%s',
					'name'              => '%s',
					'description'       => '%s',
					'unit'              => '%s',
					'length'            => '%f',
					'width'             => '%f',
					'yield'             => '%f',
					'price'             => '%f',
					'price_qty'         => '%f',
					'waste_pct'         => '%f',
					'min_qty'           => '%f',
					'correction_factor' => '%f',
					'rounding'          => '%s',
					'round_multiple'    => '%f',
					'decimals'          => '%d',
					'formula'           => '%s',
					'variants'          => '%s',
					'install_types'     => '%s',
					'sort_order'        => '%d',
					'active'            => '%d',
				),
				'timestamps' => 'both',
			),
			'variables'     => array(
				'columns'    => array(
					'var_key'     => '%s',
					'label'       => '%s',
					'formula'     => '%s',
					'description' => '%s',
					'sort_order'  => '%d',
					'active'      => '%d',
				),
				'timestamps' => 'both',
			),
			'leads'         => array(
				'columns'    => array(
					'token'      => '%s',
					'name'       => '%s',
					'company'    => '%s',
					'phone'      => '%s',
					'email'      => '%s',
					'consent'    => '%d',
					'source_url' => '%s',
				),
				'timestamps' => 'created',
			),
			'calculations'  => array(
				'columns'    => array(
					'lead_id'      => '%d',
					'inputs'       => '%s',
					'results'      => '%s',
					'area'         => '%f',
					'total'        => '%f',
					'observations' => '%s',
				),
				'timestamps' => 'created',
			),
		);
	}

	/**
	 * @param string $name categories|install_types|materials|variables|leads|calculations.
	 * @return CCR_Repository
	 */
	public static function get( $name ) {
		if ( ! isset( self::$instances[ $name ] ) ) {
			$defs = self::definitions();
			if ( ! isset( $defs[ $name ] ) ) {
				throw new InvalidArgumentException( 'Repositorio desconocido: ' . $name );
			}
			self::$instances[ $name ] = new self( $name, $defs[ $name ]['columns'], $defs[ $name ]['timestamps'] );
		}
		return self::$instances[ $name ];
	}

	private function __construct( $table, $columns, $timestamps ) {
		$this->table      = $table;
		$this->columns    = $columns;
		$this->timestamps = $timestamps;
	}

	public function table() {
		global $wpdb;
		return $wpdb->prefix . 'ccr_' . $this->table;
	}

	public function columns() {
		return array_keys( $this->columns );
	}

	/**
	 * Devuelve todas las filas.
	 *
	 * @param array $args active_only (bool), order_by (columna permitida), order (ASC|DESC).
	 * @return array[]
	 */
	public function all( $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'active_only' => false,
				'order_by'    => isset( $this->columns['sort_order'] ) ? 'sort_order' : 'id',
				'order'       => 'ASC',
			)
		);
		$order_by = $this->safe_column( $args['order_by'] );
		$order    = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$where    = ( $args['active_only'] && isset( $this->columns['active'] ) ) ? 'WHERE active = 1' : '';

		// Tabla, columna y orden provienen de listas blancas; no hay datos del usuario en la consulta.
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table()} {$where} ORDER BY {$order_by} {$order}, id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $rows ? $rows : array();
	}

	public function find( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	public function find_by( $column, $value ) {
		global $wpdb;
		$column = $this->safe_column( $column );
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE {$column} = %s LIMIT 1", $value ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Inserta una fila. Devuelve el ID o false.
	 */
	public function insert( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		if ( $this->timestamps ) {
			$data['created_at'] = $now;
		}
		if ( 'both' === $this->timestamps ) {
			$data['updated_at'] = $now;
		}
		list( $data, $formats ) = $this->filter( $data );
		$ok = $wpdb->insert( $this->table(), $data, $formats );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public function update( $id, array $data ) {
		global $wpdb;
		if ( 'both' === $this->timestamps ) {
			$data['updated_at'] = current_time( 'mysql' );
		}
		list( $data, $formats ) = $this->filter( $data );
		return false !== $wpdb->update( $this->table(), $data, array( 'id' => (int) $id ), $formats, array( '%d' ) );
	}

	public function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public function count( $where_sql = '', $params = array() ) {
		global $wpdb;
		$sql = "SELECT COUNT(*) FROM {$this->table()} " . $where_sql;
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Consulta paginada con búsqueda opcional en columnas de texto.
	 *
	 * @param array $args search, search_columns, per_page, page, order_by, order, where (array columna=>valor).
	 * @return array{items: array, total: int}
	 */
	public function paginate( $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'search'         => '',
				'search_columns' => array(),
				'per_page'       => 20,
				'page'           => 1,
				'order_by'       => 'id',
				'order'          => 'DESC',
				'where'          => array(),
			)
		);

		$clauses = array();
		$params  = array();

		foreach ( $args['where'] as $col => $val ) {
			$clauses[] = $this->safe_column( $col ) . ' = %s';
			$params[]  = $val;
		}

		if ( '' !== $args['search'] && $args['search_columns'] ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$or   = array();
			foreach ( $args['search_columns'] as $col ) {
				$or[]     = $this->safe_column( $col ) . ' LIKE %s';
				$params[] = $like;
			}
			$clauses[] = '(' . implode( ' OR ', $or ) . ')';
		}

		$where    = $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '';
		$order_by = $this->safe_column( $args['order_by'] );
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$total = $this->count( $where, $params );

		$sql      = "SELECT * FROM {$this->table()} {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		$items    = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	public function truncate() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->table()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Solo deja columnas conocidas y arma el array de formatos.
	 * Los valores null se guardan como NULL (formato ignorado por wpdb).
	 */
	private function filter( array $data ) {
		$clean   = array();
		$formats = array();
		$extra   = array(
			'created_at' => '%s',
			'updated_at' => '%s',
		);
		foreach ( $data as $key => $value ) {
			if ( isset( $this->columns[ $key ] ) ) {
				$clean[ $key ] = $value;
				$formats[]     = $this->columns[ $key ];
			} elseif ( isset( $extra[ $key ] ) ) {
				$clean[ $key ] = $value;
				$formats[]     = $extra[ $key ];
			}
		}
		return array( $clean, $formats );
	}

	private function safe_column( $column ) {
		$allowed = array_merge( array( 'id', 'created_at', 'updated_at' ), array_keys( $this->columns ) );
		return in_array( $column, $allowed, true ) ? $column : 'id';
	}
}
