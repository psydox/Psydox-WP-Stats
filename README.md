# Psydox WP Stats

Psydox WP Stats is a lightweight, privacy-focused, self-hosted analytics plugin for WordPress.
It provides website statistics without relying on external analytics providers.

## Features

- Privacy-focused analytics with local data storage only
- Frontend visit tracking with role-based exclusion rules
- Human vs bot/crawler traffic separation
- Crawler analytics including top crawlers, recent crawler visits, and most crawled pages
- Device analytics including desktop, mobile, tablet, bot, and unknown device types
- Browser and operating system breakdowns
- Real-time visitor monitoring for admin users
- Dashboard cards, charts, and top lists for traffic insights
- Export support in CSV and JSON with filters
- Data retention controls and maintenance tools
- Multisite-aware activation and uninstall cleanup

## Installation

### Option 1: Install from GitHub ZIP

1. Download the latest plugin ZIP from this GitHub project.
2. In WordPress Admin, go to Plugins > Add New > Upload Plugin.
3. Upload the ZIP file and click Install Now.
4. Click Activate Plugin.

### Option 2: Install via Psydox WP Hub

1. Open Psydox W Hub from your WordPress admin area.
2. Find Psydox WP Stats in the plugin catalog.
3. Click Install, then Activate.
4. Open Psydox Plugins > Stats to start using the dashboard.

## Settings Overview

Psydox WP Stats provides controls for:

- Tracking: enable or disable tracking
- User Exclusions: administrators, editors, authors, and all logged-in users
- Data Retention: 30/90/180/365 days or unlimited
- Real-Time Monitoring: enable or disable and set refresh interval
- Dashboard Layout: choose chart columns (1, 2, 3, or 4)
- Privacy: hash IPs and toggle referrer/browser/device storage
- Bot Management: advanced bot classification, user-agent allow/deny patterns, and spike threshold tuning
- Maintenance: clear stats, rebuild database, optimize database, retention cleanup, and generate test data

## Privacy Behavior

- No external analytics services are used.
- No telemetry is sent outside your WordPress site.
- Data is stored locally in your WordPress database.
- IP hashing is enabled by default and can be controlled in settings.
- You can control retention duration and manually purge/cleanup data.
- Uninstall cleanup removes plugin data.

## Localization

- Text domain: `psydox-wp-stats`
- Translation files location: `languages/`
- POT template: `languages/psydox-wp-stats.pot`

To regenerate the POT template with WP-CLI from the plugin root:

```powershell
wp i18n make-pot . languages/psydox-wp-stats.pot --domain=psydox-wp-stats --exclude=".git,node_modules,vendor,tests"
```

## License

This project is licensed under GPLv2.