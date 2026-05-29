<?php
/**
 * WP-CLI commands for Psydox WP Stats.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_CLI {
	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'psydox-stats maintenance', array( $this, 'maintenance' ) );
		\WP_CLI::add_command( 'psydox-stats export', array( $this, 'export' ) );
	}

	/**
	 * Run maintenance operation.
	 *
	 * ## OPTIONS
	 *
	 * --action=<action>
	 * : Maintenance action: clear|rebuild|optimize|cleanup
	 *
	 * ## EXAMPLES
	 *
	 *     wp psydox-stats maintenance --action=cleanup
	 *
	 * @param array<int,string>         $args Positional args.
	 * @param array<string,string|bool> $assoc_args Associative args.
	 * @return void
	 */
	public function maintenance( $args, $assoc_args ) {
		$action = isset( $assoc_args['action'] ) ? sanitize_key( (string) $assoc_args['action'] ) : '';
		$database = new Psydox_WP_Stats_Database();

		switch ( $action ) {
			case 'clear':
				$database->clear_all();
				Psydox_WP_Stats_Admin::invalidate_dashboard_cache();
				\WP_CLI::success( 'All statistics cleared.' );
				return;
			case 'rebuild':
				$database->create_table();
				Psydox_WP_Stats_Admin::invalidate_dashboard_cache();
				\WP_CLI::success( 'Database table rebuilt.' );
				return;
			case 'optimize':
				$database->optimize();
				Psydox_WP_Stats_Admin::invalidate_dashboard_cache();
				\WP_CLI::success( 'Database table optimized.' );
				return;
			case 'cleanup':
				$settings       = Psydox_WP_Stats::get_settings();
				$retention_days = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 90;
				if ( $retention_days <= 0 ) {
					\WP_CLI::success( 'Retention is set to unlimited. No rows were purged.' );
					return;
				}

				$purged = $database->purge_older_than_days( $retention_days );
				Psydox_WP_Stats_Admin::invalidate_dashboard_cache();
				\WP_CLI::success( sprintf( 'Retention cleanup complete. Purged rows: %d', (int) $purged ) );
				return;
			default:
				\WP_CLI::error( 'Invalid --action. Use clear, rebuild, optimize, or cleanup.' );
		}
	}

	/**
	 * Export stats data to file.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Export format: csv|json. Default: csv
	 *
	 * [--output=<path>]
	 * : Output file path. Defaults to wp-content/uploads.
	 *
	 * [--date_from=<YYYY-MM-DD>]
	 * : Filter start date.
	 *
	 * [--date_to=<YYYY-MM-DD>]
	 * : Filter end date.
	 *
	 * [--visitor_type=<type>]
	 * : Filter by visitor type: human|bot.
	 *
	 * [--page_url=<url>]
	 * : Filter by exact page URL.
	 *
	 * [--referrer=<url>]
	 * : Filter by exact referrer URL.
	 *
	 * [--crawler_name=<name>]
	 * : Filter by crawler name.
	 *
	 * [--limit=<number>]
	 * : Max rows to export. Default: 50000
	 *
	 * ## EXAMPLES
	 *
	 *     wp psydox-stats export --format=json --date_from=2026-01-01 --date_to=2026-01-31
	 *
	 * @param array<int,string>         $args Positional args.
	 * @param array<string,string|bool> $assoc_args Associative args.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		global $wpdb;

		$format = isset( $assoc_args['format'] ) ? sanitize_key( (string) $assoc_args['format'] ) : 'csv';
		if ( ! in_array( $format, array( 'csv', 'json' ), true ) ) {
			\WP_CLI::error( 'Invalid --format. Use csv or json.' );
		}

		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50000;
		$limit = max( 1, min( 200000, $limit ) );

		$date_from = isset( $assoc_args['date_from'] ) ? sanitize_text_field( (string) $assoc_args['date_from'] ) : '';
		$date_to   = isset( $assoc_args['date_to'] ) ? sanitize_text_field( (string) $assoc_args['date_to'] ) : '';
		$type      = isset( $assoc_args['visitor_type'] ) ? sanitize_key( (string) $assoc_args['visitor_type'] ) : '';
		$page      = isset( $assoc_args['page_url'] ) ? esc_url_raw( (string) $assoc_args['page_url'] ) : '';
		$referrer  = isset( $assoc_args['referrer'] ) ? esc_url_raw( (string) $assoc_args['referrer'] ) : '';
		$crawler   = isset( $assoc_args['crawler_name'] ) ? sanitize_text_field( (string) $assoc_args['crawler_name'] ) : '';

		if ( ! empty( $type ) && ! in_array( $type, array( 'human', 'bot' ), true ) ) {
			\WP_CLI::error( 'Invalid --visitor_type. Use human or bot.' );
		}

		if ( ! empty( $date_from ) && ! $this->is_valid_date( $date_from ) ) {
			\WP_CLI::error( 'Invalid --date_from. Use YYYY-MM-DD.' );
		}

		if ( ! empty( $date_to ) && ! $this->is_valid_date( $date_to ) ) {
			\WP_CLI::error( 'Invalid --date_to. Use YYYY-MM-DD.' );
		}

		if ( ! empty( $date_from ) && ! empty( $date_to ) && strtotime( $date_from ) > strtotime( $date_to ) ) {
			\WP_CLI::error( '--date_from cannot be later than --date_to.' );
		}

		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();
		$where      = array( '1=1' );
		$params     = array();

		if ( ! empty( $date_from ) ) {
			$where[]  = 'visit_date >= %s';
			$params[] = $date_from;
		}
		if ( ! empty( $date_to ) ) {
			$where[]  = 'visit_date <= %s';
			$params[] = $date_to;
		}
		if ( ! empty( $type ) ) {
			$where[]  = 'visitor_type = %s';
			$params[] = $type;
		}
		if ( ! empty( $page ) ) {
			$where[]  = 'page_url = %s';
			$params[] = $page;
		}
		if ( ! empty( $referrer ) ) {
			$where[]  = 'referrer = %s';
			$params[] = $referrer;
		}
		if ( ! empty( $crawler ) ) {
			$where[]  = 'crawler_name = %s';
			$params[] = $crawler;
		}

		$sql = "SELECT * FROM {$table_name} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d';
		$params[] = $limit;
		$sql      = $wpdb->prepare( $sql, $params );
		$rows     = $wpdb->get_results( $sql, ARRAY_A );

		$output = isset( $assoc_args['output'] ) ? sanitize_text_field( (string) $assoc_args['output'] ) : '';
		if ( empty( $output ) ) {
			$upload_dir = wp_upload_dir();
			$filename   = 'psydox-wp-stats-export-' . gmdate( 'Ymd-His' ) . '.' . $format;
			$output     = trailingslashit( $upload_dir['basedir'] ) . $filename;
		}

		$directory = dirname( $output );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			\WP_CLI::error( 'Could not create export directory.' );
		}

		if ( 'json' === $format ) {
			$written = file_put_contents( $output, wp_json_encode( $rows ) );
			if ( false === $written ) {
				\WP_CLI::error( 'Failed to write JSON export file.' );
			}

			\WP_CLI::success( sprintf( 'Exported %d rows to %s', count( $rows ), $output ) );
			return;
		}

		$file = fopen( $output, 'w' );
		if ( false === $file ) {
			\WP_CLI::error( 'Failed to open CSV export file for writing.' );
		}

		if ( ! empty( $rows ) ) {
			fputcsv( $file, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $file, $row );
			}
		}

		fclose( $file );
		\WP_CLI::success( sprintf( 'Exported %d rows to %s', count( $rows ), $output ) );
	}

	/**
	 * Validate date format (Y-m-d).
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
}
