<?php
/**
 * Activation logic.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Activator {
	/**
	 * Activation callback.
	 *
	 * @param bool $network_wide If network activation.
	 * @return void
	 */
	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install_for_current_site();
				restore_current_blog();
			}

			return;
		}

		self::install_for_current_site();
	}

	/**
	 * Install database and default options for the current site.
	 *
	 * @return void
	 */
	private static function install_for_current_site() {
		$database = new Psydox_WP_Stats_Database();
		$database->create_table();

		if ( false === get_option( PSYDOX_WP_STATS_OPTION_KEY ) ) {
			add_option( PSYDOX_WP_STATS_OPTION_KEY, Psydox_WP_Stats::get_settings() );
		}

		if ( ! wp_next_scheduled( 'psydox_wp_stats_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'psydox_wp_stats_cleanup' );
		}
	}
}
