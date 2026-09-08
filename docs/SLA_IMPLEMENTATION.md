# SLA implementation

## Audit (2026-09-08)

The project uses Laravel 13 / PHP 8.3+, React 18, TypeScript, Tailwind,
Sanctum and database-backed permissions. Existing user changes are present in
ticket controllers, routes, translations and the task detail page; preserve them.
No AGENTS.md was found in the project tree.

Reuse: sla_policies, business_calendars, business_hours, calendar_holidays,
reference categories/priorities, notifications/deliveries, ticket events and
sla.manage. Existing legacy SLA target and ticket tables remain readable.

Gaps: CalculateSlaService selects the first target by priority without policy,
organization or calendar matching; SLA listeners only log; no SLA scheduler is
registered. Calendar form and API contracts disagree. Existing notification
email job records delivery without sending email and must not be reused for SLA.

## Implementation order

1. Add immutable policy versions, ticket runs/instances, events, extensions and
   escalation outbox. Keep legacy records; do not migrate historical deadlines.
2. Implement business time and deterministic matching with tests.
3. Connect lifecycle events, pause/resume, approval and indexed escalation scan.
4. Add organization-scoped APIs and server-side permissions.
5. Replace the policy metadata modal with a five-step builder, real monitoring,
   calendars and reports; add a ticket SLA panel.
6. Run isolated SQLite backend tests and TypeScript/build checks.

Policy scope, targets, pause/extension and escalation rules are stored together
as validated JSON in each immutable version, instead of separate rule tables.
Calendar hours/holidays are copied into the version so edits cannot change
historical calculations. Published policy configuration is independent of edits
to the draft. Existing tickets keep their version when category/priority changes.

## Deployment

Back up the database; deploy code and run `php artisan migrate`. Publish a default
policy for each organization before enabling SLA for new tickets. Run the Laravel
scheduler every minute. Configure a real mail transport for email notifications.
Existing tickets are not silently enrolled or given invented historical timers.
Rollback application code first; do not roll back the SLA migration after live
SLA data exists without exporting that history. The migration down method removes
only new SLA management tables and its added policy columns.

## Status (2026-09-08, continued)

Steps 1-6 are implemented and verified. `php artisan test` runs 39 tests green,
`tsc --noEmit` and `npm run build` are clean.

Fixed while finishing step 6:

- `SlaConfiguration` rejected a policy without scope. An unrestricted fallback
  policy is legal, so `scope` is now `present` instead of `required`, and the
  scope keys are normalised to a full null-filled set for matching and conflict
  detection.
- `distinct` on `escalations.*.channels.*` and `escalations.*.user_ids.*`
  compares every value under the same wildcard path, so two escalation steps
  could not share a channel or a recipient. Duplicates are now checked per step.
- SLA monitoring linked tickets to `/tasks/{id}` (the queue list) instead of the
  ticket route `/task/{id}`.
- `DatabaseOptimizationTest` still assumed the dashboard stats endpoint runs
  without a permission middleware; its query budget now covers the two role
  lookups `permission:dashboard.view` performs.

## Baseline data

`SlaBaselineSeeder` (registered in `DatabaseSeeder`, also runnable on its own)
creates two calendars per organization - 24/7 and office hours Mon-Fri
09:00-18:00 - and publishes five policies taken from the specification matrix:
DEFAULT-SLA as the fallback plus SLA-P1..SLA-P4 scoped by priority. It is
idempotent by policy code. Escalation recipients are the assignee only and
extensions ship disabled, because managers and approvers differ per
organization and must be chosen in the SLA builder.

The migration and this seeder have been applied to the local development
database (`jira_pdf_transmitter`).

## Monitoring

`GET /sla-management/monitoring` now returns, per row, the remaining business
seconds, the consumed percentage and a risk level (OK / WARNING >=75% /
HIGH >=90% / BREACHED), plus a `summary` object counting the three risk
buckets. `level=` filters the list to one bucket. The screen renders these as
the specification's "check now" block with clickable counters, a remaining /
overdue column with a progress bar, and priority and assignee filters.

Percentages are computed with the run's own calendar, so a ticket left open
overnight is not reported as at risk.

## Service catalog

`ServiceCatalogSeeder` imports the 25 services of
`Service_Catalog 07.09.2025.xlsx` into `services` and `service_offerings`, and
publishes one SLA policy per service scoped by `service_id`, carrying the
response and resolution times from the file. Services marked "24/7 support" use
the round-the-clock calendar; the rest use office hours, where a day counts as
eight working hours and a week as five working days.

`PolicyMatcher` now scores `service_id` (48) above `priority_id` (32): a time
agreed for a concrete catalog service is more specific than a generic priority
rule. Category-scoped policies still win over both.

Tickets created by the current UI carry neither `service_offering_id` nor
`category_id`, so today they match by priority only. The catalog policies take
effect as soon as a ticket references a service offering.

## Telegram bot

The bot shares the site's ticket controller for creation, so bot tickets get SLA
timers from the same observer. Its later actions, however, update ticket columns
through the query builder, which fires no Eloquent events: accepting, starting,
resolving, rejecting and reopening a ticket from Telegram left the timers
running. `BotConversationService::syncSla()` now calls the SLA engine after
those writes, and failures there are logged instead of breaking the bot flow.

Escalations can now be delivered over Telegram: `TELEGRAM` joins `IN_APP` and
`EMAIL` as an escalation channel, both in policy validation and in the wizard.
Telegram deliveries are queued like email and retried with the same backoff;
a recipient without a linked, verified bot account fails the delivery and is
retried rather than being silently dropped.

The ticket link in escalation messages pointed at `/tasks/{id}` (the queue list)
instead of the ticket route `/task/{id}`.

## Remaining

- Notification delivery currently records in-app and email intents; wire a real
  mail transport before relying on the 90%/100% escalation emails.
- Reports export covers CSV; PDF/XLSX from the specification is not implemented.
- Categories are empty in the development database, so category- and
  service-scoped policies are untested against real data.
