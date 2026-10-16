# Attendance Import

Changed files in this task:

- `app/Services/Attendance/AttendanceImportParser.php`
- `app/Http/Controllers/AttendanceImportController.php`
- `resources/views/admin/pages/attendance/import.blade.php`
- `public/assets/admin/css/crm/attendance/import.css`
- `public/assets/admin/js/crm/attendance-import.js`
- `tests/Feature/Attendance/AttendanceImportTemplateDownloadTest.php`
- `tests/Feature/Attendance/AttendanceImportCompatibility.standalone.php`
- `tests/Feature/Attendance/AttendanceImportWorkflow.standalone.php`
- `tests/Feature/Attendance/AttendanceImportBrowser.standalone.cjs`
- `docs/attendance-import.md`

The import page implements the approved Kimi layout with scoped light/dark styles, compact upload controls, mapping counters, history filters/pagination and a read-only View action. Mobile uses fixed Dashboard, Leads, Follow-up, KPI and More controls derived only from links rendered by the permission-aware CRM sidebar. Unavailable destinations remain disabled. Routes and role permissions are owned by the main integration task.

## File Compatibility

CSV and the Excel Attendance Data worksheet have exactly these six columns, in this order:

`Date, Day, Name, In, Out, Status`

Repeat the employee name on each daily row. DD/MM/YYYY is day-first; ISO and DD-MM-YYYY are also accepted. Native Bio Manager employee blocks remain supported. The supplied MonthlyPerformance workbook has A10 `Paycode:-`, D10 paycode, E10 `Name:-`, F10 employee name. Attendance columns are detected by header, including F In, I Out and N Status; lunch and other extra columns are ignored. All attendance worksheets are read with employee context reset between sheets.

Every in-range status is retained, including WO, HLD, POH, leave and unknown codes. MIS is displayed as **Miss punch**. Import does not filter rows by KPI eligibility. The separately owned KPI service counts P and MIS as present, A as absent, and ignores other statuses.

Identical duplicate employee/date rows are deduplicated. Conflicting rows for the same employee/date reject the file before any attendance is written. Invalid dates cannot roll into another month.

## Employee Mapping

Native paycodes and normalized template names use distinct temporary mapping keys. Template groups have a null real paycode. Only one exact normalized active-user match may be selected automatically; ambiguous names need an explicit choice. Saved mappings are consulted and persisted only for native paycodes. Template confirmation preserves an existing record's real paycode and never stores its temporary name key as a paycode mapping.

Every mapping must be posted explicitly. Missing, duplicate, inactive and invalid selections are rejected by the backend; the Confirm button stays disabled until all selections are complete and unique. Changing upload dates or file invalidates the displayed preview, including an in-flight response.

Confirmation resolves shift policies once per employee with `resolveForDates`, then looks up the policy by attendance date for each row. The existing shift snapshot fields and values are preserved.

## Duplicate Protection

Preview computes SHA256 from file bytes and rejects a completed import with the same hash and selected from/to dates, independently of filename. Confirmation recomputes the hash, rejects changed files, locks the import row and repeats the completed-duplicate check inside the transaction. Existing preview rows with null hashes acquire their computed hash when confirmed.

On PostgreSQL, a transaction advisory lock uses two signed 32-bit keys derived from SHA256(hash + from + to). Separate preview IDs for identical bytes and range therefore serialize before duplicate checking. Other row-locking database engines use a common user row; SQLite tests rely on SQLite transaction semantics. The main task supplies the indexed nullable `attendance_imports.file_hash` column and model fillable entry.

Historical completed rows with null hashes are checked lazily only for the selected date range. Readable original files receive hash metadata so renamed copies are also blocked; missing/pruned originals cannot be compared. No attendance rows are rewritten by this check. View shows current attendance records whose `source_import_id` matches the import, with 50 records per page; records subsequently overwritten by another import belong to that newer import. Original import totals remain visible. This is not an immutable historical snapshot.

## Verification

These standalone suites do not bootstrap the CRM, load `.env`, run live migrations, or dispatch jobs/messages. Workflow tests construct their schema only in SQLite `:memory:`. PHPUnit is absent in this checkout; the existing PHPUnit template test expectations were updated for the six-column format.

```powershell
php tests/Feature/Attendance/AttendanceImportCompatibility.standalone.php
php tests/Feature/Attendance/AttendanceImportWorkflow.standalone.php
# Optional workbook path as first argument to the compatibility suite.
# NODE_PATH must identify an installed Playwright package.
node tests/Feature/Attendance/AttendanceImportBrowser.standalone.cjs
```

Observed RED cases: native name read as Name:-, flat template not recognized, old block-style template emitted, missing posted mapping accepted, conflicting day accepted. GREEN checks cover native parsing (actual workbook: 23 employees/690 rows), actual CSV/XLSX download/parser round trips, explicit mapping/ambiguity, conflict rejection, legacy null hash, preserved real paycodes, two pre-existing previews with one completion, renamed duplicate preview rejection, hash/range boundaries, advisory-lock identity, history/detail data, and Blade rendering.

Browser checks use the real Blade content and mocked uploads: 10,001-row payload with only 50 rendered rows, pagination, mapping gating, safe text rendering, stale preview invalidation, permission-derived mobile navigation and no horizontal page overflow at 320, 390, 768, 1024 and 1440 pixels in light/dark mode. These tests use a static layout wrapper, not a live authenticated CRM session. A simultaneous PostgreSQL two-connection race has not been run; the advisory SQL contract and duplicate recheck are tested independently.
