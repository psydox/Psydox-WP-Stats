<?php
/**
 * Database layer.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Database {
	/**
	 * Database handler.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Get visits table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->wpdb->prefix . 'psydox_wp_stats_visits';
	}

	/**
	 * Create database table.
	 *
	 * @return void
	 */
	public function create_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $this->get_table_name();
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_url text NOT NULL,
			page_title text NULL,
			referrer text NULL,
			country_code varchar(10) NULL,
			country_name varchar(120) NULL,
			browser varchar(100) NULL,
			browser_version varchar(50) NULL,
			operating_system varchar(100) NULL,
			os_version varchar(50) NULL,
			device_type varchar(30) NULL,
			device_brand varchar(100) NULL,
			device_model varchar(150) NULL,
			visitor_type varchar(20) NOT NULL DEFAULT 'human',
			crawler_name varchar(120) NULL,
			user_agent text NULL,
			ip_hash varchar(255) NULL,
			session_hash varchar(255) NULL,
			visit_date date NOT NULL,
			last_activity_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY visitor_type (visitor_type),
			KEY crawler_name (crawler_name),
			KEY country_code (country_code),
			KEY country_name (country_name),
			KEY visit_date (visit_date),
			KEY created_at (created_at),
			KEY last_activity_at (last_activity_at),
			KEY session_hash (session_hash(191))
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Insert or update a tracked visit.
	 *
	 * @param array<string,string> $data Visit data.
	 * @return void
	 */
	public function insert_or_update_visit( array $data ) {
		$table_name = $this->get_table_name();

		$existing_id = 0;
		if ( ! empty( $data['session_hash'] ) && ! empty( $data['page_url'] ) ) {
			$existing_id = (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$table_name}
					WHERE session_hash = %s
					AND page_url = %s
					AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 SECOND)
					ORDER BY id DESC
					LIMIT 1",
					$data['session_hash'],
					$data['page_url']
				)
			);
		}

		if ( $existing_id > 0 ) {
			$this->wpdb->update(
				$table_name,
				array(
					'last_activity_at' => $data['last_activity_at'],
				),
				array(
					'id' => $existing_id,
				),
				array( '%s' ),
				array( '%d' )
			);
			return;
		}

		$this->wpdb->insert(
			$table_name,
			array(
				'page_url'         => $data['page_url'],
				'page_title'       => $data['page_title'],
				'referrer'         => $data['referrer'],
				'country_code'     => $data['country_code'],
				'country_name'     => $data['country_name'],
				'browser'          => $data['browser'],
				'browser_version'  => $data['browser_version'],
				'operating_system' => $data['operating_system'],
				'os_version'       => $data['os_version'],
				'device_type'      => $data['device_type'],
				'device_brand'     => $data['device_brand'],
				'device_model'     => $data['device_model'],
				'visitor_type'     => $data['visitor_type'],
				'crawler_name'     => $data['crawler_name'],
				'user_agent'       => $data['user_agent'],
				'ip_hash'          => $data['ip_hash'],
				'session_hash'     => $data['session_hash'],
				'visit_date'       => $data['visit_date'],
				'last_activity_at' => $data['last_activity_at'],
				'created_at'       => $data['created_at'],
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
			)
		);
	}

	/**
	 * Purge data older than given retention.
	 *
	 * @param int $days Retention in days.
	 * @return int
	 */
	public function purge_older_than_days( $days ) {
		$table_name = $this->get_table_name();
		$days       = max( 1, (int) $days );

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Delete all rows.
	 *
	 * @return void
	 */
	public function clear_all() {
		$this->wpdb->query( 'TRUNCATE TABLE ' . $this->get_table_name() );
	}

	/**
	 * Optimize table.
	 *
	 * @return void
	 */
	public function optimize() {
		$this->wpdb->query( 'OPTIMIZE TABLE ' . $this->get_table_name() );
	}

	/**
	 * Generate sample test data rows.
	 *
	 * @param int $rows Number of rows to create.
	 * @return int
	 */
	public function generate_test_data( $rows = 100 ) {
		$rows       = max( 1, min( 5000, (int) $rows ) );
		$table_name = $this->get_table_name();

		$pages = array(
			array( 'title' => 'Home', 'url' => home_url( '/' ) ),
			array( 'title' => 'Blog', 'url' => home_url( '/blog/' ) ),
			array( 'title' => 'About', 'url' => home_url( '/about/' ) ),
			array( 'title' => 'Contact', 'url' => home_url( '/contact/' ) ),
			array( 'title' => 'Pricing', 'url' => home_url( '/pricing/' ) ),
		);

		$referrers = array(
			'',
			'https://google.com',
			'https://bing.com',
			'https://duckduckgo.com',
			'https://facebook.com',
			'https://twitter.com',
		);

		$browsers = array( 'Chrome', 'Firefox', 'Safari', 'Edge' );
		$oss      = array( 'Windows', 'macOS', 'Linux', 'Android', 'iOS' );
		$devices  = array( 'desktop', 'mobile', 'tablet' );
		$crawlers = array( 'Googlebot', 'Bingbot', 'DuckDuckBot', 'YandexBot', 'AhrefsBot' );
		$countries = array(
			array( 'code' => 'US', 'name' => 'United States' ),
			array( 'code' => 'GB', 'name' => 'United Kingdom' ),
			array( 'code' => 'CA', 'name' => 'Canada' ),
			array( 'code' => 'DE', 'name' => 'Germany' ),
			array( 'code' => 'FR', 'name' => 'France' ),
			array( 'code' => 'IN', 'name' => 'India' ),
			array( 'code' => 'AU', 'name' => 'Australia' ),
			array( 'code' => 'BR', 'name' => 'Brazil' ),
			array( 'code' => 'JP', 'name' => 'Japan' ),
			array( 'code' => 'PH', 'name' => 'Philippines' ),
		);

		$inserted = 0;

		for ( $i = 0; $i < $rows; $i++ ) {
			$page              = $pages[ array_rand( $pages ) ];
			$is_bot            = wp_rand( 1, 100 ) <= 18;
			$visitor_type      = $is_bot ? 'bot' : 'human';
			$crawler_name      = $is_bot ? $crawlers[ array_rand( $crawlers ) ] : null;
			$browser           = $is_bot ? $crawler_name : $browsers[ array_rand( $browsers ) ];
			$browser_version   = (string) wp_rand( 80, 130 ) . '.' . (string) wp_rand( 0, 9 );
			$operating_system  = $oss[ array_rand( $oss ) ];
			$os_version        = (string) wp_rand( 1, 15 ) . '.' . (string) wp_rand( 0, 9 );
			$device_type       = $is_bot ? 'bot' : $devices[ array_rand( $devices ) ];
			$device_brand      = 'Unknown';
			$device_model      = 'Unknown';
			$country           = $countries[ array_rand( $countries ) ];
			$referrer          = $referrers[ array_rand( $referrers ) ];
			$ip_hash           = hash_hmac( 'sha256', '127.0.0.' . (string) wp_rand( 1, 254 ), wp_salt( 'auth' ) );
			$session_hash      = hash_hmac( 'sha256', uniqid( 'psydox_', true ) . (string) wp_rand(), wp_salt( 'logged_in' ) );
			$days_ago          = wp_rand( 0, 60 );
			$seconds_in_day    = wp_rand( 0, DAY_IN_SECONDS - 1 );
			$timestamp         = time() - ( $days_ago * DAY_IN_SECONDS ) - $seconds_in_day;
			$created_at        = gmdate( 'Y-m-d H:i:s', $timestamp );
			$last_activity_at  = gmdate( 'Y-m-d H:i:s', $timestamp + wp_rand( 0, 300 ) );
			$visit_date        = gmdate( 'Y-m-d', $timestamp );
			$user_agent        = $is_bot ? strtolower( $crawler_name ) . '/2.1 (+https://example.com/bot)' : 'Mozilla/5.0 (Test Agent)';

			$result = $this->wpdb->insert(
				$table_name,
				array(
					'page_url'         => $page['url'],
					'page_title'       => $page['title'],
					'referrer'         => $referrer,
					'country_code'     => $country['code'],
					'country_name'     => $country['name'],
					'browser'          => $browser,
					'browser_version'  => $browser_version,
					'operating_system' => $operating_system,
					'os_version'       => $os_version,
					'device_type'      => $device_type,
					'device_brand'     => $device_brand,
					'device_model'     => $device_model,
					'visitor_type'     => $visitor_type,
					'crawler_name'     => $crawler_name,
					'user_agent'       => $user_agent,
					'ip_hash'          => $ip_hash,
					'session_hash'     => $session_hash,
					'visit_date'       => $visit_date,
					'last_activity_at' => $last_activity_at,
					'created_at'       => $created_at,
				),
				array(
					'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
					'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				)
			);

			if ( false !== $result ) {
				$inserted++;
			}
		}

		return $inserted;
	}
}
