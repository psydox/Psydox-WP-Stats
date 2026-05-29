<?php
/**
 * Main plugin class.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats {
	/**
	 * Internal schema version.
	 */
	private const SCHEMA_VERSION = '2';

	/**
	 * Tracker service.
	 *
	 * @var Psydox_WP_Stats_Tracker
	 */
	private $tracker;

	/**
	 * Admin service.
	 *
	 * @var Psydox_WP_Stats_Admin
	 */
	private $admin;

	/**
	 * Realtime service.
	 *
	 * @var Psydox_WP_Stats_Realtime
	 */
	private $realtime;

	/**
	 * Export service.
	 *
	 * @var Psydox_WP_Stats_Export
	 */
	private $export;

	/**
	 * WP-CLI service.
	 *
	 * @var Psydox_WP_Stats_CLI|null
	 */
	private $cli;

	/**
	 * Run plugin.
	 *
	 * @return void
	 */
	public function run() {
		$this->tracker  = new Psydox_WP_Stats_Tracker();
		$this->admin    = new Psydox_WP_Stats_Admin();
		$this->realtime = new Psydox_WP_Stats_Realtime();
		$this->export   = new Psydox_WP_Stats_Export();

		$this->tracker->register_hooks();
		$this->admin->register_hooks();
		$this->realtime->register_hooks();
		$this->export->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->cli = new Psydox_WP_Stats_CLI();
			$this->cli->register_hooks();
		}

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'maybe_schedule_cleanup' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_schema' ) );
		add_action( 'psydox_wp_stats_cleanup', array( $this, 'cleanup_old_stats' ) );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'psydox-wp-stats', false, dirname( plugin_basename( PSYDOX_WP_STATS_FILE ) ) . '/languages' );
	}

	/**
	 * Ensure schema upgrades are applied.
	 *
	 * @return void
	 */
	public function maybe_upgrade_schema() {
		$installed_version = (string) get_option( 'psydox_wp_stats_schema_version', '1' );

		if ( version_compare( $installed_version, self::SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		$database = new Psydox_WP_Stats_Database();
		$database->create_table();

		update_option( 'psydox_wp_stats_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Get plugin settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$defaults = array(
			'tracking_enabled'       => 1,
			'exclude_administrators' => 1,
			'exclude_editors'        => 0,
			'exclude_authors'        => 0,
			'exclude_logged_in'      => 0,
			'retention_days'         => 90,
			'realtime_enabled'       => 1,
			'refresh_interval'       => 10,
			'active_window_minutes'  => 5,
			'chart_columns'          => 3,
			'hash_ip'                => 1,
			'store_referrer'         => 1,
			'store_browser'          => 1,
			'store_device'           => 1,
			'bot_allowlist'          => '',
			'bot_denylist'           => '',
			'block_denied_bots'      => 1,
			'spike_ratio_threshold'  => 2.5,
		);

		$settings = get_option( PSYDOX_WP_STATS_OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return array_merge( $defaults, $settings );
	}

	/**
	 * Schedule cleanup job.
	 *
	 * @return void
	 */
	public function maybe_schedule_cleanup() {
		if ( ! wp_next_scheduled( 'psydox_wp_stats_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'psydox_wp_stats_cleanup' );
		}
	}

	/**
	 * Remove data outside retention period.
	 *
	 * @return void
	 */
	public function cleanup_old_stats() {
		$settings       = self::get_settings();
		$retention_days = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 90;

		if ( $retention_days <= 0 ) {
			return;
		}

		$database = new Psydox_WP_Stats_Database();
		$database->purge_older_than_days( $retention_days );
		Psydox_WP_Stats_Admin::invalidate_dashboard_cache();
	}
}
