(function ($) {
	'use strict';

	if (!window.psydoxWpStatsRealtime) {
		return;
	}

	var cfg = window.psydoxWpStatsRealtime;
	var intervalMs = Math.max(5000, Number(cfg.refreshInterval || 10) * 1000);

	function escHtml(value) {
		return $('<div/>').text(value == null ? '' : String(value)).html();
	}

	function renderRows($target, rows, type) {
		if (!$target.length) {
			return;
		}
		if (!rows || !rows.length) {
			$target.html('<tr><td colspan="6">No data</td></tr>');
			return;
		}

		var html = '';
		rows.forEach(function (row) {
			if (type === 'crawler') {
				html += '<tr>' +
					'<td>' + escHtml(row.crawler_name || 'Bot') + '</td>' +
					'<td>' + escHtml(row.page_url || '') + '</td>' +
					'<td>' + escHtml(row.last_activity_at || '') + '</td>' +
					'</tr>';
				return;
			}
			html += '<tr>' +
				'<td>' + escHtml(row.visitor_type || '') + '</td>' +
				'<td>' + escHtml(row.page_url || '') + '</td>' +
				'<td>' + escHtml(row.browser || '') + '</td>' +
				'<td>' + escHtml(row.device_type || '') + '</td>' +
				'<td>' + escHtml(row.referrer || '-') + '</td>' +
				'<td>' + escHtml(row.last_activity_at || '') + '</td>' +
				'</tr>';
		});
		$target.html(html);
	}

	function updateRealtime() {
		$.post(cfg.ajaxUrl, {
			action: 'psydox_wp_stats_realtime',
			nonce: cfg.nonce
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}
			$('#psydox-active-visitors').text(response.data.active_visitors || 0);
			renderRows($('#psydox-recent-visits-body'), response.data.recent_visits || [], 'visit');
			renderRows($('#psydox-recent-crawlers-body'), response.data.recent_crawlers || [], 'crawler');
		});
	}

	updateRealtime();
	setInterval(updateRealtime, intervalMs);
})(jQuery);
