# FullParty Architecture

This document explains how FullParty is put together so AI agents can make changes
without guessing where behavior lives. It focuses on this app's architecture and
domain flow, not on generic Laravel or Vue mechanics.

FullParty is a coordination app for structured Final Fantasy XIV group activities.
The central job of the system is to keep these things consistent:

- verified player characters
- groups, members, moderators, bans, follows, invites, and notes
- activity type templates and published versions
- scheduled activities
- public and private application pages
- applicant queues
- roster slots, bench slots, assignments, swaps, and attendance
- audit logs
- in-app, email, Discord, and realtime notifications
- external progress data from Lodestone and FF Logs

## Runtime Shape

FullParty has one Laravel app serving Inertia pages and JSON endpoints to a Vue 3
frontend. Long-running behavior is handled by queue workers, the scheduler, and
Reverb.

```text
Browser
  -> Laravel web routes
  -> Controllers
  -> Domain services
  -> Eloquent models / database
  -> Inertia pages or JSON payloads
  -> Vue components

Queue workers
  -> notification deliveries
  -> system notification broadcasts
  -> optional character refresh jobs

Scheduler
  -> due run reminder notifications

Reverb
  -> private notification channels
  -> private activity management channels
```

The main entry points are:

- `routes/web.php`: public, authenticated, group dashboard, account, and admin routes.
- `routes/channels.php`: private broadcast authorization.
- `routes/console.php`: scheduled commands.
- `bootstrap/app.php`: middleware, routing, and auth/session exception behavior.
- `resources/views/app.blade.php`: Inertia root view, Vite assets, Ziggy routes,
  and the authenticated meta flag used by Echo setup.
- `resources/js/app.js`: Vue/Inertia/Nuxt UI/I18n/Ziggy boot.

## Request Surfaces

The app has four route surfaces with different architectural expectations.

### Public Surface

Public routes include:

- home and legal pages
- public group profiles
- public activity overviews
- application forms
- guest application status/edit links
- invite acceptance pages

Important characteristics:

- Groups use slug route binding: `/groups/{group:slug}`.
- Private activities use a 40-character `secret_key` in attendee-facing URLs.
- Guest applications use 40-character `guest_access_token` values for edit/status
  access.
- Public pages must not leak inaccessible private activity, group, or application
  details.

Key controllers:

- `GroupController`
- `GroupActivityController`
- `GroupActivityApplicationController`
- `GroupInviteController`

### Authenticated Dashboard Surface

Authenticated and verified users can access dashboards, account pages, character
management, group management, and notification pages.

Important characteristics:

- The route group uses `auth` and `verified` middleware.
- Group dashboard routes additionally use `group.dashboard.access`.
- Group dashboard access allows group members into safe dashboard pages, then
  finer moderator/owner permissions are checked per action.
- Activity management mutations should generally authorize through
  `GroupActivityPolicy::manageDashboard`.

Key controllers:

- `GroupDashboardController`
- `GroupMemberController`
- `GroupMembershipController`
- `GroupSettingsController`
- `GroupActivity*Controller`
- `AccountApplicationController`
- `AccountNotificationController`
- `CharacterController`
- `SettingsController`
- `UserController`

### Admin Surface

Admin routes live under `/admin` inside the authenticated route group.

Important characteristics:

- Admin access currently uses `auth()->user()?->is_admin` checks in each admin
  controller.
- Admin pages manage activity types, character classes, character definitions,
  phantom jobs, audit log visibility, system notifications, and system banners.
- Pulse access is controlled separately by the `viewPulse` gate.

Key controllers:

- `AdminController`
- `ActivityTypeController`
- `AdminCharacterController`
- `CharacterClassController`
- `PhantomJobController`
- `SystemNotificationController`

### Realtime Surface

Realtime behavior is private-channel only.

Channels:

- `App.Models.User.{id}`: user notification inbox updates.
- `groups.{groupId}.activities.{activityId}.management`: live activity dashboard
  patch updates for moderators.

Events:

- `UserNotificationsUpdated` broadcasts `.notifications.updated`.
- `ActivityManagementUpdated` broadcasts `.activity.management.updated`.

Frontend listeners live primarily in:

- `resources/js/components/Navigation/NotificationBell.vue`
- `resources/js/Pages/Dashboard/Groups/Activities/Show.vue`

