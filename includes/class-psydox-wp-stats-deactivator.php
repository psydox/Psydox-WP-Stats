<?php
/**
 * Deactivation logic.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Deactivator {
	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'psydox_wp_stats_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'psydox_wp_stats_cleanup' );
		}
	}
}
