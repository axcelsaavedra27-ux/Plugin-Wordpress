<?php
/**
 * Probador: ejecuta un cálculo desde el panel mostrando todas las variables, cantidades
 * intermedias y advertencias. Ideal para validar fórmulas nuevas.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Tester {

	const OPTION = 'ccr_tester_sample';

	public function __construct() {}

	/**
	 * Ambiente de ejemplo (el último usado en el probador).
	 */
	public static function sample_input() {
		$saved = get_option( self::OPTION );
		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'largo'        => '4.80',
				'ancho'        => '3.60',
				'alto'         => '0.30',
				'install_type' => '',
				'direction'    => 'auto',
				'selections'   => array(),
				'variants'     => array(),
				'observations' => '',
			)
		);
	}

	/**
	 * Advertencias al calcular el ambiente de ejemplo.
	 *
	 * @return string[]
	 */
	public static function quick_check() {
		$calc   = new CCR_Calculator();
		$result = $calc->calculate( self::sample_input(), true );
		if ( is_wp_error( $result ) ) {
			return array( $result->get_error_message() );
		}
		return array_values( array_unique( $result['debug']['warnings'] ) );
	}

	public static function render() {
		CCR_Admin::check_cap();

		$input  = self::sample_input();
		$result = null;

		if ( isset( $_POST['ccr_tester'] ) ) {
			check_admin_referer( 'ccr_tester' );
			$post  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanitiza en CCR_Calculator::normalize().
			$input = array(
				'largo'        => isset( $post['largo'] ) ? sanitize_text_field( $post['largo'] ) : '',
				'ancho'        => isset( $post['ancho'] ) ? sanitize_text_field( $post['ancho'] ) : '',
				'alto'         => isset( $post['alto'] ) ? sanitize_text_field( $post['alto'] ) : '',
				'install_type' => isset( $post['install_type'] ) ? sanitize_key( $post['install_type'] ) : '',
				'direction'    => isset( $post['direction'] ) ? sanitize_key( $post['direction'] ) : 'auto',
				'selections'   => isset( $post['selections'] ) && is_array( $post['selections'] ) ? array_map( 'absint', $post['selections'] ) : array(),
				'variants'     => array(),
				'observations' => '',
			);
			if ( isset( $post['variants'] ) && is_array( $post['variants'] ) ) {
				foreach ( $post['variants'] as $mid => $v ) {
					$input['variants'][ absint( $mid ) ] = 'auto' === $v ? 'auto' : absint( $v );
				}
			}
			update_option( self::OPTION, $input, false );
		}

		$calc   = new CCR_Calculator();
		$config = $calc->form_config();
		$result = $calc->calculate( $input, true );
		$s      = CCR_Settings::all();
		?>
		<div class="wrap ccr-wrap">
			<h1><?php esc_html_e( 'Probador de cálculo', 'calculadora-cielorraso-pvc' ); ?></h1>
			<p class="ccr-intro"><?php esc_html_e( 'Ejecute un cálculo con todas las variables visibles. El ambiente usado aquí también se usa para validar fórmulas al guardarlas.', 'calculadora-cielorraso-pvc' ); ?></p>

			<form method="post" class="ccr-tester-form">
				<?php wp_nonce_field( 'ccr_tester' ); ?>
				<input type="hidden" name="ccr_tester" value="1">
				<div class="ccr-tester-grid">
					<label><?php esc_html_e( 'Largo (m)', 'calculadora-cielorraso-pvc' ); ?><input type="text" name="largo" value="<?php echo esc_attr( $input['largo'] ); ?>"></label>
					<label><?php esc_html_e( 'Ancho (m)', 'calculadora-cielorraso-pvc' ); ?><input type="text" name="ancho" value="<?php echo esc_attr( $input['ancho'] ); ?>"></label>
					<label><?php esc_html_e( 'Alto / cámara (m)', 'calculadora-cielorraso-pvc' ); ?><input type="text" name="alto" value="<?php echo esc_attr( $input['alto'] ); ?>"></label>
					<label><?php esc_html_e( 'Instalación', 'calculadora-cielorraso-pvc' ); ?>
						<select name="install_type">
							<?php foreach ( $config['install_types'] as $t ) : ?>
								<option value="<?php echo esc_attr( $t['slug'] ); ?>" <?php selected( $input['install_type'] ? $input['install_type'] : ( $t['is_default'] ? $t['slug'] : '' ), $t['slug'] ); ?>><?php echo esc_html( $t['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><?php esc_html_e( 'Sentido', 'calculadora-cielorraso-pvc' ); ?>
						<select name="direction">
							<option value="largo" <?php selected( $input['direction'], 'largo' ); ?>><?php echo esc_html( $s['label_dir_largo'] ); ?></option>
							<option value="ancho" <?php selected( $input['direction'], 'ancho' ); ?>><?php echo esc_html( $s['label_dir_ancho'] ); ?></option>
							<option value="auto" <?php selected( $input['direction'], 'auto' ); ?>><?php echo esc_html( $s['label_dir_auto'] ); ?></option>
						</select>
					</label>
					<?php foreach ( $config['categories'] as $cat ) : ?>
						<label><?php echo esc_html( $cat['label'] ); ?>
							<select name="selections[<?php echo esc_attr( $cat['slug'] ); ?>]">
								<?php if ( $cat['optional'] ) : ?>
									<option value="0"><?php esc_html_e( 'Ninguno', 'calculadora-cielorraso-pvc' ); ?></option>
								<?php endif; ?>
								<?php foreach ( $cat['items'] as $it ) : ?>
									<option value="<?php echo esc_attr( $it['id'] ); ?>" <?php selected( isset( $input['selections'][ $cat['slug'] ] ) ? (int) $input['selections'][ $cat['slug'] ] : 0, $it['id'] ); ?>><?php echo esc_html( $it['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<?php
						foreach ( $cat['items'] as $it ) :
							if ( count( $it['variants'] ) < 2 ) {
								continue;
							}
							$cur = isset( $input['variants'][ $it['id'] ] ) ? (string) $input['variants'][ $it['id'] ] : 'auto';
							?>
							<label class="ccr-variant-pick" data-for="<?php echo esc_attr( $it['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: material */ __( 'Largo de %s', 'calculadora-cielorraso-pvc' ), $it['name'] ) ); ?>
								<select name="variants[<?php echo esc_attr( $it['id'] ); ?>]">
									<option value="auto"><?php echo esc_html( $s['label_variant_auto'] ); ?></option>
									<?php foreach ( $it['variants'] as $v ) : ?>
										<option value="<?php echo esc_attr( $v['index'] ); ?>" <?php selected( $cur, (string) $v['index'] ); ?>><?php echo esc_html( $v['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
				<?php submit_button( __( 'Calcular', 'calculadora-cielorraso-pvc' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( is_wp_error( $result ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
			<?php else : ?>
				<?php $d = $result['debug']; ?>
				<?php if ( $d['warnings'] ) : ?>
					<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Advertencias:', 'calculadora-cielorraso-pvc' ); ?></strong></p><ul><?php foreach ( array_unique( $d['warnings'] ) as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul></div>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Resultado', 'calculadora-cielorraso-pvc' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: area, 2: install, 3: direction, 4: combos */
							__( 'Área: %1$s m² · Instalación: %2$s · Sentido elegido: %3$s · Combinaciones evaluadas: %4$d', 'calculadora-cielorraso-pvc' ),
							number_format_i18n( $result['summary']['area'], 2 ),
							$result['summary']['install_type'],
							$result['summary']['direction_label'],
							$d['combos']
						)
					);
					?>
				</p>
				<table class="widefat striped ccr-table">
					<thead><tr>
						<th><?php esc_html_e( 'Material', 'calculadora-cielorraso-pvc' ); ?></th>
						<th class="num"><?php esc_html_e( 'Base (fórmula)', 'calculadora-cielorraso-pvc' ); ?></th>
						<th class="num"><?php esc_html_e( 'Factor', 'calculadora-cielorraso-pvc' ); ?></th>
						<th class="num"><?php esc_html_e( 'Desperdicio', 'calculadora-cielorraso-pvc' ); ?></th>
						<th class="num"><?php esc_html_e( 'Cantidad final', 'calculadora-cielorraso-pvc' ); ?></th>
						<th class="num"><?php esc_html_e( 'Precio', 'calculadora-cielorraso-pvc' ); ?></th>
						<th class="num"><?php esc_html_e( 'Subtotal', 'calculadora-cielorraso-pvc' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $d['raw_lines'] as $l ) : ?>
							<tr class="<?php echo $l['qty'] > 0 ? '' : 'ccr-inactive'; ?>">
								<td><strong><?php echo esc_html( $l['name'] . ( $l['variant_label'] ? ' ' . $l['variant_label'] : '' ) ); ?></strong><br><code>q_<?php echo esc_html( CCR_Calculator::var_name( $l['code'] ) ); ?></code> · <?php echo esc_html( $l['category'] ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $l['base_qty'], 4 ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $l['factor'], 4 ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $l['waste_pct'], 2 ) ); ?> %</td>
								<td class="num"><strong><?php echo esc_html( CCR_Calculator::format_qty( $l['qty'], $l['decimals'] ) ); ?></strong> <?php echo esc_html( $l['unit'] ); ?></td>
								<td class="num"><?php echo esc_html( CCR_Settings::format_price( $l['unit_price'] ) . ( $l['price_qty'] > 1 ? ' / ' . $l['price_qty'] : '' ) ); ?></td>
								<td class="num"><?php echo esc_html( CCR_Settings::format_price( $l['subtotal'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot><tr><th colspan="6"><?php esc_html_e( 'Total', 'calculadora-cielorraso-pvc' ); ?></th><th class="num"><?php echo esc_html( CCR_Settings::format_price( $d['total'] ) ); ?></th></tr></tfoot>
				</table>

				<h2><?php esc_html_e( 'Variables del cálculo elegido', 'calculadora-cielorraso-pvc' ); ?></h2>
				<div class="ccr-vars">
					<?php foreach ( $d['variables'] as $k => $v ) : ?>
						<div><code><?php echo esc_html( $k ); ?></code> <span><?php echo esc_html( is_numeric( $v ) ? rtrim( rtrim( number_format( (float) $v, 4, '.', '' ), '0' ), '.' ) : '' ); ?></span></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
