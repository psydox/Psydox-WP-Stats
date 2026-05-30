<?php
/**
 * Dashboard view.
 *
 * @var array<string,mixed> $data
 *
 * @package PsydoxWPStats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$overview = isset( $data['overview'] ) ? $data['overview'] : array();
$crawler_summary = isset( $data['crawler_activity_summary'] ) ? $data['crawler_activity_summary'] : array();
$bot_pressure = isset( $data['bot_pressure'] ) && is_array( $data['bot_pressure'] ) ? $data['bot_pressure'] : array();
$bot_classification_summary = isset( $data['bot_classification_summary'] ) && is_array( $data['bot_classification_summary'] ) ? $data['bot_classification_summary'] : array();
$world_summary = isset( $data['world_summary'] ) && is_array( $data['world_summary'] ) ? $data['world_summary'] : array();
$page_summary = isset( $data['page_summary'] ) && is_array( $data['page_summary'] ) ? $data['page_summary'] : array();
$post_page_breakdown = isset( $data['post_page_breakdown'] ) && is_array( $data['post_page_breakdown'] ) ? $data['post_page_breakdown'] : array();
$top_pages_7d = isset( $data['top_pages_7d'] ) && is_array( $data['top_pages_7d'] ) ? $data['top_pages_7d'] : array();
$device_summary  = isset( $data['device_summary'] ) ? $data['device_summary'] : array();
$top_countries   = isset( $data['top_countries'] ) ? $data['top_countries'] : array();
$country_trends  = isset( $data['country_trends'] ) ? $data['country_trends'] : array();
$pagination      = isset( $data['pagination'] ) ? $data['pagination'] : array();
$settings        = \Psydox\WPStats\Psydox_WP_Stats::get_settings();
$today_trend     = isset( $overview['today_vs_yesterday'] ) && is_array( $overview['today_vs_yesterday'] ) ? $overview['today_vs_yesterday'] : array();
$week_trend      = isset( $overview['week_over_week'] ) && is_array( $overview['week_over_week'] ) ? $overview['week_over_week'] : array();
$chart_columns   = isset( $settings['chart_columns'] ) ? (int) $settings['chart_columns'] : 3;
if ( ! in_array( $chart_columns, array( 1, 2, 3, 4 ), true ) ) {
	$chart_columns = 3;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
if ( ! in_array( $active_tab, array( 'dashboard', 'content', 'world', 'bots', 'settings' ), true ) ) {
	$active_tab = 'dashboard';
}

$tab_intro_messages = array(
	'dashboard' => __( 'Track overall traffic performance, devices, referrers, and realtime activity.', 'psydox-wp-stats' ),
	'content'   => __( 'Measure post and page performance, discover top URLs, and review content traffic trends.', 'psydox-wp-stats' ),
	'world'     => __( 'Explore global audience distribution with country charts, maps, and trend movements.', 'psydox-wp-stats' ),
	'bots'      => __( 'Monitor crawler behavior, bot pressure, classification signals, and crawl hotspots.', 'psydox-wp-stats' ),
);

$tab_intro = isset( $tab_intro_messages[ $active_tab ] ) ? $tab_intro_messages[ $active_tab ] : $tab_intro_messages['dashboard'];

$paging_state = array(
	'top_pages_page'       => isset( $_GET['top_pages_page'] ) ? max( 1, (int) $_GET['top_pages_page'] ) : 1,
	'recent_visits_page'   => isset( $_GET['recent_visits_page'] ) ? max( 1, (int) $_GET['recent_visits_page'] ) : 1,
	'recent_crawlers_page' => isset( $_GET['recent_crawlers_page'] ) ? max( 1, (int) $_GET['recent_crawlers_page'] ) : 1,
);

$country_trend_sort = isset( $_GET['country_trend_sort'] ) ? sanitize_key( wp_unslash( $_GET['country_trend_sort'] ) ) : 'current_7d';
$country_trend_order = isset( $_GET['country_trend_order'] ) ? sanitize_key( wp_unslash( $_GET['country_trend_order'] ) ) : 'desc';

if ( ! in_array( $country_trend_sort, array( 'current_7d', 'previous_7d', 'delta', 'percent' ), true ) ) {
	$country_trend_sort = 'current_7d';
}

if ( ! in_array( $country_trend_order, array( 'asc', 'desc' ), true ) ) {
	$country_trend_order = 'desc';
}

if ( ! empty( $country_trends ) ) {
	usort(
		$country_trends,
		static function ( $a, $b ) use ( $country_trend_sort, $country_trend_order ) {
			$value_a = isset( $a[ $country_trend_sort ] ) ? (float) $a[ $country_trend_sort ] : 0;
			$value_b = isset( $b[ $country_trend_sort ] ) ? (float) $b[ $country_trend_sort ] : 0;

			if ( $value_a === $value_b ) {
				return 0;
			}

			if ( 'asc' === $country_trend_order ) {
				return ( $value_a < $value_b ) ? -1 : 1;
			}

			return ( $value_a > $value_b ) ? -1 : 1;
		}
	);
}

$render_pagination = static function ( $config ) use ( $paging_state, $active_tab ) {
	if ( empty( $config['total_pages'] ) || $config['total_pages'] <= 1 || empty( $config['query_key'] ) ) {
		return;
	}

	$key     = $config['query_key'];
	$current = (int) $config['current'];
	$total   = (int) $config['total_pages'];

	echo '<div class="tablenav" style="margin-top:10px;"><div class="tablenav-pages">';
	echo '<span class="displaying-num">' . esc_html( number_format_i18n( (int) $config['total_items'] ) ) . ' ' . esc_html__( 'items', 'psydox-wp-stats' ) . '</span>';

	if ( $current > 1 ) {
		$prev_query          = $paging_state;
		$prev_query[ $key ]  = $current - 1;
		$prev_url            = add_query_arg( array_merge( array( 'page' => 'psydox-wp-stats', 'tab' => $active_tab ), $prev_query ), admin_url( 'admin.php' ) );
		echo ' <a class="button" href="' . esc_url( $prev_url ) . '">' . esc_html__( 'Previous', 'psydox-wp-stats' ) . '</a>';
	}

	echo ' <span class="paging-input">' . esc_html( sprintf( '%d / %d', $current, $total ) ) . '</span>';

	if ( $current < $total ) {
		$next_query          = $paging_state;
		$next_query[ $key ]  = $current + 1;
		$next_url            = add_query_arg( array_merge( array( 'page' => 'psydox-wp-stats', 'tab' => $active_tab ), $next_query ), admin_url( 'admin.php' ) );
		echo ' <a class="button" href="' . esc_url( $next_url ) . '">' . esc_html__( 'Next', 'psydox-wp-stats' ) . '</a>';
	}

	echo '</div></div>';
};

$render_country_sort_link = static function ( $label, $column ) use ( $country_trend_sort, $country_trend_order, $paging_state, $active_tab ) {
	$next_order = 'desc';
	$is_active  = ( $country_trend_sort === $column );
	if ( $country_trend_sort === $column && 'desc' === $country_trend_order ) {
		$next_order = 'asc';
	}

	$query = array_merge(
		array( 'page' => 'psydox-wp-stats', 'tab' => $active_tab ),
		$paging_state,
		array(
			'country_trend_sort'  => $column,
			'country_trend_order' => $next_order,
		)
	);

	$url = add_query_arg( $query, admin_url( 'admin.php' ) );

	$direction_arrow = '&#8597;';
	if ( $is_active ) {
		$direction_arrow = ( 'asc' === $country_trend_order ) ? '&#8593;' : '&#8595;';
	}

	$classes = 'psydox-sort-link';
	if ( $is_active ) {
		$classes .= ' is-active';
	}

	$label_markup = '<span>' . esc_html( $label ) . '</span><span class="psydox-sort-arrow" aria-hidden="true">' . $direction_arrow . '</span>';

	return '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '">' . $label_markup . '</a>';
};
?>
<div class="wrap psydox-stats-wrap">
	<h1><?php esc_html_e( 'Psydox WP Stats Dashboard', 'psydox-wp-stats' ); ?></h1>
	<p class="psydox-settings-intro"><?php echo esc_html( $tab_intro ); ?></p>
	<h2 class="nav-tab-wrapper" style="margin-bottom: 16px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=dashboard' ) ); ?>" class="nav-tab <?php echo esc_attr( 'dashboard' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Stats', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=content' ) ); ?>" class="nav-tab <?php echo esc_attr( 'content' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Post/Pages', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=world' ) ); ?>" class="nav-tab <?php echo esc_attr( 'world' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'World', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=bots' ) ); ?>" class="nav-tab <?php echo esc_attr( 'bots' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Bots', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=psydox-wp-stats&tab=settings' ) ); ?>" class="nav-tab <?php echo esc_attr( 'settings' === $active_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Settings', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( 'https://brianrosario.com/support-me/' ); ?>" class="nav-tab" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support Me', 'psydox-wp-stats' ); ?></a>
		<a href="<?php echo esc_url( 'https://github.com/psydox/Psydox-WP-Stats' ); ?>" class="nav-tab" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help', 'psydox-wp-stats' ); ?></a>
	</h2>

	<?php if ( 'dashboard' === $active_tab ) : ?>
	<div class="psydox-cards">
		<div class="psydox-card"><span><?php esc_html_e( 'Total Views', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $overview['total_views'] ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Unique Visitors', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $overview['unique_visitors'] ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Views Today', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $overview['views_today'] ) ); ?></strong></div>
		<div class="psydox-card psydox-card-trend psydox-trend-<?php echo esc_attr( isset( $today_trend['direction'] ) ? (string) $today_trend['direction'] : 'flat' ); ?>">
			<span><?php esc_html_e( 'Today vs Yesterday', 'psydox-wp-stats' ); ?></span>
			<strong class="psydox-trend-value"><?php echo esc_html( sprintf( '%+.1f%%', isset( $today_trend['percent'] ) ? (float) $today_trend['percent'] : 0 ) ); ?></strong>
			<small>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: today views 2: yesterday views */
						__( '%1$s today, %2$s yesterday', 'psydox-wp-stats' ),
						number_format_i18n( isset( $today_trend['current'] ) ? (int) $today_trend['current'] : 0 ),
						number_format_i18n( isset( $today_trend['previous'] ) ? (int) $today_trend['previous'] : 0 )
					)
				);
				?>
			</small>
		</div>
		<div class="psydox-card"><span><?php esc_html_e( 'Views This Week', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $overview['views_this_week'] ) ); ?></strong></div>
		<div class="psydox-card psydox-card-trend psydox-trend-<?php echo esc_attr( isset( $week_trend['direction'] ) ? (string) $week_trend['direction'] : 'flat' ); ?>">
			<span><?php esc_html_e( 'Week over Week', 'psydox-wp-stats' ); ?></span>
			<strong class="psydox-trend-value"><?php echo esc_html( sprintf( '%+.1f%%', isset( $week_trend['percent'] ) ? (float) $week_trend['percent'] : 0 ) ); ?></strong>
			<small>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: this week views 2: last week views */
						__( '%1$s this week, %2$s last week', 'psydox-wp-stats' ),
						number_format_i18n( isset( $week_trend['current'] ) ? (int) $week_trend['current'] : 0 ),
						number_format_i18n( isset( $week_trend['previous'] ) ? (int) $week_trend['previous'] : 0 )
					)
				);
				?>
			</small>
		</div>
		<div class="psydox-card"><span><?php esc_html_e( 'Views This Month', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $overview['views_this_month'] ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Active Visitors', 'psydox-wp-stats' ); ?></span><strong id="psydox-active-visitors"><?php echo esc_html( number_format_i18n( (int) $overview['active_visitors'] ) ); ?></strong></div>
	</div>

	<div class="psydox-grid psydox-charts-grid" style="--psydox-chart-columns: <?php echo esc_attr( (string) $chart_columns ); ?>;">
		<div class="psydox-panel"><h2><?php esc_html_e( 'Daily Visits', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-daily"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Weekly Visits', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-weekly"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Monthly Visits', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-monthly"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Device Trends', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-device-trends"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Device Breakdown', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-devices"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Mobile vs Desktop Comparison', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-mobile-desktop"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Browser Breakdown', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-browsers"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Operating System Breakdown', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-os"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Human vs Bot Traffic', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-human-bot"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Real-Time Visitor Activity', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-realtime"></canvas></div>
	</div>

	<div class="psydox-grid">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Device Type Summary', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr><th><?php esc_html_e( 'Desktop Visitors', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $device_summary['desktop_visits'] ) ? $device_summary['desktop_visits'] : 0 ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Mobile Visitors', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $device_summary['mobile_visits'] ) ? $device_summary['mobile_visits'] : 0 ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Tablet Visitors', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $device_summary['tablet_visits'] ) ? $device_summary['tablet_visits'] : 0 ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Bot Visits', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $device_summary['bot_visits'] ) ? $device_summary['bot_visits'] : 0 ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Unknown Devices', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $device_summary['unknown_visits'] ) ? $device_summary['unknown_visits'] : 0 ) ) ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( 'world' === $active_tab ) : ?>
	<div class="psydox-cards">
		<div class="psydox-card">
			<span><?php esc_html_e( 'Countries Tracked', 'psydox-wp-stats' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( isset( $world_summary['total_countries_tracked'] ) ? (int) $world_summary['total_countries_tracked'] : 0 ) ); ?></strong>
		</div>
		<div class="psydox-card">
			<span><?php esc_html_e( 'Top Country', 'psydox-wp-stats' ); ?></span>
			<strong><?php echo esc_html( isset( $world_summary['top_country_name'] ) ? (string) $world_summary['top_country_name'] : __( 'Unknown', 'psydox-wp-stats' ) ); ?></strong>
			<small><?php echo esc_html( sprintf( '%s - %s', isset( $world_summary['top_country_code'] ) ? (string) $world_summary['top_country_code'] : 'UN', number_format_i18n( isset( $world_summary['top_country_visits'] ) ? (int) $world_summary['top_country_visits'] : 0 ) ) ); ?></small>
		</div>
	</div>

	<div class="psydox-grid">
		<div class="psydox-panel"><h2><?php esc_html_e( 'Country Breakdown', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-countries"></canvas></div>
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Top Countries', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Country', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Code', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Visits', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $top_countries as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['country_name'] ? $row['country_name'] : __( 'Unknown', 'psydox-wp-stats' ) ); ?></td>
						<td><?php echo esc_html( $row['country_code'] ? $row['country_code'] : 'UN' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['visits'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="psydox-grid">
		<div class="psydox-panel psydox-world-map-panel"><h2><?php esc_html_e( 'World Visitor Map', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-world-map"></canvas></div>
	</div>

	<div class="psydox-grid psydox-grid-tables">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Country Trends (Last 7 Days)', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Country', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Code', 'psydox-wp-stats' ); ?></th><th class="<?php echo esc_attr( 'current_7d' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo wp_kses_post( $render_country_sort_link( __( 'Last 7 Days', 'psydox-wp-stats' ), 'current_7d' ) ); ?></th><th class="<?php echo esc_attr( 'previous_7d' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo wp_kses_post( $render_country_sort_link( __( 'Previous 7 Days', 'psydox-wp-stats' ), 'previous_7d' ) ); ?></th><th class="<?php echo esc_attr( 'delta' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo wp_kses_post( $render_country_sort_link( __( 'Change', 'psydox-wp-stats' ), 'delta' ) ); ?></th><th class="<?php echo esc_attr( 'percent' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo wp_kses_post( $render_country_sort_link( __( 'Percent', 'psydox-wp-stats' ), 'percent' ) ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $country_trends ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No country trend data yet.', 'psydox-wp-stats' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $country_trends as $row ) : ?>
						<tr>
							<td><?php echo esc_html( ! empty( $row['country_name'] ) ? $row['country_name'] : __( 'Unknown', 'psydox-wp-stats' ) ); ?></td>
							<td><?php echo esc_html( ! empty( $row['country_code'] ) ? $row['country_code'] : 'UN' ); ?></td>
							<td class="<?php echo esc_attr( 'current_7d' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo esc_html( number_format_i18n( (int) $row['current_7d'] ) ); ?></td>
							<td class="<?php echo esc_attr( 'previous_7d' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo esc_html( number_format_i18n( (int) $row['previous_7d'] ) ); ?></td>
							<td class="<?php echo esc_attr( 'delta' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>">
								<span class="psydox-trend-chip psydox-trend-<?php echo esc_attr( isset( $row['direction'] ) ? (string) $row['direction'] : 'flat' ); ?>">
									<?php echo esc_html( sprintf( '%+d', isset( $row['delta'] ) ? (int) $row['delta'] : 0 ) ); ?>
								</span>
							</td>
							<td class="<?php echo esc_attr( 'percent' === $country_trend_sort ? 'psydox-active-sort-column' : '' ); ?>"><?php echo esc_html( sprintf( '%+.1f%%', isset( $row['percent'] ) ? (float) $row['percent'] : 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( 'bots' === $active_tab ) : ?>
	<div class="psydox-cards">
		<div class="psydox-card"><span><?php esc_html_e( 'Total Crawlers', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $overview['total_crawlers'] ) ); ?></strong></div>
		<div class="psydox-card psydox-card-trend psydox-trend-<?php echo esc_attr( isset( $bot_pressure['pressure_level'] ) ? (string) $bot_pressure['pressure_level'] : 'flat' ); ?>">
			<span><?php esc_html_e( 'Bot Pressure (Last 60m)', 'psydox-wp-stats' ); ?></span>
			<strong class="psydox-trend-value"><?php echo esc_html( sprintf( '%.1f%%', isset( $bot_pressure['pressure_score'] ) ? (float) $bot_pressure['pressure_score'] : 0 ) ); ?></strong>
			<small>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: bot visits in current hour 2: total current hour visits. */
						__( '%1$s bot visits out of %2$s total visits', 'psydox-wp-stats' ),
						number_format_i18n( isset( $bot_pressure['current_hour_bot'] ) ? (int) $bot_pressure['current_hour_bot'] : 0 ),
						number_format_i18n( isset( $bot_pressure['current_hour_total'] ) ? (int) $bot_pressure['current_hour_total'] : 0 )
					)
				);
				?>
			</small>
		</div>
		<div class="psydox-card">
			<span><?php esc_html_e( 'Bot Spike Status', 'psydox-wp-stats' ); ?></span>
			<strong><?php echo esc_html( ! empty( $bot_pressure['is_spike'] ) ? __( 'Spike Detected', 'psydox-wp-stats' ) : __( 'Normal', 'psydox-wp-stats' ) ); ?></strong>
			<small>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current spike ratio 2: configured spike threshold. */
						__( 'Ratio %1$sx (threshold %2$sx)', 'psydox-wp-stats' ),
						number_format_i18n( isset( $bot_pressure['spike_ratio'] ) ? (float) $bot_pressure['spike_ratio'] : 0, 2 ),
						number_format_i18n( isset( $bot_pressure['spike_threshold'] ) ? (float) $bot_pressure['spike_threshold'] : 2.5, 1 )
					)
				);
				?>
			</small>
		</div>
	</div>

	<div class="psydox-grid psydox-charts-grid" style="--psydox-chart-columns: <?php echo esc_attr( (string) $chart_columns ); ?>;">
		<div class="psydox-panel"><h2><?php esc_html_e( 'Human vs Bot Traffic', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-human-bot"></canvas></div>
		<div class="psydox-panel"><h2><?php esc_html_e( 'Crawler Activity', 'psydox-wp-stats' ); ?></h2><canvas id="psydox-chart-crawlers"></canvas></div>
	</div>

	<div class="psydox-grid">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Bot Classification Summary', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Category', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Risk', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Visits', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Share', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $bot_classification_summary ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No bot classification data yet.', 'psydox-wp-stats' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $bot_classification_summary as $row ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $row['category'] ) ? (string) $row['category'] : __( 'Unknown', 'psydox-wp-stats' ) ); ?></td>
							<td><?php echo esc_html( ucfirst( isset( $row['risk'] ) ? (string) $row['risk'] : 'medium' ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( isset( $row['visits'] ) ? (int) $row['visits'] : 0 ) ); ?></td>
							<td><?php echo esc_html( sprintf( '%.1f%%', isset( $row['share'] ) ? (float) $row['share'] : 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Crawler Activity Summary', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr><th><?php esc_html_e( 'Total Bot Visits', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $crawler_summary['total_bot_visits'] ) ? $crawler_summary['total_bot_visits'] : 0 ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Unique Crawlers', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( isset( $crawler_summary['unique_crawlers'] ) ? $crawler_summary['unique_crawlers'] : 0 ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Last Crawl', 'psydox-wp-stats' ); ?></th><td><?php echo esc_html( isset( $crawler_summary['last_crawl_at'] ) ? $crawler_summary['last_crawl_at'] : '-' ); ?></td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="psydox-grid psydox-grid-tables">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Top Crawlers', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Crawler', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Visits', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Last Visit', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $data['top_crawlers'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['crawler_name'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['visits'] ) ); ?></td>
						<td><?php echo esc_html( $row['last_visit'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Most Crawled Pages', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'URL', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Crawls', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $data['most_crawled_pages'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['page_url'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['crawls'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Recent Crawler Visits', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Crawler', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Page', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Time', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody id="psydox-recent-crawlers-body">
				<?php foreach ( $data['recent_crawler_visits'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['crawler_name'] ); ?></td>
						<td><?php echo esc_html( $row['page_url'] ); ?></td>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php $render_pagination( isset( $pagination['recent_crawlers'] ) ? $pagination['recent_crawlers'] : array() ); ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( 'dashboard' === $active_tab ) : ?>
	<div class="psydox-grid psydox-grid-tables">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Real-Time Visitors', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Visitor Type', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Page', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Browser', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Device', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Referrer', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Last Activity', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody id="psydox-recent-visits-body">
				<?php foreach ( $data['recent_visits'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( ucfirst( (string) $row['visitor_type'] ) ); ?></td>
						<td><?php echo esc_html( $row['page_url'] ); ?></td>
						<td><?php echo esc_html( $row['browser'] ); ?></td>
						<td><?php echo esc_html( $row['device_type'] ); ?></td>
						<td><?php echo esc_html( $row['referrer'] ? $row['referrer'] : '-' ); ?></td>
						<td><?php echo esc_html( $row['last_activity_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php $render_pagination( isset( $pagination['recent_visits'] ) ? $pagination['recent_visits'] : array() ); ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( 'content' === $active_tab ) : ?>
	<div class="psydox-cards">
		<div class="psydox-card"><span><?php esc_html_e( 'Tracked URLs', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $page_summary['tracked_urls'] ) ? (int) $page_summary['tracked_urls'] : 0 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Tracked Posts', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $post_page_breakdown['post_count'] ) ? (int) $post_page_breakdown['post_count'] : 0 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Tracked Pages', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $post_page_breakdown['page_count'] ) ? (int) $post_page_breakdown['page_count'] : 0 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Post Views', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $post_page_breakdown['post_views'] ) ? (int) $post_page_breakdown['post_views'] : 0 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Page Views', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $post_page_breakdown['page_views'] ) ? (int) $post_page_breakdown['page_views'] : 0 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Total URL Views', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $page_summary['total_page_views'] ) ? (int) $page_summary['total_page_views'] : 0 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Avg Views per URL', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $page_summary['avg_views_per_url'] ) ? (float) $page_summary['avg_views_per_url'] : 0, 1 ) ); ?></strong></div>
		<div class="psydox-card"><span><?php esc_html_e( 'Top URL Views', 'psydox-wp-stats' ); ?></span><strong><?php echo esc_html( number_format_i18n( isset( $page_summary['top_page_views'] ) ? (int) $page_summary['top_page_views'] : 0 ) ); ?></strong><small><?php echo esc_html( isset( $page_summary['top_page_title'] ) ? (string) $page_summary['top_page_title'] : __( '(No title)', 'psydox-wp-stats' ) ); ?></small></div>
	</div>

	<div class="psydox-grid psydox-grid-tables">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Top Posts', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Post Title', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'URL', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Views', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $post_page_breakdown['top_posts'] ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No post analytics data yet.', 'psydox-wp-stats' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $post_page_breakdown['top_posts'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $row['post_title'] ) ? (string) $row['post_title'] : __( '(No title)', 'psydox-wp-stats' ) ); ?></td>
							<td><a href="<?php echo esc_url( isset( $row['page_url'] ) ? (string) $row['page_url'] : '' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( isset( $row['page_url'] ) ? (string) $row['page_url'] : '' ); ?></a></td>
							<td><?php echo esc_html( number_format_i18n( isset( $row['views'] ) ? (int) $row['views'] : 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Top Pages Only', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Page Title', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'URL', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Views', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $post_page_breakdown['top_pages_only'] ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No page analytics data yet.', 'psydox-wp-stats' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $post_page_breakdown['top_pages_only'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $row['post_title'] ) ? (string) $row['post_title'] : __( '(No title)', 'psydox-wp-stats' ) ); ?></td>
							<td><a href="<?php echo esc_url( isset( $row['page_url'] ) ? (string) $row['page_url'] : '' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( isset( $row['page_url'] ) ? (string) $row['page_url'] : '' ); ?></a></td>
							<td><?php echo esc_html( number_format_i18n( isset( $row['views'] ) ? (int) $row['views'] : 0 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Top Referrers', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Referrer', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Visits', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $data['top_referrers'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['referrer'] ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['visits'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<div class="psydox-grid">
		<div class="psydox-panel">
			<h2><?php esc_html_e( 'Top Pages (Last 7 Days)', 'psydox-wp-stats' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Page Title', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'URL', 'psydox-wp-stats' ); ?></th><th><?php esc_html_e( 'Views', 'psydox-wp-stats' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $top_pages_7d ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No recent page trend data yet.', 'psydox-wp-stats' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $top_pages_7d as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['page_title'] ? $row['page_title'] : __( '(No title)', 'psydox-wp-stats' ) ); ?></td>
							<td><a href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['page_url'] ); ?></a></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>
</div>
