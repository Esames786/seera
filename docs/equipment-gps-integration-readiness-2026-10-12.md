# Equipment GPS / telematics integration — readiness contract (12 October 2026)

Status: **Live GPS tracking is NOT YET OPERATIONAL.** Phase 1 of Equipment & Vehicles stores only the readiness
fields (`tracker_provider`, `tracker_device_id`) on the asset and shows a truthful "not connected" state. No
position, route, speed, idle time or geofence event is generated, polled or displayed. This document fixes what
a real provider integration needs so that Phase 2 can be scoped against an actual provider specification and
credentials; nothing here is implemented.

## 1. What Seera already has

| Element | Where | Reuse in Phase 2 |
| --- | --- | --- |
| Asset identity | `equipment_assets` (code, type, make / model, plate, serial) | The provider device maps to one asset |
| Readiness fields | `equipment_assets.tracker_provider`, `tracker_device_id` | Device ↔ asset mapping key |
| Current assignment | `equipment_assets.current_project_id / current_site_id / current_operator_id`, `equipment_assignments` | Which project / site a position belongs to |
| Site geofence | `sites.latitude / longitude / geofence_radius / geofence_enabled` and `App\Services\Hr\AttendanceGeofenceService` (Haversine, policy) | Inside / outside evaluation of positions |
| Meter | `equipment_assets.current_meter` (never moves backwards without a logged correction) | Provider odometer / engine hours can only move it forward |
| Private files, activity log, scope service | existing | Audit of ingestion errors; per-project visibility of tracking data |

## 2. Data a provider must deliver per position

| Field | Required | Notes |
| --- | --- | --- |
| Device identifier | yes | Must equal `tracker_device_id` of exactly one asset; unknown devices are rejected and logged |
| Position timestamp (UTC) | yes | Provider time, stored as given plus the server receipt time |
| Latitude / longitude | yes | WGS-84 decimal degrees; validated like mobile attendance (range, precision) |
| Speed | optional | km/h; unit stated by the provider |
| Heading | optional | degrees |
| Ignition state | optional | on / off |
| Odometer | optional | km; applied to the asset meter only when higher than the current reading |
| Engine hours | optional | h; same rule for hour-meter assets |
| Accuracy / HDOP | optional | used to discard bad fixes |
| Provider event (geofence / idle / overspeed) | optional | stored as provider events, not re-derived |

## 3. Integration model to decide with the provider

- **Transport**: webhook push (provider → Seera HTTPS endpoint with signed payloads) or polling (Seera → provider REST API on a schedule). Polling needs a queue worker / cron on cPanel; webhooks need a public signed endpoint.
- **Authentication**: API key / OAuth client credentials / HMAC signature — stored in server configuration, never in the database or the repository.
- **API base URL, rate limits, pagination, retention** of the provider's history endpoint.
- **Retention in Seera**: raw positions are high-volume; keep the latest position per asset plus a daily summary, and purge raw rows after an agreed window.
- **Mapping maintenance**: who registers a new device, how a swapped tracker is re-mapped, what happens to an unmapped device.
- **Privacy / policy**: positions of vehicles driven by named operators are personal data; agree retention and who may view routes (likely project managers for their projects only, through the existing scope service).

## 4. Minimum Phase 2 scope (after credentials and spec exist)

1. `equipment_tracker_positions` (asset, device, provider timestamp, received at, lat / lng, speed, heading, ignition, accuracy, raw payload hash) and `equipment_tracker_events` (geofence in / out, idle, overspeed as reported by the provider).
2. Ingestion service with device → asset mapping, validation, duplicate protection (device + timestamp) and an audit of rejected payloads.
3. Latest-position and "last seen" indicator on the Equipment workspace Tracking section and on the Project / Site equipment panels; "GPS offline" when the last position is older than an agreed threshold.
4. Geofence evaluation against the assigned site's radius (reusing the attendance Haversine service) shown as a state, not as a map replay.
5. Optional meter sync from provider odometer / engine hours (forward only).
6. Tests with recorded provider payload samples; no fabricated data in production.

## 5. Out of scope until a provider is contracted

Map replay / routes, idle-time analytics, driver behaviour scoring, fuel-card integration, utilization dashboards, automatic maintenance scheduling from engine hours, depreciation.