## Domain Model

The app is organized around these domain clusters.

```text
User
  -> Character
  -> SocialAccount
  -> GroupMembership
  -> GroupFollow
  -> UserNotification
  -> NotificationDelivery

Group
  -> GroupMembership
  -> GroupInvite
  -> GroupBan
  -> GroupUserNote
  -> Activity

ActivityType
  -> ActivityTypeVersion
  -> ActivityTag

Activity
  -> ActivitySlot
  -> ActivitySlotFieldValue
  -> ActivityApplication
  -> ActivityApplicationAnswer
  -> ActivityProgressMilestone
  -> ActivitySlotAssignment

NotificationEvent
  -> UserNotification
  -> NotificationDelivery
  -> SystemNotificationBroadcast

AuditLog
  -> actor User
  -> polymorphic subject
```

### Users And Characters

Users authenticate by password, Google, Discord, or XIVAuth. Users must verify
email for the authenticated dashboard.

Characters represent FFXIV characters and may be:

- verified and owned by a user
- unverified guest-application character records
- primary characters for user display

Character data is enriched by:

- character classes and levels
- preferred classes
- phantom jobs and levels
- preferred phantom jobs
- occult/Forked Tower progress

Important files:

- `app/Models/User.php`
- `app/Models/Character.php`
- `app/Services/Characters/CharacterProfileRefreshService.php`
- `app/Services/Lodestone/*`
- `app/Services/FFLogs/*`

The preferred architecture is that Lodestone scraping returns DTOs and does not
know about persistence. `CharacterProfileRefreshService` decides how scraped data
updates database records.

### Groups And Membership

Groups are community containers. The group slug is the public route key.

Membership has three roles:

- `owner`
- `moderator`
- `member`

The `groups.owner_id` field is the source of ownership. Membership rows still use
role values to drive member/moderator behavior. `Group::hasModeratorAccess()` is
the key helper for dashboard management access.

Group membership creation also syncs group follows, and deleting membership
removes the follow. This means membership and follow behavior are coupled in the
model layer through `GroupMembership::booted()`.

Important files:

- `app/Models/Group.php`
- `app/Models/GroupMembership.php`
- `app/Models/GroupInvite.php`
- `app/Models/GroupBan.php`
- `app/Models/GroupUserNote.php`
- `app/Services/Groups/GroupUserNoteVisibilityService.php`

### Activity Types And Versions

Activity types are templates maintained by admins. They have editable draft fields
and immutable published versions.

The draft fields on `ActivityType` include:

- localized name and description
- layout schema
- slot schema
- application schema
- roster summary presets
- progress schema
- bench size
- prog points
- FF Logs zone ID

Publishing creates an `ActivityTypeVersion`. Activities point at the specific
version they were created from, which preserves the shape of an activity even if
the admin later edits the type draft.

Important files:

- `app/Models/ActivityType.php`
- `app/Models/ActivityTypeVersion.php`
- `app/Http/Controllers/ActivityTypeController.php`
- `resources/js/components/Admin/ActivityTypes/*`

Architectural rule: do not make existing activities silently adopt new activity
type drafts. Versioning is the compatibility boundary.

### Activities

An activity is a scheduled or planned group event based on one activity type
version.

Activity lifecycle statuses live on `Activity`:

- `draft`
- `planned`
- `scheduled`
- `assigned`
- `upcoming`
- `ongoing`
- `complete`
- `cancelled`

Current workflow uses these status transitions most heavily:

```text
planned
  -> scheduled
  -> assigned
  -> complete

planned/scheduled/assigned/upcoming/ongoing
  -> cancelled
```

Activity creation materializes:

- roster slots from the version layout schema
- slot field value rows from the version slot schema
- bench slots from the version bench size
- progress milestone rows from the version progress schema
- a private secret key when the activity is not public

Important files:

- `app/Models/Activity.php`
- `app/Http/Controllers/GroupActivityController.php`
- `app/Services/Groups/ActivityBenchSlotBackfillService.php`
- `app/Services/Groups/ActivityCompletionService.php`
- `app/Services/Groups/ActivityCancellationService.php`
- `app/Services/Groups/ActivityRosterCsvExportService.php`

### Applications

Applications connect a user or guest character to an activity.

