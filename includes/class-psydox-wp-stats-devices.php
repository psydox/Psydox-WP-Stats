<?php
/**
 * Device and platform detection.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Devices {
	/**
	 * Parse user agent into browser, OS, and device metadata.
	 *
	 * @param string $user_agent User agent string.
	 * @return array<string,string>
	 */
	public function parse( $user_agent ) {
		$user_agent = (string) $user_agent;

		$browser = $this->detect_browser( $user_agent );
		$os      = $this->detect_os( $user_agent );
		$device  = $this->detect_device( $user_agent );

		return array(
			'browser'          => $browser['name'],
			'browser_version'  => $browser['version'],
			'operating_system' => $os['name'],
			'os_version'       => $os['version'],
			'device_type'      => $device['type'],
			'device_brand'     => $device['brand'],
			'device_model'     => $device['model'],
		);
	}

	/**
	 * Detect browser and version.
	 *
	 * @param string $user_agent User agent.
	 * @return array<string,string>
	 */
	private function detect_browser( $user_agent ) {
		$map = array(
			'Googlebot'        => 'Googlebot',
			'Bingbot'          => 'Bingbot',
			'DuckDuckBot'      => 'DuckDuckBot',
			'YandexBot'        => 'YandexBot',
			'facebookexternalhit' => 'Facebook Crawler',
			'SamsungBrowser'   => 'Samsung Internet',
			'CriOS'            => 'Chrome',
			'FxiOS'            => 'Firefox',
			'Edg'       => 'Edge',
			'OPR'       => 'Opera',
			'Chrome'    => 'Chrome',
			'Firefox'   => 'Firefox',
			'Safari'    => 'Safari',
			'MSIE'      => 'Internet Explorer',
			'Trident/7.0' => 'Internet Explorer',
		);

		foreach ( $map as $token => $name ) {
			if ( false !== stripos( $user_agent, $token ) ) {
				$version = $this->extract_version( $user_agent, $token );
				if ( 'Safari' === $name && false !== stripos( $user_agent, 'Version/' ) ) {
					$version = $this->extract_version( $user_agent, 'Version' );
				}

				return array(
					'name'    => $name,
					'version' => $version,
				);
			}
		}

		return array(
			'name'    => 'Unknown',
			'version' => 'Unknown',
		);
	}

	/**
	 * Detect operating system and version.
	 *
	 * @param string $user_agent User agent.
	 * @return array<string,string>
	 */
	private function detect_os( $user_agent ) {
		if ( preg_match( '/Windows NT ([0-9\.]+)/i', $user_agent, $matches ) ) {
			$version_map = array(
				'10.0' => '10',
				'6.3'  => '8.1',
				'6.2'  => '8',
				'6.1'  => '7',
				'6.0'  => 'Vista',
				'5.1'  => 'XP',
			);

			return array(
				'name'    => 'Windows',
				'version' => isset( $version_map[ $matches[1] ] ) ? $version_map[ $matches[1] ] : $matches[1],
			);
		}

		if ( preg_match( '/Android ([0-9\\.]+)/i', $user_agent, $matches ) ) {
			return array(
				'name'    => 'Android',
				'version' => $matches[1],
			);
		}

		if ( preg_match( '/iPhone OS ([0-9_]+)/i', $user_agent, $matches ) ) {
			return array(
				'name'    => 'iOS',
				'version' => str_replace( '_', '.', $matches[1] ),
			);
		}

		if ( preg_match( '/iPad; CPU OS ([0-9_]+)/i', $user_agent, $matches ) ) {
			return array(
				'name'    => 'iPadOS',
				'version' => str_replace( '_', '.', $matches[1] ),
			);
		}

		if ( preg_match( '/CPU (?:iPhone )?OS ([0-9_]+)/i', $user_agent, $matches ) ) {
			return array(
				'name'    => 'iOS',
				'version' => str_replace( '_', '.', $matches[1] ),
			);
		}

		if ( false !== stripos( $user_agent, 'Macintosh' ) && false !== stripos( $user_agent, 'Mobile/' ) && false !== stripos( $user_agent, 'Safari/' ) ) {
			return array(
				'name'    => 'iPadOS',
				'version' => 'Unknown',
			);
		}

		if ( preg_match( '/Mac OS X ([0-9_]+)/i', $user_agent, $matches ) ) {
			return array(
				'name'    => 'macOS',
				'version' => str_replace( '_', '.', $matches[1] ),
			);
		}

		if ( preg_match( '/Linux/i', $user_agent ) ) {
			return array(
				'name'    => 'Linux',
				'version' => 'Unknown',
			);
		}

		return array(
			'name'    => 'Unknown',
			'version' => 'Unknown',
		);
	}

	/**
	 * Detect coarse device metadata.
	 *
	 * @param string $user_agent User agent.
	 * @return array<string,string>
	 */
	private function detect_device( $user_agent ) {
		$type  = 'desktop';
		$brand = 'Unknown';
		$model = 'Unknown';

		if ( preg_match( '/bot|spider|crawler/i', $user_agent ) ) {
			return array(
				'type'  => 'bot',
				'brand' => $brand,
				'model' => $model,
			);
		}

		if ( preg_match( '/tablet|ipad|kindle|silk/i', $user_agent ) ) {
			$type = 'tablet';
		} elseif ( preg_match( '/android/i', $user_agent ) && false === stripos( $user_agent, 'mobile' ) ) {
			$type = 'tablet';
		} elseif ( preg_match( '/mobile|iphone|android/i', $user_agent ) ) {
			$type = 'mobile';
		}

		$brands = array(
			'Apple'   => '/iPhone|iPad|Macintosh/i',
			'Samsung' => '/Samsung|SM-/i',
			'Google'  => '/Pixel/i',
			'Huawei'  => '/Huawei|Honor/i',
			'Xiaomi'  => '/Xiaomi|Redmi|Mi\s/i',
			'OnePlus' => '/OnePlus/i',
			'Oppo'    => '/OPPO|CPH[0-9]+/i',
			'Vivo'    => '/Vivo|V[0-9]{4}/i',
			'Motorola' => '/Moto|Motorola/i',
			'Nokia'   => '/Nokia|TA-[0-9]+/i',
			'Sony'    => '/Sony|XQ-|SO-[0-9A-Z]+/i',
			'Lenovo'  => '/Lenovo|TB-[0-9A-Z]+/i',
			'ASUS'    => '/ASUS|Zenfone/i',
			'Amazon'  => '/Kindle|Silk|KF[A-Z]/i',
		);

		foreach ( $brands as $candidate => $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				$brand = $candidate;
				break;
			}
		}

		if ( preg_match( '/\((?:Linux; )?Android [^;]+; ([^;\)]+)/i', $user_agent, $matches ) ) {
			$model = trim( $matches[1] );
		} elseif ( preg_match( '/\((?:iPhone|iPad)[^\)]*\)/i', $user_agent, $matches ) ) {
			$model = false !== stripos( $matches[0], 'iPad' ) ? 'iPad' : 'iPhone';
		} elseif ( preg_match( '/(SM-[A-Z0-9]+)/i', $user_agent, $matches ) ) {
			$model = strtoupper( $matches[1] );
		} elseif ( preg_match( '/(Pixel\s[0-9A-Za-z\s]+)/i', $user_agent, $matches ) ) {
			$model = trim( $matches[1] );
		}

		return array(
			'type'  => $type,
			'brand' => $brand,
			'model' => $model,
		);
	}

	/**
	 * Extract token version from UA.
	 *
	 * @param string $user_agent User agent.
	 * @param string $token Token.
	 * @return string
	 */
	private function extract_version( $user_agent, $token ) {
		$token = preg_quote( $token, '/' );
		if ( preg_match( '/(?:' . $token . ')[\/: ]([0-9\\.]+)/i', $user_agent, $matches ) ) {
			return $matches[1];
		}

		return 'Unknown';
	}
}
