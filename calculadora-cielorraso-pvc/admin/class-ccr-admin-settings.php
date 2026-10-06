<?php
/**
 * Ajustes generales: cotización (mostrar precios), formulario, leads, exportación, empresa y diseño.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Settings {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public function handle() {
		if ( ! isset( $_POST['ccr_settings_save'] ) ) {
			return;
		}
		CCR_Admin::check_cap();
		check_admin_referer( 'ccr_settings' );
		$input = isset( $_POST['ccr'] ) && is_array( $_POST['ccr'] ) ? $_POST['ccr'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CCR_Settings::sanitize() unslashea y sanitiza cada clave.
		CCR_Settings::update( CCR_Settings::sanitize( $input ) );
		CCR_Admin::redirect( CCR_Admin::url( 'ccr-settings' ), __( 'Ajustes guardados.', 'calculadora-cielorraso-pvc' ) );
	}

	private static function sections() {
		return array(
			'ccr-prices' => array(
				__( 'Cotización', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'show_prices', __( 'Mostrar precios', 'calculadora-cielorraso-pvc' ), 'bool', __( 'Activado: costos unitarios, subtotales y total. Desactivado: solo cantidades (los precios no se envían al navegador).', 'calculadora-cielorraso-pvc' ) ),
					array( 'currency_symbol', __( 'Símbolo de moneda', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'currency_position', __( 'Posición del símbolo', 'calculadora-cielorraso-pvc' ), array( 'before' => __( 'Antes ($ 100)', 'calculadora-cielorraso-pvc' ), 'after' => __( 'Después (100 $)', 'calculadora-cielorraso-pvc' ) ) ),
					array( 'price_decimals', __( 'Decimales', 'calculadora-cielorraso-pvc' ), 'int' ),
					array( 'decimal_sep', __( 'Separador decimal', 'calculadora-cielorraso-pvc' ), 'small' ),
					array( 'thousands_sep', __( 'Separador de miles', 'calculadora-cielorraso-pvc' ), 'small' ),
					array( 'total_label', __( 'Etiqueta del total', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'prices_note', __( 'Nota bajo el total', 'calculadora-cielorraso-pvc' ), 'textarea' ),
				),
			),
			'ccr-form'   => array(
				__( 'Formulario', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'eyebrow', __( 'Texto superior (sobre el título)', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'title', __( 'Título', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'subtitle', __( 'Subtítulo', 'calculadora-cielorraso-pvc' ), 'textarea' ),
					array( 'label_largo', __( 'Etiqueta largo', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'label_ancho', __( 'Etiqueta ancho', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'show_alto', __( 'Mostrar campo altura', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'label_alto', __( 'Etiqueta altura', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'default_alto', __( 'Altura por defecto (m)', 'calculadora-cielorraso-pvc' ), 'float', __( 'Se usa si el cliente deja la altura vacía (variable alto).', 'calculadora-cielorraso-pvc' ) ),
					array( 'label_install', __( 'Etiqueta tipo de instalación', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'label_direction', __( 'Etiqueta sentido', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'label_dir_largo', __( 'Opción "a lo largo"', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'label_dir_ancho', __( 'Opción "a lo ancho"', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'enable_auto_direction', __( 'Ofrecer sentido "más económico"', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'label_dir_auto', __( 'Opción "más económica"', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'default_direction', __( 'Sentido por defecto', 'calculadora-cielorraso-pvc' ), array( 'largo' => __( 'A lo largo', 'calculadora-cielorraso-pvc' ), 'ancho' => __( 'A lo ancho', 'calculadora-cielorraso-pvc' ), 'auto' => __( 'Más económica', 'calculadora-cielorraso-pvc' ) ) ),
					array( 'label_variant_auto', __( 'Opción de largo automático', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'show_observations', __( 'Mostrar observaciones', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'label_observations', __( 'Etiqueta observaciones', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'button_text', __( 'Texto del botón', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'min_dim', __( 'Medida mínima (m)', 'calculadora-cielorraso-pvc' ), 'float' ),
					array( 'max_dim', __( 'Medida máxima (m)', 'calculadora-cielorraso-pvc' ), 'float' ),
					array( 'max_alto', __( 'Altura máxima (m)', 'calculadora-cielorraso-pvc' ), 'float' ),
				),
			),
			'ccr-leads'  => array(
				__( 'Leads', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'leads_enabled', __( 'Solicitar datos antes de mostrar resultados', 'calculadora-cielorraso-pvc' ), 'bool', __( 'El servidor no devuelve resultados sin datos válidos. Se guardan en Leads.', 'calculadora-cielorraso-pvc' ) ),
					array( 'lead_title', __( 'Título del formulario', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'lead_require_name', __( 'Nombre obligatorio', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'lead_require_company', __( 'Empresa obligatoria', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'lead_require_phone', __( 'Teléfono obligatorio', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'lead_require_email', __( 'Email obligatorio', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'lead_consent_text', __( 'Texto de consentimiento', 'calculadora-cielorraso-pvc' ), 'textarea', __( 'Vacío = no se pide consentimiento.', 'calculadora-cielorraso-pvc' ) ),
					array( 'lead_notify', __( 'Avisar por email cada nuevo lead', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'lead_notify_email', __( 'Email de aviso', 'calculadora-cielorraso-pvc' ), 'email', __( 'Vacío = email del administrador.', 'calculadora-cielorraso-pvc' ) ),
				),
			),
			'ccr-export' => array(
				__( 'Exportación y empresa', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'enable_pdf', __( 'Botón "Exportar PDF"', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'enable_print', __( 'Botón "Imprimir"', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'enable_excel', __( 'Botón "Exportar Excel"', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'company_name', __( 'Nombre de la empresa', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'company_phone', __( 'Teléfono', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'company_email', __( 'Email', 'calculadora-cielorraso-pvc' ), 'email' ),
					array( 'company_address', __( 'Dirección', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'pdf_footer', __( 'Pie del PDF', 'calculadora-cielorraso-pvc' ), 'textarea' ),
				),
			),
			'ccr-sales'  => array(
				__( 'Carrito y WhatsApp', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'cart_enabled', __( 'Botón "Agregar al carrito"', 'calculadora-cielorraso-pvc' ), 'bool', __( 'Requiere WooCommerce. Cada material se vincula con el producto de la tienda que tenga el mismo SKU (o el SKU de la variante). Las cantidades se recalculan en el servidor y se cobran con el precio de WooCommerce.', 'calculadora-cielorraso-pvc' ) ),
					array( 'cart_button_text', __( 'Texto del botón del carrito', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'cart_redirect', __( 'Después de agregar', 'calculadora-cielorraso-pvc' ), array( 'cart' => __( 'Ir al carrito', 'calculadora-cielorraso-pvc' ), 'checkout' => __( 'Ir a finalizar compra', 'calculadora-cielorraso-pvc' ), 'stay' => __( 'Quedarse en la página', 'calculadora-cielorraso-pvc' ) ) ),
					array( 'whatsapp_enabled', __( 'Botón "Enviar por WhatsApp"', 'calculadora-cielorraso-pvc' ), 'bool' ),
					array( 'whatsapp_number', __( 'Número de WhatsApp de la empresa', 'calculadora-cielorraso-pvc' ), 'text', __( 'Con código de país y sin espacios ni "+". Ej.: 59899123456.', 'calculadora-cielorraso-pvc' ) ),
					array( 'whatsapp_button_text', __( 'Texto del botón de WhatsApp', 'calculadora-cielorraso-pvc' ), 'text' ),
					array( 'whatsapp_intro', __( 'Mensaje inicial', 'calculadora-cielorraso-pvc' ), 'textarea', __( 'Va al principio del mensaje; debajo se agregan las medidas, los materiales y el total.', 'calculadora-cielorraso-pvc' ) ),
				),
			),
			'ccr-design' => array(
				__( 'Diseño', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'primary_color', __( 'Color principal (azul Konex)', 'calculadora-cielorraso-pvc' ), 'color', __( 'Títulos de pasos, cantidades y caja de total.', 'calculadora-cielorraso-pvc' ) ),
					array( 'accent_color', __( 'Color de acento (bordó Konex)', 'calculadora-cielorraso-pvc' ), 'color', __( 'Botón principal y textos destacados.', 'calculadora-cielorraso-pvc' ) ),
					array( 'border_radius', __( 'Redondeo de tarjetas (px)', 'calculadora-cielorraso-pvc' ), 'int' ),
					array( 'load_fonts', __( 'Cargar tipografías Sora y Manrope', 'calculadora-cielorraso-pvc' ), 'bool', __( 'Desactivar si el tema ya las carga o si prefiere la tipografía del tema.', 'calculadora-cielorraso-pvc' ) ),
				),
			),
			'ccr-maint'  => array(
				__( 'Mantenimiento', 'calculadora-cielorraso-pvc' ),
				array(
					array( 'delete_data_on_uninstall', __( 'Borrar todos los datos al desinstalar', 'calculadora-cielorraso-pvc' ), 'bool', __( 'Tablas, leads y ajustes se eliminan al borrar el plugin.', 'calculadora-cielorraso-pvc' ) ),
				),
			),
		);
	}

	public static function render() {
		CCR_Admin::check_cap();
		$s = CCR_Settings::all();
		?>
		<div class="wrap ccr-wrap">
			<h1><?php esc_html_e( 'Ajustes', 'calculadora-cielorraso-pvc' ); ?></h1>
			<?php CCR_Admin::notices(); ?>
			<nav class="ccr-subnav">
				<?php foreach ( self::sections() as $anchor => $section ) : ?>
					<a href="#<?php echo esc_attr( $anchor ); ?>"><?php echo esc_html( $section[0] ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="post">
				<?php wp_nonce_field( 'ccr_settings' ); ?>
				<input type="hidden" name="ccr_settings_save" value="1">
				<?php foreach ( self::sections() as $anchor => $section ) : ?>
					<div class="ccr-box" id="<?php echo esc_attr( $anchor ); ?>">
						<h2><?php echo esc_html( $section[0] ); ?></h2>
						<table class="form-table" role="presentation">
							<?php
							foreach ( $section[1] as $f ) :
								list( $key, $label, $type ) = $f;
								$help = isset( $f[3] ) ? $f[3] : '';
								$id   = 'ccr-s-' . $key;
								$name = 'ccr[' . $key . ']';
								$val  = isset( $s[ $key ] ) ? $s[ $key ] : '';
								?>
								<tr>
									<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
									<td>
										<?php
										if ( is_array( $type ) ) {
											echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
											foreach ( $type as $k => $l ) {
												echo '<option value="' . esc_attr( $k ) . '" ' . selected( $val, $k, false ) . '>' . esc_html( $l ) . '</option>';
											}
											echo '</select>';
										} elseif ( 'bool' === $type ) {
											echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (int) $val, 1, false ) . '> ' . esc_html__( 'Activado', 'calculadora-cielorraso-pvc' ) . '</label>';
										} elseif ( 'textarea' === $type ) {
											echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
										} elseif ( 'color' === $type ) {
											echo '<input type="color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '">';
										} elseif ( in_array( $type, array( 'int', 'float' ), true ) ) {
											echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" step="' . ( 'int' === $type ? '1' : 'any' ) . '" class="small-text">';
										} else {
											echo '<input type="' . ( 'email' === $type ? 'email' : 'text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="' . ( 'small' === $type ? 'small-text' : 'regular-text' ) . '">';
										}
										if ( $help ) {
											echo '<p class="description">' . esc_html( $help ) . '</p>';
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endforeach; ?>
				<?php
				// Parámetros que se editan en "Fórmulas y desperdicios" (se reenvían para no perderlos).
				?>
				<input type="hidden" name="ccr[general_waste_pct]" value="<?php echo esc_attr( $s['general_waste_pct'] ); ?>">
				<input type="hidden" name="ccr[max_combinations]" value="<?php echo esc_attr( $s['max_combinations'] ); ?>">
				<?php submit_button( __( 'Guardar ajustes', 'calculadora-cielorraso-pvc' ) ); ?>
			</form>
		</div>
		<?php
	}
}