Application statuses live on `ActivityApplication`:

- `pending`
- `approved`
- `on_bench`
- `declined`
- `cancelled`
- `withdrawn`

Signed-in applications use `user_id` and usually a verified selected character.
Guest applications store applicant snapshot fields, create or reuse an unverified
character record, and use `guest_access_token` for future access.

Application answers are stored separately in `ActivityApplicationAnswer` and are
interpreted against the activity type version's application schema.

Important files:

- `app/Models/ActivityApplication.php`
- `app/Models/ActivityApplicationAnswer.php`
- `app/Http/Controllers/GroupActivityApplicationController.php`
- `app/Http/Controllers/GroupActivityApplicationDeclineController.php`
- `app/Services/Groups/ApplicantQueue/*`
- `app/Services/Notifications/ApplicationNotificationService.php`

Architectural rule: application pages serialize schema and existing answers from
the activity's version. Do not interpret application data against the current
editable activity type draft.

### Rosters, Slots, Bench, And Assignments

Roster state has two layers:

- `ActivitySlot`: the current UI state of a roster or bench slot.
- `ActivitySlotAssignment`: assignment history and attendance state.

`ActivitySlot` stores current assigned character and designation flags such as
host and raid leader. `ActivitySlotAssignment` stores source, timestamps,
attendance state, field value snapshots, and end times.

Bench slots are normal slots with `group_key = bench`, interpreted by
`ActivitySlotBench`.

The high-risk roster workflows are owned by services:

- `ActivitySlotAssignmentService`: assigning applications, manual assignment,
  reassignment, moving between main slots and bench, displaced applicant handling,
  assignment snapshots, audit, and assignment notifications.
- `ActivitySlotAttendanceService`: check-in, late, missing, undo, and active
  assignment upkeep.
- `ActivitySlotDesignationService`: host and raid leader designation rules.
- `ActivitySlotStateTokenService`: optimistic concurrency tokens for slot
  mutations.
- `ActivitySlotSerializer`: normalized slot payloads for the management UI and
  realtime patches.

Slot mutation endpoints require state tokens. A 409 means the client was acting on
stale slot state and should refetch management data.

Important files:

- `app/Models/ActivitySlot.php`
- `app/Models/ActivitySlotAssignment.php`
- `app/Services/Groups/ActivitySlotAssignmentService.php`
- `app/Services/Groups/ActivitySlotAttendanceService.php`
- `app/Services/Groups/ActivitySlotDesignationService.php`
- `app/Services/Groups/ActivitySlotStateTokenService.php`
- `app/Http/Controllers/GroupActivitySlot*Controller.php`

Architectural rule: direct slot updates are dangerous. Use the services unless the
change is strictly read-only or serialization-only.

### Progress And Completion

Progress is defined by the activity type version:

- `prog_points` represent target or furthest-progress labels.
- `progress_schema.milestones` materializes into `ActivityProgressMilestone`.
- FF Logs matching metadata may exist on milestones.

Completion is handled by `ActivityCompletionService`. It supports manual progress
entry and FF Logs-assisted completion when the activity type version has a usable
FF Logs zone and milestone matchers.

Important files:

- `app/Models/ActivityProgressMilestone.php`
- `app/Services/Groups/ActivityCompletionService.php`
- `app/Services/FFLogs/ActivityReportProgressFetcher.php`
- `app/Http/Controllers/GroupActivityFflogsCompletionPreviewController.php`

## Backend Layering

### Controllers

Controllers are route adapters. They should authorize, validate, call services,
and return Inertia, JSON, redirects, or streams.

Current controller patterns:

- Inertia pages usually get compact initial props.
- Large dashboard pages fetch detailed JSON after mount.
- Mutation controllers return serialized fragments needed by the current page.
- Some older controllers still contain too much domain logic; prefer services for
  new complex behavior.

### Services

Services own workflow consistency. Many important services combine:

- database transactions
- model state changes
- audit logging
- notification dispatch
- realtime patches

When changing a workflow, inspect service collaborators before adding logic in a
controller. The architecture expects side effects to live near the domain
workflow, not scattered across UI endpoints.

### Models

Models provide:

- relationships
- casts
- fillable fields
- constants
- small helpers
- a few lifecycle hooks

Models should not become workflow coordinators. If behavior touches multiple
models, use a service.

