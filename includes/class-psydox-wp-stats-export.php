<?php
/**
 * Export service.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Export {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_psydox_wp_stats_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Handle CSV and JSON export.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'psydox-wp-stats' ) );
		}

		check_admin_referer( 'psydox_wp_stats_export_action', 'psydox_wp_stats_export_nonce' );

		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		if ( ! in_array( $format, array( 'csv', 'json' ), true ) ) {
			$format = 'csv';
		}

		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();

		$where   = array( '1=1' );
		$prepare = array();

		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$type      = isset( $_GET['visitor_type'] ) ? sanitize_key( wp_unslash( $_GET['visitor_type'] ) ) : '';
		$page      = isset( $_GET['page_url'] ) ? esc_url_raw( wp_unslash( $_GET['page_url'] ) ) : '';
		$referrer  = isset( $_GET['referrer'] ) ? esc_url_raw( wp_unslash( $_GET['referrer'] ) ) : '';
		$crawler   = isset( $_GET['crawler_name'] ) ? sanitize_text_field( wp_unslash( $_GET['crawler_name'] ) ) : '';

		if ( ! empty( $type ) && ! in_array( $type, array( 'human', 'bot' ), true ) ) {
			$type = '';
		}

		if ( ! empty( $date_from ) && ! $this->is_valid_date( $date_from ) ) {
			$this->redirect_with_export_error( 'invalid_date_from' );
		}

		if ( ! empty( $date_to ) && ! $this->is_valid_date( $date_to ) ) {
			$this->redirect_with_export_error( 'invalid_date_to' );
		}

		if ( ! empty( $date_from ) && ! empty( $date_to ) && strtotime( $date_from ) > strtotime( $date_to ) ) {
			$this->redirect_with_export_error( 'invalid_date_range' );
		}

		if ( ! empty( $date_from ) ) {
			$where[]   = 'visit_date >= %s';
			$prepare[] = $date_from;
		}
		if ( ! empty( $date_to ) ) {
			$where[]   = 'visit_date <= %s';
			$prepare[] = $date_to;
		}
		if ( ! empty( $type ) ) {
			$where[]   = 'visitor_type = %s';
			$prepare[] = $type;
		}
		if ( ! empty( $page ) ) {
			$where[]   = 'page_url = %s';
			$prepare[] = $page;
		}
		if ( ! empty( $referrer ) ) {
			$where[]   = 'referrer = %s';
			$prepare[] = $referrer;
		}
		if ( ! empty( $crawler ) ) {
			$where[]   = 'crawler_name = %s';
			$prepare[] = $crawler;
		}

		$sql = "SELECT * FROM {$table_name} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT 50000';

		if ( ! empty( $prepare ) ) {
			$sql = $wpdb->prepare( $sql, $prepare );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="psydox-wp-stats-export.json"' );
			echo wp_json_encode( $rows );
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="psydox-wp-stats-export.csv"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Unable to generate export file.', 'psydox-wp-stats' ) );
		}

		if ( ! empty( $rows ) ) {
			fputcsv( $output, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Validate date format (Y-m-d) and value.
	 *
	 * @param string $date Date string.
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		$parts = explode( '-', $date );
		return 3 === count( $parts ) && checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Redirect to settings screen with export error code.
	 *
	 * @param string $code Error code.
	 * @return void
	 */
	private function redirect_with_export_error( $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'psydox-wp-stats',
					'tab'          => 'settings',
					'export_error' => sanitize_key( $code ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
