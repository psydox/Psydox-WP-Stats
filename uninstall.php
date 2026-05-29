<?php
/**
 * Uninstall cleanup.
 *
 * @package PsydoxWPStats
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data for one site.
 *
 * @return void
 */
function psydox_wp_stats_uninstall_site() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'psydox_wp_stats_visits';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	delete_option( 'psydox_wp_stats_settings' );
	delete_option( 'psydox_wp_stats_version' );
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		psydox_wp_stats_uninstall_site();
		restore_current_blog();
	}
} else {
	psydox_wp_stats_uninstall_site();
}