### Support Classes

`app/Support` contains small enum-like classes for audit and notification
concepts. These are used to keep category/severity/channel values consistent
across services and tests.

Important files:

- `app/Support/Audit/AuditScope.php`
- `app/Support/Audit/AuditSeverity.php`
- `app/Support/Notifications/NotificationCategory.php`
- `app/Support/Notifications/NotificationChannel.php`

### DTOs

DTOs are used heavily for parsed Lodestone/FFXIV progress data. They should stay
immutable and persistence-free.

Important files:

- `app/DTOs/LodestoneCharacterData.php`
- `app/DTOs/LodestoneUrls.php`
- `app/DTOs/MainJobsData.php`
- `app/DTOs/OccultData.php`
- `app/DTOs/PhantomJobData.php`

## Frontend Architecture

### Boot And Shared Data

The browser starts from `resources/views/app.blade.php`, which loads Vite assets,
Ziggy route definitions, and the Inertia root.

`resources/js/app.js` installs:

- Inertia Vue plugin
- Nuxt UI
- Ziggy Vue integration
- Vue I18n
- default `DefaultLayout`

`HandleInertiaRequests` shares app-wide props:

- flash messages
- authenticated user with primary character and social accounts
- group quick links
- notification summary
- system banner
- project links
- legal config values
- datacenter lookup options
- locale metadata

Frontend code should treat shared props as cross-cutting context, not as a dumping
ground for feature-specific payloads.

### Layouts And Navigation

`DefaultLayout` wraps most pages in Nuxt UI dashboard primitives and wires:

- sidebar navigation
- topbar
- optional system banner
- group navigation on group dashboard URLs
- dashboard footer
- Nuxt UI locale

Important files:

- `resources/js/Layouts/DefaultLayout.vue`
- `resources/js/components/Navigation/CSidebar.vue`
- `resources/js/components/Navigation/CTopbar.vue`
- `resources/js/components/Navigation/NotificationBell.vue`
- `resources/js/components/Groups/GroupNavigation.vue`
- `resources/js/components/SystemBanner.vue`

### Page Data Flow

The frontend uses two data-loading styles.

Many pages receive all data from the Inertia response:

```text
Controller
  -> Inertia::render(page, props)
  -> Page component
  -> child components
```

The activity management show page receives only group/activity identifiers first,
then loads operational data through JSON:

```text
GroupActivityController@show
  -> Inertia page with group and activity id
  -> Show.vue mounted
  -> GET groups.dashboard.activities.management-data
  -> GroupActivityManagementDataController
  -> ActivitySlotSerializer and builders
  -> activityData ref
```

This second style exists because activity management data is large, interactive,
and updated through realtime patches.

The account settings surface now uses focused components under
`resources/js/components/Settings/*`. Do not treat
`resources/js/components/Pages/Settings/*` as the pattern for new work.

### Activity Management Frontend

`resources/js/Pages/Dashboard/Groups/Activities/Show.vue` is the main management
orchestrator. It connects:

- `ActivityOverview`
- `RosterAssignments`
- `ApplicantQueue`
- assignment modals
- manual assignment modal
- completion modal
- JSON endpoints
- Reverb management patches
- custom browser events used between queue and roster components

Important data sources:

- `management-data`: full activity management payload.
- `applicant-queue`: pending applications and filter data.
- `applicant-queue.application`: one application payload for modal/detail refresh.
- slot assignment endpoints: mutation payloads and updated serialized slots.
- attendance/designation/missing endpoints: mutation payloads and updated slots.

Realtime patches are applied locally by `applyManagementPatch()` and can include:

- updated slots
- pending application count
- queue application sync IDs
- queue application remove IDs
- missing assignment upserts/removals

Architectural rule: this page is an orchestration layer. New persistent business
rules should be backend services first, then exposed as narrow payloads.

### Applicant Queue Frontend

`ApplicantQueue.vue` owns the moderator queue UI. It:

- fetches pending queue payloads
- filters applicants by answers/progress/user stats
- opens details and group member notes
- reacts to custom events from roster actions
- refetches individual applications when realtime patches say they need sync

It does not own slot assignment behavior; it emits or dispatches intent that the
management page and backend services resolve.

### Frontend Type And Utility Boundaries

