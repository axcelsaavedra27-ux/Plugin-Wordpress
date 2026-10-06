<?php
/**
 * CRUD genérico de administración (listado, alta, edición, duplicado, activar/desactivar, borrado).
 * Todas las acciones verifican permisos + nonce y usan el patrón POST/Redirect/GET.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Admin_Crud {

	/** @var array */
	private $c;

	public function __construct( array $config ) {
		$this->c = $config;
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public function slug() {
		return $this->c['slug'];
	}

	/** @return CCR_Repository */
	private function repo() {
		return CCR_Repository::get( $this->c['repo'] );
	}

	private function options( $field ) {
		if ( ! isset( $field['options'] ) ) {
			return array();
		}
		return is_callable( $field['options'] ) ? call_user_func( $field['options'] ) : (array) $field['options'];
	}

	/* --------------------------------------------------------------------
	 * Acciones
	 * ------------------------------------------------------------------ */

	public function handle() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page !== $this->c['slug'] ) {
			return;
		}

		$list = CCR_Admin::url( $this->c['slug'] );

		// Guardar.
		if ( isset( $_POST['ccr_crud_action'] ) && 'save' === $_POST['ccr_crud_action'] ) {
			CCR_Admin::check_cap();
			check_admin_referer( 'ccr_save_' . $this->c['key'] );
			$this->save();
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $action, array( 'delete', 'toggle', 'duplicate' ), true ) || ! $id ) {
			return;
		}
		CCR_Admin::check_cap();
		check_admin_referer( 'ccr_' . $action . '_' . $this->c['key'] . '_' . $id );

		$row = $this->repo()->find( $id );
		if ( ! $row ) {
			CCR_Admin::redirect( $list, __( 'Registro no encontrado.', 'calculadora-cielorraso-pvc' ), 'error' );
		}

		switch ( $action ) {
			case 'delete':
				if ( isset( $this->c['before_delete'] ) ) {
					$ok = call_user_func( $this->c['before_delete'], $id );
					if ( true !== $ok ) {
						CCR_Admin::redirect( $list, $ok, 'error' );
					}
				}
				$this->repo()->delete( $id );
				CCR_Admin::redirect( $list, __( 'Registro eliminado.', 'calculadora-cielorraso-pvc' ) );
				break;

			case 'toggle':
				$this->repo()->update( $id, array( 'active' => (int) $row['active'] ? 0 : 1 ) );
				CCR_Admin::redirect( $list, (int) $row['active'] ? __( 'Desactivado.', 'calculadora-cielorraso-pvc' ) : __( 'Activado.', 'calculadora-cielorraso-pvc' ) );
				break;

			case 'duplicate':
				$copy   = $row;
				$unique = $this->c['unique'];
				unset( $copy['id'], $copy['created_at'], $copy['updated_at'] );
				$base = $copy[ $unique ] . '_copia';
				$try  = $base;
				$n    = 2;
				while ( $this->repo()->find_by( $unique, $try ) ) {
					$try = $base . $n++;
				}
				$copy[ $unique ] = $try;
				if ( isset( $copy['name'] ) ) {
					$copy['name'] .= ' ' . __( '(copia)', 'calculadora-cielorraso-pvc' );
				}
				if ( isset( $copy['is_default'] ) ) {
					$copy['is_default'] = 0;
				}
				$copy['active'] = 0;
				$new_id         = $this->repo()->insert( $copy );
				CCR_Admin::redirect( CCR_Admin::url( $this->c['slug'], array( 'action' => 'edit', 'id' => $new_id ) ), __( 'Copia creada (inactiva). Revísela y actívela.', 'calculadora-cielorraso-pvc' ) );
				break;
		}
	}

	private function save() {
		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verificado en handle().
		$raw    = isset( $_POST['f'] ) && is_array( $_POST['f'] ) ? wp_unslash( $_POST['f'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanitiza campo por campo.
		$data   = array();
		$errors = array();

		foreach ( $this->c['fields'] as $name => $field ) {
			if ( 'section' === $field['type'] ) {
				continue;
			}
			$value         = isset( $raw[ $name ] ) ? $raw[ $name ] : null;
			$data[ $name ] = $this->sanitize_field( $field, $value );

			if ( ! empty( $field['required'] ) && ( null === $data[ $name ] || '' === $data[ $name ] ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( 'El campo "%s" es obligatorio.', 'calculadora-cielorraso-pvc' ), $field['label'] );
			}
			if ( 'formula' === $field['type'] && '' !== $data[ $name ] ) {
				$valid = CCR_Expression::validate( $data[ $name ] );
				if ( true !== $valid ) {
					/* translators: 1: field label, 2: error */
					$errors[] = sprintf( __( 'Error en "%1$s": %2$s', 'calculadora-cielorraso-pvc' ), $field['label'], $valid );
				}
			}
		}

		// Identificador único.
		$unique = $this->c['unique'];
		if ( ! empty( $data[ $unique ] ) ) {
			$existing = $this->repo()->find_by( $unique, $data[ $unique ] );
			if ( $existing && (int) $existing['id'] !== $id ) {
				/* translators: %s: identifier */
				$errors[] = sprintf( __( 'El identificador "%s" ya existe.', 'calculadora-cielorraso-pvc' ), $data[ $unique ] );
			}
		}

		if ( isset( $this->c['validate'] ) ) {
			$errors = array_merge( $errors, (array) call_user_func( $this->c['validate'], $data, $id ) );
		}

		if ( $errors ) {
			set_transient(
				'ccr_form_' . get_current_user_id(),
				array(
					'key'    => $this->c['key'],
					'data'   => $data,
					'errors' => $errors,
				),
				300
			);
			wp_safe_redirect( CCR_Admin::url( $this->c['slug'], array( 'action' => $id ? 'edit' : 'new', 'id' => $id, 'ccr_err' => 1 ) ) );
			exit;
		}

		if ( $id ) {
			$this->repo()->update( $id, $data );
		} else {
			$id = $this->repo()->insert( $data );
		}

		if ( isset( $this->c['after_save'] ) ) {
			call_user_func( $this->c['after_save'], $id, $data );
		}

		$message = __( 'Guardado correctamente.', 'calculadora-cielorraso-pvc' );

		// Advertencia (no bloqueante) si la fórmula falla con el ejemplo del probador.
		foreach ( $this->c['fields'] as $name => $field ) {
			if ( 'formula' === $field['type'] && ! empty( $data['active'] ) ) {
				$warn = CCR_Admin_Tester::quick_check();
				if ( $warn ) {
					$message .= '<br><strong>' . esc_html__( 'Advertencias con el ambiente de ejemplo:', 'calculadora-cielorraso-pvc' ) . '</strong><br>' . implode( '<br>', array_map( 'esc_html', $warn ) );
					CCR_Admin::redirect( CCR_Admin::url( $this->c['slug'], array( 'action' => 'edit', 'id' => $id ) ), $message, 'warning' );
				}
				break;
			}
		}

		CCR_Admin::redirect( CCR_Admin::url( $this->c['slug'], array( 'action' => 'edit', 'id' => $id ) ), $message );
	}

	private function sanitize_field( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'text':
				return sanitize_text_field( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'slug':
				$v = CCR_Calculator::var_name( sanitize_text_field( (string) $value ) );
				if ( '' !== $v && ! preg_match( '/^[a-z]/', $v ) ) {
					$v = 'x_' . $v;
				}
				return substr( $v, 0, 60 );

			case 'formula':
				return self::sanitize_formula( $value );

			case 'number':
				$value = is_scalar( $value ) ? trim( str_replace( ',', '.', (string) $value ) ) : '';
				if ( '' === $value ) {
					if ( ! empty( $field['nullable'] ) ) {
						return null;
					}
					return isset( $field['default'] ) ? $field['default'] : 0;
				}
				return ! empty( $field['int'] ) ? (int) $value : (float) $value;

			case 'select':
				$options = $this->options( $field );
				$value   = is_scalar( $value ) ? (string) $value : '';
				if ( array_key_exists( $value, $options ) ) {
					return ! empty( $field['int'] ) ? (int) $value : $value;
				}
				if ( isset( $field['default'] ) ) {
					return $field['default'];
				}
				return ! empty( $field['int'] ) ? 0 : '';

			case 'checkbox':
				return empty( $value ) ? 0 : 1;

			case 'multicheck':
				$options = $this->options( $field );
				$ids     = array();
				foreach ( (array) $value as $v ) {
					$v = absint( $v );
					if ( isset( $options[ $v ] ) ) {
						$ids[] = $v;
					}
				}
				return implode( ',', array_unique( $ids ) );

			case 'variants':
				$rows = array();
				foreach ( (array) $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$num   = static function ( $k ) use ( $row ) {
						return isset( $row[ $k ] ) ? (float) str_replace( ',', '.', (string) $row[ $k ] ) : 0;
					};
					$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
					if ( '' === $label && $num( 'length' ) <= 0 && $num( 'price' ) <= 0 ) {
						continue;
					}
					$rows[] = array(
						'label'  => $label,
						'length' => max( 0, $num( 'length' ) ),
						'width'  => max( 0, $num( 'width' ) ),
						'price'  => max( 0, $num( 'price' ) ),
						'sku'    => isset( $row['sku'] ) ? sanitize_text_field( (string) $row['sku'] ) : '',
					);
				}
				return $rows ? wp_json_encode( $rows ) : '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Deja solo los caracteres que acepta el motor de fórmulas.
	 */
	public static function sanitize_formula( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/[^A-Za-z0-9_\.\s\+\-\*\/%\^\(\),<>=!&\|\?:]/', '', $value );
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/* --------------------------------------------------------------------
	 * Vistas
	 * ------------------------------------------------------------------ */

	public function render() {
		CCR_Admin::check_cap();
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap ccr-wrap">';
		if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
			$this->render_form();
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function render_list() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lectura (búsqueda/filtro/paginación).
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter = isset( $_GET['filter'] ) ? absint( $_GET['filter'] ) : 0;
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$where = array();
		if ( $filter && isset( $this->c['filter'] ) ) {
			$where[ $this->c['filter']['column'] ] = $filter;
		}
		$per_page = 50;
		$result   = $this->repo()->paginate(
			array(
				'search'         => $search,
				'search_columns' => $this->c['search'],
				'where'          => $where,
				'per_page'       => $per_page,
				'page'           => $paged,
				'order_by'       => isset( $this->c['filter'] ) ? $this->c['filter']['column'] : 'sort_order',
				'order'          => 'ASC',
			)
		);
		if ( isset( $this->c['filter'] ) ) {
			// Orden: categoría (según su orden) y luego orden del material.
			$cat_order = array();
			foreach ( CCR_Repository::get( 'categories' )->all() as $cat ) {
				$cat_order[ $cat['id'] ] = (int) $cat['sort_order'];
			}
			usort(
				$result['items'],
				static function ( $a, $b ) use ( $cat_order ) {
					$ca = isset( $cat_order[ $a['category_id'] ] ) ? $cat_order[ $a['category_id'] ] : 9999;
					$cb = isset( $cat_order[ $b['category_id'] ] ) ? $cat_order[ $b['category_id'] ] : 9999;
					return $ca !== $cb ? $ca - $cb : (int) $a['sort_order'] - (int) $b['sort_order'];
				}
			);
		}
		$cols = $this->c['columns'];
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html( $this->c['plural'] ); ?></h1>
		<a href="<?php echo esc_url( CCR_Admin::url( $this->c['slug'], array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir nuevo', 'calculadora-cielorraso-pvc' ); ?></a>
		<hr class="wp-header-end">
		<?php CCR_Admin::notices(); ?>
		<?php if ( ! empty( $this->c['intro'] ) ) : ?>
			<p class="ccr-intro"><?php echo esc_html( $this->c['intro'] ); ?></p>
		<?php endif; ?>

		<form method="get" class="ccr-toolbar">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->c['slug'] ); ?>">
			<?php if ( isset( $this->c['filter'] ) ) : ?>
				<select name="filter">
					<option value="0"><?php echo esc_html( $this->c['filter']['label'] ); ?></option>
					<?php foreach ( call_user_func( $this->c['filter']['options'] ) as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $filter, (int) $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar…', 'calculadora-cielorraso-pvc' ); ?>">
			<button class="button"><?php esc_html_e( 'Filtrar', 'calculadora-cielorraso-pvc' ); ?></button>
		</form>

		<table class="wp-list-table widefat fixed striped ccr-table">
			<thead>
				<tr>
					<?php foreach ( $cols as $key => $col ) : ?>
						<th scope="col" class="column-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $col[0] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $result['items'] ) : ?>
					<tr><td colspan="<?php echo count( $cols ); ?>"><?php esc_html_e( 'No hay registros.', 'calculadora-cielorraso-pvc' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $result['items'] as $row ) : ?>
					<tr class="<?php echo (int) ( isset( $row['active'] ) ? $row['active'] : 1 ) ? '' : 'ccr-inactive'; ?>">
						<?php
						$first = true;
						foreach ( $cols as $key => $col ) :
							?>
							<td class="column-<?php echo esc_attr( $key ); ?>">
								<?php
								if ( isset( $col[1] ) && is_callable( $col[1] ) ) {
									echo call_user_func( $col[1], $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- las columnas escapan su salida.
								} else {
									echo esc_html( isset( $row[ $key ] ) ? $row[ $key ] : '' );
								}
								if ( $first ) {
									$this->row_actions( $row );
									$first = false;
								}
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = (int) ceil( $result['total'] / $per_page );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			echo '</div></div>';
		}
	}

	private function row_actions( array $row ) {
		$id      = (int) $row['id'];
		$key     = $this->c['key'];
		$slug    = $this->c['slug'];
		$actions = array(
			'<a href="' . esc_url( CCR_Admin::url( $slug, array( 'action' => 'edit', 'id' => $id ) ) ) . '">' . esc_html__( 'Editar', 'calculadora-cielorraso-pvc' ) . '</a>',
			'<a href="' . esc_url( wp_nonce_url( CCR_Admin::url( $slug, array( 'action' => 'duplicate', 'id' => $id ) ), 'ccr_duplicate_' . $key . '_' . $id ) ) . '">' . esc_html__( 'Duplicar', 'calculadora-cielorraso-pvc' ) . '</a>',
			'<a href="' . esc_url( wp_nonce_url( CCR_Admin::url( $slug, array( 'action' => 'toggle', 'id' => $id ) ), 'ccr_toggle_' . $key . '_' . $id ) ) . '">' . ( (int) $row['active'] ? esc_html__( 'Desactivar', 'calculadora-cielorraso-pvc' ) : esc_html__( 'Activar', 'calculadora-cielorraso-pvc' ) ) . '</a>',
			'<a class="ccr-danger ccr-confirm-delete" href="' . esc_url( wp_nonce_url( CCR_Admin::url( $slug, array( 'action' => 'delete', 'id' => $id ) ), 'ccr_delete_' . $key . '_' . $id ) ) . '">' . esc_html__( 'Eliminar', 'calculadora-cielorraso-pvc' ) . '</a>',
		);
		echo '<div class="row-actions visible">' . implode( ' | ', $actions ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- construido con esc_url/esc_html.
	}

	private function render_form() {
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$row    = $id ? $this->repo()->find( $id ) : null;
		$errors = array();

		if ( $id && ! $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Registro no encontrado.', 'calculadora-cielorraso-pvc' ) . '</p></div>';
			return;
		}

		$values = array();
		foreach ( $this->c['fields'] as $name => $field ) {
			if ( 'section' === $field['type'] ) {
				continue;
			}
			$values[ $name ] = $row ? $row[ $name ] : ( isset( $field['default'] ) ? $field['default'] : '' );
		}

		// Datos rechazados por validación.
		if ( isset( $_GET['ccr_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$flash = get_transient( 'ccr_form_' . get_current_user_id() );
			if ( $flash && $flash['key'] === $this->c['key'] ) {
				delete_transient( 'ccr_form_' . get_current_user_id() );
				$values = array_merge( $values, $flash['data'] );
				$errors = $flash['errors'];
			}
		}

		$title = $row
			/* translators: %s: entity */
			? sprintf( __( 'Editar %s', 'calculadora-cielorraso-pvc' ), $this->c['singular'] )
			/* translators: %s: entity */
			: sprintf( __( 'Nuevo %s', 'calculadora-cielorraso-pvc' ), $this->c['singular'] );
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html( ucfirst( $title ) ); ?></h1>
		<a href="<?php echo esc_url( CCR_Admin::url( $this->c['slug'] ) ); ?>" class="page-title-action">&larr; <?php echo esc_html( $this->c['plural'] ); ?></a>
		<hr class="wp-header-end">
		<?php CCR_Admin::notices(); ?>

		<?php if ( $errors ) : ?>
			<div class="notice notice-error"><ul><?php foreach ( $errors as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul></div>
		<?php endif; ?>

		<form method="post" class="ccr-form" action="<?php echo esc_url( CCR_Admin::url( $this->c['slug'] ) ); ?>">
			<?php wp_nonce_field( 'ccr_save_' . $this->c['key'] ); ?>
			<input type="hidden" name="ccr_crud_action" value="save">
			<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>">

			<table class="form-table" role="presentation">
				<?php
				foreach ( $this->c['fields'] as $name => $field ) {
					$this->render_field( $name, $field, isset( $values[ $name ] ) ? $values[ $name ] : '' );
				}
				?>
			</table>

			<?php if ( ! empty( $this->c['formula_help'] ) ) : ?>
				<?php CCR_Admin::formula_help(); ?>
			<?php endif; ?>

			<?php submit_button( __( 'Guardar', 'calculadora-cielorraso-pvc' ) ); ?>
		</form>
		<?php
	}

	private function render_field( $name, array $field, $value ) {
		if ( 'section' === $field['type'] ) {
			echo '<tr class="ccr-section-row"><th colspan="2"><h2>' . esc_html( $field['label'] ) . '</h2></th></tr>';
			return;
		}
		$id    = 'ccr-f-' . $name;
		$input = 'f[' . $name . ']';
		$req   = ! empty( $field['required'] );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo $req ? ' <span class="ccr-req">*</span>' : ''; ?></label></th>
			<td>
				<?php
				switch ( $field['type'] ) {
					case 'textarea':
						printf( '<textarea id="%1$s" name="%2$s" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $input ), esc_textarea( (string) $value ) );
						break;

					case 'formula':
						printf(
							'<textarea id="%1$s" name="%2$s" rows="3" class="large-text code ccr-formula" spellcheck="false" %4$s>%3$s</textarea>',
							esc_attr( $id ),
							esc_attr( $input ),
							esc_textarea( (string) $value ),
							$req ? 'required' : ''
						);
						echo '<p><button type="button" class="button ccr-test-formula" data-target="' . esc_attr( $id ) . '">' . esc_html__( 'Probar fórmula', 'calculadora-cielorraso-pvc' ) . '</button> <span class="ccr-test-result" aria-live="polite"></span></p>';
						break;

					case 'number':
						printf(
							'<input type="number" id="%1$s" name="%2$s" value="%3$s" step="%4$s" class="regular-text ccr-num-%5$s" %6$s>',
							esc_attr( $id ),
							esc_attr( $input ),
							esc_attr( null === $value ? '' : $value ),
							esc_attr( isset( $field['step'] ) ? $field['step'] : 'any' ),
							esc_attr( $name ),
							! empty( $field['nullable'] ) ? 'placeholder="' . esc_attr__( 'general', 'calculadora-cielorraso-pvc' ) . '"' : ''
						);
						break;

					case 'select':
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $input ) . '">';
						foreach ( $this->options( $field ) as $k => $label ) {
							echo '<option value="' . esc_attr( $k ) . '" ' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $label ) . '</option>';
						}
						echo '</select>';
						break;

					case 'checkbox':
						printf( '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>', esc_attr( $id ), esc_attr( $input ), checked( (int) $value, 1, false ), esc_html__( 'Sí', 'calculadora-cielorraso-pvc' ) );
						break;

					case 'multicheck':
						$selected = CCR_Calculator::decode_ids( $value );
						echo '<fieldset>';
						foreach ( $this->options( $field ) as $k => $label ) {
							printf( '<label class="ccr-multicheck"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>', esc_attr( $input ), esc_attr( $k ), checked( in_array( (int) $k, $selected, true ), true, false ), esc_html( $label ) );
						}
						echo '</fieldset>';
						break;

					case 'variants':
						$this->render_variants( $input, CCR_Calculator::decode_variants( $value ) );
						break;

					default:
						printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text%4$s" %5$s>', esc_attr( $id ), esc_attr( $input ), esc_attr( (string) $value ), 'slug' === $field['type'] ? ' code' : '', $req ? 'required' : '' );
				}
				if ( ! empty( $field['help'] ) ) {
					echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	private function render_variants( $input, array $rows ) {
		$row_html = static function ( $i, $r ) use ( $input ) {
			$n = $input . '[' . $i . ']';
			return '<tr>'
				. '<td><input type="text" name="' . esc_attr( $n . '[label]' ) . '" value="' . esc_attr( $r['label'] ) . '" placeholder="5 m"></td>'
				. '<td><input type="number" step="0.0001" name="' . esc_attr( $n . '[length]' ) . '" value="' . esc_attr( $r['length'] ) . '"></td>'
				. '<td><input type="number" step="0.0001" name="' . esc_attr( $n . '[width]' ) . '" value="' . esc_attr( $r['width'] ? $r['width'] : '' ) . '" placeholder="—"></td>'
				. '<td><input type="number" step="0.0001" name="' . esc_attr( $n . '[price]' ) . '" value="' . esc_attr( $r['price'] ) . '"></td>'
				. '<td><input type="text" name="' . esc_attr( $n . '[sku]' ) . '" value="' . esc_attr( $r['sku'] ) . '" placeholder="' . esc_attr__( 'SKU del material', 'calculadora-cielorraso-pvc' ) . '"></td>'
				. '<td><button type="button" class="button-link ccr-danger ccr-remove-row" aria-label="' . esc_attr__( 'Quitar', 'calculadora-cielorraso-pvc' ) . '">&times;</button></td>'
				. '</tr>';
		};
		$empty = array(
			'label'  => '',
			'length' => '',
			'width'  => '',
			'price'  => '',
			'sku'    => '',
		);
		?>
		<div class="ccr-variants" data-name="<?php echo esc_attr( $input ); ?>">
			<table class="widefat ccr-variants-table">
				<thead><tr>
					<th><?php esc_html_e( 'Etiqueta', 'calculadora-cielorraso-pvc' ); ?></th>
					<th><?php esc_html_e( 'Largo (m)', 'calculadora-cielorraso-pvc' ); ?></th>
					<th><?php esc_html_e( 'Ancho (m, opcional)', 'calculadora-cielorraso-pvc' ); ?></th>
					<th><?php esc_html_e( 'Precio', 'calculadora-cielorraso-pvc' ); ?></th>
					<th><?php esc_html_e( 'SKU tienda (opcional)', 'calculadora-cielorraso-pvc' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
					<?php
					foreach ( array_values( $rows ) as $i => $r ) {
						echo $row_html( $i, $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en $row_html.
					}
					?>
				</tbody>
			</table>
			<template class="ccr-variant-template"><?php echo $row_html( '__i__', $empty ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></template>
			<p><button type="button" class="button ccr-add-row"><?php esc_html_e( '+ Agregar variante', 'calculadora-cielorraso-pvc' ); ?></button></p>
		</div>
		<?php
	}
}
