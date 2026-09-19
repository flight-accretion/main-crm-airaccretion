# Attendance Upload Range and Shift Policy Design

## Purpose

The CRM attendance feature needs to support range-based uploads and dynamic office timing. HR/admin users should be able to upload attendance for a From Date and To Date, define multiple office time policies, assign policies to selected CRM employees, and configure grace minutes from the UI. KPI attendance scoring should use the correct employee-specific policy for each attendance date.

## Current State

- Attendance import now accepts `from_date` and `to_date` in the controller and UI.
- Attendance rows are parsed from uploaded XLSX, XLS, or CSV files and stored as one final row per `user_id` plus `attendance_date`.
- The database has two range-date migrations. `2026_09_19_115449_change_attendance_import_date_to_range` has run, while `2026_09_19_122050_change_selected_date_to_attendance_date_range` is still pending and fails because `from_date` already exists.
- KPI attendance scoring reads one global `kpi.attendance.shift_start` and `kpi.attendance.grace_minutes` value from config, so all employees share the same punctuality cutoff.

## Goals

- Fix attendance range migration state without losing existing imported data.
- Make office time and grace period configurable from the CRM UI.
- Allow admins to assign different office times to different employees.
- Allow an employee to have multiple office time assignments across time, using effective dates.
- Keep attendance import focused on raw attendance data and keep punctuality policy in a separate resolver.
- Keep KPI scoring transparent by exposing the applied shift and grace details in evidence.

## Non-Goals

- Do not change the uploaded attendance file format.
- Do not make imported attendance rows store final punctual/late status as a permanent value. Late/punctual should be calculated from policy so changed assignments can be reflected.
- Do not merge this with lead allocation office hours. Attendance office time is employee-specific and date-sensitive; lead allocation office hours are routing configuration.

## Data Model

### `attendance_imports`

Use `from_date` and `to_date` as the selected upload range.

Recommended cleanup:

- Update the base attendance import migration for fresh installs to create `from_date` and `to_date` directly.
- Keep compatibility for existing databases by guarding the pending duplicate migration or removing the duplicate migration if it has not shipped anywhere else.
- Add an index on `status`, `from_date`, and `to_date`.
- Stop using `selected_date` in new code.

### `attendance_shift_policies`

Stores reusable office time rules.

Fields:

- `id` UUID primary key
- `name` string
- `start_time` time
- `end_time` time nullable
- `grace_minutes` unsigned small integer
- `is_default` boolean
- `is_active` boolean
- `created_by` UUID nullable
- `updated_by` UUID nullable
- timestamps

Rules:

- At most one active default policy.
- `start_time` is required.
- `grace_minutes` should be between 0 and 240.
- `end_time` is optional for attendance KPI because punctuality uses clock-in, but keeping it supports future reporting.
- Inactive policies remain for history but cannot be assigned to new users.

### `attendance_user_shift_assignments`

Stores user-specific effective-dated policy assignments.

Fields:

- `id` UUID primary key
- `user_id` UUID
- `shift_policy_id` UUID
- `effective_from` date
- `effective_to` date nullable
- `created_by` UUID nullable
- `updated_by` UUID nullable
- timestamps

Rules:

- An employee can have multiple assignments over time.
- Assignments for the same employee must not overlap.
- `effective_to` must be null or greater than or equal to `effective_from`.
- If no assignment matches a date, the default shift policy is used.

## Backend Design

### Services

Create `AttendanceShiftResolver`.

Responsibilities:

- Resolve the applicable shift policy for a user on a specific date.
- Prefer the matching effective-dated employee assignment.
- Fall back to the active default policy.
- Fall back to the legacy config value only if no default policy exists, so deployment remains safe during migration.
- Provide a bulk/month resolver path for KPI calculations to avoid one query per user/date.

Create or extend an attendance settings controller.

Responsibilities:

- List policies and current assignments.
- Create and update shift policies.
- Bulk assign selected users to a policy with effective dates.
- Validate no overlapping assignments.
- Optionally close an existing open-ended assignment before creating a new one.

### KPI Resolver

Update `SalesAttendanceKpiResolver`:

- Inject `AttendanceShiftResolver`.
- For each scheduled attendance date, resolve the policy for that user/date.
- Calculate cutoff as `attendance_date + policy.start_time + policy.grace_minutes`.
- Keep the existing attendance denominator behavior:
  - Missing uploaded rows do not count as absence.
  - Weekly off and holiday statuses do not enter the denominator.
  - Explicit absence or missing valid clock-in counts as absent.
- Include policy evidence:
  - default shift policy name if only one policy applies
  - per-policy breakdown if multiple policies apply in the month
  - `grace_minutes`
  - `punctual_cutoff`

## UI Design

Add an Attendance Settings area near the existing Attendance Import page.

Views:

- Attendance Import
  - Keep From Date, To Date, file upload, preview, employee mapping, and recent imports.
  - Improve validation messages so they reference From Date and To Date.

- Office Time Policies
  - Table of policies with name, start time, end time, grace minutes, default flag, active flag, and actions.
  - Create/edit form for policy details.
  - Mark one policy as default.

- Employee Office Time Assignments
  - Searchable/filterable employee list with checkboxes.
  - Bulk action form:
    - selected office time policy
    - effective from
    - optional effective to
  - Current assignment display per employee.
  - Previous assignments shown in a compact history table.

## Migration Strategy

1. Fix the duplicate range-date migration so `php artisan migrate` can run on the current database.
2. Adjust the original attendance import migration for fresh installs to use `from_date` and `to_date`.
3. Add shift policy and assignment migrations.
4. Seed a default policy from existing config:
   - `KPI_ATTENDANCE_SHIFT_START`, default `10:30`
   - `KPI_ATTENDANCE_GRACE_MINUTES`, default `15`
5. Keep config fallback in the resolver for safety, but the UI-managed default policy becomes the normal source of truth.

## Error Handling

- Upload preview should reject missing or invalid From/To dates.
- Upload preview should reject `to_date` before `from_date`.
- Migration cleanup should avoid duplicate column errors.
- Shift policy creation should reject invalid time and grace values.
- Employee assignment should reject overlapping date ranges and inactive shift policies.
- KPI calculation should still return an empty/safe result if attendance rows or policies are missing.

## Verification

Automated checks:

- `php artisan migrate`
- Unit tests for:
  - duplicate migration safety
  - attendance parser range filtering
  - shift policy resolution fallback
  - assignment overlap validation
  - KPI punctual/late calculation with different users on different shifts

Manual checks:

- Upload an attendance file for a date range and confirm outside-range rows are ignored.
- Create two office time policies with different start times and grace minutes.
- Assign different users to different policies.
- Confirm KPI attendance uses each employee's assigned cutoff.
- Change a future effective assignment and confirm historical dates still use the old policy.

## Implementation Notes

- Keep imported attendance records raw and stable.
- Put all date-sensitive shift logic in `AttendanceShiftResolver`.
- Avoid direct config reads from KPI resolver once policies exist.
- Use eager loading or bulk lookup in KPI paths so team dashboards remain fast.
- Do not include generated cache files in attendance feature commits.
