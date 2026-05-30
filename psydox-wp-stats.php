<?php
/**
 * Plugin Name: Psydox WP Stats
 * Plugin URI: https://psydox.com/
 * Description: Lightweight, privacy-focused, self-hosted analytics for WordPress.
 * Version: 2026.05.30.1742
 * Author: Psydox
 * Author URI: https://github.com/Psydox
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: psydox-wp-stats
 * Domain Path: /languages
 *
 * @package PsydoxWPStats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PSYDOX_WP_STATS_VERSION', '2026.05.30.1742' );
define( 'PSYDOX_WP_STATS_FILE', __FILE__ );
define( 'PSYDOX_WP_STATS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PSYDOX_WP_STATS_URL', plugin_dir_url( __FILE__ ) );
define( 'PSYDOX_WP_STATS_OPTION_KEY', 'psydox_wp_stats_settings' );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Psydox\\WPStats\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$short_name = str_replace( $prefix, '', $class_name );
		$file_name  = 'class-' . strtolower( str_replace( '_', '-', $short_name ) ) . '.php';
		$file_path  = PSYDOX_WP_STATS_PATH . 'includes/' . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

register_activation_hook( PSYDOX_WP_STATS_FILE, array( 'Psydox\\WPStats\\Psydox_WP_Stats_Activator', 'activate' ) );
register_deactivation_hook( PSYDOX_WP_STATS_FILE, array( 'Psydox\\WPStats\\Psydox_WP_Stats_Deactivator', 'deactivate' ) );

require_once PSYDOX_WP_STATS_PATH . 'includes/class-psydox-wp-stats.php';

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new Psydox\WPStats\Psydox_WP_Stats();
		$plugin->run();
	}
);
