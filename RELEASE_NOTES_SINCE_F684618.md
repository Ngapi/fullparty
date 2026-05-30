# Changes Since `f684618`

## Fixes

- Fixed run discovery sorting/search, including tag search like `M4S`, `FRU`.
- Fixed full runs showing in discovery results.
- Fixed run overview relative time always showing `in 2h`.
- Fixed progress input validation so invalid `%` input errors properly.
- Fixed missing/off-site notifications for application and roster events.
- Fixed duplicate `starting_soon` run notifications.
- Fixed activity fallback names so payloads use activity type names instead of `Activity #id`.
- Fixed notes replies so sub-notes can be edited/deleted.

## Security

- Added security headers middleware.
- Added scoped Bearer-token auth for integration API clients.
- Added hashed/expiring Discord guild/user link tokens.
- Hardened Discord guild linking validation and error responses.
- Added admin-only integration management.
- Added admin notifications for failed integration deliveries.
- Improved upload handling with managed image processing/WebP conversion.
- Added more coverage around auth, password reset, Google auth, and integration access.

## Updates

- Major mobile/tablet responsive pass across auth, dashboard layout, run discovery, characters, groups, members, audit log, settings, notes, and group activity pages.
- Reworked run create/edit into a stepped flow with improved draft summary.
- Added proper responsive group runs calendar/agenda UI.
- Updated run/application/roster notification payloads with richer system data.
- Split and expanded activity seeders for Savage, Ultimate, and Large Content.
- Added proper activity tags and local activity images.
- Improved homepage/dashboard sections, lazy loading, account setup behavior, and upcoming/last run widgets.
- Added localization keys across new admin, landing, dashboard, Discord, notification, and group UI.

## New Features

- New landing page with hero, features, this-week preview, player/leader section, and footer.
- Global search for runs, groups, and activity types.
- User onboarding modal with persisted backend state.
- Discord app install/linking flow for users and guilds.
- Group Discord integration page with bot invite, link token, settings, health/status, and membership coverage stats.
- Integration client admin UI, healthchecks, webhook dispatching, and API scopes.
- Integration REST API for user upcoming runs, applications, primary characters, and run data.
- Featured groups admin curation system.
- XIVAuth character sync on login.
- Account completion tracking and profile/home customization.
