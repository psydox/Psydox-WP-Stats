<?php
/**
 * Admin UI service.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Admin {
	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_menu', array( $this, 'cleanup_parent_submenu' ), 999 );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widgets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_psydox_wp_stats_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_psydox_wp_stats_maintenance', array( $this, 'handle_maintenance' ) );
	}

	/**
	 * Register WordPress Dashboard widgets.
	 *
	 * @return void
	 */
	public function register_dashboard_widgets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'psydox_wp_stats_quick_overview',
			esc_html__( 'Psydox WP Stats: Quick Overview', 'psydox-wp-stats' ),
			array( $this, 'render_dashboard_widget_overview' )
		);

		wp_add_dashboard_widget(
			'psydox_wp_stats_top_content',
			esc_html__( 'Psydox WP Stats: Top Content', 'psydox-wp-stats' ),
			array( $this, 'render_dashboard_widget_top_content' )
		);
	}

	/**
	 * Register Psydox parent menu and plugin pages.
	 *
	 * @return void
	 */
	public function register_menus() {
		$parent_slug = 'psydox-plugins';
		if ( ! $this->parent_menu_exists( $parent_slug ) ) {
			add_menu_page(
				__( 'Psydox Plugins', 'psydox-wp-stats' ),
				__( 'Psydox Plugins', 'psydox-wp-stats' ),
				'manage_options',
				$parent_slug,
				array( $this, 'render_parent_landing' ),
				'dashicons-chart-area',
				58
			);
		}

		add_submenu_page(
			$parent_slug,
			__( 'Psydox WP Stats', 'psydox-wp-stats' ),
			__( 'Stats', 'psydox-wp-stats' ),
			'manage_options',
			'psydox-wp-stats',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Remove duplicate parent self-submenu entry.
	 *
	 * @return void
	 */
	public function cleanup_parent_submenu() {
		remove_submenu_page( 'psydox-plugins', 'psydox-plugins' );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current screen hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'psydox-wp-stats' ) ) {
			return;
		}

		wp_enqueue_style(
			'psydox-wp-stats-admin',
			PSYDOX_WP_STATS_URL . 'admin/css/admin.css',
			array(),
			PSYDOX_WP_STATS_VERSION
		);

		wp_enqueue_script(
			'psydox-wp-stats-chartjs',
			PSYDOX_WP_STATS_URL . 'admin/js/chart.umd.min.js',
			array(),
			PSYDOX_WP_STATS_VERSION,
			true
		);

		wp_enqueue_script(
			'psydox-wp-stats-chartjs-geo',
			PSYDOX_WP_STATS_URL . 'admin/js/chartjs-chart-geo.umd.min.js',
			array( 'psydox-wp-stats-chartjs' ),
			PSYDOX_WP_STATS_VERSION,
			true
		);

		wp_enqueue_script(
			'psydox-wp-stats-charts',
			PSYDOX_WP_STATS_URL . 'admin/js/charts.js',
			array( 'psydox-wp-stats-chartjs', 'psydox-wp-stats-chartjs-geo' ),
			PSYDOX_WP_STATS_VERSION,
			true
		);

		$settings = Psydox_WP_Stats::get_settings();

		if ( ! empty( $settings['realtime_enabled'] ) ) {
			wp_enqueue_script(
				'psydox-wp-stats-realtime',
				PSYDOX_WP_STATS_URL . 'admin/js/realtime.js',
				array( 'jquery' ),
				PSYDOX_WP_STATS_VERSION,
				true
			);
		}

		wp_localize_script(
			'psydox-wp-stats-charts',
			'psydoxWpStatsChartData',
			array(
				'daily'      => $this->get_time_series( 30, 'day' ),
				'weekly'     => $this->get_time_series( 12, 'week' ),
				'monthly'    => $this->get_time_series( 12, 'month' ),
				'deviceTrends' => $this->get_device_trends( 30 ),
				'devices'    => $this->get_group_counts( 'device_type' ),
				'mobileVsDesktop' => $this->get_mobile_desktop_counts(),
				'browsers'   => $this->get_group_counts( 'browser' ),
				'os'         => $this->get_group_counts( 'operating_system' ),
				'countries'  => $this->get_group_counts( 'country_name', "country_name IS NOT NULL AND country_name <> '' AND country_name <> 'Unknown'" ),
				'countryDots' => $this->get_country_dot_data(),
				'worldGeoJsonUrl' => PSYDOX_WP_STATS_URL . 'admin/data/world.geojson',
				'humanVsBot' => $this->get_group_counts( 'visitor_type' ),
				'crawlers'   => $this->get_group_counts( 'crawler_name', "crawler_name IS NOT NULL AND crawler_name <> ''" ),
			)
		);

		if ( ! empty( $settings['realtime_enabled'] ) ) {
			wp_localize_script(
				'psydox-wp-stats-realtime',
				'psydoxWpStatsRealtime',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'psydox_wp_stats_realtime_nonce' ),
					'refreshInterval' => max( 5, (int) $settings['refresh_interval'] ),
				)
			);
		}
	}


	/**
	 * Get world-map dot data based on country aggregates.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_country_dot_data() {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();

		$rows = $wpdb->get_results(
			"SELECT country_code, country_name, COUNT(*) AS visits
			FROM {$table_name}
			WHERE country_name IS NOT NULL AND country_name <> '' AND country_name <> 'Unknown'
			GROUP BY country_code, country_name
			ORDER BY visits DESC
			LIMIT 100",
			ARRAY_A
		);

		$dots = array();
		foreach ( $rows as $row ) {
			$code = isset( $row['country_code'] ) ? strtoupper( trim( sanitize_text_field( (string) $row['country_code'] ) ) ) : '';
			if ( 'UN' === $code ) {
				$code = '';
			}

			$coords = $this->get_country_centroid( $code );
			$name = isset( $row['country_name'] ) ? sanitize_text_field( (string) $row['country_name'] ) : $code;

			$dots[] = array(
				'country_code' => $code,
				'country_name' => $name,
				'visits'       => isset( $row['visits'] ) ? (int) $row['visits'] : 0,
				'lat'          => isset( $coords['lat'] ) ? (float) $coords['lat'] : null,
				'lng'          => isset( $coords['lng'] ) ? (float) $coords['lng'] : null,
			);
		}

		return $dots;
	}

	/**
	 * Get approximate country centroid coordinates.
	 *
	 * @param string $country_code ISO country code.
	 * @return array<string,float>|array<int,mixed>
	 */
	private function get_country_centroid( $country_code ) {
		$centroids = array(
			'AF' => array( 'lat' => 33.9391, 'lng' => 67.7100 ),
			'AL' => array( 'lat' => 41.1533, 'lng' => 20.1683 ),
			'DZ' => array( 'lat' => 28.0339, 'lng' => 1.6596 ),
			'AD' => array( 'lat' => 42.5063, 'lng' => 1.5218 ),
			'AO' => array( 'lat' => -11.2027, 'lng' => 17.8739 ),
			'AR' => array( 'lat' => -38.4161, 'lng' => -63.6167 ),
			'AM' => array( 'lat' => 40.0691, 'lng' => 45.0382 ),
			'AU' => array( 'lat' => -25.2744, 'lng' => 133.7751 ),
			'AT' => array( 'lat' => 47.5162, 'lng' => 14.5501 ),
			'AZ' => array( 'lat' => 40.1431, 'lng' => 47.5769 ),
			'BH' => array( 'lat' => 26.0667, 'lng' => 50.5577 ),
			'BD' => array( 'lat' => 23.6850, 'lng' => 90.3563 ),
			'BY' => array( 'lat' => 53.7098, 'lng' => 27.9534 ),
			'BE' => array( 'lat' => 50.5039, 'lng' => 4.4699 ),
			'BZ' => array( 'lat' => 17.1899, 'lng' => -88.4976 ),
			'BJ' => array( 'lat' => 9.3077, 'lng' => 2.3158 ),
			'BT' => array( 'lat' => 27.5142, 'lng' => 90.4336 ),
			'BO' => array( 'lat' => -16.2902, 'lng' => -63.5887 ),
			'BA' => array( 'lat' => 43.9159, 'lng' => 17.6791 ),
			'BW' => array( 'lat' => -22.3285, 'lng' => 24.6849 ),
			'BR' => array( 'lat' => -14.2350, 'lng' => -51.9253 ),
			'BN' => array( 'lat' => 4.5353, 'lng' => 114.7277 ),
			'BG' => array( 'lat' => 42.7339, 'lng' => 25.4858 ),
			'BF' => array( 'lat' => 12.2383, 'lng' => -1.5616 ),
			'BI' => array( 'lat' => -3.3731, 'lng' => 29.9189 ),
			'KH' => array( 'lat' => 12.5657, 'lng' => 104.9910 ),
			'CM' => array( 'lat' => 7.3697, 'lng' => 12.3547 ),
			'US' => array( 'lat' => 39.8283, 'lng' => -98.5795 ),
			'CA' => array( 'lat' => 56.1304, 'lng' => -106.3468 ),
			'CV' => array( 'lat' => 16.5388, 'lng' => -23.0418 ),
			'CF' => array( 'lat' => 6.6111, 'lng' => 20.9394 ),
			'TD' => array( 'lat' => 15.4542, 'lng' => 18.7322 ),
			'CL' => array( 'lat' => -35.6751, 'lng' => -71.5430 ),
			'CN' => array( 'lat' => 35.8617, 'lng' => 104.1954 ),
			'CO' => array( 'lat' => 4.5709, 'lng' => -74.2973 ),
			'KM' => array( 'lat' => -11.8750, 'lng' => 43.8722 ),
			'CG' => array( 'lat' => -0.2280, 'lng' => 15.8277 ),
			'CD' => array( 'lat' => -4.0383, 'lng' => 21.7587 ),
			'CR' => array( 'lat' => 9.7489, 'lng' => -83.7534 ),
			'CI' => array( 'lat' => 7.5400, 'lng' => -5.5471 ),
			'HR' => array( 'lat' => 45.1000, 'lng' => 15.2000 ),
			'CU' => array( 'lat' => 21.5218, 'lng' => -77.7812 ),
			'CY' => array( 'lat' => 35.1264, 'lng' => 33.4299 ),
			'CZ' => array( 'lat' => 49.8175, 'lng' => 15.4730 ),
			'DK' => array( 'lat' => 56.2639, 'lng' => 9.5018 ),
			'DJ' => array( 'lat' => 11.8251, 'lng' => 42.5903 ),
			'DO' => array( 'lat' => 18.7357, 'lng' => -70.1627 ),
			'EC' => array( 'lat' => -1.8312, 'lng' => -78.1834 ),
			'EG' => array( 'lat' => 26.8206, 'lng' => 30.8025 ),
			'SV' => array( 'lat' => 13.7942, 'lng' => -88.8965 ),
			'GQ' => array( 'lat' => 1.6508, 'lng' => 10.2679 ),
			'ER' => array( 'lat' => 15.1794, 'lng' => 39.7823 ),
			'EE' => array( 'lat' => 58.5953, 'lng' => 25.0136 ),
			'SZ' => array( 'lat' => -26.5225, 'lng' => 31.4659 ),
			'ET' => array( 'lat' => 9.1450, 'lng' => 40.4897 ),
			'FI' => array( 'lat' => 61.9241, 'lng' => 25.7482 ),
			'MX' => array( 'lat' => 23.6345, 'lng' => -102.5528 ),
			'FR' => array( 'lat' => 46.2276, 'lng' => 2.2137 ),
			'GA' => array( 'lat' => -0.8037, 'lng' => 11.6094 ),
			'GM' => array( 'lat' => 13.4432, 'lng' => -15.3101 ),
			'GE' => array( 'lat' => 42.3154, 'lng' => 43.3569 ),
			'DE' => array( 'lat' => 51.1657, 'lng' => 10.4515 ),
			'GH' => array( 'lat' => 7.9465, 'lng' => -1.0232 ),
			'GR' => array( 'lat' => 39.0742, 'lng' => 21.8243 ),
			'GT' => array( 'lat' => 15.7835, 'lng' => -90.2308 ),
			'GN' => array( 'lat' => 9.9456, 'lng' => -9.6966 ),
			'GW' => array( 'lat' => 11.8037, 'lng' => -15.1804 ),
			'GY' => array( 'lat' => 4.8604, 'lng' => -58.9302 ),
			'HT' => array( 'lat' => 18.9712, 'lng' => -72.2852 ),
			'HN' => array( 'lat' => 15.2000, 'lng' => -86.2419 ),
			'HK' => array( 'lat' => 22.3193, 'lng' => 114.1694 ),
			'HU' => array( 'lat' => 47.1625, 'lng' => 19.5033 ),
			'IS' => array( 'lat' => 64.9631, 'lng' => -19.0208 ),
			'IN' => array( 'lat' => 20.5937, 'lng' => 78.9629 ),
			'ID' => array( 'lat' => -0.7893, 'lng' => 113.9213 ),
			'IR' => array( 'lat' => 32.4279, 'lng' => 53.6880 ),
			'IQ' => array( 'lat' => 33.2232, 'lng' => 43.6793 ),
			'GB' => array( 'lat' => 55.3781, 'lng' => -3.4360 ),
			'IE' => array( 'lat' => 53.4129, 'lng' => -8.2439 ),
			'IL' => array( 'lat' => 31.0461, 'lng' => 34.8516 ),
			'IT' => array( 'lat' => 41.8719, 'lng' => 12.5674 ),
			'JM' => array( 'lat' => 18.1096, 'lng' => -77.2975 ),
			'JP' => array( 'lat' => 36.2048, 'lng' => 138.2529 ),
			'JO' => array( 'lat' => 30.5852, 'lng' => 36.2384 ),
			'KZ' => array( 'lat' => 48.0196, 'lng' => 66.9237 ),
			'KE' => array( 'lat' => -0.0236, 'lng' => 37.9062 ),
			'KW' => array( 'lat' => 29.3117, 'lng' => 47.4818 ),
			'KG' => array( 'lat' => 41.2044, 'lng' => 74.7661 ),
			'LA' => array( 'lat' => 19.8563, 'lng' => 102.4955 ),
			'LV' => array( 'lat' => 56.8796, 'lng' => 24.6032 ),
			'LB' => array( 'lat' => 33.8547, 'lng' => 35.8623 ),
			'LY' => array( 'lat' => 26.3351, 'lng' => 17.2283 ),
			'LT' => array( 'lat' => 55.1694, 'lng' => 23.8813 ),
			'LU' => array( 'lat' => 49.8153, 'lng' => 6.1296 ),
			'MO' => array( 'lat' => 22.1987, 'lng' => 113.5439 ),
			'MG' => array( 'lat' => -18.7669, 'lng' => 46.8691 ),
			'MW' => array( 'lat' => -13.2543, 'lng' => 34.3015 ),
			'MY' => array( 'lat' => 4.2105, 'lng' => 101.9758 ),
			'MV' => array( 'lat' => 3.2028, 'lng' => 73.2207 ),
			'ML' => array( 'lat' => 17.5707, 'lng' => -3.9962 ),
			'MT' => array( 'lat' => 35.9375, 'lng' => 14.3754 ),
			'MR' => array( 'lat' => 21.0079, 'lng' => -10.9408 ),
			'MU' => array( 'lat' => -20.3484, 'lng' => 57.5522 ),
			'MD' => array( 'lat' => 47.4116, 'lng' => 28.3699 ),
			'MN' => array( 'lat' => 46.8625, 'lng' => 103.8467 ),
			'ME' => array( 'lat' => 42.7087, 'lng' => 19.3744 ),
			'MA' => array( 'lat' => 31.7917, 'lng' => -7.0926 ),
			'MZ' => array( 'lat' => -18.6657, 'lng' => 35.5296 ),
			'MM' => array( 'lat' => 21.9162, 'lng' => 95.9560 ),
			'NA' => array( 'lat' => -22.9576, 'lng' => 18.4904 ),
			'NP' => array( 'lat' => 28.3949, 'lng' => 84.1240 ),
			'ES' => array( 'lat' => 40.4637, 'lng' => -3.7492 ),
			'NL' => array( 'lat' => 52.1326, 'lng' => 5.2913 ),
			'NZ' => array( 'lat' => -40.9006, 'lng' => 174.8860 ),
			'NI' => array( 'lat' => 12.8654, 'lng' => -85.2072 ),
			'NE' => array( 'lat' => 17.6078, 'lng' => 8.0817 ),
			'NG' => array( 'lat' => 9.0820, 'lng' => 8.6753 ),
			'NO' => array( 'lat' => 60.4720, 'lng' => 8.4689 ),
			'OM' => array( 'lat' => 21.4735, 'lng' => 55.9754 ),
			'PK' => array( 'lat' => 30.3753, 'lng' => 69.3451 ),
			'PA' => array( 'lat' => 8.5380, 'lng' => -80.7821 ),
			'PG' => array( 'lat' => -6.3150, 'lng' => 143.9555 ),
			'PY' => array( 'lat' => -23.4425, 'lng' => -58.4438 ),
			'PE' => array( 'lat' => -9.1900, 'lng' => -75.0152 ),
			'PH' => array( 'lat' => 12.8797, 'lng' => 121.7740 ),
			'PL' => array( 'lat' => 51.9194, 'lng' => 19.1451 ),
			'PT' => array( 'lat' => 39.3999, 'lng' => -8.2245 ),
			'QA' => array( 'lat' => 25.3548, 'lng' => 51.1839 ),
			'RO' => array( 'lat' => 45.9432, 'lng' => 24.9668 ),
			'SE' => array( 'lat' => 60.1282, 'lng' => 18.6435 ),
			'RW' => array( 'lat' => -1.9403, 'lng' => 29.8739 ),
			'SN' => array( 'lat' => 14.4974, 'lng' => -14.4524 ),
			'RS' => array( 'lat' => 44.0165, 'lng' => 21.0059 ),
			'SL' => array( 'lat' => 8.4606, 'lng' => -11.7799 ),
			'SG' => array( 'lat' => 1.3521, 'lng' => 103.8198 ),
			'SK' => array( 'lat' => 48.6690, 'lng' => 19.6990 ),
			'SI' => array( 'lat' => 46.1512, 'lng' => 14.9955 ),
			'SO' => array( 'lat' => 5.1521, 'lng' => 46.1996 ),
			'ZA' => array( 'lat' => -30.5595, 'lng' => 22.9375 ),
			'KR' => array( 'lat' => 35.9078, 'lng' => 127.7669 ),
			'LK' => array( 'lat' => 7.8731, 'lng' => 80.7718 ),
			'SD' => array( 'lat' => 12.8628, 'lng' => 30.2176 ),
			'SR' => array( 'lat' => 3.9193, 'lng' => -56.0278 ),
			'CH' => array( 'lat' => 46.8182, 'lng' => 8.2275 ),
			'SY' => array( 'lat' => 34.8021, 'lng' => 38.9968 ),
			'TW' => array( 'lat' => 23.6978, 'lng' => 120.9605 ),
			'TJ' => array( 'lat' => 38.8610, 'lng' => 71.2761 ),
			'TZ' => array( 'lat' => -6.3690, 'lng' => 34.8888 ),
			'TH' => array( 'lat' => 15.8700, 'lng' => 100.9925 ),
			'TN' => array( 'lat' => 33.8869, 'lng' => 9.5375 ),
			'TR' => array( 'lat' => 38.9637, 'lng' => 35.2433 ),
			'UG' => array( 'lat' => 1.3733, 'lng' => 32.2903 ),
			'UA' => array( 'lat' => 48.3794, 'lng' => 31.1656 ),
			'AE' => array( 'lat' => 23.4241, 'lng' => 53.8478 ),
			'SA' => array( 'lat' => 23.8859, 'lng' => 45.0792 ),
			'UY' => array( 'lat' => -32.5228, 'lng' => -55.7658 ),
			'UZ' => array( 'lat' => 41.3775, 'lng' => 64.5853 ),
			'VE' => array( 'lat' => 6.4238, 'lng' => -66.5897 ),
			'VN' => array( 'lat' => 14.0583, 'lng' => 108.2772 ),
			'YE' => array( 'lat' => 15.5527, 'lng' => 48.5164 ),
			'ZM' => array( 'lat' => -13.1339, 'lng' => 27.8493 ),
			'ZW' => array( 'lat' => -19.0154, 'lng' => 29.1549 ),
			'RU' => array( 'lat' => 61.5240, 'lng' => 105.3188 ),
		);

		return isset( $centroids[ $country_code ] ) ? $centroids[ $country_code ] : array();
	}

	/**
	 * Save settings form.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'psydox-wp-stats' ) );
		}

		check_admin_referer( 'psydox_wp_stats_save_settings_action', 'psydox_wp_stats_settings_nonce' );

		$posted = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();

		$settings = array(
			'tracking_enabled'       => isset( $posted['tracking_enabled'] ) ? 1 : 0,
			'exclude_administrators' => isset( $posted['exclude_administrators'] ) ? 1 : 0,
			'exclude_editors'        => isset( $posted['exclude_editors'] ) ? 1 : 0,
			'exclude_authors'        => isset( $posted['exclude_authors'] ) ? 1 : 0,
			'exclude_logged_in'      => isset( $posted['exclude_logged_in'] ) ? 1 : 0,
			'retention_days'         => isset( $posted['retention_days'] ) ? (int) $posted['retention_days'] : 90,
			'realtime_enabled'       => isset( $posted['realtime_enabled'] ) ? 1 : 0,
			'refresh_interval'       => isset( $posted['refresh_interval'] ) ? max( 5, (int) $posted['refresh_interval'] ) : 10,
			'active_window_minutes'  => isset( $posted['active_window_minutes'] ) ? max( 1, (int) $posted['active_window_minutes'] ) : 5,
			'chart_columns'          => isset( $posted['chart_columns'] ) ? (int) $posted['chart_columns'] : 3,
			'hash_ip'                => isset( $posted['hash_ip'] ) ? 1 : 0,
			'store_referrer'         => isset( $posted['store_referrer'] ) ? 1 : 0,
			'store_browser'          => isset( $posted['store_browser'] ) ? 1 : 0,
			'store_device'           => isset( $posted['store_device'] ) ? 1 : 0,
			'bot_allowlist'          => isset( $posted['bot_allowlist'] ) ? sanitize_textarea_field( (string) $posted['bot_allowlist'] ) : '',
			'bot_denylist'           => isset( $posted['bot_denylist'] ) ? sanitize_textarea_field( (string) $posted['bot_denylist'] ) : '',
			'block_denied_bots'      => isset( $posted['block_denied_bots'] ) ? 1 : 0,
			'spike_ratio_threshold'  => isset( $posted['spike_ratio_threshold'] ) ? (float) $posted['spike_ratio_threshold'] : 2.5,
		);

		$allowed_retention = array( 0, 30, 90, 180, 365 );
		if ( ! in_array( $settings['retention_days'], $allowed_retention, true ) ) {
			$settings['retention_days'] = 90;
		}

		if ( ! in_array( $settings['chart_columns'], array( 1, 2, 3, 4 ), true ) ) {
			$settings['chart_columns'] = 3;
		}

		$settings['spike_ratio_threshold'] = min( 20, max( 1, (float) $settings['spike_ratio_threshold'] ) );

		update_option( PSYDOX_WP_STATS_OPTION_KEY, $settings );
		self::invalidate_dashboard_cache();

		wp_safe_redirect( admin_url( 'admin.php?page=psydox-wp-stats&tab=settings&updated=1' ) );
		exit;
	}

	/**
	 * Handle maintenance actions.
	 *
	 * @return void
	 */
	public function handle_maintenance() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'psydox-wp-stats' ) );
		}

		check_admin_referer( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' );

		$action   = isset( $_POST['maintenance_action'] ) ? sanitize_key( wp_unslash( $_POST['maintenance_action'] ) ) : '';
		$database = new Psydox_WP_Stats_Database();
		$args     = array(
			'page'       => 'psydox-wp-stats',
			'tab'        => 'settings',
			'maintenance' => 1,
		);

		switch ( $action ) {
			case 'clear':
				$database->clear_all();
				self::invalidate_dashboard_cache();
				break;
			case 'rebuild':
				$database->create_table();
				self::invalidate_dashboard_cache();
				break;
			case 'optimize':
				$database->optimize();
				self::invalidate_dashboard_cache();
				break;
			case 'cleanup_retention':
				$settings       = Psydox_WP_Stats::get_settings();
				$retention_days = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 90;

				if ( $retention_days > 0 ) {
					$purged_rows = $database->purge_older_than_days( $retention_days );
					$args['purged'] = max( 0, (int) $purged_rows );
				} else {
					$args['purged'] = 0;
				}

				$args['maintenance_action'] = 'cleanup_retention';
				self::invalidate_dashboard_cache();
				break;
			case 'generate_test_data':
				if ( ! self::is_debug_mode_enabled() ) {
					$args['maintenance_action'] = 'generate_test_data';
					$args['debug_required']     = 1;
					break;
				}

				$requested_rows = isset( $_POST['test_data_rows'] ) ? absint( wp_unslash( $_POST['test_data_rows'] ) ) : 100;
				$requested_rows = max( 1, min( 5000, $requested_rows ) );
				$inserted_rows  = $database->generate_test_data( $requested_rows );
				$args['maintenance_action'] = 'generate_test_data';
				$args['inserted']           = max( 0, (int) $inserted_rows );
				self::invalidate_dashboard_cache();
				break;
			case 'download_geo_db':
				$source = isset( $_POST['geo_db_source'] ) ? sanitize_key( wp_unslash( $_POST['geo_db_source'] ) ) : 'dbip_lite';
				$result = $this->download_geo_database( $source );

				$args['maintenance_action'] = 'download_geo_db';
				$args['geo_source']         = $source;
				$args['geo_status']         = ! empty( $result['success'] ) ? 'success' : 'error';
				$args['geo_message']        = isset( $result['message'] ) ? rawurlencode( (string) $result['message'] ) : '';
				break;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Parent page fallback.
	 *
	 * @return void
	 */
	public function render_parent_landing() {
		$this->render_dashboard();
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'psydox-wp-stats' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		if ( 'settings' === $tab ) {
			$this->render_settings();
			return;
		}

		if ( ! in_array( $tab, array( 'dashboard', 'content', 'world', 'bots' ), true ) ) {
			$tab = 'dashboard';
		}

		$pagination = array(
			'top_pages_page'       => isset( $_GET['top_pages_page'] ) ? max( 1, (int) $_GET['top_pages_page'] ) : 1,
			'recent_visits_page'   => isset( $_GET['recent_visits_page'] ) ? max( 1, (int) $_GET['recent_visits_page'] ) : 1,
			'recent_crawlers_page' => isset( $_GET['recent_crawlers_page'] ) ? max( 1, (int) $_GET['recent_crawlers_page'] ) : 1,
		);

		$data = $this->get_dashboard_data( $pagination );
		require PSYDOX_WP_STATS_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized request.', 'psydox-wp-stats' ) );
		}

		$settings = Psydox_WP_Stats::get_settings();
		$geo_db_status = $this->get_geo_db_status();
		require PSYDOX_WP_STATS_PATH . 'admin/views/settings.php';
	}

	/**
	 * Download geo database file for server-side country lookup.
	 *
	 * @param string $source Database source identifier.
	 * @return array<string,mixed>
	 */
	private function download_geo_database( $source ) {
		if ( 'maxmind_manual' === $source ) {
			return $this->upload_geo_database_file();
		}

		if ( 'dbip_lite' !== $source ) {
			return array(
				'success' => false,
				'message' => __( 'Unknown geo database source selected.', 'psydox-wp-stats' ),
			);
		}

		$year_month = gmdate( 'Y-m' );
		$download_url = 'https://download.db-ip.com/free/dbip-country-lite-' . $year_month . '.mmdb.gz';
		$upload_dir = wp_upload_dir();

		if ( empty( $upload_dir['basedir'] ) || ! is_dir( $upload_dir['basedir'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Uploads directory is not available.', 'psydox-wp-stats' ),
			);
		}

		$tmp_file = wp_tempnam( 'psydox_geo_db.mmdb.gz' );
		if ( ! $tmp_file ) {
			return array(
				'success' => false,
				'message' => __( 'Could not allocate temporary file for download.', 'psydox-wp-stats' ),
			);
		}

		$response = wp_remote_get(
			$download_url,
			array(
				'timeout' => 90,
				'stream'  => true,
				'filename' => $tmp_file,
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $tmp_file );
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s is error message from HTTP request. */
					__( 'Geo database download failed: %s', 'psydox-wp-stats' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			@unlink( $tmp_file );
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d is HTTP status code. */
					__( 'Geo database server returned HTTP %d.', 'psydox-wp-stats' ),
					$status_code
				),
			);
		}

		$destination = trailingslashit( $upload_dir['basedir'] ) . 'GeoLite2-Country.mmdb';
		$gunzip_result = $this->gunzip_file( $tmp_file, $destination );
		@unlink( $tmp_file );

		if ( ! $gunzip_result ) {
			return array(
				'success' => false,
				'message' => __( 'Downloaded file could not be decompressed.', 'psydox-wp-stats' ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s is destination path. */
				__( 'Geo database installed at %s', 'psydox-wp-stats' ),
				$destination
			),
		);
	}

	/**
	 * Upload a manually provided GeoLite2 country MMDB file.
	 *
	 * @return array<string,mixed>
	 */
	private function upload_geo_database_file() {
		if ( empty( $_FILES['geo_mmdb_file'] ) || ! is_array( $_FILES['geo_mmdb_file'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please choose a .mmdb file to upload.', 'psydox-wp-stats' ),
			);
		}

		$file = $_FILES['geo_mmdb_file'];
		if ( ! empty( $file['error'] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d is PHP upload error code. */
					__( 'Upload failed with error code %d.', 'psydox-wp-stats' ),
					(int) $file['error']
				),
			);
		}

		$filename = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( 'mmdb' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid file type. Please upload a .mmdb file.', 'psydox-wp-stats' ),
			);
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Temporary upload file was not found.', 'psydox-wp-stats' ),
			);
		}

		$upload_dir = wp_upload_dir();
		if ( empty( $upload_dir['basedir'] ) || ! is_dir( $upload_dir['basedir'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Uploads directory is not available.', 'psydox-wp-stats' ),
			);
		}

		$destination = trailingslashit( $upload_dir['basedir'] ) . 'GeoLite2-Country.mmdb';
		if ( ! @move_uploaded_file( $tmp_name, $destination ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not move uploaded file to uploads directory.', 'psydox-wp-stats' ),
			);
		}

		@chmod( $destination, 0644 );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s is destination path. */
				__( 'GeoLite2 database uploaded to %s', 'psydox-wp-stats' ),
				$destination
			),
		);
	}

	/**
	 * Decompress a GZip file to a destination path.
	 *
	 * @param string $source_gz Source .gz path.
	 * @param string $destination Destination file path.
	 * @return bool
	 */
	private function gunzip_file( $source_gz, $destination ) {
		$in = @gzopen( $source_gz, 'rb' );
		if ( false === $in ) {
			return false;
		}

		$out = @fopen( $destination, 'wb' );
		if ( false === $out ) {
			@gzclose( $in );
			return false;
		}

		$ok = true;
		while ( ! gzeof( $in ) ) {
			$chunk = gzread( $in, 8192 );
			if ( false === $chunk ) {
				$ok = false;
				break;
			}
			if ( false === fwrite( $out, $chunk ) ) {
				$ok = false;
				break;
			}
		}

		@gzclose( $in );
		@fclose( $out );

		if ( ! $ok ) {
			@unlink( $destination );
		}

		return $ok;
	}

	/**
	 * Get current geo database status for settings UI.
	 *
	 * @return array<string,mixed>
	 */
	private function get_geo_db_status() {
		$candidates = array(
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/uploads/GeoLite2-Country.mmdb' : '',
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/GeoLite2-Country.mmdb' : '',
			defined( 'PSYDOX_WP_STATS_PATH' ) ? PSYDOX_WP_STATS_PATH . 'data/GeoLite2-Country.mmdb' : '',
		);

		$filtered_path = apply_filters( 'psydox_wp_stats_mmdb_path', '' );
		if ( is_string( $filtered_path ) && '' !== trim( $filtered_path ) ) {
			array_unshift( $candidates, trim( $filtered_path ) );
		}

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate && file_exists( $candidate ) ) {
				return array(
					'found' => true,
					'path'  => $candidate,
					'size'  => (int) @filesize( $candidate ),
				);
			}
		}

		return array(
			'found' => false,
			'path'  => '',
			'size'  => 0,
		);
	}

	/**
	 * Render quick overview widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget_overview() {
		$data     = $this->get_dashboard_data();
		$overview = isset( $data['overview'] ) && is_array( $data['overview'] ) ? $data['overview'] : array();

		$today_trend = isset( $overview['today_vs_yesterday'] ) && is_array( $overview['today_vs_yesterday'] ) ? $overview['today_vs_yesterday'] : array();
		$week_trend  = isset( $overview['week_over_week'] ) && is_array( $overview['week_over_week'] ) ? $overview['week_over_week'] : array();

		echo '<ul style="margin:0;">';
		echo '<li><strong>' . esc_html__( 'Total Views:', 'psydox-wp-stats' ) . '</strong> ' . esc_html( number_format_i18n( isset( $overview['total_views'] ) ? (int) $overview['total_views'] : 0 ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Unique Visitors:', 'psydox-wp-stats' ) . '</strong> ' . esc_html( number_format_i18n( isset( $overview['unique_visitors'] ) ? (int) $overview['unique_visitors'] : 0 ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Views Today:', 'psydox-wp-stats' ) . '</strong> ' . esc_html( number_format_i18n( isset( $overview['views_today'] ) ? (int) $overview['views_today'] : 0 ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Active Visitors:', 'psydox-wp-stats' ) . '</strong> ' . esc_html( number_format_i18n( isset( $overview['active_visitors'] ) ? (int) $overview['active_visitors'] : 0 ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Today vs Yesterday:', 'psydox-wp-stats' ) . '</strong> ' . esc_html( sprintf( '%+.1f%%', isset( $today_trend['percent'] ) ? (float) $today_trend['percent'] : 0 ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Week over Week:', 'psydox-wp-stats' ) . '</strong> ' . esc_html( sprintf( '%+.1f%%', isset( $week_trend['percent'] ) ? (float) $week_trend['percent'] : 0 ) ) . '</li>';
		echo '</ul>';

		echo '<p style="margin:12px 0 0;">';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=psydox-wp-stats' ) ) . '">' . esc_html__( 'Open Full Dashboard', 'psydox-wp-stats' ) . '</a>';
		echo '</p>';
	}

	/**
	 * Render top content widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget_top_content() {
		$data          = $this->get_dashboard_data();
		$top_pages     = isset( $data['top_pages'] ) && is_array( $data['top_pages'] ) ? array_slice( $data['top_pages'], 0, 5 ) : array();
		$top_referrers = isset( $data['top_referrers'] ) && is_array( $data['top_referrers'] ) ? array_slice( $data['top_referrers'], 0, 5 ) : array();

		echo '<p><strong>' . esc_html__( 'Top Pages', 'psydox-wp-stats' ) . '</strong></p>';
		if ( empty( $top_pages ) ) {
			echo '<p>' . esc_html__( 'No page view data yet.', 'psydox-wp-stats' ) . '</p>';
		} else {
			echo '<ol style="margin-top:0;">';
			foreach ( $top_pages as $row ) {
				$title = isset( $row['page_title'] ) && '' !== (string) $row['page_title'] ? (string) $row['page_title'] : __( '(No title)', 'psydox-wp-stats' );
				echo '<li>' . esc_html( $title ) . ' <span style="color:#646970;">(' . esc_html( number_format_i18n( isset( $row['views'] ) ? (int) $row['views'] : 0 ) ) . ')</span></li>';
			}
			echo '</ol>';
		}

		echo '<p><strong>' . esc_html__( 'Top Referrers', 'psydox-wp-stats' ) . '</strong></p>';
		if ( empty( $top_referrers ) ) {
			echo '<p>' . esc_html__( 'No referrer data yet.', 'psydox-wp-stats' ) . '</p>';
		} else {
			echo '<ul style="margin-top:0;">';
			foreach ( $top_referrers as $row ) {
				echo '<li>' . esc_html( isset( $row['referrer'] ) ? (string) $row['referrer'] : '-' ) . ' <span style="color:#646970;">(' . esc_html( number_format_i18n( isset( $row['visits'] ) ? (int) $row['visits'] : 0 ) ) . ')</span></li>';
			}
			echo '</ul>';
		}
	}

	/**
	 * Check if menu slug already exists.
	 *
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	private function parent_menu_exists( $slug ) {
		global $menu;

		if ( empty( $menu ) || ! is_array( $menu ) ) {
			return false;
		}

		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build dashboard metrics.
	 *
	 * @return array<string,mixed>
	 */
	private function get_dashboard_data( array $pagination = array() ) {
		$pagination = wp_parse_args(
			$pagination,
			array(
				'top_pages_page'       => 1,
				'recent_visits_page'   => 1,
				'recent_crawlers_page' => 1,
			)
		);

		$top_pages_page       = max( 1, (int) $pagination['top_pages_page'] );
		$recent_visits_page   = max( 1, (int) $pagination['recent_visits_page'] );
		$recent_crawlers_page = max( 1, (int) $pagination['recent_crawlers_page'] );

		$cache_key   = self::get_dashboard_cache_key( $pagination );
		$cached_data = get_transient( $cache_key );
		if ( is_array( $cached_data ) ) {
			return $cached_data;
		}

		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();
		$settings   = Psydox_WP_Stats::get_settings();
		$top_pages_per_page = 10;
		$recent_per_page    = 20;
		$top_pages_offset   = ( $top_pages_page - 1 ) * $top_pages_per_page;
		$recent_visits_offset = ( $recent_visits_page - 1 ) * $recent_per_page;
		$recent_crawlers_offset = ( $recent_crawlers_page - 1 ) * $recent_per_page;

		$today = gmdate( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );

		$views_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE visit_date = %s", $today ) );
		$views_yesterday = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE visit_date = %s", $yesterday ) );
		$views_this_week = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE YEARWEEK(visit_date, 1) = YEARWEEK(UTC_DATE(), 1)" );
		$views_last_week = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE YEARWEEK(visit_date, 1) = YEARWEEK(DATE_SUB(UTC_DATE(), INTERVAL 1 WEEK), 1)" );

		$overview = array(
			'total_views'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ),
			'unique_visitors'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_hash) FROM {$table_name}" ),
			'views_today'      => $views_today,
			'views_yesterday'  => $views_yesterday,
			'views_this_week'  => $views_this_week,
			'views_last_week'  => $views_last_week,
			'views_this_month' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE DATE_FORMAT(visit_date, '%Y-%m') = DATE_FORMAT(UTC_DATE(), '%Y-%m')" ),
			'active_visitors'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_hash) FROM {$table_name} WHERE last_activity_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)" ),
			'total_crawlers'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE visitor_type = 'bot'" ),
			'today_vs_yesterday' => $this->build_trend_metric( $views_today, $views_yesterday ),
			'week_over_week'     => $this->build_trend_metric( $views_this_week, $views_last_week ),
		);

		$crawler_activity_summary = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total_bot_visits,
				COUNT(DISTINCT crawler_name) AS unique_crawlers,
				MAX(created_at) AS last_crawl_at
			FROM {$table_name}
			WHERE visitor_type = 'bot'",
			ARRAY_A
		);

		$current_hour_bot = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE visitor_type = 'bot' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)" );
		$previous_hour_bot = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE visitor_type = 'bot' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 120 MINUTE) AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)" );
		$current_hour_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)" );
		$baseline_avg_hour = (float) $wpdb->get_var( "SELECT COUNT(*) / 24 FROM {$table_name} WHERE visitor_type = 'bot' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR) AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 MINUTE)" );

		if ( $baseline_avg_hour <= 0 ) {
			$baseline_avg_hour = (float) $previous_hour_bot;
		}

		$pressure_score = $current_hour_total > 0 ? round( ( $current_hour_bot / $current_hour_total ) * 100, 1 ) : 0.0;
		$spike_ratio    = $baseline_avg_hour > 0 ? round( $current_hour_bot / $baseline_avg_hour, 2 ) : ( $current_hour_bot > 0 ? (float) $current_hour_bot : 0.0 );
		$spike_threshold = isset( $settings['spike_ratio_threshold'] ) ? (float) $settings['spike_ratio_threshold'] : 2.5;

		$bot_pressure = array(
			'current_hour_bot'   => $current_hour_bot,
			'previous_hour_bot'  => $previous_hour_bot,
			'current_hour_total' => $current_hour_total,
			'baseline_avg_hour'  => round( $baseline_avg_hour, 2 ),
			'pressure_score'     => $pressure_score,
			'spike_ratio'        => $spike_ratio,
			'spike_threshold'    => $spike_threshold,
			'is_spike'           => ( $current_hour_bot >= 8 && $spike_ratio >= $spike_threshold ),
			'pressure_level'     => $this->get_bot_pressure_level( $pressure_score ),
			'trend'              => $this->build_trend_metric( $current_hour_bot, $previous_hour_bot ),
		);

		$bot_classification_summary = $this->get_bot_classification_summary( 8 );

		$device_summary = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) AS desktop_visits,
				SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) AS mobile_visits,
				SUM(CASE WHEN device_type = 'tablet' THEN 1 ELSE 0 END) AS tablet_visits,
				SUM(CASE WHEN device_type = 'bot' THEN 1 ELSE 0 END) AS bot_visits,
				SUM(CASE WHEN device_type IS NULL OR device_type = '' OR device_type = 'unknown' THEN 1 ELSE 0 END) AS unknown_visits
			FROM {$table_name}",
			ARRAY_A
		);

		$top_pages_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT 1
				FROM {$table_name}
				GROUP BY page_url, page_title
			) AS page_groups"
		);

		$top_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_title, page_url, COUNT(*) AS views
			FROM {$table_name}
			GROUP BY page_url, page_title
			ORDER BY views DESC
			LIMIT %d OFFSET %d",
				$top_pages_per_page,
				$top_pages_offset
			),
			ARRAY_A
		);

		$top_page_row = $wpdb->get_row(
			"SELECT page_title, page_url, COUNT(*) AS views
			FROM {$table_name}
			WHERE page_url IS NOT NULL AND page_url <> ''
			GROUP BY page_url, page_title
			ORDER BY views DESC
			LIMIT 1",
			ARRAY_A
		);

		$tracked_urls = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT page_url) FROM {$table_name} WHERE page_url IS NOT NULL AND page_url <> ''" );
		$total_page_views = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE page_url IS NOT NULL AND page_url <> ''" );
		$avg_views_per_url = $tracked_urls > 0 ? round( $total_page_views / $tracked_urls, 1 ) : 0.0;

		$page_summary = array(
			'tracked_urls'      => $tracked_urls,
			'total_page_views'  => $total_page_views,
			'avg_views_per_url' => $avg_views_per_url,
			'top_page_title'    => isset( $top_page_row['page_title'] ) && '' !== (string) $top_page_row['page_title'] ? (string) $top_page_row['page_title'] : __( '(No title)', 'psydox-wp-stats' ),
			'top_page_url'      => isset( $top_page_row['page_url'] ) ? (string) $top_page_row['page_url'] : '',
			'top_page_views'    => isset( $top_page_row['views'] ) ? (int) $top_page_row['views'] : 0,
		);

		$post_page_breakdown = $this->get_post_page_breakdown( 10, 400 );

		$top_pages_7d = $wpdb->get_results(
			"SELECT page_title, page_url, COUNT(*) AS views
			FROM {$table_name}
			WHERE page_url IS NOT NULL AND page_url <> ''
				AND visit_date >= DATE_SUB(UTC_DATE(), INTERVAL 6 DAY)
			GROUP BY page_url, page_title
			ORDER BY views DESC
			LIMIT 10",
			ARRAY_A
		);

		$top_referrers = $wpdb->get_results(
			"SELECT referrer, COUNT(*) AS visits
			FROM {$table_name}
			WHERE referrer IS NOT NULL AND referrer <> ''
			GROUP BY referrer
			ORDER BY visits DESC
			LIMIT 10",
			ARRAY_A
		);

		$top_countries = $wpdb->get_results(
			"SELECT country_name, country_code, COUNT(*) AS visits
			FROM {$table_name}
			WHERE country_name IS NOT NULL AND country_name <> ''
			GROUP BY country_name, country_code
			ORDER BY visits DESC
			LIMIT 10",
			ARRAY_A
		);

		$top_country_row = $wpdb->get_row(
			"SELECT country_name, country_code, COUNT(*) AS visits
			FROM {$table_name}
			WHERE country_name IS NOT NULL AND country_name <> ''
			GROUP BY country_name, country_code
			ORDER BY visits DESC
			LIMIT 1",
			ARRAY_A
		);

		$world_summary = array(
			'total_countries_tracked' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT country_code) FROM {$table_name} WHERE country_code IS NOT NULL AND country_code <> '' AND country_code <> 'UN'" ),
			'top_country_name'        => isset( $top_country_row['country_name'] ) ? (string) $top_country_row['country_name'] : __( 'Unknown', 'psydox-wp-stats' ),
			'top_country_code'        => isset( $top_country_row['country_code'] ) ? (string) $top_country_row['country_code'] : 'UN',
			'top_country_visits'      => isset( $top_country_row['visits'] ) ? (int) $top_country_row['visits'] : 0,
		);

		$country_trends = $this->get_country_trends( 10 );

		$top_crawlers = $wpdb->get_results(
			"SELECT crawler_name, COUNT(*) AS visits, MAX(created_at) AS last_visit
			FROM {$table_name}
			WHERE visitor_type = 'bot'
			GROUP BY crawler_name
			ORDER BY visits DESC
			LIMIT 10",
			ARRAY_A
		);

		$most_crawled_pages = $wpdb->get_results(
			"SELECT page_url, COUNT(*) AS crawls
			FROM {$table_name}
			WHERE visitor_type = 'bot'
			GROUP BY page_url
			ORDER BY crawls DESC
			LIMIT 10",
			ARRAY_A
		);

		$recent_crawlers_total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$table_name}
			WHERE visitor_type = 'bot'"
		);

		$recent_crawler_visits = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT crawler_name, page_url, user_agent, ip_hash, created_at
			FROM {$table_name}
			WHERE visitor_type = 'bot'
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
				$recent_per_page,
				$recent_crawlers_offset
			),
			ARRAY_A
		);

		$recent_visits_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		$recent_visits = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT visitor_type, page_url, browser, device_type, operating_system, referrer, ip_hash, last_activity_at
			FROM {$table_name}
			ORDER BY last_activity_at DESC
			LIMIT %d OFFSET %d",
				$recent_per_page,
				$recent_visits_offset
			),
			ARRAY_A
		);

		$data = array(
			'overview'              => $overview,
			'crawler_activity_summary' => $crawler_activity_summary,
			'bot_pressure'          => $bot_pressure,
			'bot_classification_summary' => $bot_classification_summary,
			'device_summary'        => $device_summary,
			'world_summary'         => $world_summary,
			'page_summary'          => $page_summary,
			'post_page_breakdown'   => $post_page_breakdown,
			'top_pages'             => $top_pages,
			'top_pages_7d'          => $top_pages_7d,
			'top_referrers'         => $top_referrers,
			'top_countries'         => $top_countries,
			'country_trends'        => $country_trends,
			'top_crawlers'          => $top_crawlers,
			'most_crawled_pages'    => $most_crawled_pages,
			'recent_crawler_visits' => $recent_crawler_visits,
			'recent_visits'         => $recent_visits,
			'pagination'            => array(
				'top_pages' => array(
					'current'     => $top_pages_page,
					'total_items' => $top_pages_total,
					'total_pages' => max( 1, (int) ceil( $top_pages_total / $top_pages_per_page ) ),
					'query_key'   => 'top_pages_page',
				),
				'recent_visits' => array(
					'current'     => $recent_visits_page,
					'total_items' => $recent_visits_total,
					'total_pages' => max( 1, (int) ceil( $recent_visits_total / $recent_per_page ) ),
					'query_key'   => 'recent_visits_page',
				),
				'recent_crawlers' => array(
					'current'     => $recent_crawlers_page,
					'total_items' => $recent_crawlers_total,
					'total_pages' => max( 1, (int) ceil( $recent_crawlers_total / $recent_per_page ) ),
					'query_key'   => 'recent_crawlers_page',
				),
			),
		);

		set_transient( $cache_key, $data, MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Invalidate cached dashboard aggregates.
	 *
	 * @return void
	 */
	public static function invalidate_dashboard_cache() {
		delete_transient( self::get_dashboard_cache_key() );
	}

	/**
	 * Get per-site dashboard cache key.
	 *
	 * @return string
	 */
	private static function get_dashboard_cache_key( array $pagination = array() ) {
		$pagination = wp_parse_args(
			$pagination,
			array(
				'top_pages_page'       => 1,
				'recent_visits_page'   => 1,
				'recent_crawlers_page' => 1,
			)
		);

		return 'psydox_wp_stats_dashboard_data_' . get_current_blog_id() . '_' . md5( wp_json_encode( $pagination ) );
	}

	/**
	 * Determine whether debug-only features are enabled.
	 *
	 * @return bool
	 */
	public static function is_debug_mode_enabled() {
		return file_exists( PSYDOX_WP_STATS_PATH . '.debug' );
	}

	/**
	 * Get aggregate counts grouped by column.
	 *
	 * @param string $column Column name.
	 * @param string $condition Optional condition.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_group_counts( $column, $condition = '1=1' ) {
		global $wpdb;
		$allowed_columns = array( 'device_type', 'browser', 'operating_system', 'country_name', 'visitor_type', 'crawler_name' );
		if ( ! in_array( $column, $allowed_columns, true ) ) {
			return array();
		}

		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();

		return $wpdb->get_results(
			"SELECT {$column} AS label, COUNT(*) AS value
			FROM {$table_name}
			WHERE {$condition}
			GROUP BY {$column}
			ORDER BY value DESC
			LIMIT 20",
			ARRAY_A
		);
	}

	/**
	 * Get chart time series data.
	 *
	 * @param int    $count Number of periods.
	 * @param string $unit Unit (day/week/month).
	 * @return array<int,array<string,mixed>>
	 */
	private function get_time_series( $count, $unit ) {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();

		$count = max( 1, (int) $count );

		switch ( $unit ) {
			case 'week':
				$sql = "SELECT DATE_FORMAT(visit_date, '%x-W%v') AS label, COUNT(*) AS value
					FROM {$table_name}
					WHERE visit_date >= DATE_SUB(UTC_DATE(), INTERVAL {$count} WEEK)
					GROUP BY DATE_FORMAT(visit_date, '%x-W%v')
					ORDER BY label ASC";
				break;
			case 'month':
				$sql = "SELECT DATE_FORMAT(visit_date, '%Y-%m') AS label, COUNT(*) AS value
					FROM {$table_name}
					WHERE visit_date >= DATE_SUB(UTC_DATE(), INTERVAL {$count} MONTH)
					GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
					ORDER BY label ASC";
				break;
			case 'day':
			default:
				$sql = "SELECT DATE_FORMAT(visit_date, '%Y-%m-%d') AS label, COUNT(*) AS value
					FROM {$table_name}
					WHERE visit_date >= DATE_SUB(UTC_DATE(), INTERVAL {$count} DAY)
					GROUP BY DATE_FORMAT(visit_date, '%Y-%m-%d')
					ORDER BY label ASC";
				break;
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get mobile vs desktop comparison data.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_mobile_desktop_counts() {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();

		$counts = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) AS mobile,
				SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) AS desktop
			FROM {$table_name}",
			ARRAY_A
		);

		return array(
			array(
				'label' => 'Mobile',
				'value' => isset( $counts['mobile'] ) ? (int) $counts['mobile'] : 0,
			),
			array(
				'label' => 'Desktop',
				'value' => isset( $counts['desktop'] ) ? (int) $counts['desktop'] : 0,
			),
		);
	}

	/**
	 * Get device trend data for recent days.
	 *
	 * @param int $days Number of days.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_device_trends( $days ) {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();
		$days       = max( 1, (int) $days );

		return $wpdb->get_results(
			"SELECT CONCAT(DATE_FORMAT(visit_date, '%Y-%m-%d'), ' ', device_type) AS label, COUNT(*) AS value
			FROM {$table_name}
			WHERE visit_date >= DATE_SUB(UTC_DATE(), INTERVAL {$days} DAY)
			GROUP BY DATE_FORMAT(visit_date, '%Y-%m-%d'), device_type
			ORDER BY visit_date ASC, device_type ASC",
			ARRAY_A
		);
	}

	/**
	 * Get country trends for current 7 days vs previous 7 days.
	 *
	 * @param int $limit Number of countries to return.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_country_trends( $limit = 10 ) {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();
		$limit      = max( 1, (int) $limit );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT country_name, country_code,
					SUM(CASE WHEN visit_date >= DATE_SUB(UTC_DATE(), INTERVAL 6 DAY) THEN 1 ELSE 0 END) AS current_7d,
					SUM(CASE WHEN visit_date BETWEEN DATE_SUB(UTC_DATE(), INTERVAL 13 DAY) AND DATE_SUB(UTC_DATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS previous_7d
				FROM {$table_name}
				WHERE country_name IS NOT NULL AND country_name <> ''
					AND visit_date >= DATE_SUB(UTC_DATE(), INTERVAL 13 DAY)
				GROUP BY country_name, country_code
				HAVING current_7d > 0 OR previous_7d > 0
				ORDER BY current_7d DESC, previous_7d DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		$trends = array();
		foreach ( $rows as $row ) {
			$current  = isset( $row['current_7d'] ) ? (int) $row['current_7d'] : 0;
			$previous = isset( $row['previous_7d'] ) ? (int) $row['previous_7d'] : 0;
			$trend    = $this->build_trend_metric( $current, $previous );

			$trends[] = array(
				'country_name' => isset( $row['country_name'] ) ? (string) $row['country_name'] : '',
				'country_code' => isset( $row['country_code'] ) ? (string) $row['country_code'] : 'UN',
				'current_7d'   => $current,
				'previous_7d'  => $previous,
				'delta'        => isset( $trend['delta'] ) ? (int) $trend['delta'] : 0,
				'percent'      => isset( $trend['percent'] ) ? (float) $trend['percent'] : 0,
				'direction'    => isset( $trend['direction'] ) ? (string) $trend['direction'] : 'flat',
			);
		}

		return $trends;
	}

	/**
	 * Build post/page breakdown using URL to post resolution.
	 *
	 * @param int $limit Max rows per list.
	 * @param int $pool_size Number of URLs to inspect.
	 * @return array<string,mixed>
	 */
	private function get_post_page_breakdown( $limit = 10, $pool_size = 400 ) {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();
		$limit      = max( 1, (int) $limit );
		$pool_size  = max( $limit, (int) $pool_size );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_title, page_url, COUNT(*) AS views
				FROM {$table_name}
				WHERE page_url IS NOT NULL AND page_url <> ''
				GROUP BY page_url, page_title
				ORDER BY views DESC
				LIMIT %d",
				$pool_size
			),
			ARRAY_A
		);

		$posts = array();
		$pages = array();
		$post_views = 0;
		$page_views = 0;

		foreach ( $rows as $row ) {
			$url   = isset( $row['page_url'] ) ? esc_url_raw( (string) $row['page_url'] ) : '';
			$title = isset( $row['page_title'] ) && '' !== (string) $row['page_title'] ? (string) $row['page_title'] : __( '(No title)', 'psydox-wp-stats' );
			$views = isset( $row['views'] ) ? (int) $row['views'] : 0;

			if ( '' === $url ) {
				continue;
			}

			$post_id = url_to_postid( $url );
			if ( $post_id <= 0 ) {
				continue;
			}

			$type = get_post_type( $post_id );
			$item = array(
				'post_id'    => (int) $post_id,
				'post_title' => $title,
				'page_url'   => $url,
				'views'      => $views,
			);

			if ( 'post' === $type ) {
				$posts[] = $item;
				$post_views += $views;
			} elseif ( 'page' === $type ) {
				$pages[] = $item;
				$page_views += $views;
			}
		}

		return array(
			'top_posts'         => array_slice( $posts, 0, $limit ),
			'top_pages_only'    => array_slice( $pages, 0, $limit ),
			'post_count'        => count( $posts ),
			'page_count'        => count( $pages ),
			'post_views'        => $post_views,
			'page_views'        => $page_views,
		);
	}

	/**
	 * Get grouped bot classification summary.
	 *
	 * @param int $limit Number of categories to return.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_bot_classification_summary( $limit = 8 ) {
		global $wpdb;
		$database   = new Psydox_WP_Stats_Database();
		$table_name = $database->get_table_name();
		$rows       = $wpdb->get_results(
			"SELECT crawler_name, COUNT(*) AS visits
			FROM {$table_name}
			WHERE visitor_type = 'bot'
			GROUP BY crawler_name
			ORDER BY visits DESC",
			ARRAY_A
		);

		$total = 0;
		foreach ( $rows as $row ) {
			$total += isset( $row['visits'] ) ? (int) $row['visits'] : 0;
		}

		$categories = array();
		foreach ( $rows as $row ) {
			$crawler_name = isset( $row['crawler_name'] ) ? (string) $row['crawler_name'] : '';
			$visits       = isset( $row['visits'] ) ? (int) $row['visits'] : 0;
			$profile      = $this->classify_crawler_name( $crawler_name );

			if ( ! isset( $categories[ $profile['category'] ] ) ) {
				$categories[ $profile['category'] ] = array(
					'category' => $profile['category'],
					'risk'     => $profile['risk'],
					'visits'   => 0,
				);
			}

			$categories[ $profile['category'] ]['visits'] += $visits;
		}

		$summary = array_values( $categories );
		usort(
			$summary,
			static function ( $a, $b ) {
				$visits_a = isset( $a['visits'] ) ? (int) $a['visits'] : 0;
				$visits_b = isset( $b['visits'] ) ? (int) $b['visits'] : 0;
				if ( $visits_a === $visits_b ) {
					return 0;
				}

				return $visits_a > $visits_b ? -1 : 1;
			}
		);

		$summary = array_slice( $summary, 0, max( 1, (int) $limit ) );
		foreach ( $summary as &$item ) {
			$visits = isset( $item['visits'] ) ? (int) $item['visits'] : 0;
			$item['share'] = $total > 0 ? round( ( $visits / $total ) * 100, 1 ) : 0.0;
		}
		unset( $item );

		return $summary;
	}

	/**
	 * Classify crawler into category and risk bucket.
	 *
	 * @param string $crawler_name Crawler label from dataset.
	 * @return array<string,string>
	 */
	private function classify_crawler_name( $crawler_name ) {
		$normalized = strtolower( trim( (string) $crawler_name ) );

		if ( '' === $normalized || false !== strpos( $normalized, 'generic' ) || false !== strpos( $normalized, 'unknown' ) ) {
			return array(
				'category' => 'Generic Bot',
				'risk'     => 'high',
			);
		}

		if ( false !== strpos( $normalized, 'denied' ) ) {
			return array(
				'category' => 'Denied',
				'risk'     => 'high',
			);
		}

		if ( preg_match( '/googlebot|bingbot|duckduckbot|yandexbot|baiduspider|petalbot|slurp/', $normalized ) ) {
			return array(
				'category' => 'Search Engine',
				'risk'     => 'low',
			);
		}

		if ( preg_match( '/facebookexternalhit|linkedinbot|twitterbot/', $normalized ) ) {
			return array(
				'category' => 'Social Preview',
				'risk'     => 'low',
			);
		}

		if ( preg_match( '/ahrefs|semrush|mj12/', $normalized ) ) {
			return array(
				'category' => 'SEO Analyzer',
				'risk'     => 'medium',
			);
		}

		if ( preg_match( '/uptimerobot|pingdom|statuscake/', $normalized ) ) {
			return array(
				'category' => 'Monitoring',
				'risk'     => 'medium',
			);
		}

		if ( preg_match( '/gptbot|chatgpt|claudebot|bytespider/', $normalized ) ) {
			return array(
				'category' => 'AI Crawler',
				'risk'     => 'high',
			);
		}

		return array(
			'category' => 'Other Bot',
			'risk'     => 'medium',
		);
	}

	/**
	 * Convert pressure score into qualitative level.
	 *
	 * @param float $score Bot pressure score (0-100).
	 * @return string
	 */
	private function get_bot_pressure_level( $score ) {
		$score = max( 0.0, (float) $score );

		if ( $score >= 50 ) {
			return 'high';
		}

		if ( $score >= 20 ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Build trend delta and percentage metrics.
	 *
	 * @param int $current Current period value.
	 * @param int $previous Previous period value.
	 * @return array<string,mixed>
	 */
	private function build_trend_metric( $current, $previous ) {
		$current  = max( 0, (int) $current );
		$previous = max( 0, (int) $previous );
		$delta    = $current - $previous;

		if ( $previous > 0 ) {
			$percent = round( ( $delta / $previous ) * 100, 1 );
		} else {
			$percent = $current > 0 ? 100.0 : 0.0;
		}

		$direction = 'flat';
		if ( $delta > 0 ) {
			$direction = 'up';
		} elseif ( $delta < 0 ) {
			$direction = 'down';
		}

		return array(
			'current'   => $current,
			'previous'  => $previous,
			'delta'     => $delta,
			'percent'   => $percent,
			'direction' => $direction,
		);
	}
}
