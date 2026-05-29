<?php
/**
 * Crawler detection.
 *
 * @package PsydoxWPStats
 */

namespace Psydox\WPStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Psydox_WP_Stats_Crawlers {
	/**
	 * Crawler signatures.
	 *
	 * @var array<int,array<string,string>>
	 */
	private $signatures = array(
		array(
			'needle'   => 'googlebot',
			'name'     => 'Googlebot',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'bingbot',
			'name'     => 'Bingbot',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'duckduckbot',
			'name'     => 'DuckDuckBot',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'yandexbot',
			'name'     => 'YandexBot',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'baiduspider',
			'name'     => 'Baiduspider',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'petalbot',
			'name'     => 'PetalBot',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'slurp',
			'name'     => 'Yahoo Slurp',
			'category' => 'Search Engine',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'facebookexternalhit',
			'name'     => 'Facebook External Hit',
			'category' => 'Social Preview',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'linkedinbot',
			'name'     => 'LinkedInBot',
			'category' => 'Social Preview',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'twitterbot',
			'name'     => 'TwitterBot',
			'category' => 'Social Preview',
			'risk'     => 'low',
		),
		array(
			'needle'   => 'ahrefsbot',
			'name'     => 'AhrefsBot',
			'category' => 'SEO Analyzer',
			'risk'     => 'medium',
		),
		array(
			'needle'   => 'semrushbot',
			'name'     => 'SemrushBot',
			'category' => 'SEO Analyzer',
			'risk'     => 'medium',
		),
		array(
			'needle'   => 'mj12bot',
			'name'     => 'MJ12bot',
			'category' => 'SEO Analyzer',
			'risk'     => 'medium',
		),
		array(
			'needle'   => 'uptimerobot',
			'name'     => 'UptimeRobot',
			'category' => 'Monitoring',
			'risk'     => 'medium',
		),
		array(
			'needle'   => 'pingdom',
			'name'     => 'Pingdom',
			'category' => 'Monitoring',
			'risk'     => 'medium',
		),
		array(
			'needle'   => 'statuscake',
			'name'     => 'StatusCake',
			'category' => 'Monitoring',
			'risk'     => 'medium',
		),
		array(
			'needle'   => 'gptbot',
			'name'     => 'GPTBot',
			'category' => 'AI Crawler',
			'risk'     => 'high',
		),
		array(
			'needle'   => 'chatgpt-user',
			'name'     => 'ChatGPT-User',
			'category' => 'AI Crawler',
			'risk'     => 'high',
		),
		array(
			'needle'   => 'claudebot',
			'name'     => 'ClaudeBot',
			'category' => 'AI Crawler',
			'risk'     => 'high',
		),
		array(
			'needle'   => 'bytespider',
			'name'     => 'ByteSpider',
			'category' => 'AI Crawler',
			'risk'     => 'high',
		),
		array(
			'needle'   => 'crawler',
			'name'     => 'Generic Crawler',
			'category' => 'Generic Bot',
			'risk'     => 'high',
		),
		array(
			'needle'   => 'spider',
			'name'     => 'Generic Spider',
			'category' => 'Generic Bot',
			'risk'     => 'high',
		),
		array(
			'needle'   => 'bot',
			'name'     => 'Generic Bot',
			'category' => 'Generic Bot',
			'risk'     => 'high',
		),
	);

	/**
	 * Detect crawler from user agent.
	 *
	 * @param string              $user_agent Raw user agent.
	 * @param array<string,mixed> $settings Settings for allow/deny patterns.
	 * @return array<string,mixed>
	 */
	public function detect( $user_agent, array $settings = array() ) {
		$user_agent = strtolower( (string) $user_agent );

		$allow_patterns = $this->parse_patterns( isset( $settings['bot_allowlist'] ) ? (string) $settings['bot_allowlist'] : '' );
		$deny_patterns  = $this->parse_patterns( isset( $settings['bot_denylist'] ) ? (string) $settings['bot_denylist'] : '' );

		$is_allowed = $this->matches_patterns( $user_agent, $allow_patterns );
		$is_denied  = ! $is_allowed && $this->matches_patterns( $user_agent, $deny_patterns );

		if ( $is_denied ) {
			return array(
				'is_crawler'            => true,
				'crawler_name'          => 'Denied Bot',
				'crawler_category'      => 'Denied',
				'crawler_risk'          => 'high',
				'is_allowed'            => false,
				'is_denied'             => true,
				'classification_source' => 'custom_deny',
			);
		}

		foreach ( $this->signatures as $signature ) {
			if ( false !== strpos( $user_agent, $signature['needle'] ) ) {
				return array(
					'is_crawler'            => true,
					'crawler_name'          => $signature['name'],
					'crawler_category'      => $signature['category'],
					'crawler_risk'          => $signature['risk'],
					'is_allowed'            => $is_allowed,
					'is_denied'             => false,
					'classification_source' => 'signature',
				);
			}
		}

		if ( preg_match( '/bot|spider|crawler|crawl|slurp|scrapy|wget|curl|python-requests/i', $user_agent ) ) {
			return array(
				'is_crawler'            => true,
				'crawler_name'          => 'Generic Bot',
				'crawler_category'      => 'Generic Bot',
				'crawler_risk'          => 'high',
				'is_allowed'            => $is_allowed,
				'is_denied'             => false,
				'classification_source' => 'heuristic',
			);
		}

		return array(
			'is_crawler'            => false,
			'crawler_name'          => null,
			'crawler_category'      => null,
			'crawler_risk'          => null,
			'is_allowed'            => $is_allowed,
			'is_denied'             => false,
			'classification_source' => $is_allowed ? 'custom_allow' : 'none',
		);
	}

	/**
	 * Parse custom allow/deny patterns from textarea-like input.
	 *
	 * @param string $raw Raw setting value.
	 * @return array<int,string>
	 */
	private function parse_patterns( $raw ) {
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[\r\n,]+/', strtolower( $raw ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$patterns = array();
		foreach ( $parts as $part ) {
			$pattern = trim( (string) $part );
			if ( '' !== $pattern ) {
				$patterns[] = $pattern;
			}
		}

		return array_values( array_unique( $patterns ) );
	}

	/**
	 * Check whether user agent contains at least one pattern.
	 *
	 * @param string            $user_agent User agent.
	 * @param array<int,string> $patterns Patterns.
	 * @return bool
	 */
	private function matches_patterns( $user_agent, array $patterns ) {
		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $user_agent, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}
