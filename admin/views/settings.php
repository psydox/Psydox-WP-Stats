<?php
/**
 * Settings view.
 *
 * @var array<string,mixed> $settings
 *
 * @package PsydoxWPStats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$debug_mode_enabled = \Psydox\WPStats\Psydox_WP_Stats_Admin::is_debug_mode_enabled();
$geo_download_notice = array(
	'show'    => isset( $_GET['maintenance_action'] ) && 'download_geo_db' === sanitize_key( wp_unslash( $_GET['maintenance_action'] ) ),
	'success' => isset( $_GET['geo_status'] ) && 'success' === sanitize_key( wp_unslash( $_GET['geo_status'] ) ),
	'message' => isset( $_GET['geo_message'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['geo_message'] ) ) ) : '',
);
?>
<div class="wrap psydox-stats-wrap">
	<h1><?php esc_html_e( 'Psydox WP Stats Settings', 'psydox-wp-stats' ); ?></h1>
	<p class="psydox-settings-intro"><?php esc_html_e( 'Configure tracking behavior, privacy controls, maintenance tools, and export filters.', 'psydox-wp-stats' ); ?></p>
	<h2 class="nav-tab-wrapper" style="margin-bottom: 16px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Stats', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=content' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Post/Pages', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=world' ) ); ?>" class="nav-tab"><?php esc_html_e( 'World', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=bots' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Bots', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=settings' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( 'https://brianrosario.com/support-me/' ); ?>" class="nav-tab" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support Me', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( 'https://github.com/psydox/Psydox-WP-Stats' ); ?>" class="nav-tab" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help', 'psydox-wp-stats' ); ?></a>
	</h2>

	<?php if ( isset( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings updated.', 'psydox-wp-stats' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['maintenance'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Maintenance action completed.', 'psydox-wp-stats' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['maintenance_action'] ) && 'cleanup_retention' === sanitize_key( wp_unslash( $_GET['maintenance_action'] ) ) ) : ?>
		<div class="notice notice-info is-dismissible"><p>
			<?php
			printf(
				/* translators: %d is number of deleted rows. */
				esc_html__( 'Retention cleanup finished. Purged rows: %d', 'psydox-wp-stats' ),
				isset( $_GET['purged'] ) ? (int) $_GET['purged'] : 0
			);
			?>
		</p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['maintenance_action'] ) && 'generate_test_data' === sanitize_key( wp_unslash( $_GET['maintenance_action'] ) ) ) : ?>
		<?php if ( isset( $_GET['debug_required'] ) ) : ?>
			<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Generate Test Data is available only when a .debug file exists in the plugin root folder.', 'psydox-wp-stats' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				printf(
					/* translators: %d is number of inserted rows. */
					esc_html__( 'Test data generated successfully. Inserted rows: %d', 'psydox-wp-stats' ),
					isset( $_GET['inserted'] ) ? (int) $_GET['inserted'] : 0
				);
				?>
			</p></div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( isset( $_GET['export_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Export failed due to invalid filters. Please check date format and date range.', 'psydox-wp-stats' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-panel psydox-settings-form">
		<?php wp_nonce_field( 'psydox_wp_stats_save_settings_action', 'psydox_wp_stats_settings_nonce' ); ?>
		<input type="hidden" name="action" value="psydox_wp_stats_save_settings" />

		<div class="psydox-settings-grid">
			<section class="psydox-settings-section">
				<h2><?php esc_html_e( 'Tracking', 'psydox-wp-stats' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Control who gets tracked and how long data is retained.', 'psydox-wp-stats' ); ?></p>
				<label class="psydox-checkline"><input type="checkbox" name="settings[tracking_enabled]" value="1" <?php checked( ! empty( $settings['tracking_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable Tracking', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-field-label" for="psydox-retention-days"><?php esc_html_e( 'Data Retention', 'psydox-wp-stats' ); ?></label>
				<select id="psydox-retention-days" name="settings[retention_days]">
					<option value="30" <?php selected( 30 === (int) $settings['retention_days'] ); ?>><?php esc_html_e( '30 Days', 'psydox-wp-stats' ); ?></option>
					<option value="90" <?php selected( 90 === (int) $settings['retention_days'] ); ?>><?php esc_html_e( '90 Days', 'psydox-wp-stats' ); ?></option>
					<option value="180" <?php selected( 180 === (int) $settings['retention_days'] ); ?>><?php esc_html_e( '180 Days', 'psydox-wp-stats' ); ?></option>
					<option value="365" <?php selected( 365 === (int) $settings['retention_days'] ); ?>><?php esc_html_e( '365 Days', 'psydox-wp-stats' ); ?></option>
					<option value="0" <?php selected( 0 === (int) $settings['retention_days'] ); ?>><?php esc_html_e( 'Unlimited', 'psydox-wp-stats' ); ?></option>
				</select>
			</section>

			<section class="psydox-settings-section">
				<h2><?php esc_html_e( 'User Exclusions', 'psydox-wp-stats' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Exclude selected user groups from analytics data.', 'psydox-wp-stats' ); ?></p>
				<label class="psydox-checkline"><input type="checkbox" name="settings[exclude_administrators]" value="1" <?php checked( ! empty( $settings['exclude_administrators'] ) ); ?> /> <?php esc_html_e( 'Exclude Administrators', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-checkline"><input type="checkbox" name="settings[exclude_editors]" value="1" <?php checked( ! empty( $settings['exclude_editors'] ) ); ?> /> <?php esc_html_e( 'Exclude Editors', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-checkline"><input type="checkbox" name="settings[exclude_authors]" value="1" <?php checked( ! empty( $settings['exclude_authors'] ) ); ?> /> <?php esc_html_e( 'Exclude Authors', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-checkline"><input type="checkbox" name="settings[exclude_logged_in]" value="1" <?php checked( ! empty( $settings['exclude_logged_in'] ) ); ?> /> <?php esc_html_e( 'Exclude Logged-In Users', 'psydox-wp-stats' ); ?></label>
			</section>

			<section class="psydox-settings-section">
				<h2><?php esc_html_e( 'Real-Time Monitoring', 'psydox-wp-stats' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Tune how frequently realtime data updates in the dashboard.', 'psydox-wp-stats' ); ?></p>
				<label class="psydox-checkline"><input type="checkbox" name="settings[realtime_enabled]" value="1" <?php checked( ! empty( $settings['realtime_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable Real-Time Monitoring', 'psydox-wp-stats' ); ?></label>
				<div class="psydox-field-grid">
					<p>
						<label class="psydox-field-label" for="psydox-refresh-interval"><?php esc_html_e( 'Refresh Interval (seconds)', 'psydox-wp-stats' ); ?></label>
						<input id="psydox-refresh-interval" type="number" min="5" max="120" name="settings[refresh_interval]" value="<?php echo esc_attr( (string) $settings['refresh_interval'] ); ?>" />
					</p>
					<p>
						<label class="psydox-field-label" for="psydox-active-window"><?php esc_html_e( 'Active Visitor Window (minutes)', 'psydox-wp-stats' ); ?></label>
						<input id="psydox-active-window" type="number" min="1" max="60" name="settings[active_window_minutes]" value="<?php echo esc_attr( (string) $settings['active_window_minutes'] ); ?>" />
					</p>
					<p>
						<label class="psydox-field-label" for="psydox-chart-columns"><?php esc_html_e( 'Chart Columns', 'psydox-wp-stats' ); ?></label>
						<select id="psydox-chart-columns" name="settings[chart_columns]">
							<option value="1" <?php selected( 1 === (int) $settings['chart_columns'] ); ?>><?php esc_html_e( '1 Column', 'psydox-wp-stats' ); ?></option>
							<option value="2" <?php selected( 2 === (int) $settings['chart_columns'] ); ?>><?php esc_html_e( '2 Columns', 'psydox-wp-stats' ); ?></option>
							<option value="3" <?php selected( 3 === (int) $settings['chart_columns'] ); ?>><?php esc_html_e( '3 Columns', 'psydox-wp-stats' ); ?></option>
							<option value="4" <?php selected( 4 === (int) $settings['chart_columns'] ); ?>><?php esc_html_e( '4 Columns', 'psydox-wp-stats' ); ?></option>
						</select>
					</p>
				</div>
			</section>

			<section class="psydox-settings-section">
				<h2><?php esc_html_e( 'Privacy', 'psydox-wp-stats' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose what visitor metadata is stored in your local database.', 'psydox-wp-stats' ); ?></p>
				<label class="psydox-checkline"><input type="checkbox" name="settings[hash_ip]" value="1" <?php checked( ! empty( $settings['hash_ip'] ) ); ?> /> <?php esc_html_e( 'Hash IP Addresses', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-checkline"><input type="checkbox" name="settings[store_referrer]" value="1" <?php checked( ! empty( $settings['store_referrer'] ) ); ?> /> <?php esc_html_e( 'Store Referrer Data', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-checkline"><input type="checkbox" name="settings[store_browser]" value="1" <?php checked( ! empty( $settings['store_browser'] ) ); ?> /> <?php esc_html_e( 'Store Browser Information', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-checkline"><input type="checkbox" name="settings[store_device]" value="1" <?php checked( ! empty( $settings['store_device'] ) ); ?> /> <?php esc_html_e( 'Store Device Information', 'psydox-wp-stats' ); ?></label>
			</section>

			<section class="psydox-settings-section">
				<h2><?php esc_html_e( 'Bot Management', 'psydox-wp-stats' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Classify advanced bot traffic and control which bot user-agents are blocked or always allowed.', 'psydox-wp-stats' ); ?></p>
				<label class="psydox-checkline"><input type="checkbox" name="settings[block_denied_bots]" value="1" <?php checked( ! empty( $settings['block_denied_bots'] ) ); ?> /> <?php esc_html_e( 'Block Denied Bot Patterns From Being Stored', 'psydox-wp-stats' ); ?></label>
				<label class="psydox-field-label" for="psydox-spike-threshold"><?php esc_html_e( 'Spike Detection Ratio Threshold', 'psydox-wp-stats' ); ?></label>
				<input id="psydox-spike-threshold" type="number" min="1" max="20" step="0.1" name="settings[spike_ratio_threshold]" value="<?php echo esc_attr( (string) $settings['spike_ratio_threshold'] ); ?>" />

				<label class="psydox-field-label" for="psydox-bot-allowlist"><?php esc_html_e( 'Allowlist Bot User-Agent Patterns', 'psydox-wp-stats' ); ?></label>
				<textarea id="psydox-bot-allowlist" name="settings[bot_allowlist]" rows="4" placeholder="googlebot&#10;uptimerobot"><?php echo esc_textarea( (string) $settings['bot_allowlist'] ); ?></textarea>

				<label class="psydox-field-label" for="psydox-bot-denylist"><?php esc_html_e( 'Denylist Bot User-Agent Patterns', 'psydox-wp-stats' ); ?></label>
				<textarea id="psydox-bot-denylist" name="settings[bot_denylist]" rows="4" placeholder="semrushbot&#10;ahrefsbot"><?php echo esc_textarea( (string) $settings['bot_denylist'] ); ?></textarea>
			</section>
		</div>

		<div class="psydox-settings-actions">
			<?php submit_button( __( 'Save Settings', 'psydox-wp-stats' ) ); ?>
		</div>
	</form>

	<div class="psydox-panel">
		<h2><?php esc_html_e( 'Maintenance', 'psydox-wp-stats' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Run maintenance tools to keep your stats table healthy.', 'psydox-wp-stats' ); ?></p>
		<div class="psydox-maintenance-actions">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-inline-form" id="psydox-clear-statistics-form">
			<?php wp_nonce_field( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' ); ?>
			<input type="hidden" name="action" value="psydox_wp_stats_maintenance" />
			<input type="hidden" name="maintenance_action" value="clear" />
			<?php submit_button( __( 'Clear Statistics', 'psydox-wp-stats' ), 'delete', 'submit', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-inline-form">
			<?php wp_nonce_field( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' ); ?>
			<input type="hidden" name="action" value="psydox_wp_stats_maintenance" />
			<input type="hidden" name="maintenance_action" value="rebuild" />
			<?php submit_button( __( 'Rebuild Database', 'psydox-wp-stats' ), 'secondary', 'submit', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-inline-form">
			<?php wp_nonce_field( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' ); ?>
			<input type="hidden" name="action" value="psydox_wp_stats_maintenance" />
			<input type="hidden" name="maintenance_action" value="optimize" />
			<?php submit_button( __( 'Optimize Database', 'psydox-wp-stats' ), 'secondary', 'submit', false ); ?>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-inline-form">
			<?php wp_nonce_field( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' ); ?>
			<input type="hidden" name="action" value="psydox_wp_stats_maintenance" />
			<input type="hidden" name="maintenance_action" value="cleanup_retention" />
			<?php submit_button( __( 'Run Retention Cleanup', 'psydox-wp-stats' ), 'secondary', 'submit', false ); ?>
		</form>
		</div>

		<?php if ( $debug_mode_enabled ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-debug-form" onsubmit="return window.confirm('<?php echo esc_js( __( 'Generate demo analytics data for testing?', 'psydox-wp-stats' ) ); ?>');">
				<?php wp_nonce_field( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' ); ?>
				<input type="hidden" name="action" value="psydox_wp_stats_maintenance" />
				<input type="hidden" name="maintenance_action" value="generate_test_data" />
				<p class="psydox-field-inline">
					<label>
						<?php esc_html_e( 'Rows to generate:', 'psydox-wp-stats' ); ?>
						<input type="number" name="test_data_rows" min="1" max="5000" step="1" value="120" />
					</label>
				</p>
				<?php submit_button( __( 'Generate Test Data', 'psydox-wp-stats' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>

	<div class="psydox-panel">
		<h2><?php esc_html_e( 'Geo Database', 'psydox-wp-stats' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Download and manage local geolocation databases for server-side country detection without third-party API requests.', 'psydox-wp-stats' ); ?></p>

		<?php if ( ! empty( $geo_download_notice['show'] ) ) : ?>
			<div class="notice <?php echo esc_attr( ! empty( $geo_download_notice['success'] ) ? 'notice-success' : 'notice-error' ); ?> is-dismissible" style="margin: 8px 0 12px;">
				<p><?php echo esc_html( ! empty( $geo_download_notice['message'] ) ? (string) $geo_download_notice['message'] : __( 'Geo database action completed.', 'psydox-wp-stats' ) ); ?></p>
			</div>
		<?php endif; ?>

		<table class="widefat striped" style="margin-bottom:12px; max-width: 920px;">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Current Database Status', 'psydox-wp-stats' ); ?></th>
					<td>
						<?php if ( ! empty( $geo_db_status['found'] ) ) : ?>
							<strong><?php esc_html_e( 'Installed', 'psydox-wp-stats' ); ?></strong><br />
							<?php echo esc_html( (string) $geo_db_status['path'] ); ?><br />
							<?php
							printf(
								/* translators: %s is file size in MB. */
								esc_html__( 'Size: %s MB', 'psydox-wp-stats' ),
								esc_html( number_format_i18n( ( (int) $geo_db_status['size'] ) / 1048576, 2 ) )
							);
							?>
						<?php else : ?>
							<strong><?php esc_html_e( 'Not Installed', 'psydox-wp-stats' ); ?></strong>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="psydox-inline-form" style="margin-bottom: 12px;" enctype="multipart/form-data">
			<?php wp_nonce_field( 'psydox_wp_stats_maintenance_action', 'psydox_wp_stats_maintenance_nonce' ); ?>
			<input type="hidden" name="action" value="psydox_wp_stats_maintenance" />
			<input type="hidden" name="maintenance_action" value="download_geo_db" />

			<p class="psydox-field-inline">
				<label class="psydox-field-label" for="psydox-geo-db-source"><?php esc_html_e( 'Database Source', 'psydox-wp-stats' ); ?></label>
				<select id="psydox-geo-db-source" name="geo_db_source">
					<option value="dbip_lite"><?php esc_html_e( 'DB-IP Country Lite (Auto Download)', 'psydox-wp-stats' ); ?></option>
					<option value="maxmind_manual"><?php esc_html_e( 'MaxMind GeoLite2 (Manual Download)', 'psydox-wp-stats' ); ?></option>
				</select>
			</p>
			<p class="psydox-field-inline" style="margin-top:8px;">
				<label class="psydox-field-label" for="psydox-geo-mmdb-file"><?php esc_html_e( 'MaxMind MMDB File', 'psydox-wp-stats' ); ?></label>
				<input id="psydox-geo-mmdb-file" type="file" name="geo_mmdb_file" accept=".mmdb" />
				<span class="description" style="display:block; margin-top:4px;"><?php esc_html_e( 'For MaxMind GeoLite2, choose the GeoLite2-Country .mmdb file, then click Download / Install.', 'psydox-wp-stats' ); ?></span>
			</p>
			<?php submit_button( __( 'Download / Install', 'psydox-wp-stats' ), 'secondary', 'submit', false ); ?>
		</form>

		<p class="description">
			<?php esc_html_e( 'Selecting DB-IP Country Lite downloads the latest monthly MMDB file automatically into your WordPress uploads directory.', 'psydox-wp-stats' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'If using MaxMind manual mode, the uploaded file will be stored as wp-content/uploads/GeoLite2-Country.mmdb.', 'psydox-wp-stats' ); ?>
		</p>

		<div class="psydox-geo-attribution" style="margin: 8px 0 0; padding: 10px 12px; border: 1px solid #dcdcde; border-left: 4px solid #72aee6; background: #f6f7f7;">
			<p><strong><?php esc_html_e( 'Attribution and Licensing', 'psydox-wp-stats' ); ?></strong></p>
			<p><?php esc_html_e( 'DB-IP Lite is licensed under CC BY 4.0 and requires attribution when geolocation results are used in your web application.', 'psydox-wp-stats' ); ?></p>
			<p>
				<a href="https://db-ip.com" target="_blank" rel="noopener noreferrer">https://db-ip.com</a>
				<?php esc_html_e( ' - Suggested attribution: IP Geolocation by DB-IP', 'psydox-wp-stats' ); ?>
			</p>
			<p><?php esc_html_e( 'MaxMind GeoLite2 has separate license terms and may require account-based/manual download depending on your compliance requirements.', 'psydox-wp-stats' ); ?></p>
		</div>
	</div>

	<div class="psydox-panel">
		<h2><?php esc_html_e( 'Export Statistics', 'psydox-wp-stats' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Filter your dataset, then export in CSV or JSON format.', 'psydox-wp-stats' ); ?></p>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="psydox_wp_stats_export" />
			<?php wp_nonce_field( 'psydox_wp_stats_export_action', 'psydox_wp_stats_export_nonce' ); ?>
			<p class="psydox-field-grid">
				<label class="psydox-field-label"><?php esc_html_e( 'Date From', 'psydox-wp-stats' ); ?> <input type="date" name="date_from" /></label>
				<label class="psydox-field-label"><?php esc_html_e( 'Date To', 'psydox-wp-stats' ); ?> <input type="date" name="date_to" /></label>
			</p>
			<p>
				<label class="psydox-field-label"><?php esc_html_e( 'Visitor Type', 'psydox-wp-stats' ); ?>
					<select name="visitor_type">
						<option value=""><?php esc_html_e( 'All', 'psydox-wp-stats' ); ?></option>
						<option value="human"><?php esc_html_e( 'Human', 'psydox-wp-stats' ); ?></option>
						<option value="bot"><?php esc_html_e( 'Bot', 'psydox-wp-stats' ); ?></option>
					</select>
				</label>
			</p>
			<p class="psydox-field-grid psydox-export-grid">
				<label class="psydox-field-label"><?php esc_html_e( 'Page URL', 'psydox-wp-stats' ); ?> <input type="url" name="page_url" /></label>
				<label class="psydox-field-label"><?php esc_html_e( 'Referrer', 'psydox-wp-stats' ); ?> <input type="url" name="referrer" /></label>
				<label class="psydox-field-label"><?php esc_html_e( 'Crawler', 'psydox-wp-stats' ); ?> <input type="text" name="crawler_name" /></label>
			</p>
			<p class="psydox-export-actions">
				<button type="submit" name="format" value="csv" class="button button-primary"><?php esc_html_e( 'Export CSV', 'psydox-wp-stats' ); ?></button>
				<button type="submit" name="format" value="json" class="button button-secondary"><?php esc_html_e( 'Export JSON', 'psydox-wp-stats' ); ?></button>
			</p>
		</form>
	</div>

	<div class="psydox-modal-backdrop" id="psydox-clear-modal" hidden>
		<div class="psydox-modal" role="dialog" aria-modal="true" aria-labelledby="psydox-clear-modal-title" aria-describedby="psydox-clear-modal-desc">
			<h3 id="psydox-clear-modal-title"><?php esc_html_e( 'Confirm Clear Statistics', 'psydox-wp-stats' ); ?></h3>
			<p id="psydox-clear-modal-desc"><?php esc_html_e( 'This will permanently delete all collected statistics. This action cannot be undone.', 'psydox-wp-stats' ); ?></p>
			<div class="psydox-modal-actions">
				<button type="button" class="button" id="psydox-clear-modal-cancel"><?php esc_html_e( 'Cancel', 'psydox-wp-stats' ); ?></button>
				<button type="button" class="button button-primary" id="psydox-clear-modal-confirm"><?php esc_html_e( 'Proceed', 'psydox-wp-stats' ); ?></button>
			</div>
		</div>
	</div>

	<script>
	(function () {
		'use strict';

		var clearForm = document.getElementById('psydox-clear-statistics-form');
		var modal = document.getElementById('psydox-clear-modal');
		var cancelButton = document.getElementById('psydox-clear-modal-cancel');
		var confirmButton = document.getElementById('psydox-clear-modal-confirm');
		var isConfirmedSubmit = false;

		if (!clearForm || !modal || !cancelButton || !confirmButton) {
			return;
		}

		var openModal = function () {
			modal.hidden = false;
			document.body.classList.add('psydox-modal-open');
			confirmButton.focus();
		};

		var closeModal = function () {
			modal.hidden = true;
			document.body.classList.remove('psydox-modal-open');
		};

		clearForm.addEventListener('submit', function (event) {
			if (isConfirmedSubmit) {
				isConfirmedSubmit = false;
				return;
			}

			event.preventDefault();
			openModal();
		});

		cancelButton.addEventListener('click', closeModal);

		confirmButton.addEventListener('click', function () {
			closeModal();
			isConfirmedSubmit = true;
			if (window.HTMLFormElement && window.HTMLFormElement.prototype && typeof window.HTMLFormElement.prototype.submit === 'function') {
				window.HTMLFormElement.prototype.submit.call(clearForm);
			}
		});

		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				closeModal();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !modal.hidden) {
				closeModal();
			}
		});
	})();
	</script>
</div>
