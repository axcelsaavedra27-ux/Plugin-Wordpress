<?php
/**
 * "Fórmulas y desperdicios": edición masiva de desperdicio general, fórmulas, desperdicio por
 * material, mínimos, redondeos, factores de corrección y rendimientos en una sola pantalla.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Formulas {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public function handle() {
		if ( ! isset( $_POST['ccr_formulas_save'] ) ) {
			return;
		}
		CCR_Admin::check_cap();
		check_admin_referer( 'ccr_formulas' );

		$url = CCR_Admin::url( 'ccr-formulas' );

		// Parámetros generales.
		$settings                      = CCR_Settings::all();
		$settings['general_waste_pct'] = isset( $_POST['general_waste_pct'] ) ? max( 0, min( 500, (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['general_waste_pct'] ) ) ) ) ) : $settings['general_waste_pct'];
		$settings['max_combinations']  = isset( $_POST['max_combinations'] ) ? max( 1, min( 5000, absint( $_POST['max_combinations'] ) ) ) : $settings['max_combinations'];
		CCR_Settings::update( $settings );

		// Materiales.
		$rows   = isset( $_POST['m'] ) && is_array( $_POST['m'] ) ? wp_unslash( $_POST['m'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanitiza abajo.
		$repo   = CCR_Repository::get( 'materials' );
		$errors = array();
		$num    = static function ( $v, $nullable = false ) {
			$v = trim( str_replace( ',', '.', (string) $v ) );
			if ( '' === $v ) {
				return $nullable ? null : 0;
			}
			return (float) $v;
		};

		foreach ( $rows as $id => $r ) {
			$id  = absint( $id );
			$mat = $id ? $repo->find( $id ) : null;
			if ( ! $mat || ! is_array( $r ) ) {
				continue;
			}
			$formula = CCR_Admin_Crud::sanitize_formula( isset( $r['formula'] ) ? $r['formula'] : '' );
			$valid   = '' === $formula ? __( 'La fórmula está vacía.', 'calculadora-cielorraso-pvc' ) : CCR_Expression::validate( $formula );
			if ( true !== $valid ) {
				/* translators: 1: material, 2: error */
				$errors[] = sprintf( __( '%1$s: %2$s (no se guardó la fórmula)', 'calculadora-cielorraso-pvc' ), $mat['name'], $valid );
				$formula  = $mat['formula'];
			}
			$rounding = isset( $r['rounding'] ) && array_key_exists( $r['rounding'], CCR_Admin_Entities::rounding_options() ) ? $r['rounding'] : 'ceil';
			$factor   = $num( isset( $r['correction_factor'] ) ? $r['correction_factor'] : 1 );

			$repo->update(
				$id,
				array(
					'formula'           => $formula,
					'correction_factor' => $factor > 0 ? $factor : 1,
					'waste_pct'         => $num( isset( $r['waste_pct'] ) ? $r['waste_pct'] : '', true ),
					'min_qty'           => max( 0, $num( isset( $r['min_qty'] ) ? $r['min_qty'] : 0 ) ),
					'rounding'          => $rounding,
					'round_multiple'    => max( 0, $num( isset( $r['round_multiple'] ) ? $r['round_multiple'] : 1 ) ),
					'yield'             => max( 0, $num( isset( $r['yield'] ) ? $r['yield'] : 0 ) ),
					'active'            => empty( $r['active'] ) ? 0 : 1,
				)
			);
		}

		$warn = CCR_Admin_Tester::quick_check();
		$msg  = __( 'Cambios guardados.', 'calculadora-cielorraso-pvc' );
		if ( $errors || $warn ) {
			$msg .= '<br>' . implode( '<br>', array_map( 'esc_html', array_merge( $errors, $warn ) ) );
			CCR_Admin::redirect( $url, $msg, 'warning' );
		}
		CCR_Admin::redirect( $url, $msg );
	}

	public static function render() {
		CCR_Admin::check_cap();
		$s         = CCR_Settings::all();
		$materials = CCR_Repository::get( 'materials' )->all();
		$cats      = array();
		foreach ( CCR_Repository::get( 'categories' )->all() as $c ) {
			$cats[ $c['id'] ] = $c;
		}
		usort(
			$materials,
			static function ( $a, $b ) use ( $cats ) {
				$ca = isset( $cats[ $a['category_id'] ] ) ? (int) $cats[ $a['category_id'] ]['sort_order'] : 9999;
				$cb = isset( $cats[ $b['category_id'] ] ) ? (int) $cats[ $b['category_id'] ]['sort_order'] : 9999;
				return $ca !== $cb ? $ca - $cb : (int) $a['sort_order'] - (int) $b['sort_order'];
			}
		);
		$rounding = CCR_Admin_Entities::rounding_options();
		?>
		<div class="wrap ccr-wrap">
			<h1><?php esc_html_e( 'Fórmulas y desperdicios', 'calculadora-cielorraso-pvc' ); ?></h1>
			<?php CCR_Admin::notices(); ?>
			<p class="ccr-intro">
				<?php esc_html_e( 'Edite en una sola pantalla las fórmulas y parámetros de cálculo de todos los materiales. Las separaciones, solapes y demás valores intermedios están en', 'calculadora-cielorraso-pvc' ); ?>
				<a href="<?php echo esc_url( CCR_Admin::url( 'ccr-variables' ) ); ?>"><?php esc_html_e( 'Reglas de cálculo', 'calculadora-cielorraso-pvc' ); ?></a>.
			</p>

			<form method="post">
				<?php wp_nonce_field( 'ccr_formulas' ); ?>
				<input type="hidden" name="ccr_formulas_save" value="1">

				<div class="ccr-box">
					<h2><?php esc_html_e( 'Parámetros generales', 'calculadora-cielorraso-pvc' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="ccr-gw"><?php esc_html_e( 'Desperdicio general (%)', 'calculadora-cielorraso-pvc' ); ?></label></th>
							<td><input type="number" step="0.01" min="0" id="ccr-gw" name="general_waste_pct" value="<?php echo esc_attr( $s['general_waste_pct'] ); ?>" class="small-text">
								<p class="description"><?php esc_html_e( 'Se aplica a los materiales que no tienen un desperdicio propio.', 'calculadora-cielorraso-pvc' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="ccr-mc"><?php esc_html_e( 'Máximo de combinaciones a evaluar', 'calculadora-cielorraso-pvc' ); ?></label></th>
							<td><input type="number" step="1" min="1" id="ccr-mc" name="max_combinations" value="<?php echo esc_attr( $s['max_combinations'] ); ?>" class="small-text">
								<p class="description"><?php esc_html_e( 'Para "Más económica" y largos automáticos (sentido × variantes).', 'calculadora-cielorraso-pvc' ); ?></p></td>
						</tr>
					</table>
				</div>

				<div class="ccr-table-scroll">
					<table class="widefat striped ccr-bulk">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Material', 'calculadora-cielorraso-pvc' ); ?></th>
								<th class="ccr-col-formula"><?php esc_html_e( 'Fórmula de cantidad', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Factor', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Desp. %', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Mínimo', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Redondeo', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Múltiplo', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Rend.', 'calculadora-cielorraso-pvc' ); ?></th>
								<th><?php esc_html_e( 'Activo', 'calculadora-cielorraso-pvc' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$last_cat = null;
							foreach ( $materials as $m ) :
								$id = (int) $m['id'];
								$n  = 'm[' . $id . ']';
								if ( $last_cat !== $m['category_id'] ) :
									$last_cat = $m['category_id'];
									?>
									<tr class="ccr-group-row"><th colspan="9"><?php echo esc_html( isset( $cats[ $m['category_id'] ] ) ? $cats[ $m['category_id'] ]['name'] : __( 'Sin categoría', 'calculadora-cielorraso-pvc' ) ); ?></th></tr>
								<?php endif; ?>
								<tr class="<?php echo (int) $m['active'] ? '' : 'ccr-inactive'; ?>">
									<td>
										<a href="<?php echo esc_url( CCR_Admin::url( 'ccr-materials', array( 'action' => 'edit', 'id' => $id ) ) ); ?>"><strong><?php echo esc_html( $m['name'] ); ?></strong></a><br>
										<code>q_<?php echo esc_html( CCR_Calculator::var_name( $m['code'] ) ); ?></code>
										<input type="hidden" class="ccr-num-length" value="<?php echo esc_attr( $m['length'] ); ?>">
										<input type="hidden" class="ccr-num-width" value="<?php echo esc_attr( $m['width'] ); ?>">
										<input type="hidden" class="ccr-num-price" value="<?php echo esc_attr( $m['price'] ); ?>">
									</td>
									<td class="ccr-col-formula">
										<textarea id="ccr-bf-<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $n ); ?>[formula]" rows="2" class="code ccr-formula" spellcheck="false"><?php echo esc_textarea( $m['formula'] ); ?></textarea>
										<button type="button" class="button button-small ccr-test-formula" data-target="ccr-bf-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Probar', 'calculadora-cielorraso-pvc' ); ?></button>
										<span class="ccr-test-result" aria-live="polite"></span>
									</td>
									<td><input type="number" step="0.0001" name="<?php echo esc_attr( $n ); ?>[correction_factor]" value="<?php echo esc_attr( (float) $m['correction_factor'] ); ?>"></td>
									<td><input type="number" step="0.001" name="<?php echo esc_attr( $n ); ?>[waste_pct]" value="<?php echo esc_attr( null === $m['waste_pct'] ? '' : (float) $m['waste_pct'] ); ?>" placeholder="<?php echo esc_attr( $s['general_waste_pct'] ); ?>"></td>
									<td><input type="number" step="0.0001" name="<?php echo esc_attr( $n ); ?>[min_qty]" value="<?php echo esc_attr( (float) $m['min_qty'] ); ?>"></td>
									<td>
										<select name="<?php echo esc_attr( $n ); ?>[rounding]">
											<?php foreach ( $rounding as $k => $label ) : ?>
												<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $m['rounding'], $k ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><input type="number" step="0.0001" name="<?php echo esc_attr( $n ); ?>[round_multiple]" value="<?php echo esc_attr( (float) $m['round_multiple'] ); ?>"></td>
									<td><input type="number" step="0.0001" name="<?php echo esc_attr( $n ); ?>[yield]" class="ccr-num-yield" value="<?php echo esc_attr( (float) $m['yield'] ); ?>"></td>
									<td><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[active]" value="1" <?php checked( (int) $m['active'], 1 ); ?> aria-label="<?php esc_attr_e( 'Activo', 'calculadora-cielorraso-pvc' ); ?>"></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php CCR_Admin::formula_help(); ?>
				<?php submit_button( __( 'Guardar cambios', 'calculadora-cielorraso-pvc' ) ); ?>
			</form>
		</div>
		<?php
	}
}
