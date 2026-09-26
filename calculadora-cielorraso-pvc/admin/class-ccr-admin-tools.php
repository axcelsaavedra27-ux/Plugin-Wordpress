<?php
/**
 * Herramientas: exportar / importar configuración (JSON) y restaurar datos de ejemplo.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Tools {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public function handle() {
		if ( ! isset( $_POST['ccr_tools_action'] ) ) {
			return;
		}
		CCR_Admin::check_cap();
		check_admin_referer( 'ccr_tools' );
		$url    = CCR_Admin::url( 'ccr-tools' );
		$action = sanitize_key( $_POST['ccr_tools_action'] );

		if ( 'export' === $action ) {
			$this->export();
		}

		if ( 'reset' === $action ) {
			CCR_Seeder::reset();
			CCR_Admin::redirect( $url, __( 'Se restauraron los datos de ejemplo.', 'calculadora-cielorraso-pvc' ) );
		}

		if ( 'import' === $action ) {
			if ( empty( $_FILES['ccr_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ccr_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				CCR_Admin::redirect( $url, __( 'Seleccione un archivo.', 'calculadora-cielorraso-pvc' ), 'error' );
			}
			if ( (int) $_FILES['ccr_file']['size'] > 2 * MB_IN_BYTES ) {
				CCR_Admin::redirect( $url, __( 'El archivo es demasiado grande.', 'calculadora-cielorraso-pvc' ), 'error' );
			}
			$json = file_get_contents( $_FILES['ccr_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput
			$data = json_decode( (string) $json, true );
			if ( ! is_array( $data ) || empty( $data['plugin'] ) || 'calculadora-cielorraso-pvc' !== $data['plugin'] ) {
				CCR_Admin::redirect( $url, __( 'El archivo no es una exportación válida de esta calculadora.', 'calculadora-cielorraso-pvc' ), 'error' );
			}
			$this->import( $data, ! empty( $_POST['import_settings'] ) );
			CCR_Admin::redirect( $url, __( 'Configuración importada.', 'calculadora-cielorraso-pvc' ) );
		}
	}

	private function export() {
		$data = array(
			'plugin'        => 'calculadora-cielorraso-pvc',
			'version'       => CCR_VERSION,
			'exported'      => gmdate( 'c' ),
			'settings'      => CCR_Settings::all(),
			'categories'    => CCR_Repository::get( 'categories' )->all(),
			'install_types' => CCR_Repository::get( 'install_types' )->all(),
			'variables'     => CCR_Repository::get( 'variables' )->all(),
			'materials'     => CCR_Repository::get( 'materials' )->all(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="calculadora-cielorraso-config-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- descarga JSON.
		exit;
	}

	/**
	 * Reemplaza el catálogo por el del archivo, re-mapeando IDs.
	 * Los datos pasan por los mismos filtros que el CRUD (columnas permitidas + sanitización).
	 */
	private function import( array $data, $with_settings ) {
		foreach ( array( 'materials', 'categories', 'install_types', 'variables' ) as $repo ) {
			CCR_Repository::get( $repo )->truncate();
		}

		$text = static function ( $v ) {
			return sanitize_text_field( (string) $v );
		};

		$cat_map = array();
		foreach ( isset( $data['categories'] ) ? (array) $data['categories'] : array() as $c ) {
			$old             = isset( $c['id'] ) ? (int) $c['id'] : 0;
			$cat_map[ $old ] = CCR_Repository::get( 'categories' )->insert(
				array(
					'name'           => $text( isset( $c['name'] ) ? $c['name'] : '' ),
					'slug'           => CCR_Calculator::var_name( isset( $c['slug'] ) ? $c['slug'] : 'cat_' . $old ),
					'description'    => sanitize_textarea_field( isset( $c['description'] ) ? (string) $c['description'] : '' ),
					'selection_mode' => isset( $c['selection_mode'] ) && in_array( $c['selection_mode'], array( 'all', 'single', 'optional' ), true ) ? $c['selection_mode'] : 'all',
					'customer_label' => $text( isset( $c['customer_label'] ) ? $c['customer_label'] : '' ),
					'sort_order'     => isset( $c['sort_order'] ) ? (int) $c['sort_order'] : 0,
					'active'         => ! empty( $c['active'] ) ? 1 : 0,
				)
			);
		}

		$type_map = array();
		foreach ( isset( $data['install_types'] ) ? (array) $data['install_types'] : array() as $t ) {
			$old              = isset( $t['id'] ) ? (int) $t['id'] : 0;
			$type_map[ $old ] = CCR_Repository::get( 'install_types' )->insert(
				array(
					'name'        => $text( isset( $t['name'] ) ? $t['name'] : '' ),
					'slug'        => CCR_Calculator::var_name( isset( $t['slug'] ) ? $t['slug'] : 'tipo_' . $old ),
					'description' => sanitize_textarea_field( isset( $t['description'] ) ? (string) $t['description'] : '' ),
					'is_default'  => ! empty( $t['is_default'] ) ? 1 : 0,
					'sort_order'  => isset( $t['sort_order'] ) ? (int) $t['sort_order'] : 0,
					'active'      => ! empty( $t['active'] ) ? 1 : 0,
				)
			);
		}

		foreach ( isset( $data['variables'] ) ? (array) $data['variables'] : array() as $v ) {
			CCR_Repository::get( 'variables' )->insert(
				array(
					'var_key'     => CCR_Calculator::var_name( isset( $v['var_key'] ) ? $v['var_key'] : '' ),
					'label'       => $text( isset( $v['label'] ) ? $v['label'] : '' ),
					'formula'     => CCR_Admin_Crud::sanitize_formula( isset( $v['formula'] ) ? $v['formula'] : '0' ),
					'description' => sanitize_textarea_field( isset( $v['description'] ) ? (string) $v['description'] : '' ),
					'sort_order'  => isset( $v['sort_order'] ) ? (int) $v['sort_order'] : 0,
					'active'      => ! empty( $v['active'] ) ? 1 : 0,
				)
			);
		}

		foreach ( isset( $data['materials'] ) ? (array) $data['materials'] : array() as $m ) {
			$types = array();
			foreach ( CCR_Calculator::decode_ids( isset( $m['install_types'] ) ? $m['install_types'] : '' ) as $old ) {
				if ( isset( $type_map[ $old ] ) ) {
					$types[] = $type_map[ $old ];
				}
			}
			$variants = CCR_Calculator::decode_variants( isset( $m['variants'] ) ? $m['variants'] : '' );
			foreach ( $variants as &$var ) {
				$var['label'] = sanitize_text_field( $var['label'] );
			}
			unset( $var );
			$f = static function ( $k, $def = 0 ) use ( $m ) {
				return isset( $m[ $k ] ) ? (float) $m[ $k ] : $def;
			};
			CCR_Repository::get( 'materials' )->insert(
				array(
					'category_id'       => isset( $m['category_id'], $cat_map[ (int) $m['category_id'] ] ) ? $cat_map[ (int) $m['category_id'] ] : 0,
					'code'              => CCR_Calculator::var_name( isset( $m['code'] ) ? $m['code'] : '' ),
					'sku'               => $text( isset( $m['sku'] ) ? $m['sku'] : '' ),
					'name'              => $text( isset( $m['name'] ) ? $m['name'] : '' ),
					'description'       => sanitize_textarea_field( isset( $m['description'] ) ? (string) $m['description'] : '' ),
					'unit'              => $text( isset( $m['unit'] ) ? $m['unit'] : 'unidad' ),
					'length'            => $f( 'length' ),
					'width'             => $f( 'width' ),
					'yield'             => $f( 'yield' ),
					'price'             => $f( 'price' ),
					'price_qty'         => $f( 'price_qty', 1 ),
					'waste_pct'         => isset( $m['waste_pct'] ) && '' !== $m['waste_pct'] ? (float) $m['waste_pct'] : null,
					'min_qty'           => $f( 'min_qty' ),
					'correction_factor' => $f( 'correction_factor', 1 ),
					'rounding'          => isset( $m['rounding'] ) && in_array( $m['rounding'], array( 'ceil', 'floor', 'round', 'none' ), true ) ? $m['rounding'] : 'ceil',
					'round_multiple'    => $f( 'round_multiple', 1 ),
					'decimals'          => isset( $m['decimals'] ) ? (int) $m['decimals'] : 0,
					'formula'           => CCR_Admin_Crud::sanitize_formula( isset( $m['formula'] ) ? $m['formula'] : '0' ),
					'variants'          => $variants ? wp_json_encode( $variants ) : '',
					'install_types'     => implode( ',', $types ),
					'sort_order'        => isset( $m['sort_order'] ) ? (int) $m['sort_order'] : 0,
					'active'            => ! empty( $m['active'] ) ? 1 : 0,
				)
			);
		}

		if ( $with_settings && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$settings = array();
			foreach ( $data['settings'] as $k => $v ) {
				$settings[ $k ] = is_scalar( $v ) ? wp_slash( (string) $v ) : '';
			}
			CCR_Settings::update( CCR_Settings::sanitize( $settings ) );
		}
	}

	public static function render() {
		CCR_Admin::check_cap();
		?>
		<div class="wrap ccr-wrap">
			<h1><?php esc_html_e( 'Herramientas', 'calculadora-cielorraso-pvc' ); ?></h1>
			<?php CCR_Admin::notices(); ?>

			<div class="ccr-panel-grid">
				<div class="ccr-box">
					<h2><?php esc_html_e( 'Exportar configuración', 'calculadora-cielorraso-pvc' ); ?></h2>
					<p><?php esc_html_e( 'Descarga un JSON con materiales, categorías, tipos de instalación, reglas y ajustes. Útil como copia de seguridad o para copiar la calculadora a otro sitio.', 'calculadora-cielorraso-pvc' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'ccr_tools' ); ?>
						<button class="button button-primary" name="ccr_tools_action" value="export"><?php esc_html_e( 'Descargar JSON', 'calculadora-cielorraso-pvc' ); ?></button>
					</form>
				</div>

				<div class="ccr-box">
					<h2><?php esc_html_e( 'Importar configuración', 'calculadora-cielorraso-pvc' ); ?></h2>
					<p><?php esc_html_e( 'Reemplaza materiales, categorías, tipos y reglas por los del archivo. Los leads no se modifican.', 'calculadora-cielorraso-pvc' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'ccr_tools' ); ?>
						<p><input type="file" name="ccr_file" accept="application/json,.json" required></p>
						<p><label><input type="checkbox" name="import_settings" value="1"> <?php esc_html_e( 'Importar también los ajustes', 'calculadora-cielorraso-pvc' ); ?></label></p>
						<button class="button" name="ccr_tools_action" value="import"><?php esc_html_e( 'Importar', 'calculadora-cielorraso-pvc' ); ?></button>
					</form>
				</div>

				<div class="ccr-box">
					<h2><?php esc_html_e( 'Restaurar datos de ejemplo', 'calculadora-cielorraso-pvc' ); ?></h2>
					<p><?php esc_html_e( 'Borra el catálogo y las reglas actuales y carga la configuración inicial (equivalente a la calculadora de referencia).', 'calculadora-cielorraso-pvc' ); ?></p>
					<form method="post" class="ccr-confirm-reset">
						<?php wp_nonce_field( 'ccr_tools' ); ?>
						<button class="button ccr-danger-button" name="ccr_tools_action" value="reset"><?php esc_html_e( 'Restaurar', 'calculadora-cielorraso-pvc' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
