<?php
/**
 * Plantilla del formulario público (diseño Konex: formulario a la izquierda, resultados a la derecha).
 *
 * Se puede sobrescribir copiándola a: wp-content/themes/SU-TEMA/calculadora-cielorraso/calculator.php
 *
 * Variables disponibles: $s (ajustes), $atts, $config, $uid.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

$ccr_default_type = $config['install_types'] ? $config['install_types'][0]['slug'] : '';
foreach ( $config['install_types'] as $ccr_t ) {
	if ( $ccr_t['is_default'] ) {
		$ccr_default_type = $ccr_t['slug'];
	}
}
$ccr_directions = array(
	'largo' => $s['label_dir_largo'],
	'ancho' => $s['label_dir_ancho'],
);
if ( $s['enable_auto_direction'] ) {
	$ccr_directions['auto'] = $s['label_dir_auto'];
}
$ccr_default_dir = isset( $ccr_directions[ $s['default_direction'] ] ) ? $s['default_direction'] : 'largo';
$ccr_step        = 0;
$ccr_eyebrow     = isset( $atts['eyebrow'] ) ? $atts['eyebrow'] : $s['eyebrow'];
?>
<div class="ccr-calc <?php echo esc_attr( $atts['class'] ); ?>" id="<?php echo esc_attr( $uid ); ?>" data-ccr>
	<script type="application/json" class="ccr-config"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>

	<?php if ( '' !== $atts['title'] || '' !== $atts['subtitle'] ) : ?>
		<div class="ccr-header">
			<div>
				<?php if ( '' !== $ccr_eyebrow ) : ?>
					<span class="ccr-eyebrow"><?php echo esc_html( $ccr_eyebrow ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $atts['title'] ) : ?>
					<h2 class="ccr-title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $atts['subtitle'] ) : ?>
				<p class="ccr-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="ccr-layout">
		<form class="ccr-form" novalidate>
			<fieldset class="ccr-step">
				<legend><span class="ccr-n"><?php echo esc_html( ++$ccr_step ); ?></span><?php esc_html_e( 'Medidas del ambiente', 'calculadora-cielorraso-pvc' ); ?></legend>
				<div class="ccr-c2">
					<div class="ccr-field">
						<label for="<?php echo esc_attr( $uid ); ?>-largo"><?php echo esc_html( $s['label_largo'] ); ?> <span class="ccr-req" aria-hidden="true">*</span></label>
						<span class="ccr-unit"><input type="text" inputmode="decimal" id="<?php echo esc_attr( $uid ); ?>-largo" name="largo" required placeholder="4,80" autocomplete="off"><em>m</em></span>
					</div>
					<div class="ccr-field">
						<label for="<?php echo esc_attr( $uid ); ?>-ancho"><?php echo esc_html( $s['label_ancho'] ); ?> <span class="ccr-req" aria-hidden="true">*</span></label>
						<span class="ccr-unit"><input type="text" inputmode="decimal" id="<?php echo esc_attr( $uid ); ?>-ancho" name="ancho" required placeholder="3,60" autocomplete="off"><em>m</em></span>
					</div>
				</div>
				<?php if ( $s['show_alto'] ) : ?>
					<div class="ccr-field">
						<label for="<?php echo esc_attr( $uid ); ?>-alto"><?php echo esc_html( $s['label_alto'] ); ?></label>
						<span class="ccr-unit"><input type="text" inputmode="decimal" id="<?php echo esc_attr( $uid ); ?>-alto" name="alto" placeholder="<?php echo esc_attr( str_replace( '.', ',', (string) $s['default_alto'] ) ); ?>" autocomplete="off"><em>m</em></span>
						<small class="ccr-help"><?php esc_html_e( 'Opcional. Distancia entre el techo y el cielorraso (largo de los colgantes).', 'calculadora-cielorraso-pvc' ); ?></small>
					</div>
				<?php endif; ?>
			</fieldset>

			<fieldset class="ccr-step">
				<legend><span class="ccr-n"><?php echo esc_html( ++$ccr_step ); ?></span><?php echo esc_html( $s['label_install'] ); ?></legend>
				<div class="ccr-options">
					<?php foreach ( $config['install_types'] as $ccr_t ) : ?>
						<label class="ccr-option">
							<input type="radio" name="install_type" value="<?php echo esc_attr( $ccr_t['slug'] ); ?>" <?php checked( $ccr_t['slug'], $ccr_default_type ); ?>>
							<span class="ccr-option-body">
								<strong><?php echo esc_html( $ccr_t['name'] ); ?></strong>
								<?php if ( $ccr_t['description'] ) : ?>
									<small><?php echo esc_html( $ccr_t['description'] ); ?></small>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="ccr-field">
					<span class="ccr-label" id="<?php echo esc_attr( $uid ); ?>-dir-label"><?php echo esc_html( $s['label_direction'] ); ?></span>
					<div class="ccr-seg" role="radiogroup" aria-labelledby="<?php echo esc_attr( $uid ); ?>-dir-label">
						<?php foreach ( $ccr_directions as $ccr_key => $ccr_label ) : ?>
							<label>
								<input type="radio" name="direction" value="<?php echo esc_attr( $ccr_key ); ?>" <?php checked( $ccr_key, $ccr_default_dir ); ?>>
								<span><?php echo esc_html( $ccr_label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</fieldset>

			<?php if ( $config['categories'] ) : ?>
				<fieldset class="ccr-step">
					<legend><span class="ccr-n"><?php echo esc_html( ++$ccr_step ); ?></span><?php esc_html_e( 'Materiales', 'calculadora-cielorraso-pvc' ); ?></legend>
					<?php foreach ( $config['categories'] as $ccr_cat ) : ?>
						<div class="ccr-field ccr-category" data-category="<?php echo esc_attr( $ccr_cat['slug'] ); ?>">
							<label for="<?php echo esc_attr( $uid . '-cat-' . $ccr_cat['slug'] ); ?>"><?php echo esc_html( $ccr_cat['label'] ); ?></label>
							<div class="ccr-inline">
								<select id="<?php echo esc_attr( $uid . '-cat-' . $ccr_cat['slug'] ); ?>" class="ccr-select-material" name="sel_<?php echo esc_attr( $ccr_cat['slug'] ); ?>">
									<?php if ( $ccr_cat['optional'] ) : ?>
										<option value="0"><?php esc_html_e( 'Ninguno', 'calculadora-cielorraso-pvc' ); ?></option>
									<?php endif; ?>
									<?php foreach ( $ccr_cat['items'] as $ccr_item ) : ?>
										<option value="<?php echo esc_attr( $ccr_item['id'] ); ?>" data-types="<?php echo esc_attr( implode( ',', $ccr_item['types'] ) ); ?>"><?php echo esc_html( $ccr_item['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<select class="ccr-select-variant" aria-label="<?php esc_attr_e( 'Largo / presentación', 'calculadora-cielorraso-pvc' ); ?>" hidden></select>
							</div>
						</div>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>

			<?php if ( $s['show_observations'] ) : ?>
				<fieldset class="ccr-step">
					<legend><span class="ccr-n"><?php echo esc_html( ++$ccr_step ); ?></span><?php echo esc_html( $s['label_observations'] ); ?></legend>
					<div class="ccr-field">
						<label class="ccr-sr" for="<?php echo esc_attr( $uid ); ?>-obs"><?php echo esc_html( $s['label_observations'] ); ?></label>
						<textarea id="<?php echo esc_attr( $uid ); ?>-obs" name="observations" rows="3" maxlength="2000" placeholder="<?php esc_attr_e( 'Ej.: cocina, colocar con moldura, entrega en obra…', 'calculadora-cielorraso-pvc' ); ?>"></textarea>
					</div>
				</fieldset>
			<?php endif; ?>

			<div class="ccr-hp" aria-hidden="true">
				<label>Website <input type="text" name="ccr_website" tabindex="-1" autocomplete="off"></label>
			</div>

			<div class="ccr-message" role="alert" aria-live="assertive"></div>

			<div class="ccr-form-actions">
				<button type="submit" class="ccr-btn ccr-btn-primary ccr-btn-block"><?php echo esc_html( $s['button_text'] ); ?> <span aria-hidden="true">&rarr;</span></button>
			</div>
		</form>

		<div class="ccr-side">
			<div class="ccr-panel ccr-placeholder">
				<span class="ccr-eyebrow"><?php esc_html_e( 'Lista de materiales', 'calculadora-cielorraso-pvc' ); ?></span>
				<div class="ccr-placeholder-body">
					<svg viewBox="0 0 64 64" width="56" height="56" aria-hidden="true"><rect x="8" y="12" width="48" height="40" rx="6" fill="none" stroke="currentColor" stroke-width="3"/><path d="M8 24h48M20 12v40M32 12v40M44 12v40" stroke="currentColor" stroke-width="2" opacity=".45"/></svg>
					<p><?php esc_html_e( 'Completá las medidas y tocá el botón para ver al instante la lista de materiales, el plano de colocación y la cotización.', 'calculadora-cielorraso-pvc' ); ?></p>
				</div>
			</div>

			<?php if ( $s['leads_enabled'] ) : ?>
				<form class="ccr-panel ccr-lead" hidden novalidate>
					<span class="ccr-eyebrow"><?php esc_html_e( 'Último paso', 'calculadora-cielorraso-pvc' ); ?></span>
					<h3 class="ccr-panel-title"><?php echo esc_html( $s['lead_title'] ); ?></h3>
					<div class="ccr-c2">
						<?php
						$ccr_lead_fields = array(
							'name'    => array( __( 'Nombre', 'calculadora-cielorraso-pvc' ), 'text', 'name', $s['lead_require_name'] ),
							'company' => array( __( 'Empresa', 'calculadora-cielorraso-pvc' ), 'text', 'organization', $s['lead_require_company'] ),
							'phone'   => array( __( 'Teléfono', 'calculadora-cielorraso-pvc' ), 'tel', 'tel', $s['lead_require_phone'] ),
							'email'   => array( __( 'Email', 'calculadora-cielorraso-pvc' ), 'email', 'email', $s['lead_require_email'] ),
						);
						foreach ( $ccr_lead_fields as $ccr_key => $ccr_f ) :
							?>
							<div class="ccr-field">
								<label for="<?php echo esc_attr( $uid . '-lead-' . $ccr_key ); ?>"><?php echo esc_html( $ccr_f[0] ); ?><?php echo $ccr_f[3] ? ' <span class="ccr-req" aria-hidden="true">*</span>' : ''; ?></label>
								<input type="<?php echo esc_attr( $ccr_f[1] ); ?>" id="<?php echo esc_attr( $uid . '-lead-' . $ccr_key ); ?>" name="<?php echo esc_attr( $ccr_key ); ?>" autocomplete="<?php echo esc_attr( $ccr_f[2] ); ?>" <?php echo $ccr_f[3] ? 'required' : ''; ?> maxlength="190">
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( '' !== trim( $s['lead_consent_text'] ) ) : ?>
						<label class="ccr-check">
							<input type="checkbox" name="consent" value="1" required>
							<span><?php echo esc_html( $s['lead_consent_text'] ); ?></span>
						</label>
					<?php endif; ?>
					<div class="ccr-message" role="alert" aria-live="assertive"></div>
					<div class="ccr-actions">
						<button type="button" class="ccr-btn ccr-btn-ghost" data-ccr-back><?php esc_html_e( 'Volver', 'calculadora-cielorraso-pvc' ); ?></button>
						<button type="submit" class="ccr-btn ccr-btn-primary"><?php esc_html_e( 'Ver resultados', 'calculadora-cielorraso-pvc' ); ?> <span aria-hidden="true">&rarr;</span></button>
					</div>
				</form>
			<?php endif; ?>

			<div class="ccr-panel ccr-results" hidden tabindex="-1"></div>
		</div>
	</div>
</div>