Current frontend types are partly local to component folders and partly inline in
Vue files. Future shared types should move toward `resources/js/Types`.

Utility files in `resources/js/utils` should remain pure. Current examples:

- `activityLifecycle.ts`: frontend mirrors of activity lifecycle predicates.
- `activityStatusMeta.ts`: status presentation metadata.
- `localizedValue.ts`: localized object fallback logic.
- `notificationPresentation.ts`: notification display metadata and formatting.
- `slugify.ts`: string normalization.

Composables are for reactive shared behavior. Current example:

- `usePersistentLocale.ts`: syncs backend locale, Vue I18n locale, Nuxt UI locale,
  and locale update posts.

## Notifications Architecture

Notifications have three layers:

```text
NotificationEvent
  -> UserNotification records for in-app inbox
  -> NotificationDelivery records for off-site delivery
  -> realtime UserNotificationsUpdated event
```

`NotificationService` is the central creation and dispatch service. Domain-specific
services call it:

- `ApplicationNotificationService`
- `AssignmentNotificationService`
- `RunNotificationService`
- `GroupUpdateNotificationService`
- `AccountCharacterNotificationService`
- `SystemNotificationService`

Notification categories and channels come from support classes. User preference
fields live on `users`.

Off-site delivery:

- email deliveries become `SendNotificationEmailDeliveryJob`
- Discord delivery currently goes through `DiscordNotificationDeliveryService`
- delivery outcomes are stored on `NotificationDelivery`

System notifications:

- admins create broadcasts or banners
- broadcast jobs chunk recipients by user ID range
- mandatory events can bypass normal preference filtering

Frontend notification display:

- shared notification summary comes from `HandleInertiaRequests`
- `NotificationBell.vue` listens for `.notifications.updated`
- the bell refetches summary as a fallback and on visibility changes
- `notificationPresentation.ts` maps event/category to icons and display text

## Audit Architecture

Audit logs record important user, group, activity, application, admin, and roster
events.

Core pieces:

- `AuditLog` model stores action, severity, scope, subject, actor, message key,
  metadata, and timestamp.
- `AuditLogger` writes generic audit records.
- `GroupActivityAuditService` writes activity-specific audit events.
- Audit message strings are localized under `lang/*/audit_log.json`.

Architecture rule: if a workflow changes user-visible coordination state, consider
whether an audit log already exists for adjacent workflows and keep the audit trail
consistent.

## External Integration Architecture

### Lodestone

Lodestone integration is intentionally split:

```text
input normalizer
  -> HTTP client
  -> HTML parsers
  -> immutable DTOs
  -> character refresh service or controller decides persistence
```

Important files:

- `LodestoneInputNormalizer`
- `LodestoneHttpClient`
- `LodestoneScraper`
- `LodestoneProfileParser`
- `LodestoneClassJobParser`
- parser classes for main jobs, Eureka, Bozja, and Occult progress

Architectural rule: parsers should not know about users, groups, applications, or
database writes.

### FF Logs

FF Logs integration supports character and activity progress.

Important files:

- `ActivityReportProgressFetcher`
- `CharacterZoneProgressFetcher`
- `ForkedTowerBloodProgressFetcher`

FF Logs data is optional. Character refresh catches FF Logs failures and preserves
fallback progress data, because Lodestone refresh should still succeed when FF
Logs is unavailable.

### Social Auth And XIVAuth

Password auth is handled by `AuthController`. Social providers have their own
controllers:

- `GoogleAuthController`
- `DiscordAuthController`
- `XIVAuthController`

Social provider config lives in `config/services.php`. Social account links are
stored in `SocialAccount`.

## Localization Architecture

Backend middleware chooses the locale from session, cookie, or app config and
stores it in the session.

Supported locales are defined in `ApplyLocale`:

- `en`
- `de`
- `fr`
- `ja`

Frontend I18n loads JSON files from `lang/<locale>/**/*.json` through
`resources/js/lang.js`. Email notification labels use PHP translation files under
`lang/<locale>/email`.

Localized database fields are generally stored as objects keyed by locale, for
example activity type names, layout labels, slot labels, schema labels, and roster
summary labels. The frontend resolves these with `localizedValue()`.

Architecture rule: schema-defined labels travel as localized objects. Do not
flatten them to English-only strings in API payloads unless the value is strictly
internal.

