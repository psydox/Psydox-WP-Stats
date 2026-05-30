<?php
/**
 * Realtime monitoring service.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Realtime {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_psydox_wp_stats_realtime', array( $this, 'ajax_get_realtime' ) );
	}

	/**
	 * Realtime admin ajax endpoint.
	 *
	 * @return void
	 */
	public function ajax_get_realtime() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'psydox-wp-stats' ) ), 403 );
		}

		check_ajax_referer( 'psydox_wp_stats_realtime_nonce', 'nonce' );

		$settings         = Psydox_WP_Stats::get_settings();
		if ( empty( $settings['realtime_enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Real-time monitoring is disabled.', 'psydox-wp-stats' ) ), 403 );
		}

		$window_minutes   = max( 1, (int) $settings['active_window_minutes'] );
		$window_condition = gmdate( 'Y-m-d H:i:s', time() - ( $window_minutes * MINUTE_IN_SECONDS ) );
		$database         = new Psydox_WP_Stats_Database();
		global $wpdb;
		$table_name = $database->get_table_name();

		$active_visitors = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_hash) FROM {$table_name} WHERE last_activity_at >= %s",
				$window_condition
			)
		);

		$active_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, COUNT(*) as visits
				FROM {$table_name}
				WHERE last_activity_at >= %s
				GROUP BY page_url
				ORDER BY visits DESC
				LIMIT 10",
				$window_condition
			),
			ARRAY_A
		);

		$recent_visits = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT visitor_type, page_url, browser, operating_system, device_type, referrer, crawler_name, ip_hash, last_activity_at
				FROM {$table_name}
				WHERE last_activity_at >= %s
				ORDER BY last_activity_at DESC
				LIMIT 30",
				$window_condition
			),
			ARRAY_A
		);

		$recent_crawlers = array_values(
			array_filter(
				$recent_visits,
				static function ( $visit ) {
					return isset( $visit['visitor_type'] ) && 'bot' === $visit['visitor_type'];
				}
			)
		);

		wp_send_json_success(
			array(
				'active_visitors' => $active_visitors,
				'active_pages'    => $active_pages,
				'recent_visits'   => $recent_visits,
				'recent_crawlers' => $recent_crawlers,
			)
		);
	}
}
