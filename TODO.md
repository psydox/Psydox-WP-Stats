# Psydox WP Stats TODO

## Current Status

- Plugin scaffold and core OOP architecture created.
- Tracking pipeline implemented for frontend visits.
- Crawler detection, device detection, realtime monitoring, exports, settings, and maintenance actions implemented.
- Admin menu behavior corrected (no duplicate parent submenu).

## Completed

- [x] Create plugin structure and bootstrap file.
- [x] Add activation/deactivation handlers.
- [x] Create stats database table with dbDelta.
- [x] Add multisite-aware activation and uninstall cleanup.
- [x] Implement frontend tracking rules and role-based exclusions.
- [x] Track bots/crawlers separately from human traffic.
- [x] Track browser, OS, device type/brand/model (best-effort UA parsing).
- [x] Add hashed IP/session storage support.
- [x] Build admin dashboard with overview cards and data tables.
- [x] Add realtime AJAX endpoint with nonce/capability checks.
- [x] Add CSV/JSON export with filters.
- [x] Build settings page (tracking, privacy, retention, realtime, maintenance).
- [x] Fix menu hierarchy behavior for Psydox Plugins/Stats/Stats Settings.
- [x] Add plugin author link to GitHub.
- [x] Switch versioning format to YYYY.MM.DD.HHMM.
- [x] Add crawler activity summary and device summary sections.
- [x] Add mobile vs desktop comparison and device trend datasets/charts.

## In Progress

- [x] Improve chart rendering with full local Chart.js bundle (replace compatibility layer).

## Next Milestones

- [x] Add stronger input validation for export date ranges and filter combinations.
- [x] Add optional retention cleanup trigger button with confirmation and result notice.
- [x] Add paging for large dashboard tables (top pages, recent visits, crawler visits).
- [x] Add transient caching for heavy dashboard aggregate queries.
- [x] Add WP-CLI commands for maintenance and exports.
- [x] Add automated tests (PHPUnit/WP integration where feasible).
- [x] Add readme documentation for installation, settings, and privacy behavior.

## Nice To Have

- [x] Refine user-agent parsing heuristics for broader device/model coverage.
- [x] Add trend comparison cards (today vs yesterday, week over week).
- [x] Add dashboard widgets integration for quick stats.
- [x] Add localization scaffold (.pot generation workflow).

## Deferred For Now

- [ ] Add extension hooks/filters documentation for third-party integrations.
- [ ] Add UTM and campaign analytics (source/medium/campaign/term/content).
- [ ] Add goals and conversion tracking (forms, CTA clicks, checkout success).
- [ ] Add internal site search analytics (top terms, zero-result searches).
- [ ] Add entry/exit page analytics and single-page-session rate.
- [ ] Add visitor journey and funnel analytics with drop-off steps.
- [ ] Add optional privacy-safe geo analytics using local DB lookup.
- [ ] Add custom events API for frontend/backend event tracking.
- [ ] Add scheduled email reports and anomaly alerts.
- [x] Add advanced bot classification and allow/deny management.
- [ ] Add authenticated REST API endpoints for stats access.
- [ ] Add WordPress dashboard widgets for KPI snapshots.
- [ ] Add compliance helpers (export/delete/anonymize workflows).
- [ ] Add aggregation tables/background jobs for high-traffic performance.
- [ ] Add multisite network rollup dashboard.

## Candidate Statistics Features

- [ ] Add live visitors feed (latest visitors with auto-refresh).
- [ ] Add session duration estimate (first-hit to last-hit per session).
- [ ] Add single-page-session rate (bounce-like metric).
- [ ] Add entry/exit pages analytics with top entry-to-exit pairs.
- [ ] Add hourly traffic heatmap (hour x day-of-week).
- [ ] Add content performance trends (per-page 7-day movement and human/bot split).
- [ ] Add referrer quality metrics (pages/session, return rate, session duration proxy).
- [x] Add country trend table (7-day movement up/down).
- [ ] Add new vs returning visitors windows (7/30/90 days).
- [ ] Add normalized top paths view (strip query strings and rank routes).
- [x] Add bot pressure score and spike detection.
- [ ] Add scheduled summary email reports for admins.
- [ ] Add compare ranges controls (e.g., last 7 days vs previous 7 days).
- [ ] Add taxonomy analytics (categories/tags performance).
- [ ] Add lightweight conversion events (button clicks/form submits).