## Data Persistence And Database Assumptions

The product target is PostgreSQL. The default test suite uses SQLite in memory,
and CI also runs a PostgreSQL suite.

Important persistence patterns:

- Many schema-like fields are JSON arrays/objects.
- Activity type versioning preserves historical schema compatibility.
- Activity slots are materialized rows, not computed from schema on every request.
- Active assignment history is represented by `ActivitySlotAssignment.ended_at`
  being null.
- Guest application access relies on generated 40-character tokens.
- Private activity access relies on generated 40-character secret keys.
- Notification delivery state is persisted so queued/off-site outcomes can be
  retried, skipped, or inspected.

When adding persistence changes, consider both:

- SQLite compatibility for the default test suite.
- PostgreSQL behavior for production intent and CI.

## Background Processes

Local and production-like FullParty needs these workers for full behavior:

- queue worker: emails, off-site notification jobs, system notification chunks
- scheduler: due run reminders
- Reverb: live notification and activity-management patches
- Vite: local frontend development
- Pulse: operational visibility for admins

Scheduled command:

- `notifications:dispatch-run-reminders` runs every minute from `routes/console.php`.

Queue jobs:

- `SendNotificationEmailDeliveryJob`
- `DispatchSystemNotificationBroadcastJob`
- `DispatchSystemNotificationBroadcastChunkJob`
- `RefreshCharacterFromLodestone` exists as an example/optional queued refresh
  path; the current richer refresh logic lives in `CharacterProfileRefreshService`.

## Testing Architecture

Tests are Pest tests.

Default suite:

- `php artisan test`
- SQLite in memory
- sync queue
- array mail
- disabled broadcasting

PostgreSQL suite:

- `php vendor/bin/pest --configuration=phpunit.pgsql.xml`

CI runs both suites in `.github/workflows/backend-tests.yml`.

Most behavior coverage is in `tests/Feature`. Unit coverage currently exists for
Lodestone character search. Feature tests commonly use:

- `RefreshDatabase`
- factories
- `$this->actingAs()`
- named routes
- `Queue::fake()`
- `Event::fake()`
- database assertions

Architectural rule: tests should verify full workflow outcomes where side effects
matter, not just controller status codes. For roster and notification behavior,
assert database state plus emitted jobs/events when relevant.

## Change Impact Checklist For Agents

When changing a feature, locate the workflow owner before editing:

- Character/profile refresh: `CharacterController`,
  `CharacterProfileRefreshService`, Lodestone/FF Logs services.
- Group membership/moderation: group controllers, group model helpers,
  `GroupUpdateNotificationService`, audit services.
- Activity type schema: `ActivityTypeController`, admin activity type components,
  activity type/version models.
- Activity lifecycle: `GroupActivityController`,
  `ActivityCompletionService`, `ActivityCancellationService`,
  `RunNotificationService`, `GroupUpdateNotificationService`.
- Application submission/edit/decline: `GroupActivityApplicationController`,
  `GroupActivityApplicationDeclineController`, applicant queue builders,
  `ApplicationNotificationService`.
- Roster assignment/swap/bench: slot mutation controllers,
  `ActivitySlotAssignmentService`, `ActivitySlotStateTokenService`,
  `ActivityManagementRealtimeService`, assignment notifications, audit.
- Attendance/missing/designations: slot mutation controllers,
  `ActivitySlotAttendanceService`, `ActivitySlotDesignationService`,
  management realtime patches, audit.
- Notifications: domain-specific notification service first, then
  `NotificationService`, delivery services, inbox serializer, frontend
  presentation.
- Realtime UI updates: backend patch shape, broadcast event, channel auth,
  frontend patch application.
- Localized UI text: `lang/*` JSON or PHP files, plus Vue `t()` usage.

Before finishing an architectural change, ask:

- Did the request touch private, guest, admin, or group-scoped access?
- Did the request touch data that also needs audit logging?
- Did the request touch data that also needs notifications?
- Did the request touch data displayed live on an activity dashboard?
- Did the request touch versioned activity type schema?
- Did the request touch JSON fields or indexes that may differ between SQLite and
  PostgreSQL?
- Did the request add user-visible strings across every supported locale?
- Did the request change frontend payload shapes that should become shared types?
