<?php
/**
 * Leads: listado, detalle con sus cálculos, borrado y exportación (CSV / Excel).
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Leads {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public function handle() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'ccr-leads' !== $page ) {
			return;
		}
		$action = isset( $_REQUEST['ccr_action'] ) ? sanitize_key( $_REQUEST['ccr_action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $action ) {
			return;
		}
		CCR_Admin::check_cap();
		$list = CCR_Admin::url( 'ccr-leads' );

		switch ( $action ) {
			case 'export_csv':
			case 'export_xlsx':
				check_admin_referer( 'ccr_leads_export' );
				$this->export( 'export_csv' === $action ? 'csv' : 'xlsx' );
				break;

			case 'delete':
				$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
				check_admin_referer( 'ccr_lead_delete_' . $id );
				$this->delete( array( $id ) );
				CCR_Admin::redirect( $list, __( 'Lead eliminado.', 'calculadora-cielorraso-pvc' ) );
				break;

			case 'bulk_delete':
				check_admin_referer( 'ccr_leads_bulk' );
				$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
				$this->delete( $ids );
				/* translators: %d: count */
				CCR_Admin::redirect( $list, sprintf( __( '%d leads eliminados.', 'calculadora-cielorraso-pvc' ), count( array_filter( $ids ) ) ) );
				break;
		}
	}

	private function delete( array $ids ) {
		global $wpdb;
		$calcs = CCR_Repository::get( 'calculations' )->table();
		foreach ( array_filter( $ids ) as $id ) {
			CCR_Repository::get( 'leads' )->delete( $id );
			$wpdb->delete( $calcs, array( 'lead_id' => $id ), array( '%d' ) );
		}
	}

	/**
	 * Exporta todos los leads (o los filtrados por búsqueda) con su último cálculo.
	 */
	private function export( $format ) {
		global $wpdb;
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verificado en handle().
		$data   = CCR_Repository::get( 'leads' )->paginate(
			array(
				'search'         => $search,
				'search_columns' => array( 'name', 'company', 'phone', 'email' ),
				'per_page'       => 100000,
				'page'           => 1,
				'order_by'       => 'created_at',
				'order'          => 'DESC',
			)
		);

		$calcs_table = CCR_Repository::get( 'calculations' )->table();
		$rows        = array(
			array( 'ID', 'Fecha', 'Nombre', 'Empresa', 'Teléfono', 'Email', 'Consentimiento', 'Cálculos', 'Último: largo (m)', 'Último: ancho (m)', 'Último: área (m²)', 'Último: instalación', 'Último: total', 'Observaciones', 'Origen' ),
		);
		foreach ( $data['items'] as $lead ) {
			$last  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$calcs_table} WHERE lead_id = %d ORDER BY id DESC LIMIT 1", $lead['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$calcs_table} WHERE lead_id = %d", $lead['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$inp   = $last ? json_decode( $last['inputs'], true ) : array();
			$rows[] = array(
				(int) $lead['id'],
				$lead['created_at'],
				$lead['name'],
				$lead['company'],
				$lead['phone'],
				$lead['email'],
				(int) $lead['consent'] ? 'Sí' : 'No',
				$count,
				$last && isset( $inp['largo'] ) ? (float) $inp['largo'] : '',
				$last && isset( $inp['ancho'] ) ? (float) $inp['ancho'] : '',
				$last ? (float) $last['area'] : '',
				$last && isset( $inp['install_type']['name'] ) ? $inp['install_type']['name'] : '',
				$last ? (float) $last['total'] : '',
				$last ? (string) $last['observations'] : '',
				$lead['source_url'],
			);
		}

		$filename = 'leads-cielorraso-' . gmdate( 'Ymd-His' );
		nocache_headers();

		if ( 'xlsx' === $format ) {
			$bin = CCR_Xlsx::build( $rows, 'Leads', array( 6, 18, 24, 22, 16, 28, 14, 10, 14, 14, 14, 28, 14, 40, 40 ) );
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
			header( 'Content-Length: ' . strlen( $bin ) );
			echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binario.
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM para que Excel reconozca UTF-8.
		foreach ( $rows as $row ) {
			// Evita inyección de fórmulas en hojas de cálculo (CSV injection).
			$row = array_map(
				static function ( $v ) {
					return is_string( $v ) && preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
				},
				$row
			);
			fputcsv( $out, $row, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	public static function render() {
		CCR_Admin::check_cap();
		$view = isset( $_GET['action'] ) && 'view' === $_GET['action'] ? absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap ccr-wrap">';
		if ( $view ) {
			self::render_view( $view );
		} else {
			self::render_list();
		}
		echo '</div>';
	}

	private static function render_list() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$per   = 30;
		$data  = CCR_Repository::get( 'leads' )->paginate(
			array(
				'search'         => $search,
				'search_columns' => array( 'name', 'company', 'phone', 'email' ),
				'per_page'       => $per,
				'page'           => $paged,
				'order_by'       => 'created_at',
				'order'          => 'DESC',
			)
		);
		$calcs = CCR_Repository::get( 'calculations' )->table();
		$s     = CCR_Settings::all();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'calculadora-cielorraso-pvc' ); ?></h1>
		<hr class="wp-header-end">
		<?php CCR_Admin::notices(); ?>

		<?php if ( ! $s['leads_enabled'] ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'La captura de leads está desactivada.', 'calculadora-cielorraso-pvc' ); ?>
				<a href="<?php echo esc_url( CCR_Admin::url( 'ccr-settings' ) . '#ccr-leads' ); ?>"><?php esc_html_e( 'Activarla en Ajustes', 'calculadora-cielorraso-pvc' ); ?></a>
			</p></div>
		<?php endif; ?>

		<div class="ccr-toolbar">
			<form method="get">
				<input type="hidden" name="page" value="ccr-leads">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Nombre, empresa, teléfono o email', 'calculadora-cielorraso-pvc' ); ?>">
				<button class="button"><?php esc_html_e( 'Buscar', 'calculadora-cielorraso-pvc' ); ?></button>
			</form>
			<form method="post" class="ccr-export-forms">
				<?php wp_nonce_field( 'ccr_leads_export' ); ?>
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
				<button class="button" name="ccr_action" value="export_csv"><?php esc_html_e( 'Exportar CSV', 'calculadora-cielorraso-pvc' ); ?></button>
				<button class="button button-primary" name="ccr_action" value="export_xlsx"><?php esc_html_e( 'Exportar Excel', 'calculadora-cielorraso-pvc' ); ?></button>
			</form>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'ccr_leads_bulk' ); ?>
			<table class="wp-list-table widefat fixed striped ccr-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" class="ccr-check-all" aria-label="<?php esc_attr_e( 'Seleccionar todos', 'calculadora-cielorraso-pvc' ); ?>"></td>
						<th><?php esc_html_e( 'Nombre', 'calculadora-cielorraso-pvc' ); ?></th>
						<th><?php esc_html_e( 'Empresa', 'calculadora-cielorraso-pvc' ); ?></th>
						<th><?php esc_html_e( 'Teléfono', 'calculadora-cielorraso-pvc' ); ?></th>
						<th><?php esc_html_e( 'Email', 'calculadora-cielorraso-pvc' ); ?></th>
						<th><?php esc_html_e( 'Cálculos', 'calculadora-cielorraso-pvc' ); ?></th>
						<th><?php esc_html_e( 'Fecha', 'calculadora-cielorraso-pvc' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $data['items'] ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Todavía no hay leads.', 'calculadora-cielorraso-pvc' ); ?></td></tr>
					<?php endif; ?>
					<?php
					foreach ( $data['items'] as $lead ) :
						$n    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$calcs} WHERE lead_id = %d", $lead['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$view = CCR_Admin::url( 'ccr-leads', array( 'action' => 'view', 'id' => $lead['id'] ) );
						$del  = wp_nonce_url( CCR_Admin::url( 'ccr-leads', array( 'ccr_action' => 'delete', 'id' => $lead['id'] ) ), 'ccr_lead_delete_' . $lead['id'] );
						?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="<?php echo esc_attr( $lead['id'] ); ?>"></th>
							<td>
								<strong><a href="<?php echo esc_url( $view ); ?>"><?php echo esc_html( $lead['name'] ? $lead['name'] : '—' ); ?></a></strong>
								<div class="row-actions visible">
									<a href="<?php echo esc_url( $view ); ?>"><?php esc_html_e( 'Ver', 'calculadora-cielorraso-pvc' ); ?></a> |
									<a class="ccr-danger ccr-confirm-delete" href="<?php echo esc_url( $del ); ?>"><?php esc_html_e( 'Eliminar', 'calculadora-cielorraso-pvc' ); ?></a>
								</div>
							</td>
							<td><?php echo esc_html( $lead['company'] ); ?></td>
							<td><?php echo esc_html( $lead['phone'] ); ?></td>
							<td><?php echo $lead['email'] ? '<a href="' . esc_url( 'mailto:' . $lead['email'] ) . '">' . esc_html( $lead['email'] ) . '</a>' : ''; ?></td>
							<td><?php echo esc_html( number_format_i18n( $n ) ); ?></td>
							<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $lead['created_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $data['items'] ) : ?>
				<p><button class="button ccr-confirm-delete" name="ccr_action" value="bulk_delete"><?php esc_html_e( 'Eliminar seleccionados', 'calculadora-cielorraso-pvc' ); ?></button></p>
			<?php endif; ?>
		</form>
		<?php
		$pages = (int) ceil( $data['total'] / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			) . '</div></div>';
		}
	}

	private static function render_view( $id ) {
		global $wpdb;
		$lead = CCR_Repository::get( 'leads' )->find( $id );
		if ( ! $lead ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Lead no encontrado.', 'calculadora-cielorraso-pvc' ) . '</p></div>';
			return;
		}
		$calcs = CCR_Repository::get( 'calculations' )->table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$calcs} WHERE lead_id = %d ORDER BY id DESC LIMIT 100", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html( $lead['name'] ? $lead['name'] : __( 'Lead', 'calculadora-cielorraso-pvc' ) ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( CCR_Admin::url( 'ccr-leads' ) ); ?>">&larr; <?php esc_html_e( 'Leads', 'calculadora-cielorraso-pvc' ); ?></a>
		<hr class="wp-header-end">

		<div class="ccr-box">
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Empresa', 'calculadora-cielorraso-pvc' ); ?></th><td><?php echo esc_html( $lead['company'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Teléfono', 'calculadora-cielorraso-pvc' ); ?></th><td><?php echo esc_html( $lead['phone'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'calculadora-cielorraso-pvc' ); ?></th><td><?php echo esc_html( $lead['email'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Consentimiento', 'calculadora-cielorraso-pvc' ); ?></th><td><?php echo (int) $lead['consent'] ? esc_html__( 'Sí', 'calculadora-cielorraso-pvc' ) : esc_html__( 'No', 'calculadora-cielorraso-pvc' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Origen', 'calculadora-cielorraso-pvc' ); ?></th><td><?php echo esc_html( $lead['source_url'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Fecha', 'calculadora-cielorraso-pvc' ); ?></th><td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $lead['created_at'] ) ); ?></td></tr>
			</table>
		</div>

		<h2><?php esc_html_e( 'Cálculos realizados', 'calculadora-cielorraso-pvc' ); ?></h2>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'Sin cálculos.', 'calculadora-cielorraso-pvc' ); ?></p>
		<?php endif; ?>
		<?php
		foreach ( $rows as $calc ) :
			$res = json_decode( $calc['results'], true );
			if ( ! is_array( $res ) ) {
				continue;
			}
			$sum = $res['summary'];
			?>
			<details class="ccr-box ccr-calc-detail">
				<summary>
					<strong><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $calc['created_at'] ) ); ?></strong> —
					<?php echo esc_html( sprintf( '%s × %s m (%s m²) · %s · %s', $sum['largo'], $sum['ancho'], $sum['area'], $sum['install_type'], $sum['direction_label'] ) ); ?>
					<?php if ( ! empty( $res['total_display'] ) ) : ?> · <strong><?php echo esc_html( $res['total_display'] ); ?></strong><?php endif; ?>
				</summary>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Material', 'calculadora-cielorraso-pvc' ); ?></th><th><?php esc_html_e( 'Cantidad', 'calculadora-cielorraso-pvc' ); ?></th><th><?php esc_html_e( 'Unidad', 'calculadora-cielorraso-pvc' ); ?></th><th><?php esc_html_e( 'Subtotal', 'calculadora-cielorraso-pvc' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $res['lines'] as $l ) : ?>
							<tr>
								<td><?php echo esc_html( $l['name'] ); ?></td>
								<td><?php echo esc_html( $l['qty_display'] ); ?></td>
								<td><?php echo esc_html( $l['unit'] ); ?></td>
								<td><?php echo esc_html( isset( $l['subtotal_display'] ) ? $l['subtotal_display'] : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $calc['observations'] ) : ?>
					<p><strong><?php esc_html_e( 'Observaciones:', 'calculadora-cielorraso-pvc' ); ?></strong> <?php echo esc_html( $calc['observations'] ); ?></p>
				<?php endif; ?>
			</details>
		<?php endforeach; ?>
		<?php
	}
}
