(function () {
	'use strict';

	function buildConfig(label, series) {
		return {
			type: 'bar',
			data: {
				labels: (series || []).map(function (item) { return item.label; }),
				datasets: [{
					label: label,
					data: (series || []).map(function (item) { return Number(item.value || 0); }),
					backgroundColor: '#2271b1'
				}]
			}
		};
	}

	function draw(selector, label, series) {
		var canvas = document.querySelector(selector);
		if (!canvas || typeof window.Chart !== 'function') {
			return;
		}
		new window.Chart(canvas, buildConfig(label, series));
	}

	function normalizeCountryName(name) {
		return String(name || '')
			.toLowerCase()
			.replace(/&/g, ' and ')
			.replace(/[^a-z0-9\s]/g, ' ')
			.replace(/\s+/g, ' ')
			.trim();
	}

	function getCountryAliases() {
		return {
			'united states': 'united states of america',
			'russia': 'russian federation',
			'vietnam': 'viet nam',
			'south korea': 'korea republic of',
			'north korea': 'korea democratic people s republic of',
			'iran': 'iran islamic republic of',
			'syria': 'syrian arab republic',
			'tanzania': 'tanzania united republic of',
			'venezuela': 'venezuela bolivarian republic of',
			'bolivia': 'bolivia plurinational state of',
			'moldova': 'moldova republic of',
			'laos': 'lao people s democratic republic',
			'brunei': 'brunei darussalam',
			'ivory coast': 'cote d ivoire',
			'czech republic': 'czechia',
			'palestine': 'palestine state of'
		};
	}

	function drawGeoWorldMap(selector, dots) {
		var canvas = document.querySelector(selector);
		var dataRef = window.psydoxWpStatsChartData || {};
		var geoUrl = dataRef.worldGeoJsonUrl || '';

		if (!canvas || !canvas.getContext || typeof window.Chart !== 'function' || typeof window.fetch !== 'function' || !geoUrl) {
			return Promise.resolve(false);
		}

		var aliases = getCountryAliases();
		var visitsByCountry = {};
		var maxVisits = 0;

		var hexToRgb = function (hex) {
			var clean = String(hex || '').replace('#', '');
			return {
				r: parseInt(clean.substring(0, 2), 16),
				g: parseInt(clean.substring(2, 4), 16),
				b: parseInt(clean.substring(4, 6), 16)
			};
		};
		var rgbToHex = function (rgb) {
			var toHex = function (value) {
				var h = Math.max(0, Math.min(255, Math.round(value))).toString(16);
				return h.length === 1 ? '0' + h : h;
			};
			return '#' + toHex(rgb.r) + toHex(rgb.g) + toHex(rgb.b);
		};
		var mixColor = function (startHex, endHex, t) {
			var a = hexToRgb(startHex);
			var b = hexToRgb(endHex);
			var ratio = Math.max(0, Math.min(1, Number(t || 0)));
			return rgbToHex({
				r: a.r + (b.r - a.r) * ratio,
				g: a.g + (b.g - a.g) * ratio,
				b: a.b + (b.b - a.b) * ratio
			});
		};
		var getCountryFill = function (value) {
			if (!value || value <= 0) {
				return '#ffffff';
			}

			var ratio = maxVisits > 0 ? value / maxVisits : 0;
			return mixColor('#cfe8ff', '#0b5cab', ratio);
		};

		(dots || []).forEach(function (dot) {
			var country = normalizeCountryName(dot.country_name || dot.country_code || '');
			var visits = Number(dot.visits || 0);
			if (!country) {
				return;
			}

			if (!visitsByCountry[country]) {
				visitsByCountry[country] = 0;
			}
			visitsByCountry[country] += visits;
			maxVisits = Math.max(maxVisits, visitsByCountry[country]);

			if (aliases[country]) {
				if (!visitsByCountry[aliases[country]]) {
					visitsByCountry[aliases[country]] = 0;
				}
				visitsByCountry[aliases[country]] += visits;
				maxVisits = Math.max(maxVisits, visitsByCountry[aliases[country]]);
			}
		});

		return window.fetch(geoUrl)
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Unable to load world map');
				}
				return response.json();
			})
			.then(function (geo) {
				var features = geo && geo.features ? geo.features : [];
				if (!features.length) {
					throw new Error('World map features missing');
				}

				if (canvas._psydoxGeoChart && typeof canvas._psydoxGeoChart.destroy === 'function') {
					canvas._psydoxGeoChart.destroy();
				}

				canvas._psydoxGeoChart = new window.Chart(canvas.getContext('2d'), {
					type: 'choropleth',
					data: {
						labels: features.map(function (d) {
							return (d.properties && d.properties.name) ? d.properties.name : 'Unknown';
						}),
						datasets: [{
							label: 'Visits by Country',
							outline: features,
							showOutline: true,
							showGraticule: true,
							borderColor: '#9eb8d6',
							borderWidth: 0.6,
							backgroundColor: function (context) {
								var raw = context && context.raw ? context.raw : null;
								var value = raw ? Number(raw.value || 0) : 0;
								return getCountryFill(value);
							},
							data: features.map(function (feature) {
								var name = feature && feature.properties ? feature.properties.name : '';
								var key = normalizeCountryName(name);
								return {
									feature: feature,
									value: Number(visitsByCountry[key] || 0)
								};
							}
						)
						}]
					},
					options: {
						animation: false,
						plugins: {
							legend: {
								display: false
							}
						},
						scales: {
							projection: {
								axis: 'x',
								projection: 'equalEarth'
							}
						}
					}
				});

				return true;
			})
			.catch(function () {
				return false;
			});
	}

	function drawFallbackWorldMap(selector, dots) {
		var canvas = document.querySelector(selector);
		if (!canvas || !canvas.getContext) {
			return;
		}

		var ctx = canvas.getContext('2d');
		var width = canvas.width = canvas.clientWidth || 1200;
		var height = canvas.height = canvas.clientHeight || 360;
		var project = function (lng, lat) {
			return {
				x: ((Number(lng) + 180) / 360) * width,
				y: ((90 - Number(lat)) / 180) * height
			};
		};
		var drawPolygon = function (points) {
			if (!points || !points.length) {
				return;
			}

			ctx.beginPath();
			points.forEach(function (point, index) {
				var p = project(point[0], point[1]);
				if (index === 0) {
					ctx.moveTo(p.x, p.y);
				} else {
					ctx.lineTo(p.x, p.y);
				}
			});
			ctx.closePath();
			ctx.fill();
			ctx.stroke();
		};

		ctx.clearRect(0, 0, width, height);

		// Ocean background and map frame.
		ctx.fillStyle = '#dff1ff';
		ctx.fillRect(0, 0, width, height);
		ctx.strokeStyle = '#9ec6df';
		ctx.lineWidth = 1;
		ctx.strokeRect(0.5, 0.5, width - 1, height - 1);

		// Graticule.
		ctx.strokeStyle = '#c3dff0';
		for (var lon = -120; lon <= 120; lon += 60) {
			var x = project(lon, 0).x;
			ctx.beginPath();
			ctx.moveTo(x, 0);
			ctx.lineTo(x, height);
			ctx.stroke();
		}
		for (var lat = -60; lat <= 60; lat += 30) {
			var y = project(0, lat).y;
			ctx.beginPath();
			ctx.moveTo(0, y);
			ctx.lineTo(width, y);
			ctx.stroke();
		}

		// Simplified continent polygons in lon/lat to show a clear world map shape.
		ctx.fillStyle = '#b8dba4';
		ctx.strokeStyle = '#8fb57e';
		ctx.lineWidth = 1;

		var continents = [
			[[-168, 72], [-140, 70], [-124, 56], [-116, 44], [-106, 32], [-98, 22], [-90, 18], [-86, 25], [-80, 30], [-74, 41], [-67, 47], [-58, 53], [-62, 60], [-78, 70], [-110, 73], [-145, 74]], // North America
			[[-82, 12], [-76, 8], [-72, -2], [-70, -10], [-66, -18], [-62, -30], [-58, -40], [-52, -52], [-44, -54], [-36, -40], [-36, -20], [-44, -6], [-54, 2], [-66, 8]], // South America
			[[-10, 36], [-2, 42], [10, 46], [26, 50], [40, 56], [30, 64], [16, 66], [2, 60], [-6, 52]], // Europe
			[[-18, 34], [4, 36], [20, 30], [30, 18], [34, 6], [40, -6], [38, -20], [30, -32], [20, -35], [8, -34], [-4, -26], [-12, -8], [-16, 10]], // Africa
			[[34, 32], [48, 36], [64, 44], [78, 52], [96, 56], [114, 52], [128, 48], [142, 44], [154, 32], [152, 18], [136, 12], [118, 8], [104, 16], [90, 22], [80, 18], [72, 10], [60, 14], [50, 20], [40, 22]], // Asia
			[[112, -12], [128, -14], [142, -20], [152, -30], [150, -40], [136, -44], [122, -40], [114, -30]], // Australia
			[[-52, 82], [-42, 82], [-34, 76], [-40, 70], [-50, 72], [-58, 78]], // Greenland
			[[46, -14], [52, -16], [50, -24], [44, -24], [42, -18]] // Madagascar
		];

		continents.forEach(drawPolygon);

		var maxVisits = 1;
		(dots || []).forEach(function (dot) {
			var visits = Number(dot.visits || 0);
			if (visits > maxVisits) {
				maxVisits = visits;
			}
		});

		(dots || []).forEach(function (dot) {
			if (typeof dot.lat === 'undefined' || typeof dot.lng === 'undefined') {
				return;
			}

			var lng = Number(dot.lng);
			var lat = Number(dot.lat);
			var visits = Number(dot.visits || 0);
			var p = project(lng, lat);
			var x = p.x;
			var y = p.y;
			var radius = Math.max(3, Math.min(14, 3 + (visits / maxVisits) * 11));

			ctx.beginPath();
			ctx.fillStyle = 'rgba(226, 77, 61, 0.28)';
			ctx.arc(x, y, radius * 1.8, 0, Math.PI * 2);
			ctx.fill();

			ctx.beginPath();
			ctx.fillStyle = 'rgba(226, 77, 61, 0.95)';
			ctx.arc(x, y, radius, 0, Math.PI * 2);
			ctx.fill();
		});
	}

	if (!window.psydoxWpStatsChartData) {
		return;
	}

	var data = window.psydoxWpStatsChartData;
	draw('#psydox-chart-daily', 'Daily Visits', data.daily || []);
	draw('#psydox-chart-weekly', 'Weekly Visits', data.weekly || []);
	draw('#psydox-chart-monthly', 'Monthly Visits', data.monthly || []);
	draw('#psydox-chart-device-trends', 'Device Trends', data.deviceTrends || []);
	draw('#psydox-chart-devices', 'Device Breakdown', data.devices || []);
	draw('#psydox-chart-mobile-desktop', 'Mobile vs Desktop', data.mobileVsDesktop || []);
	draw('#psydox-chart-browsers', 'Browser Breakdown', data.browsers || []);
	draw('#psydox-chart-os', 'Operating Systems', data.os || []);
	draw('#psydox-chart-countries', 'Country Breakdown', data.countries || []);
	draw('#psydox-chart-human-bot', 'Human vs Bot', data.humanVsBot || []);
	draw('#psydox-chart-crawlers', 'Crawler Activity', data.crawlers || []);
	draw('#psydox-chart-realtime', 'Real-time Activity', data.daily || []);
	drawGeoWorldMap('#psydox-world-map', data.countryDots || []).then(function (rendered) {
		if (!rendered) {
			drawFallbackWorldMap('#psydox-world-map', data.countryDots || []);
		}
	});
})();
