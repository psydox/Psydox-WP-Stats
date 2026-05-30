<?php
/**
 * Country detection helper.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Country {
	/**
	 * Detect visitor country locally from server headers.
	 *
	 * @return array<string,string>
	 */
	public function detect() {
		$code = $this->detect_country_code();

		if ( empty( $code ) ) {
			return array(
				'country_code' => 'UN',
				'country_name' => 'Unknown',
			);
		}

		return array(
			'country_code' => $code,
			'country_name' => $this->country_name_from_code( $code ),
		);
	}

	/**
	 * Detect country code from known local server headers.
	 *
	 * @return string
	 */
	private function detect_country_code() {
		$header_keys = array(
			'HTTP_CF_IPCOUNTRY',
			'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
			'HTTP_X_GEO_COUNTRY',
			'HTTP_X_COUNTRY',
			'GEOIP_COUNTRY_CODE',
			'HTTP_GEOIP_COUNTRY_CODE',
			'HTTP_X_COUNTRY_CODE',
			'HTTP_X_APPENGINE_COUNTRY',
		);

		foreach ( $header_keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			$code = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) );
			$code = $this->normalize_country_code( $code );
			if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
				return $code;
			}
		}

		$ip_code = $this->detect_country_code_from_ip();
		if ( '' !== $ip_code ) {
			return $ip_code;
		}

		return '';
	}

	/**
	 * Detect country code from visitor IP using local/provider-backed integrations.
	 *
	 * @return string
	 */
	private function detect_country_code_from_ip() {
		$ip = $this->get_visitor_ip();
		if ( '' === $ip ) {
			return '';
		}

		// Allow sites/integrations to provide a country code from IP without editing plugin core.
		$filtered_code = apply_filters( 'psydox_wp_stats_country_code_from_ip', '', $ip );
		if ( is_string( $filtered_code ) && '' !== trim( $filtered_code ) ) {
			$normalized = $this->normalize_country_code( strtoupper( trim( $filtered_code ) ) );
			if ( preg_match( '/^[A-Z]{2}$/', $normalized ) ) {
				return $normalized;
			}
		}

		// Use PHP GeoIP extension when available (local DB-based, no external HTTP request).
		if ( function_exists( 'geoip_country_code_by_name' ) ) {
			$code = geoip_country_code_by_name( $ip );
			if ( is_string( $code ) && '' !== trim( $code ) ) {
				$normalized = $this->normalize_country_code( strtoupper( trim( $code ) ) );
				if ( $this->is_valid_country_code( $normalized ) ) {
					return $normalized;
				}
			}
		}

		$mmdb_code = $this->detect_country_code_from_mmdb( $ip );
		if ( '' !== $mmdb_code ) {
			return $mmdb_code;
		}

		return '';
	}

	/**
	 * Detect country code from a local MaxMind MMDB database.
	 *
	 * Supports:
	 * - GeoIP2 PHP library (if present in host/vendor autoload)
	 * - PECL maxminddb extension functions
	 *
	 * @param string $ip Public visitor IP.
	 * @return string
	 */
	private function detect_country_code_from_mmdb( $ip ) {
		$mmdb_path = $this->get_mmdb_path();
		if ( '' === $mmdb_path || ! file_exists( $mmdb_path ) || ! is_readable( $mmdb_path ) ) {
			return '';
		}

		// Preferred: GeoIP2 PHP library.
		if ( class_exists( '\\GeoIp2\\Database\\Reader' ) ) {
			try {
				$reader = new \GeoIp2\Database\Reader( $mmdb_path );
				$record = $reader->country( $ip );
				$reader->close();

				if ( isset( $record->country->isoCode ) && is_string( $record->country->isoCode ) ) {
					$code = $this->normalize_country_code( strtoupper( trim( $record->country->isoCode ) ) );
					if ( $this->is_valid_country_code( $code ) ) {
						return $code;
					}
				}
			} catch ( \Exception $exception ) {
				// Fall through to extension path below.
			}
		}

		// Fallback: PECL maxminddb extension.
		if ( function_exists( 'maxminddb_open' ) && function_exists( 'maxminddb_get' ) && function_exists( 'maxminddb_close' ) ) {
			$handle = @maxminddb_open( $mmdb_path );
			if ( $handle ) {
				$result = @maxminddb_get( $handle, $ip );
				@maxminddb_close( $handle );

				if ( is_array( $result ) && isset( $result['country']['iso_code'] ) ) {
					$raw_code = $result['country']['iso_code'];
					if ( is_string( $raw_code ) ) {
						$code = $this->normalize_country_code( strtoupper( trim( $raw_code ) ) );
						if ( $this->is_valid_country_code( $code ) ) {
							return $code;
						}
					}
				}
			}
		}

		return '';
	}

	/**
	 * Resolve local MMDB file path.
	 *
	 * @return string
	 */
	private function get_mmdb_path() {
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
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * Resolve visitor IP from common proxy headers.
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

				if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $candidate;
				}
			}
		}

		return '';
	}

	/**
	 * Normalize non-standard country codes returned by some providers.
	 *
	 * @param string $code Country code.
	 * @return string
	 */
	private function normalize_country_code( $code ) {
		$map = array(
			'UK' => 'GB',
			'EL' => 'GR',
		);

		return isset( $map[ $code ] ) ? $map[ $code ] : $code;
	}

	/**
	 * Validate ISO alpha-2 country code format.
	 *
	 * @param string $code Country code.
	 * @return bool
	 */
	private function is_valid_country_code( $code ) {
		return is_string( $code ) && (bool) preg_match( '/^[A-Z]{2}$/', $code );
	}

	/**
	 * Map ISO country code to readable country name.
	 *
	 * @param string $code ISO alpha-2 code.
	 * @return string
	 */
	private function country_name_from_code( $code ) {
		$map = array(
			'AF' => 'Afghanistan',
			'AX' => 'Aland Islands',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua and Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BQ' => 'Bonaire, Sint Eustatius and Saba',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'CV' => 'Cabo Verde',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo (Democratic Republic)',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => 'Cote d\'Ivoire',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CW' => 'Curacao',
			'CY' => 'Cyprus',
			'CZ' => 'Czechia',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'SZ' => 'Eswatini',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island and McDonald Islands',
			'VA' => 'Holy See',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KP' => 'North Korea',
			'KR' => 'South Korea',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Laos',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macao',
			'MK' => 'North Macedonia',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestine',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russia',
			'RW' => 'Rwanda',
			'BL' => 'Saint Barthelemy',
			'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
			'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia',
			'MF' => 'Saint Martin',
			'PM' => 'Saint Pierre and Miquelon',
			'VC' => 'Saint Vincent and the Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome and Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SX' => 'Sint Maarten',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia and the South Sandwich Islands',
			'SS' => 'South Sudan',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard and Jan Mayen',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syria',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TL' => 'Timor-Leste',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks and Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UM' => 'United States Minor Outlying Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'VG' => 'Virgin Islands (British)',
			'VI' => 'Virgin Islands (U.S.)',
			'WF' => 'Wallis and Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);

		return isset( $map[ $code ] ) ? $map[ $code ] : $code;
	}
}
