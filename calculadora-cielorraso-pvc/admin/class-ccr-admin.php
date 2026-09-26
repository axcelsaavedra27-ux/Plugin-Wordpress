<?php
/**
 * Administración: menú "Cálculos de Cielorraso", assets, avisos y utilidades comunes.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin {

	/** @var CCR_Admin_Crud[] */
	private $cruds = array();

	public function __construct() {
		foreach ( CCR_Admin_Entities::all() as $key => $config ) {
			$this->cruds[ $key ] = new CCR_Admin_Crud( $config );
		}
		new CCR_Admin_Formulas();
		new CCR_Admin_Tester();
		new CCR_Admin_Leads();
		new CCR_Admin_Settings();
		new CCR_Admin_Tools();

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_ccr_test_formula', array( $this, 'ajax_test_formula' ) );
		add_filter( 'plugin_action_links_' . CCR_BASENAME, array( $this, 'action_links' ) );
	}

	public static function capability() {
		return apply_filters( 'ccr_capability', 'manage_options' );
	}

	public function menu() {
		$cap = self::capability();
		add_menu_page(
			__( 'Cálculos de Cielorraso', 'calculadora-cielorraso-pvc' ),
			__( 'Cálculos de Cielorraso', 'calculadora-cielorraso-pvc' ),
			$cap,
			'ccr-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-calculator',
			56
		);
		add_submenu_page( 'ccr-dashboard', __( 'Panel', 'calculadora-cielorraso-pvc' ), __( 'Panel', 'calculadora-cielorraso-pvc' ), $cap, 'ccr-dashboard', array( $this, 'dashboard' ) );

		$pages = array(
			'materials'     => __( 'Materiales', 'calculadora-cielorraso-pvc' ),
			'categories'    => __( 'Categorías', 'calculadora-cielorraso-pvc' ),
			'install_types' => __( 'Tipos de instalación', 'calculadora-cielorraso-pvc' ),
			'variables'     => __( 'Reglas de cálculo', 'calculadora-cielorraso-pvc' ),
		);
		foreach ( $pages as $key => $label ) {
			add_submenu_page( 'ccr-dashboard', $label, $label, $cap, $this->cruds[ $key ]->slug(), array( $this->cruds[ $key ], 'render' ) );
		}

		add_submenu_page( 'ccr-dashboard', __( 'Fórmulas y desperdicios', 'calculadora-cielorraso-pvc' ), __( 'Fórmulas y desperdicios', 'calculadora-cielorraso-pvc' ), $cap, 'ccr-formulas', array( 'CCR_Admin_Formulas', 'render' ) );
		add_submenu_page( 'ccr-dashboard', __( 'Probador', 'calculadora-cielorraso-pvc' ), __( 'Probador', 'calculadora-cielorraso-pvc' ), $cap, 'ccr-tester', array( 'CCR_Admin_Tester', 'render' ) );
		add_submenu_page( 'ccr-dashboard', __( 'Leads', 'calculadora-cielorraso-pvc' ), __( 'Leads', 'calculadora-cielorraso-pvc' ), $cap, 'ccr-leads', array( 'CCR_Admin_Leads', 'render' ) );
		add_submenu_page( 'ccr-dashboard', __( 'Ajustes', 'calculadora-cielorraso-pvc' ), __( 'Ajustes', 'calculadora-cielorraso-pvc' ), $cap, 'ccr-settings', array( 'CCR_Admin_Settings', 'render' ) );
		add_submenu_page( 'ccr-dashboard', __( 'Herramientas', 'calculadora-cielorraso-pvc' ), __( 'Herramientas', 'calculadora-cielorraso-pvc' ), $cap, 'ccr-tools', array( 'CCR_Admin_Tools', 'render' ) );
	}

	public function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'ccr-' ) ) {
			return;
		}
		wp_enqueue_style( 'ccr-admin', CCR_URL . 'assets/css/ccr-admin.css', array(), CCR_VERSION );
		wp_enqueue_script( 'ccr-admin', CCR_URL . 'assets/js/ccr-admin.js', array(), CCR_VERSION, true );
		wp_localize_script(
			'ccr-admin',
			'CCR_ADMIN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ccr_admin' ),
				'i18n'    => array(
					'confirmDelete' => __( '¿Eliminar definitivamente este registro?', 'calculadora-cielorraso-pvc' ),
					'confirmReset'  => __( 'Se borrarán todos los materiales, categorías, tipos y reglas y se cargarán los datos de ejemplo. Los leads no se tocan. ¿Continuar?', 'calculadora-cielorraso-pvc' ),
					'testing'       => __( 'Probando…', 'calculadora-cielorraso-pvc' ),
					'result'        => __( 'Resultado con el ejemplo del probador:', 'calculadora-cielorraso-pvc' ),
				),
			)
		);
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=ccr-settings' ) ) . '">' . esc_html__( 'Ajustes', 'calculadora-cielorraso-pvc' ) . '</a>' );
		return $links;
	}

	/* --------------------------------------------------------------------
	 * Utilidades compartidas
	 * ------------------------------------------------------------------ */

	public static function url( $page, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Redirige con un aviso (patrón POST/Redirect/GET).
	 */
	public static function redirect( $url, $message = '', $type = 'success' ) {
		if ( $message ) {
			set_transient(
				'ccr_notice_' . get_current_user_id(),
				array(
					'message' => $message,
					'type'    => $type,
				),
				60
			);
		}
		wp_safe_redirect( $url );
		exit;
	}

	public static function notices() {
		$key    = 'ccr_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( $notice ) {
			delete_transient( $key );
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info' ),
				wp_kses( $notice['message'], array( 'br' => array(), 'code' => array(), 'strong' => array() ) )
			);
		}
	}

	public static function check_cap() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'No tenés permisos para realizar esta acción.', 'calculadora-cielorraso-pvc' ), 403 );
		}
	}

	/**
	 * Panel de ayuda con todas las variables y funciones disponibles en las fórmulas.
	 */
	public static function formula_help() {
		$base = array(
			'largo'               => 'Largo del ambiente (m)',
			'ancho'               => 'Ancho del ambiente (m)',
			'alto'                => 'Altura / cámara de aire (m)',
			'area'                => 'largo × ancho (m²)',
			'perimetro'           => '2 × (largo + ancho) (m)',
			'lado_mayor'          => 'Mayor entre largo y ancho',
			'lado_menor'          => 'Menor entre largo y ancho',
			'lado_paralelo'       => 'Lado en el que se colocan las láminas (según sentido)',
			'lado_perpendicular'  => 'El otro lado',
			'sentido_largo'       => '1 si las láminas van a lo largo',
			'sentido_ancho'       => '1 si las láminas van a lo ancho',
			'desperdicio_general' => 'Desperdicio general (%)',
			'm_largo'             => 'Solo en fórmulas de material: largo del material (o de la variante)',
			'm_ancho'             => 'Solo en fórmulas de material: ancho del material',
			'm_rend'              => 'Solo en fórmulas de material: rendimiento',
			'm_precio'            => 'Solo en fórmulas de material: precio',
		);

		$dynamic = array();
		foreach ( CCR_Repository::get( 'install_types' )->all() as $t ) {
			$dynamic[ 'inst_' . CCR_Calculator::var_name( $t['slug'] ) ] = sprintf( '1 si la instalación es "%s"', $t['name'] );
		}
		foreach ( CCR_Repository::get( 'categories' )->all() as $c ) {
			$slug                                   = CCR_Calculator::var_name( $c['slug'] );
			$dynamic[ 'cat_' . $slug . '_largo' ]   = sprintf( 'Largo del material elegido en "%s" (también _ancho, _rend)', $c['name'] );
			$dynamic[ 'sel_cat_' . $slug ]          = sprintf( '1 si hay un material de "%s" en uso', $c['name'] );
			$dynamic[ 'q_cat_' . $slug ]            = sprintf( 'Cantidad total calculada de "%s" (solo categorías anteriores)', $c['name'] );
		}
		foreach ( CCR_Repository::get( 'variables' )->all() as $v ) {
			$dynamic[ CCR_Calculator::var_name( $v['var_key'] ) ] = 'Regla: ' . ( $v['label'] ? $v['label'] : $v['formula'] );
		}

		$materials = array();
		foreach ( CCR_Repository::get( 'materials' )->all() as $m ) {
			$materials[] = CCR_Calculator::var_name( $m['code'] );
		}
		?>
		<details class="ccr-help">
			<summary><?php esc_html_e( 'Variables y funciones disponibles en las fórmulas', 'calculadora-cielorraso-pvc' ); ?></summary>
			<div class="ccr-help-body">
				<p><?php esc_html_e( 'Operadores: + - * / % ^ ( )   comparaciones: == != < <= > >=   lógicos: && || !   condicional: condición ? a : b', 'calculadora-cielorraso-pvc' ); ?></p>
				<div class="ccr-help-cols">
					<div>
						<h4><?php esc_html_e( 'Medidas y contexto', 'calculadora-cielorraso-pvc' ); ?></h4>
						<ul><?php foreach ( $base as $k => $d ) : ?><li><code><?php echo esc_html( $k ); ?></code> — <?php echo esc_html( $d ); ?></li><?php endforeach; ?></ul>
						<h4><?php esc_html_e( 'Instalación, categorías y reglas', 'calculadora-cielorraso-pvc' ); ?></h4>
						<ul><?php foreach ( $dynamic as $k => $d ) : ?><li><code><?php echo esc_html( $k ); ?></code> — <?php echo esc_html( $d ); ?></li><?php endforeach; ?></ul>
					</div>
					<div>
						<h4><?php esc_html_e( 'Funciones', 'calculadora-cielorraso-pvc' ); ?></h4>
						<ul><?php foreach ( CCR_Expression::functions() as $name => $f ) : ?><li><code><?php echo esc_html( $name ); ?>()</code> — <?php echo esc_html( $f[2] ); ?></li><?php endforeach; ?></ul>
						<h4><?php esc_html_e( 'Por cada material (reemplace CODIGO)', 'calculadora-cielorraso-pvc' ); ?></h4>
						<ul>
							<li><code>q_CODIGO</code> — <?php esc_html_e( 'cantidad final calculada (solo materiales evaluados antes)', 'calculadora-cielorraso-pvc' ); ?></li>
							<li><code>qbase_CODIGO</code> — <?php esc_html_e( 'cantidad antes de desperdicio y redondeo', 'calculadora-cielorraso-pvc' ); ?></li>
							<li><code>sel_CODIGO</code> — <?php esc_html_e( '1 si el material está en uso', 'calculadora-cielorraso-pvc' ); ?></li>
							<li><code>CODIGO_largo</code>, <code>CODIGO_ancho</code>, <code>CODIGO_rend</code>, <code>CODIGO_precio</code></li>
						</ul>
						<?php if ( $materials ) : ?>
							<p class="description"><?php esc_html_e( 'Códigos actuales:', 'calculadora-cielorraso-pvc' ); ?> <?php echo esc_html( implode( ', ', $materials ) ); ?></p>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'floor() y ceil() toleran errores de coma flotante: floor(3.6 / 0.2) = 18.', 'calculadora-cielorraso-pvc' ); ?></p>
					</div>
				</div>
			</div>
		</details>
		<?php
	}

	/**
	 * Evalúa una fórmula con el ambiente de ejemplo del probador (AJAX).
	 */
	public function ajax_test_formula() {
		check_ajax_referer( 'ccr_admin', 'nonce' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 );
		}
		$formula = isset( $_POST['formula'] ) ? sanitize_textarea_field( wp_unslash( $_POST['formula'] ) ) : '';
		$syntax  = CCR_Expression::validate( $formula );
		if ( true !== $syntax ) {
			wp_send_json_error( array( 'message' => $syntax ) );
		}

		$sample = CCR_Admin_Tester::sample_input();
		$calc   = new CCR_Calculator();
		$result = $calc->calculate( $sample, true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$vars = $result['debug']['variables'];
		foreach ( array( 'm_largo' => 'length', 'm_ancho' => 'width', 'm_rend' => 'yield', 'm_precio' => 'price' ) as $var => $field ) {
			$vars[ $var ] = isset( $_POST[ $field ] ) ? (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : 0;
		}
		try {
			$value = CCR_Expression::evaluate( $formula, $vars );
			wp_send_json_success(
				array(
					'value'  => round( $value, 6 ),
					'sample' => sprintf( '%s × %s m, alto %s m, %s', $sample['largo'], $sample['ancho'], $sample['alto'], $result['summary']['install_type'] ),
				)
			);
		} catch ( CCR_Expression_Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/* --------------------------------------------------------------------
	 * Panel
	 * ------------------------------------------------------------------ */

	public function dashboard() {
		self::check_cap();
		$counts = array(
			'materials'    => CCR_Repository::get( 'materials' )->count( 'WHERE active = 1' ),
			'categories'   => CCR_Repository::get( 'categories' )->count( 'WHERE active = 1' ),
			'types'        => CCR_Repository::get( 'install_types' )->count( 'WHERE active = 1' ),
			'variables'    => CCR_Repository::get( 'variables' )->count( 'WHERE active = 1' ),
			'leads'        => CCR_Repository::get( 'leads' )->count(),
			'calculations' => CCR_Repository::get( 'calculations' )->count(),
		);
		$s = CCR_Settings::all();
		?>
		<div class="wrap ccr-wrap">
			<h1><?php esc_html_e( 'Cálculos de Cielorraso', 'calculadora-cielorraso-pvc' ); ?></h1>
			<?php self::notices(); ?>

			<div class="ccr-cards">
				<?php
				$cards = array(
					array( $counts['materials'], __( 'Materiales activos', 'calculadora-cielorraso-pvc' ), 'ccr-materials' ),
					array( $counts['categories'], __( 'Categorías', 'calculadora-cielorraso-pvc' ), 'ccr-categories' ),
					array( $counts['types'], __( 'Tipos de instalación', 'calculadora-cielorraso-pvc' ), 'ccr-install-types' ),
					array( $counts['variables'], __( 'Reglas de cálculo', 'calculadora-cielorraso-pvc' ), 'ccr-variables' ),
					array( $counts['leads'], __( 'Leads', 'calculadora-cielorraso-pvc' ), 'ccr-leads' ),
					array( $counts['calculations'], __( 'Cálculos guardados', 'calculadora-cielorraso-pvc' ), 'ccr-leads' ),
				);
				foreach ( $cards as $c ) :
					?>
					<a class="ccr-card-stat" href="<?php echo esc_url( self::url( $c[2] ) ); ?>">
						<strong><?php echo esc_html( number_format_i18n( $c[0] ) ); ?></strong>
						<span><?php echo esc_html( $c[1] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="ccr-panel-grid">
				<div class="ccr-box">
					<h2><?php esc_html_e( 'Insertar la calculadora', 'calculadora-cielorraso-pvc' ); ?></h2>
					<p><?php esc_html_e( 'Shortcode (Elementor, Gutenberg, widgets, constructores):', 'calculadora-cielorraso-pvc' ); ?></p>
					<p><code class="ccr-copy">[calculadora_cielorraso]</code></p>
					<p><?php esc_html_e( 'Con título propio:', 'calculadora-cielorraso-pvc' ); ?> <code>[calculadora_cielorraso title="Calculá tu cielorraso"]</code></p>
					<p><?php esc_html_e( 'En Gutenberg buscá el bloque "Calculadora de cielorraso PVC". En Elementor, el widget "Calculadora cielorraso PVC".', 'calculadora-cielorraso-pvc' ); ?></p>
				</div>
				<div class="ccr-box">
					<h2><?php esc_html_e( 'Estado', 'calculadora-cielorraso-pvc' ); ?></h2>
					<ul class="ccr-status">
						<li><?php echo $s['show_prices'] ? '✅' : '⛔'; ?> <?php esc_html_e( 'Mostrar precios', 'calculadora-cielorraso-pvc' ); ?></li>
						<li><?php echo $s['leads_enabled'] ? '✅' : '⛔'; ?> <?php esc_html_e( 'Solicitar datos (leads) antes del resultado', 'calculadora-cielorraso-pvc' ); ?></li>
						<li><?php echo esc_html( sprintf( /* translators: %s: percent */ __( 'Desperdicio general: %s %%', 'calculadora-cielorraso-pvc' ), number_format_i18n( $s['general_waste_pct'], 2 ) ) ); ?></li>
					</ul>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( self::url( 'ccr-tester' ) ); ?>"><?php esc_html_e( 'Probar un cálculo', 'calculadora-cielorraso-pvc' ); ?></a>
						<a class="button" href="<?php echo esc_url( self::url( 'ccr-settings' ) ); ?>"><?php esc_html_e( 'Ajustes', 'calculadora-cielorraso-pvc' ); ?></a>
					</p>
				</div>
				<div class="ccr-box ccr-box-wide">
					<h2><?php esc_html_e( 'Cómo funciona el cálculo', 'calculadora-cielorraso-pvc' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Se toman las medidas del cliente, el tipo de instalación y el sentido de colocación.', 'calculadora-cielorraso-pvc' ); ?></li>
						<li><?php esc_html_e( 'Se evalúan las "Reglas de cálculo" en orden (separaciones, cantidad de líneas de perfiles, fijaciones…).', 'calculadora-cielorraso-pvc' ); ?></li>
						<li><?php esc_html_e( 'Para cada material activo se evalúa su fórmula y se aplica: × factor de corrección → + desperdicio % → cantidad mínima → redondeo al múltiplo.', 'calculadora-cielorraso-pvc' ); ?></li>
						<li><?php esc_html_e( 'Con sentido "Más económica" o largos en "Automático" se prueban todas las combinaciones y se elige la de menor costo total.', 'calculadora-cielorraso-pvc' ); ?></li>
					</ol>
				</div>
			</div>
		</div>
		<?php
	}
}
