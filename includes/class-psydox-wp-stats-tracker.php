<?php
/**
 * Frontend tracking service.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Tracker {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'track_visit' ), 1 );
	}

	/**
	 * Track a frontend visit.
	 *
	 * @return void
	 */
	public function track_visit() {
		if ( ! $this->should_track() ) {
			return;
		}

		$settings = Psydox_WP_Stats::get_settings();
		if ( empty( $settings['tracking_enabled'] ) ) {
			return;
		}

		$database = new Psydox_WP_Stats_Database();
		$crawlers = new Psydox_WP_Stats_Crawlers();
		$devices  = new Psydox_WP_Stats_Devices();
		$country  = new Psydox_WP_Stats_Country();

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$crawler    = $crawlers->detect( $user_agent, $settings );

		if ( ! empty( $crawler['is_denied'] ) && ! empty( $settings['block_denied_bots'] ) ) {
			return;
		}

		$device     = $devices->parse( $user_agent );
		$geo        = $country->detect();
		$referrer   = $this->get_referrer( $settings );
		$ip_hash    = $this->get_ip_hash( $settings );
		$session    = $this->get_session_hash( $ip_hash, $user_agent );
		$url        = $this->get_current_url();
		$timestamp  = gmdate( 'Y-m-d H:i:s' );

		$data = array(
			'page_url'         => $url,
			'page_title'       => wp_strip_all_tags( wp_get_document_title() ),
			'referrer'         => $referrer,
			'country_code'     => $geo['country_code'],
			'country_name'     => $geo['country_name'],
			'browser'          => ! empty( $settings['store_browser'] ) ? $device['browser'] : 'Unknown',
			'browser_version'  => ! empty( $settings['store_browser'] ) ? $device['browser_version'] : 'Unknown',
			'operating_system' => ! empty( $settings['store_device'] ) ? $device['operating_system'] : 'Unknown',
			'os_version'       => ! empty( $settings['store_device'] ) ? $device['os_version'] : 'Unknown',
			'device_type'      => ! empty( $settings['store_device'] ) ? $device['device_type'] : 'unknown',
			'device_brand'     => ! empty( $settings['store_device'] ) ? $device['device_brand'] : 'Unknown',
			'device_model'     => ! empty( $settings['store_device'] ) ? $device['device_model'] : 'Unknown',
			'visitor_type'     => ! empty( $crawler['is_crawler'] ) ? 'bot' : 'human',
			'crawler_name'     => ! empty( $crawler['is_crawler'] ) ? $crawler['crawler_name'] : null,
			'user_agent'       => $user_agent,
			'ip_hash'          => $ip_hash,
			'session_hash'     => $session,
			'visit_date'       => gmdate( 'Y-m-d' ),
			'last_activity_at' => $timestamp,
			'created_at'       => $timestamp,
		);

		$database->insert_or_update_visit( $data );
		Psydox_WP_Stats_Admin::invalidate_dashboard_cache();
	}

	/**
	 * Check if request should be tracked.
	 *
	 * @return bool
	 */
	private function should_track() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/wp-admin/' ) ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( isset( $_REQUEST['action'] ) && 'heartbeat' === sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) ) {
			return false;
		}

		$settings = Psydox_WP_Stats::get_settings();

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! empty( $settings['exclude_logged_in'] ) ) {
				return false;
			}

			if ( in_array( 'administrator', (array) $user->roles, true ) && ! empty( $settings['exclude_administrators'] ) ) {
				return false;
			}

			if ( in_array( 'editor', (array) $user->roles, true ) && ! empty( $settings['exclude_editors'] ) ) {
				return false;
			}

			if ( in_array( 'author', (array) $user->roles, true ) && ! empty( $settings['exclude_authors'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get current URL.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return esc_url_raw( home_url( $uri ) );
	}

	/**
	 * Get sanitized referrer.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string|null
	 */
	private function get_referrer( array $settings ) {
		if ( empty( $settings['store_referrer'] ) ) {
			return null;
		}

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		if ( empty( $referrer ) ) {
			return null;
		}

		return esc_url_raw( $referrer );
	}

	/**
	 * Hash IP based on settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string|null
	 */
	private function get_ip_hash( array $settings ) {
		$ip = $this->get_visitor_ip();
		if ( empty( $ip ) ) {
			return null;
		}

		if ( empty( $settings['hash_ip'] ) ) {
			return $ip;
		}

		$site_salt = wp_salt( 'auth' );
		return hash_hmac( 'sha256', $ip, $site_salt );
	}

	/**
	 * Resolve visitor IP from common proxy/CDN headers.
	 *
	 * @return string
	 */
	private function get_visitor_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$raw_value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
			$parts     = array_map( 'trim', explode( ',', $raw_value ) );

			foreach ( $parts as $candidate ) {
				if ( '' === $candidate ) {
					continue;
				}

				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					return $candidate;
				}
			}
		}

		return '';
	}

	/**
	 * Get stable session hash.
	 *
	 * @param string|null $ip_hash IP hash.
	 * @param string      $user_agent User agent.
	 * @return string
	 */
	private function get_session_hash( $ip_hash, $user_agent ) {
		$cookie_name = 'psydox_wp_stats_session';
		if ( isset( $_COOKIE[ $cookie_name ] ) && ! empty( $_COOKIE[ $cookie_name ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		}

		$seed    = $ip_hash . '|' . $user_agent . '|' . wp_rand( 1000, 999999 );
		$session = hash_hmac( 'sha256', $seed, wp_salt( 'logged_in' ) );

		setcookie( $cookie_name, $session, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		return $session;
	}
}
