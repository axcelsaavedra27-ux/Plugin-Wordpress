<?php
/**
 * Activación, creación/migración de tablas y datos iniciales.
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'ABSPATH' ) || exit;

class CCR_Activator {

	/**
	 * Activación (admite activación de red en multisitio).
	 *
	 * @param bool $network_wide Activación en toda la red.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	public static function deactivate() {
		// No se borra nada al desactivar. Ver uninstall.php.
	}

	/**
	 * Crea / actualiza tablas, opciones y datos de ejemplo.
	 */
	public static function install() {
		self::create_tables();

		if ( false === get_option( CCR_Settings::OPTION ) ) {
			add_option( CCR_Settings::OPTION, CCR_Settings::defaults() );
		} else {
			self::migrate_design();
		}

		if ( ! get_option( 'ccr_seeded' ) ) {
			CCR_Seeder::seed();
			update_option( 'ccr_seeded', 1 );
		}

		update_option( 'ccr_db_version', CCR_DB_VERSION );
	}

	/**
	 * 1.1.0: aplica el diseño Konex si el sitio seguía con los valores de fábrica de la 1.0.
	 * Los valores personalizados por el administrador no se tocan.
	 */
	private static function migrate_design() {
		$saved = get_option( CCR_Settings::OPTION );
		if ( ! is_array( $saved ) ) {
			return;
		}
		$old = array(
			'primary_color' => array( '#0b6bcb', '#292A87' ),
			'accent_color'  => array( '#0f9d58', '#AE2F45' ),
			'border_radius' => array( 10, 24 ),
			'subtitle'      => array( 'Ingresá las medidas del ambiente y obtené al instante la lista de materiales.', 'Ingresá las medidas y obtené al instante la lista de materiales, el plano de colocación y una cotización estimada.' ),
		);
		$changed = false;
		foreach ( $old as $key => $pair ) {
			if ( isset( $saved[ $key ] ) && strtolower( (string) $saved[ $key ] ) === strtolower( (string) $pair[0] ) ) {
				$saved[ $key ] = $pair[1];
				$changed       = true;
			}
		}
		if ( $changed ) {
			update_option( CCR_Settings::OPTION, $saved );
		}
	}

	/**
	 * Esquema de la base de datos (dbDelta). Ver también sql/schema.sql.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}ccr_categories (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  slug varchar(100) NOT NULL,
  description text NULL,
  selection_mode varchar(20) NOT NULL DEFAULT 'all',
  customer_label varchar(190) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY active_order (active,sort_order)
) $charset;";

		$sql[] = "CREATE TABLE {$p}ccr_install_types (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  slug varchar(100) NOT NULL,
  description text NULL,
  is_default tinyint(1) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug)
) $charset;";

		$sql[] = "CREATE TABLE {$p}ccr_materials (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  category_id bigint(20) unsigned NOT NULL DEFAULT 0,
  code varchar(64) NOT NULL,
  sku varchar(100) NOT NULL DEFAULT '',
  name varchar(190) NOT NULL,
  description text NULL,
  unit varchar(50) NOT NULL DEFAULT 'unidad',
  length decimal(12,4) NOT NULL DEFAULT 0,
  width decimal(12,4) NOT NULL DEFAULT 0,
  yield decimal(14,4) NOT NULL DEFAULT 0,
  price decimal(14,4) NOT NULL DEFAULT 0,
  price_qty decimal(12,4) NOT NULL DEFAULT 1,
  waste_pct decimal(7,3) NULL DEFAULT NULL,
  min_qty decimal(12,4) NOT NULL DEFAULT 0,
  correction_factor decimal(10,4) NOT NULL DEFAULT 1,
  rounding varchar(10) NOT NULL DEFAULT 'ceil',
  round_multiple decimal(12,4) NOT NULL DEFAULT 1,
  decimals tinyint(3) unsigned NOT NULL DEFAULT 0,
  formula text NULL,
  variants longtext NULL,
  install_types varchar(255) NOT NULL DEFAULT '',
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code (code),
  KEY category_id (category_id),
  KEY active_order (active,sort_order)
) $charset;";

		$sql[] = "CREATE TABLE {$p}ccr_variables (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  var_key varchar(64) NOT NULL,
  label varchar(190) NOT NULL DEFAULT '',
  formula text NULL,
  description text NULL,
  sort_order int(11) NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY var_key (var_key)
) $charset;";

		$sql[] = "CREATE TABLE {$p}ccr_leads (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token char(32) NOT NULL,
  name varchar(190) NOT NULL DEFAULT '',
  company varchar(190) NOT NULL DEFAULT '',
  phone varchar(60) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  consent tinyint(1) NOT NULL DEFAULT 0,
  source_url varchar(255) NOT NULL DEFAULT '',
  created_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token (token),
  KEY email (email),
  KEY created_at (created_at)
) $charset;";

		$sql[] = "CREATE TABLE {$p}ccr_calculations (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
  inputs longtext NULL,
  results longtext NULL,
  area decimal(12,4) NOT NULL DEFAULT 0,
  total decimal(16,4) NOT NULL DEFAULT 0,
  observations text NULL,
  created_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY lead_id (lead_id),
  KEY created_at (created_at)
) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
