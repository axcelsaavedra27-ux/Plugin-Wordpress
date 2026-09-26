<?php
/**
 * Desinstalación. Solo borra datos si se activó "Borrar todos los datos al desinstalar".
 *
 * @package CalculadoraCielorrasoPVC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Borra tablas y opciones del sitio actual.
 */
function ccr_uninstall_site() {
	global $wpdb;
	$settings = get_option( 'ccr_settings' );
	if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}
	foreach ( array( 'ccr_calculations', 'ccr_leads', 'ccr_materials', 'ccr_variables', 'ccr_install_types', 'ccr_categories' ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	foreach ( array( 'ccr_settings', 'ccr_db_version', 'ccr_seeded', 'ccr_tester_sample' ) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $ccr_site_id ) {
		switch_to_blog( $ccr_site_id );
		ccr_uninstall_site();
		restore_current_blog();
	}
} else {
	ccr_uninstall_site();
}
