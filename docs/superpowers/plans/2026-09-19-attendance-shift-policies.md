# Attendance Shift Policies Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build scalable attendance upload range handling plus UI-managed office time policies, employee assignments, and employee-specific KPI punctuality calculation.

**Architecture:** Attendance import remains a raw data ingestion flow. Shift policy and grace-period rules live in dedicated tables and are resolved per employee/date through `AttendanceShiftResolver`, with KPI attendance reading that resolver instead of static config.

**Tech Stack:** Laravel 8.75, PHP 8.2, Eloquent models, Blade, PHPUnit 9, Carbon, PhpSpreadsheet already present in the project.

**Spec:** `docs/superpowers/specs/2026-09-19-attendance-shift-policy-design.md`

## Global Constraints

- Do not add new Composer or npm dependencies.
- Keep imported attendance records raw; do not persist final punctual/late status.
- Use `from_date` and `to_date` for attendance import range; new code must not require `selected_date`.
- Attendance settings routes use the existing `ATTENDANCE_IMPORT_ROLES` middleware.
- Grace minutes are configurable from the UI and must validate from `0` through `240`.
- Employee shift assignments are effective-dated and must not overlap for the same employee.
- If no assignment matches a user/date, use the active default shift policy.
- If no active default shift policy exists, fall back to legacy config values so KPI does not crash during deployment.
- Do not commit generated cache files such as `bootstrap/cache/packages.php` or `bootstrap/cache/services.php`.

## Review Focus

- Existing database already has `from_date` and `to_date`: pending migration must no-op instead of throwing duplicate-column errors. Covered by Task 1 migration source test and `php artisan migrate`.
- Existing database still has non-null `selected_date`: migration must make it non-blocking so `AttendanceImport::create()` can insert only `from_date` and `to_date`. Covered by Task 1 migration source test and `php artisan migrate`.
- Assignment range boundaries: overlapping assignments for one employee are rejected, but an assignment starting the day after the old `effective_to` is accepted. Covered by Task 4 controller tests.
- Missing default policy: shift resolver returns a legacy config-backed policy instead of crashing. Covered by Task 3 resolver test.
- Multiple office times in one KPI month: attendance KPI uses the policy effective on each attendance date, not one monthly/global cutoff. Covered by Task 5 KPI resolver test.

---

## File Map

- Modify `database/migrations/2026_09_18_193505_create_attendance_import_tables.php`: fresh installs create `from_date` and `to_date` directly.
- Modify `database/migrations/2026_09_19_115449_change_attendance_import_date_to_range.php`: guard column adds/drops so fresh installs and partially migrated databases are safe.
- Modify `database/migrations/2026_09_19_122050_change_selected_date_to_attendance_date_range.php`: convert it into the compatibility migration that backfills range columns and makes legacy `selected_date` non-blocking.
- Create `database/migrations/2026_09_19_130000_create_attendance_shift_policy_tables.php`: shift policy and assignment tables plus seeded default policy.
- Create `app/Models/AttendanceShiftPolicy.php`: reusable office time policy model.
- Create `app/Models/AttendanceUserShiftAssignment.php`: effective-dated user assignment model.
- Create `app/Services/Attendance/AttendanceShiftResolver.php`: per-date and bulk shift resolution.
- Create `app/Http/Controllers/AttendanceSettingsController.php`: office time policy and bulk assignment actions.
- Modify `app/Http/Controllers/AttendanceImportController.php`: validation copy for From Date and To Date.
- Modify `app/Services/Kpi/SalesAttendanceKpiResolver.php`: replace global config shift lookup with `AttendanceShiftResolver`.
- Create `resources/views/admin/pages/attendance/settings.blade.php`: office time policies and employee assignment UI.
- Modify `resources/views/admin/layouts/header.blade.php`: add Attendance Settings menu item near Attendance Import.
- Modify `routes/web.php`: add attendance settings routes under the existing attendance route group.
- Modify `tests/Feature/Kpi/KpiFeatureTestCase.php`: add attendance policy/assignment/record tables for KPI tests.
- Create `tests/Feature/Attendance/AttendanceFeatureTestCase.php`: isolated in-memory attendance test schema.
- Create `tests/Unit/AttendanceImportDateRangeMigrationTest.php`: source-level migration/date validation guard tests.
- Create `tests/Feature/Attendance/AttendanceShiftResolverTest.php`: resolver behavior.
- Create `tests/Feature/Attendance/AttendanceSettingsControllerTest.php`: policy and assignment validation.
- Create `tests/Feature/Kpi/SalesAttendanceKpiResolverShiftPolicyTest.php`: KPI uses date-specific shift policies.
- Create `tests/Unit/AttendanceSettingsViewTest.php`: view contract for searchable checkbox assignment UI.

### Task 1: Repair Attendance Import Range Migrations

**Files:**
- Modify: `database/migrations/2026_09_18_193505_create_attendance_import_tables.php`
- Modify: `database/migrations/2026_09_19_115449_change_attendance_import_date_to_range.php`
- Modify: `database/migrations/2026_09_19_122050_change_selected_date_to_attendance_date_range.php`
- Modify: `app/Http/Controllers/AttendanceImportController.php`
- Test: `tests/Unit/AttendanceImportDateRangeMigrationTest.php`

**Interfaces:**
- Produces: `attendance_imports.from_date` and `attendance_imports.to_date` as the selected import range columns.
- Produces: compatibility behavior where legacy `selected_date` no longer blocks inserts.
- Consumes: existing `AttendanceImport::create([... 'from_date', 'to_date' ...])` calls.

- [ ] **Step 1: Write the failing migration source test**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceImportDateRangeMigrationTest extends TestCase
{
    public function test_base_attendance_import_migration_uses_date_range_columns(): void
    {
        $source = file_get_contents(
            database_path('migrations/2026_09_18_193505_create_attendance_import_tables.php')
        );

        $this->assertStringContainsString("\$table->date('from_date')", $source);
        $this->assertStringContainsString("\$table->date('to_date')", $source);
        $this->assertStringContainsString("['status', 'from_date', 'to_date']", $source);
        $this->assertStringNotContainsString("\$table->date('selected_date');", $source);
    }

    public function test_pending_range_migration_guards_existing_columns(): void
    {
        $source = file_get_contents(
            database_path('migrations/2026_09_19_122050_change_selected_date_to_attendance_date_range.php')
        );

        $this->assertStringContainsString("Schema::hasColumn('attendance_imports', 'from_date')", $source);
        $this->assertStringContainsString("Schema::hasColumn('attendance_imports', 'to_date')", $source);
        $this->assertStringContainsString("Schema::hasColumn('attendance_imports', 'selected_date')", $source);
        $this->assertStringContainsString("whereNull('from_date')", $source);
        $this->assertStringContainsString("whereNull('to_date')", $source);
        $this->assertStringContainsString("nullable()->change()", $source);
    }

    public function test_attendance_preview_validation_messages_use_date_range_language(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/AttendanceImportController.php')
        );

        $this->assertStringContainsString('Please select the From Date.', $source);
        $this->assertStringContainsString('Please select the To Date.', $source);
        $this->assertStringContainsString('To Date cannot be before From Date.', $source);
        $this->assertStringNotContainsString('Please select the attendance date.', $source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor\bin\phpunit tests\Unit\AttendanceImportDateRangeMigrationTest.php`

Expected: FAIL because the base migration still contains `selected_date`, the pending migration does not guard column creation, and validation copy still references the old attendance date.

- [ ] **Step 3: Update the base attendance import migration**

Replace the legacy selected date column and index with range columns:

```php
// HR-selected attendance upload range.
$table->date('from_date');
$table->date('to_date');

// Existing actual period columns remain below this block.
```

Replace:

```php
$table->index(['status', 'selected_date']);
```

With:

```php
$table->index(['status', 'from_date', 'to_date']);
```

- [ ] **Step 4: Guard the already-ran range migration**

In `2026_09_19_115449_change_attendance_import_date_to_range.php`, make `up()` safe on fresh installs:

```php
public function up()
{
    if (!Schema::hasColumn('attendance_imports', 'from_date')) {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->date('from_date')->nullable()->after('id');
        });
    }

    if (!Schema::hasColumn('attendance_imports', 'to_date')) {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->date('to_date')->nullable()->after('from_date');
        });
    }
}
```

Use a guarded `down()`:

```php
public function down()
{
    $columns = [];

    if (Schema::hasColumn('attendance_imports', 'from_date')) {
        $columns[] = 'from_date';
    }

    if (Schema::hasColumn('attendance_imports', 'to_date')) {
        $columns[] = 'to_date';
    }

    if (!empty($columns)) {
        Schema::table('attendance_imports', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
}
```

- [ ] **Step 5: Convert the pending duplicate migration into compatibility cleanup**

In `2026_09_19_122050_change_selected_date_to_attendance_date_range.php`, add imports:

```php
use Illuminate\Support\Facades\DB;
```

Use this `up()`:

```php
public function up()
{
    if (!Schema::hasColumn('attendance_imports', 'from_date')) {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->date('from_date')->nullable();
        });
    }

    if (!Schema::hasColumn('attendance_imports', 'to_date')) {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->date('to_date')->nullable();
        });
    }

    if (Schema::hasColumn('attendance_imports', 'selected_date')) {
        DB::table('attendance_imports')
            ->whereNull('from_date')
            ->update(['from_date' => DB::raw('selected_date')]);

        DB::table('attendance_imports')
            ->whereNull('to_date')
            ->update(['to_date' => DB::raw('selected_date')]);

        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->date('selected_date')->nullable()->change();
        });
    }
}
```

Use this guarded `down()`:

```php
public function down()
{
    $columns = [];

    if (Schema::hasColumn('attendance_imports', 'from_date')) {
        $columns[] = 'from_date';
    }

    if (Schema::hasColumn('attendance_imports', 'to_date')) {
        $columns[] = 'to_date';
    }

    if (!empty($columns)) {
        Schema::table('attendance_imports', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
}
```

- [ ] **Step 6: Fix upload validation copy**

In `AttendanceImportController::preview()`, replace validation messages with:

```php
[
    'from_date.required' => 'Please select the From Date.',
    'from_date.before_or_equal' => 'From Date cannot be in the future.',
    'to_date.required' => 'Please select the To Date.',
    'to_date.after_or_equal' => 'To Date cannot be before From Date.',
    'to_date.before_or_equal' => 'To Date cannot be in the future.',
    'excel_file.required' => 'Please select an attendance file.',
    'excel_file.mimes' => 'Attendance file must be XLSX, XLS, or CSV.',
    'excel_file.max' => 'Attendance file cannot exceed 10MB.',
]
```

- [ ] **Step 7: Run focused tests and migration**

Run:

```bash
vendor\bin\phpunit tests\Unit\AttendanceImportDateRangeMigrationTest.php
php artisan migrate
```

Expected: unit test PASS; `php artisan migrate` completes without duplicate `from_date` column errors.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/AttendanceImportController.php database/migrations/2026_09_18_193505_create_attendance_import_tables.php database/migrations/2026_09_19_115449_change_attendance_import_date_to_range.php database/migrations/2026_09_19_122050_change_selected_date_to_attendance_date_range.php tests/Unit/AttendanceImportDateRangeMigrationTest.php
git commit -m "fix: make attendance import date range migration safe"
```

### Task 2: Add Shift Policy and Assignment Schema

**Files:**
- Create: `database/migrations/2026_09_19_130000_create_attendance_shift_policy_tables.php`
- Create: `app/Models/AttendanceShiftPolicy.php`
- Create: `app/Models/AttendanceUserShiftAssignment.php`
- Create: `tests/Feature/Attendance/AttendanceFeatureTestCase.php`
- Test: `tests/Feature/Attendance/AttendanceShiftPolicyModelTest.php`

**Interfaces:**
- Produces: `AttendanceShiftPolicy` with relationships `assignments()`.
- Produces: `AttendanceUserShiftAssignment` with relationships `user()` and `shiftPolicy()`.
- Produces: reusable test base `Tests\Feature\Attendance\AttendanceFeatureTestCase`.
- Consumes: existing `User` model IDs as UUID strings.

- [ ] **Step 1: Write the attendance feature test base**

```php
<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AttendanceFeatureTestCase extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'attendance_feature_testing');
        config()->set('database.connections.attendance_feature_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('attendance_feature_testing');
        DB::reconnect('attendance_feature_testing');
        DB::setDefaultConnection('attendance_feature_testing');

        Carbon::setTestNow(Carbon::create(2026, 9, 19, 10, 0, 0));

        $this->createAttendanceSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('attendance_feature_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('attendance_feature_testing');

        parent::tearDown();
    }

    protected function createUserWithRole(string $name, string $role = UserType::HR): User
    {
        $type = UserType::query()->firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'description' => null,
                'status' => 1,
            ]
        );

        return User::create([
            'name' => $name,
            'email' => Str::slug($name) . '-' . Str::lower(Str::random(6)) . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function createAttendanceSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('attendance_shift_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_user_shift_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('shift_policy_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
```

- [ ] **Step 2: Write failing model tests**

```php
<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\UserType;

class AttendanceShiftPolicyModelTest extends AttendanceFeatureTestCase
{
    public function test_shift_policy_generates_uuid_and_casts_flags(): void
    {
        $policy = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertNotEmpty($policy->id);
        $this->assertTrue($policy->is_default);
        $this->assertTrue($policy->is_active);
        $this->assertSame(15, $policy->grace_minutes);
    }

    public function test_assignment_links_user_to_shift_policy(): void
    {
        $user = $this->createUserWithRole('HR User', UserType::HR);

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Early Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        $assignment = AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $policy->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
        ]);

        $this->assertSame($user->id, $assignment->user->id);
        $this->assertSame($policy->id, $assignment->shiftPolicy->id);
        $this->assertSame('2026-09-01', $assignment->effective_from->toDateString());
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor\bin\phpunit tests\Feature\Attendance\AttendanceShiftPolicyModelTest.php`

Expected: FAIL because `AttendanceShiftPolicy` and `AttendanceUserShiftAssignment` do not exist.

- [ ] **Step 4: Create shift policy migration**

Create `database/migrations/2026_09_19_130000_create_attendance_shift_policy_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CreateAttendanceShiftPolicyTables extends Migration
{
    public function up()
    {
        Schema::create('attendance_shift_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_user_shift_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('shift_policy_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'effective_from', 'effective_to'], 'attendance_user_shift_range_index');
            $table->index('shift_policy_id');
        });

        DB::table('attendance_shift_policies')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Default Office Time',
            'start_time' => config('kpi.attendance.shift_start', '10:30'),
            'end_time' => null,
            'grace_minutes' => (int) config('kpi.attendance.grace_minutes', 15),
            'is_default' => true,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('attendance_user_shift_assignments');
        Schema::dropIfExists('attendance_shift_policies');
    }
}
```

- [ ] **Step 5: Create models**

Create `app/Models/AttendanceShiftPolicy.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceShiftPolicy extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'grace_minutes' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function assignments()
    {
        return $this->hasMany(
            AttendanceUserShiftAssignment::class,
            'shift_policy_id'
        );
    }
}
```

Create `app/Models/AttendanceUserShiftAssignment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceUserShiftAssignment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'shift_policy_id',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shiftPolicy()
    {
        return $this->belongsTo(
            AttendanceShiftPolicy::class,
            'shift_policy_id'
        );
    }
}
```

- [ ] **Step 6: Run focused tests**

Run: `vendor\bin\phpunit tests\Feature\Attendance\AttendanceShiftPolicyModelTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/AttendanceShiftPolicy.php app/Models/AttendanceUserShiftAssignment.php database/migrations/2026_09_19_130000_create_attendance_shift_policy_tables.php tests/Feature/Attendance/AttendanceFeatureTestCase.php tests/Feature/Attendance/AttendanceShiftPolicyModelTest.php
git commit -m "feat: add attendance shift policy models"
```

### Task 3: Add Attendance Shift Resolver

**Files:**
- Create: `app/Services/Attendance/AttendanceShiftResolver.php`
- Test: `tests/Feature/Attendance/AttendanceShiftResolverTest.php`

**Interfaces:**
- Consumes: `AttendanceShiftPolicy` and `AttendanceUserShiftAssignment` from Task 2.
- Produces: `AttendanceShiftResolver::resolveForDate(User $user, Carbon $date): AttendanceShiftPolicy`.
- Produces: `AttendanceShiftResolver::resolveForDates(User $user, Collection $dates): Collection`, keyed by `Y-m-d`, values `AttendanceShiftPolicy`.

- [ ] **Step 1: Write failing resolver tests**

```php
<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Services\Attendance\AttendanceShiftResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceShiftResolverTest extends AttendanceFeatureTestCase
{
    public function test_resolves_matching_user_assignment_for_date(): void
    {
        $user = $this->createUserWithRole('Assigned Employee');

        AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $early = AttendanceShiftPolicy::create([
            'name' => 'Early Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $early->id,
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-09-30',
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame($early->id, $resolved->id);
        $this->assertSame('Early Shift', $resolved->name);
        $this->assertSame(5, $resolved->grace_minutes);
    }

    public function test_falls_back_to_active_default_policy(): void
    {
        $user = $this->createUserWithRole('Default Employee');

        $default = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame($default->id, $resolved->id);
    }

    public function test_falls_back_to_legacy_config_when_no_default_policy_exists(): void
    {
        config()->set('kpi.attendance.shift_start', '11:00');
        config()->set('kpi.attendance.grace_minutes', 20);

        $user = $this->createUserWithRole('Legacy Fallback Employee');

        $resolved = app(AttendanceShiftResolver::class)->resolveForDate(
            $user,
            Carbon::parse('2026-09-15')
        );

        $this->assertSame('Legacy Config Office Time', $resolved->name);
        $this->assertSame('11:00', substr((string) $resolved->start_time, 0, 5));
        $this->assertSame(20, $resolved->grace_minutes);
        $this->assertFalse($resolved->exists);
    }

    public function test_bulk_resolution_uses_date_specific_assignments(): void
    {
        $user = $this->createUserWithRole('Changing Shift Employee');

        $default = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $late = AttendanceShiftPolicy::create([
            'name' => 'Late Shift',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'grace_minutes' => 10,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $late->id,
            'effective_from' => '2026-09-10',
            'effective_to' => null,
        ]);

        $resolved = app(AttendanceShiftResolver::class)->resolveForDates(
            $user,
            collect([
                Carbon::parse('2026-09-09'),
                Carbon::parse('2026-09-10'),
            ])
        );

        $this->assertInstanceOf(Collection::class, $resolved);
        $this->assertSame($default->id, $resolved->get('2026-09-09')->id);
        $this->assertSame($late->id, $resolved->get('2026-09-10')->id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor\bin\phpunit tests\Feature\Attendance\AttendanceShiftResolverTest.php`

Expected: FAIL because `AttendanceShiftResolver` does not exist.

- [ ] **Step 3: Implement resolver**

Create `app/Services/Attendance/AttendanceShiftResolver.php`:

```php
<?php

namespace App\Services\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceShiftResolver
{
    public function resolveForDate(
        User $user,
        Carbon $date
    ): AttendanceShiftPolicy {
        $date = $date->copy()->startOfDay();

        $assignment = AttendanceUserShiftAssignment::query()
            ->with('shiftPolicy')
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->latest('effective_from')
            ->first();

        if ($assignment && $assignment->shiftPolicy) {
            return $assignment->shiftPolicy;
        }

        return $this->defaultPolicy();
    }

    public function resolveForDates(
        User $user,
        Collection $dates
    ): Collection {
        $normalizedDates = $dates
            ->map(fn ($date) => $date instanceof Carbon
                ? $date->copy()->startOfDay()
                : Carbon::parse($date)->startOfDay())
            ->sortBy(fn (Carbon $date) => $date->toDateString())
            ->values();

        if ($normalizedDates->isEmpty()) {
            return collect();
        }

        $from = $normalizedDates->first()->toDateString();
        $to = $normalizedDates->last()->toDateString();

        $assignments = AttendanceUserShiftAssignment::query()
            ->with('shiftPolicy')
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $to)
            ->where(function ($query) use ($from) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from);
            })
            ->orderBy('effective_from')
            ->get();

        $defaultPolicy = $this->defaultPolicy();

        return $normalizedDates
            ->mapWithKeys(function (Carbon $date) use ($assignments, $defaultPolicy) {
                $dateKey = $date->toDateString();

                $assignment = $assignments
                    ->filter(function (AttendanceUserShiftAssignment $assignment) use ($date) {
                        $starts = $assignment->effective_from->lte($date);
                        $ends = !$assignment->effective_to
                            || $assignment->effective_to->gte($date);

                        return $starts && $ends && $assignment->shiftPolicy;
                    })
                    ->sortByDesc(fn (AttendanceUserShiftAssignment $assignment) =>
                        $assignment->effective_from->toDateString())
                    ->first();

                return [
                    $dateKey => $assignment && $assignment->shiftPolicy
                        ? $assignment->shiftPolicy
                        : $defaultPolicy,
                ];
            });
    }

    private function defaultPolicy(): AttendanceShiftPolicy
    {
        $policy = AttendanceShiftPolicy::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($policy) {
            return $policy;
        }

        return new AttendanceShiftPolicy([
            'name' => 'Legacy Config Office Time',
            'start_time' => config('kpi.attendance.shift_start', '10:30'),
            'end_time' => null,
            'grace_minutes' => (int) config('kpi.attendance.grace_minutes', 15),
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
```

- [ ] **Step 4: Run resolver tests**

Run: `vendor\bin\phpunit tests\Feature\Attendance\AttendanceShiftResolverTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Attendance/AttendanceShiftResolver.php tests/Feature/Attendance/AttendanceShiftResolverTest.php
git commit -m "feat: resolve attendance shifts by user and date"
```

### Task 4: Add Attendance Settings UI and Controller

**Files:**
- Create: `app/Http/Controllers/AttendanceSettingsController.php`
- Create: `resources/views/admin/pages/attendance/settings.blade.php`
- Modify: `resources/views/admin/layouts/header.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Attendance/AttendanceSettingsControllerTest.php`
- Test: `tests/Unit/AttendanceSettingsViewTest.php`

**Interfaces:**
- Consumes: `AttendanceShiftPolicy` and `AttendanceUserShiftAssignment`.
- Produces route names:
  - `admin.attendance.settings.index`
  - `admin.attendance.settings.policies.store`
  - `admin.attendance.settings.policies.update`
  - `admin.attendance.settings.assignments.store`
- Produces request payloads:
  - Policy form: `name`, `start_time`, `end_time`, `grace_minutes`, `is_default`, `is_active`
  - Assignment form: `user_ids[]`, `shift_policy_id`, `effective_from`, `effective_to`

- [ ] **Step 1: Write failing controller tests**

```php
<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\UserType;

class AttendanceSettingsControllerTest extends AttendanceFeatureTestCase
{
    public function test_store_policy_makes_new_policy_default_and_clears_previous_default(): void
    {
        $admin = $this->createUserWithRole('Attendance Admin', UserType::SUPER_ADMIN);

        $oldDefault = AttendanceShiftPolicy::create([
            'name' => 'Old Default',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.attendance.settings.policies.store'), [
                'name' => 'New Default',
                'start_time' => '09:30',
                'end_time' => '18:30',
                'grace_minutes' => 10,
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.attendance.settings.index'));

        $this->assertFalse($oldDefault->fresh()->is_default);
        $this->assertDatabaseHas('attendance_shift_policies', [
            'name' => 'New Default',
            'start_time' => '09:30',
            'grace_minutes' => 10,
            'is_default' => true,
        ]);
    }

    public function test_bulk_assignment_rejects_overlapping_employee_range(): void
    {
        $admin = $this->createUserWithRole('Attendance Admin', UserType::SUPER_ADMIN);
        $employee = $this->createUserWithRole('Sales Employee', UserType::SALES_EXECUTIVE);

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_policy_id' => $policy->id,
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-09-30',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.attendance.settings.index'))
            ->post(route('admin.attendance.settings.assignments.store'), [
                'user_ids' => [$employee->id],
                'shift_policy_id' => $policy->id,
                'effective_from' => '2026-09-15',
                'effective_to' => '2026-10-15',
            ])
            ->assertRedirect(route('admin.attendance.settings.index'))
            ->assertSessionHasErrors('assignments');
    }

    public function test_bulk_assignment_allows_next_day_after_existing_range(): void
    {
        $admin = $this->createUserWithRole('Attendance Admin', UserType::SUPER_ADMIN);
        $employee = $this->createUserWithRole('Sales Employee', UserType::SALES_EXECUTIVE);

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $employee->id,
            'shift_policy_id' => $policy->id,
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-09-30',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.attendance.settings.assignments.store'), [
                'user_ids' => [$employee->id],
                'shift_policy_id' => $policy->id,
                'effective_from' => '2026-10-01',
                'effective_to' => null,
            ])
            ->assertRedirect(route('admin.attendance.settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_user_shift_assignments', [
            'user_id' => $employee->id,
            'shift_policy_id' => $policy->id,
            'effective_from' => '2026-10-01',
        ]);
    }

    public function test_bulk_assignment_rejects_inactive_policy(): void
    {
        $admin = $this->createUserWithRole('Attendance Admin', UserType::SUPER_ADMIN);
        $employee = $this->createUserWithRole('Sales Employee', UserType::SALES_EXECUTIVE);

        $policy = AttendanceShiftPolicy::create([
            'name' => 'Inactive Shift',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'grace_minutes' => 15,
            'is_default' => false,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.attendance.settings.assignments.store'), [
                'user_ids' => [$employee->id],
                'shift_policy_id' => $policy->id,
                'effective_from' => '2026-10-01',
            ])
            ->assertSessionHasErrors('shift_policy_id');
    }
}
```

- [ ] **Step 2: Write failing view contract test**

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceSettingsViewTest extends TestCase
{
    public function test_attendance_settings_has_searchable_employee_checkbox_assignment_ui(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/attendance/settings.blade.php')
        );

        $this->assertStringContainsString('data-attendance-user-search', $source);
        $this->assertStringContainsString('data-attendance-user-option', $source);
        $this->assertStringContainsString('name="user_ids[]"', $source);
        $this->assertStringContainsString('name="shift_policy_id"', $source);
        $this->assertStringContainsString('name="effective_from"', $source);
        $this->assertStringContainsString('name="effective_to"', $source);
        $this->assertStringContainsString('Grace Minutes', $source);
        $this->assertStringContainsString('Office Time Policies', $source);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run:

```bash
vendor\bin\phpunit tests\Feature\Attendance\AttendanceSettingsControllerTest.php
vendor\bin\phpunit tests\Unit\AttendanceSettingsViewTest.php
```

Expected: FAIL because routes, controller, and view do not exist.

- [ ] **Step 4: Add routes**

In `routes/web.php`, import the controller:

```php
use App\Http\Controllers\AttendanceSettingsController;
```

Inside the existing `Route::prefix('admin/attendance')->middleware('role:ATTENDANCE_IMPORT_ROLES')->group(...)`, add:

```php
Route::get('/settings', [AttendanceSettingsController::class, 'index'])
    ->name('admin.attendance.settings.index');

Route::post('/settings/policies', [AttendanceSettingsController::class, 'storePolicy'])
    ->name('admin.attendance.settings.policies.store');

Route::put('/settings/policies/{policy}', [AttendanceSettingsController::class, 'updatePolicy'])
    ->name('admin.attendance.settings.policies.update');

Route::post('/settings/assignments', [AttendanceSettingsController::class, 'storeAssignments'])
    ->name('admin.attendance.settings.assignments.store');
```

- [ ] **Step 5: Implement controller**

Create `app/Http/Controllers/AttendanceSettingsController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceSettingsController extends Controller
{
    public function index()
    {
        $policies = AttendanceShiftPolicy::query()
            ->withCount('assignments')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->with('userType:id,user_type')
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'user_type_id']);

        $assignments = AttendanceUserShiftAssignment::query()
            ->with(['user:id,name', 'shiftPolicy:id,name,start_time,end_time,grace_minutes'])
            ->orderByDesc('effective_from')
            ->limit(100)
            ->get();

        $currentAssignments = AttendanceUserShiftAssignment::query()
            ->with('shiftPolicy:id,name,start_time,end_time,grace_minutes')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->get()
            ->keyBy('user_id');

        return view('admin.pages.attendance.settings', [
            'policies' => $policies,
            'users' => $users,
            'assignments' => $assignments,
            'currentAssignments' => $currentAssignments,
        ]);
    }

    public function storePolicy(Request $request)
    {
        $validated = $this->validatePolicy($request);

        DB::transaction(function () use ($validated, $request) {
            if ($validated['is_default']) {
                AttendanceShiftPolicy::query()->update(['is_default' => false]);
            }

            AttendanceShiftPolicy::create(array_merge($validated, [
                'created_by' => optional($request->user())->id,
                'updated_by' => optional($request->user())->id,
            ]));
        });

        return redirect()
            ->route('admin.attendance.settings.index')
            ->with('success', 'Office time policy created successfully.');
    }

    public function updatePolicy(Request $request, AttendanceShiftPolicy $policy)
    {
        $validated = $this->validatePolicy($request);

        DB::transaction(function () use ($policy, $validated, $request) {
            if ($validated['is_default']) {
                AttendanceShiftPolicy::query()
                    ->where('id', '!=', $policy->id)
                    ->update(['is_default' => false]);
            }

            $policy->update(array_merge($validated, [
                'updated_by' => optional($request->user())->id,
            ]));
        });

        return redirect()
            ->route('admin.attendance.settings.index')
            ->with('success', 'Office time policy updated successfully.');
    }

    public function storeAssignments(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'uuid', 'exists:users,id'],
            'shift_policy_id' => [
                'required',
                'uuid',
                Rule::exists('attendance_shift_policies', 'id')
                    ->where('is_active', true),
            ],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $from = $validated['effective_from'];
        $to = $validated['effective_to'] ?? null;

        foreach ($validated['user_ids'] as $userId) {
            if ($this->hasOverlappingAssignment($userId, $from, $to)) {
                throw ValidationException::withMessages([
                    'assignments' => 'One or more selected employees already has an office time assignment in this date range.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $request, $from, $to) {
            foreach ($validated['user_ids'] as $userId) {
                AttendanceUserShiftAssignment::create([
                    'user_id' => $userId,
                    'shift_policy_id' => $validated['shift_policy_id'],
                    'effective_from' => $from,
                    'effective_to' => $to,
                    'created_by' => optional($request->user())->id,
                    'updated_by' => optional($request->user())->id,
                ]);
            }
        });

        return redirect()
            ->route('admin.attendance.settings.index')
            ->with('success', 'Office time assigned successfully.');
    }

    private function validatePolicy(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function hasOverlappingAssignment(
        string $userId,
        string $from,
        ?string $to
    ): bool {
        $rangeEnd = $to ?: '9999-12-31';

        return AttendanceUserShiftAssignment::query()
            ->where('user_id', $userId)
            ->whereDate('effective_from', '<=', $rangeEnd)
            ->where(function ($query) use ($from) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from);
            })
            ->exists();
    }
}
```

- [ ] **Step 6: Create settings view**

Create `resources/views/admin/pages/attendance/settings.blade.php`. Keep the existing admin UI style. Include these fields and data attributes:

```blade
@extends('admin.layouts.header')

@section('content')

<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="!text-defaulttextcolor text-[1.125rem] font-semibold">
            Attendance Settings
        </h3>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
        {{ $errors->first() }}
    </div>
@endif

<div class="box">
    <div class="box-header">
        <h5 class="box-title">Office Time Policies</h5>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.attendance.settings.policies.store') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
            @csrf
            <input class="form-control" name="name" placeholder="Policy name" required>
            <input class="form-control" type="time" name="start_time" required>
            <input class="form-control" type="time" name="end_time">
            <input class="form-control" type="number" name="grace_minutes" min="0" max="240" value="15" required>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_default" value="1">
                Default
            </label>
            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
            </label>
            <button class="ti-btn ti-btn-primary-full ti-btn-wave" type="submit">
                Save Policy
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="table whitespace-nowrap min-w-full">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Grace Minutes</th>
                        <th>Default</th>
                        <th>Active</th>
                        <th>Assignments</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($policies as $policy)
                        <tr>
                            <td>{{ $policy->name }}</td>
                            <td>{{ substr((string) $policy->start_time, 0, 5) }}</td>
                            <td>{{ $policy->end_time ? substr((string) $policy->end_time, 0, 5) : '-' }}</td>
                            <td>{{ $policy->grace_minutes }}</td>
                            <td>{{ $policy->is_default ? 'Yes' : 'No' }}</td>
                            <td>{{ $policy->is_active ? 'Yes' : 'No' }}</td>
                            <td>{{ $policy->assignments_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header">
        <h5 class="box-title">Employee Office Time Assignments</h5>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.attendance.settings.assignments.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <select class="form-control" name="shift_policy_id" required>
                    <option value="">Select office time</option>
                    @foreach($policies->where('is_active', true) as $policy)
                        <option value="{{ $policy->id }}">
                            {{ $policy->name }} ({{ substr((string) $policy->start_time, 0, 5) }} + {{ $policy->grace_minutes }} min)
                        </option>
                    @endforeach
                </select>
                <input class="form-control" type="date" name="effective_from" required>
                <input class="form-control" type="date" name="effective_to">
                <button class="ti-btn ti-btn-primary-full ti-btn-wave" type="submit">
                    Assign Selected
                </button>
            </div>

            <input class="form-control mb-3" data-attendance-user-search placeholder="Search employees">

            <div class="overflow-x-auto max-h-[420px]">
                <table class="table whitespace-nowrap min-w-full">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Current Office Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            @php($current = $currentAssignments->get($user->id))
                            <tr data-attendance-user-option data-attendance-user-name="{{ strtolower($user->name) }}">
                                <td>
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}">
                                </td>
                                <td>{{ $user->name }}</td>
                                <td>{{ optional($user->userType)->user_type ?: '-' }}</td>
                                <td>{{ optional(optional($current)->shiftPolicy)->name ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header">
        <h5 class="box-title">Recent Assignment History</h5>
    </div>
    <div class="box-body overflow-x-auto">
        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Office Time</th>
                    <th>Effective From</th>
                    <th>Effective To</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assignments as $assignment)
                    <tr>
                        <td>{{ optional($assignment->user)->name ?: '-' }}</td>
                        <td>{{ optional($assignment->shiftPolicy)->name ?: '-' }}</td>
                        <td>{{ optional($assignment->effective_from)->format('d M Y') }}</td>
                        <td>{{ $assignment->effective_to ? $assignment->effective_to->format('d M Y') : 'Open' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.querySelector('[data-attendance-user-search]');
    const rows = Array.from(document.querySelectorAll('[data-attendance-user-option]'));

    if (!search) {
        return;
    }

    search.addEventListener('input', function () {
        const value = search.value.trim().toLowerCase();

        rows.forEach(function (row) {
            const name = row.getAttribute('data-attendance-user-name') || '';
            row.classList.toggle('hidden', value !== '' && name.indexOf(value) === -1);
        });
    });
});
</script>
@endpush
```

- [ ] **Step 7: Add navigation link**

In `resources/views/admin/layouts/header.blade.php`, near the Attendance Import link, add:

```blade
<a href="{{ route('admin.attendance.settings.index') }}" class="side-menu__item">
    Attendance Settings
</a>
```

Only show this link in the same role block that currently shows Attendance Import.

- [ ] **Step 8: Run focused tests**

Run:

```bash
vendor\bin\phpunit tests\Feature\Attendance\AttendanceSettingsControllerTest.php
vendor\bin\phpunit tests\Unit\AttendanceSettingsViewTest.php
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/AttendanceSettingsController.php resources/views/admin/pages/attendance/settings.blade.php resources/views/admin/layouts/header.blade.php routes/web.php tests/Feature/Attendance/AttendanceSettingsControllerTest.php tests/Unit/AttendanceSettingsViewTest.php
git commit -m "feat: add attendance office time settings"
```

### Task 5: Use Shift Policies in Attendance KPI

**Files:**
- Modify: `app/Services/Kpi/SalesAttendanceKpiResolver.php`
- Modify: `app/Services/Kpi/KpiDashboardService.php`
- Modify: `tests/Feature/Kpi/KpiFeatureTestCase.php`
- Test: `tests/Feature/Kpi/SalesAttendanceKpiResolverShiftPolicyTest.php`

**Interfaces:**
- Consumes: `AttendanceShiftResolver::resolveForDates(User $user, Collection $dates): Collection`.
- Produces KPI evidence keys:
  - `shift_start`
  - `grace_minutes`
  - `punctual_cutoff`
  - `shift_policy_breakdown`
- Preserves existing evidence keys:
  - `eligible_scheduled_days`
  - `punctual_days`
  - `late_days`
  - `absent_days`
  - `missing_uploaded_days`
  - `non_scheduled_rows`
  - `latest_attendance_date`
  - `attendance_coverage_through`
  - `punctuality_percent`

- [ ] **Step 1: Extend KPI feature schema for attendance**

In `tests/Feature/Kpi/KpiFeatureTestCase.php`, add these schema blocks to `createKpiSchema()`:

```php
Schema::create('attendance_records', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id');
    $table->string('paycode', 100)->nullable();
    $table->date('attendance_date');
    $table->string('day_name', 20)->nullable();
    $table->time('in_time')->nullable();
    $table->time('out_time')->nullable();
    $table->string('raw_in', 50)->nullable();
    $table->string('raw_out', 50)->nullable();
    $table->string('raw_status', 50)->nullable();
    $table->uuid('source_import_id')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'attendance_date']);
});

Schema::create('attendance_shift_policies', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name', 150);
    $table->time('start_time');
    $table->time('end_time')->nullable();
    $table->unsignedSmallInteger('grace_minutes')->default(0);
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->uuid('created_by')->nullable();
    $table->uuid('updated_by')->nullable();
    $table->timestamps();
});

Schema::create('attendance_user_shift_assignments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('user_id');
    $table->uuid('shift_policy_id');
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->uuid('created_by')->nullable();
    $table->uuid('updated_by')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 2: Write failing KPI tests**

```php
<?php

namespace Tests\Feature\Kpi;

use App\Models\AttendanceRecord;
use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\KpiMetric;
use App\Models\KpiTemplate;
use App\Models\UserType;
use App\Services\Kpi\SalesAttendanceKpiResolver;
use Carbon\Carbon;

class SalesAttendanceKpiResolverShiftPolicyTest extends KpiFeatureTestCase
{
    public function test_attendance_kpi_uses_employee_specific_shift_policy(): void
    {
        $user = $this->createUserWithRole(
            'Shifted Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $metric = $this->attendanceMetric();

        $default = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $early = AttendanceShiftPolicy::create([
            'name' => 'Early Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $early->id,
            'effective_from' => '2026-09-01',
            'effective_to' => null,
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'paycode' => 'E001',
            'attendance_date' => '2026-09-01',
            'day_name' => 'Tuesday',
            'in_time' => '09:04:00',
            'out_time' => '18:00:00',
            'raw_status' => 'P',
            'source_import_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'paycode' => 'E001',
            'attendance_date' => '2026-09-02',
            'day_name' => 'Wednesday',
            'in_time' => '09:06:00',
            'out_time' => '18:00:00',
            'raw_status' => 'P',
            'source_import_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $resolved = app(SalesAttendanceKpiResolver::class)->resolve(
            $user,
            $metric,
            Carbon::parse('2026-09-02 23:59:59'),
            22
        );

        $this->assertSame(2, $resolved['evidence']['eligible_scheduled_days']);
        $this->assertSame(1, $resolved['evidence']['punctual_days']);
        $this->assertSame(1, $resolved['evidence']['late_days']);
        $this->assertSame(50.0, $resolved['evidence']['punctuality_percent']);
        $this->assertSame('09:00', $resolved['evidence']['shift_start']);
        $this->assertSame(5, $resolved['evidence']['grace_minutes']);
        $this->assertSame('09:05', $resolved['evidence']['punctual_cutoff']);
        $this->assertArrayHasKey('Early Shift', $resolved['evidence']['shift_policy_breakdown']);
        $this->assertArrayNotHasKey('Default Office Time', $resolved['evidence']['shift_policy_breakdown']);
    }

    public function test_attendance_kpi_uses_different_policy_after_effective_date_change(): void
    {
        $user = $this->createUserWithRole(
            'Changing Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $metric = $this->attendanceMetric();

        $default = AttendanceShiftPolicy::create([
            'name' => 'Default Office Time',
            'start_time' => '10:30',
            'end_time' => '19:30',
            'grace_minutes' => 15,
            'is_default' => true,
            'is_active' => true,
        ]);

        $late = AttendanceShiftPolicy::create([
            'name' => 'Late Shift',
            'start_time' => '12:00',
            'end_time' => '21:00',
            'grace_minutes' => 0,
            'is_default' => false,
            'is_active' => true,
        ]);

        AttendanceUserShiftAssignment::create([
            'user_id' => $user->id,
            'shift_policy_id' => $late->id,
            'effective_from' => '2026-09-02',
            'effective_to' => null,
        ]);

        foreach (['2026-09-01', '2026-09-02'] as $date) {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'paycode' => 'E002',
                'attendance_date' => $date,
                'day_name' => Carbon::parse($date)->format('l'),
                'in_time' => '10:40:00',
                'out_time' => '19:00:00',
                'raw_status' => 'P',
                'source_import_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }

        $resolved = app(SalesAttendanceKpiResolver::class)->resolve(
            $user,
            $metric,
            Carbon::parse('2026-09-02 23:59:59'),
            22
        );

        $this->assertSame(1, $resolved['evidence']['punctual_days']);
        $this->assertSame(1, $resolved['evidence']['late_days']);
        $this->assertSame(1, $resolved['evidence']['shift_policy_breakdown']['Default Office Time']['eligible_days']);
        $this->assertSame(1, $resolved['evidence']['shift_policy_breakdown']['Late Shift']['eligible_days']);
    }

    private function attendanceMetric(): KpiMetric
    {
        $template = KpiTemplate::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Retail Sales KPI',
            'department' => 'sales',
            'working_days_per_month' => 22,
            'active' => true,
        ]);

        return KpiMetric::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'template_id' => $template->id,
            'code' => 'attendance',
            'name' => 'Attendance',
            'measurement_type' => 'automatic',
            'source_key' => 'sales_attendance',
            'target_value' => 95,
            'direction' => 'higher_better',
            'active' => true,
        ]);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `vendor\bin\phpunit tests\Feature\Kpi\SalesAttendanceKpiResolverShiftPolicyTest.php`

Expected: FAIL because `SalesAttendanceKpiResolver` still reads global config shift values.

- [ ] **Step 4: Inject `AttendanceShiftResolver` into KPI resolver**

In `app/Services/Kpi/SalesAttendanceKpiResolver.php`, add import:

```php
use App\Services\Attendance\AttendanceShiftResolver;
```

Update constructor:

```php
public function __construct(
    private KpiWorkingDayService $workingDays,
    private AttendanceShiftResolver $shiftResolver
) {}
```

- [ ] **Step 5: Replace global shift/grace reads with per-date policies**

Remove these global reads from the top of `resolve()`:

```php
$shiftStart = (string) config('kpi.attendance.shift_start', '10:30');
$graceMinutes = (int) config('kpi.attendance.grace_minutes', 15);
```

After `$scheduledDates` is built, add:

```php
$shiftPoliciesByDate = $this->shiftResolver->resolveForDates(
    $user,
    $scheduledDates
);

$shiftPolicyBreakdown = [];
$lastShiftStart = null;
$lastGraceMinutes = null;
$lastPunctualCutoff = null;
```

Inside the scheduled date loop, before cutoff creation, add:

```php
$policy = $shiftPoliciesByDate->get($dateKey);
$shiftStart = substr((string) optional($policy)->start_time, 0, 5) ?: '10:30';
$graceMinutes = (int) (optional($policy)->grace_minutes ?? 15);
$policyName = (string) (optional($policy)->name ?: 'Default Office Time');

if (!isset($shiftPolicyBreakdown[$policyName])) {
    $shiftPolicyBreakdown[$policyName] = [
        'shift_start' => $shiftStart,
        'grace_minutes' => $graceMinutes,
        'eligible_days' => 0,
        'punctual_days' => 0,
        'late_days' => 0,
        'absent_days' => 0,
    ];
}
```

After `$eligible++`, add:

```php
$shiftPolicyBreakdown[$policyName]['eligible_days']++;
```

When absent increments, add:

```php
$shiftPolicyBreakdown[$policyName]['absent_days']++;
```

After cutoff creation, set summary values:

```php
$lastShiftStart = $shiftStart;
$lastGraceMinutes = $graceMinutes;
$lastPunctualCutoff = Carbon::createFromFormat('H:i', $shiftStart)
    ->addMinutes($graceMinutes)
    ->format('H:i');
```

When punctual increments, also add:

```php
$shiftPolicyBreakdown[$policyName]['punctual_days']++;
```

When late increments, also add:

```php
$shiftPolicyBreakdown[$policyName]['late_days']++;
```

- [ ] **Step 6: Update KPI evidence**

In the returned evidence, replace static shift values with:

```php
'shift_start' => $lastShiftStart,
'grace_minutes' => $lastGraceMinutes,
'punctual_cutoff' => $lastPunctualCutoff,
'shift_policy_breakdown' => $shiftPolicyBreakdown,
```

In `emptyResult()`, add:

```php
'shift_start' => null,
'grace_minutes' => null,
'punctual_cutoff' => null,
'shift_policy_breakdown' => [],
```

In `KpiDashboardService::zeroMetricResult()`, add the same four keys under the `attendance` empty evidence block.

- [ ] **Step 7: Run KPI tests**

Run:

```bash
vendor\bin\phpunit tests\Feature\Kpi\SalesAttendanceKpiResolverShiftPolicyTest.php
vendor\bin\phpunit tests\Feature\Kpi
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Kpi/SalesAttendanceKpiResolver.php app/Services/Kpi/KpiDashboardService.php tests/Feature/Kpi/KpiFeatureTestCase.php tests/Feature/Kpi/SalesAttendanceKpiResolverShiftPolicyTest.php
git commit -m "feat: apply attendance shift policies to KPI scoring"
```

### Task 6: Final Integration, Verification, and Cleanup

**Files:**
- Modify only files from Tasks 1-5 if verification reveals a concrete issue.
- Do not stage `bootstrap/cache/packages.php`.
- Do not stage `bootstrap/cache/services.php`.

**Interfaces:**
- Consumes all previous tasks.
- Produces a migrated database with attendance date range and shift policy tables.
- Produces passing focused test suite for attendance import, attendance settings, shift resolver, and attendance KPI.

- [ ] **Step 1: Run migration**

Run:

```bash
php artisan migrate
```

Expected: migrations complete; no duplicate-column error for `attendance_imports.from_date`; new attendance shift tables exist.

- [ ] **Step 2: Run focused attendance/KPI tests**

Run:

```bash
vendor\bin\phpunit tests\Unit\AttendanceImportDateRangeMigrationTest.php
vendor\bin\phpunit tests\Feature\Attendance
vendor\bin\phpunit tests\Unit\AttendanceSettingsViewTest.php
vendor\bin\phpunit tests\Feature\Kpi\SalesAttendanceKpiResolverShiftPolicyTest.php
```

Expected: PASS.

- [ ] **Step 3: Run broader KPI regression tests**

Run:

```bash
vendor\bin\phpunit tests\Feature\Kpi
vendor\bin\phpunit tests\Unit\KpiImprovementServiceTest.php
vendor\bin\phpunit tests\Unit\KpiScoreServiceTest.php
```

Expected: PASS.

- [ ] **Step 4: Check syntax for changed PHP files**

Run these commands for changed application files:

```bash
php -l app/Http/Controllers/AttendanceImportController.php
php -l app/Http/Controllers/AttendanceSettingsController.php
php -l app/Models/AttendanceShiftPolicy.php
php -l app/Models/AttendanceUserShiftAssignment.php
php -l app/Services/Attendance/AttendanceShiftResolver.php
php -l app/Services/Kpi/SalesAttendanceKpiResolver.php
```

Expected: each command reports no syntax errors.

- [ ] **Step 5: Inspect git status for generated cache files**

Run:

```bash
git status --short
```

Expected: `bootstrap/cache/packages.php` and `bootstrap/cache/services.php` are not staged. If they appear as untracked, leave them untracked.

- [ ] **Step 6: Check whitespace**

Run:

```bash
git diff --check
```

Expected: no trailing whitespace or conflict marker warnings.

- [ ] **Step 7: Manual browser check**

Open these URLs in the local CRM:

```text
/admin/attendance/import
/admin/attendance/settings
/admin/kpi
```

Verify:

- Attendance Import shows From Date and To Date.
- Attendance Settings shows Office Time Policies.
- Attendance Settings employee assignment search filters rows.
- Bulk employee assignment saves a non-overlapping range.
- KPI dashboard still loads and Attendance evidence reflects the applied shift.

- [ ] **Step 8: Final commit if verification required small fixes**

Only if this task produced fixes after the previous task commits:

```bash
git add app/Http/Controllers/AttendanceImportController.php app/Http/Controllers/AttendanceSettingsController.php app/Models/AttendanceShiftPolicy.php app/Models/AttendanceUserShiftAssignment.php app/Services/Attendance/AttendanceShiftResolver.php app/Services/Kpi/SalesAttendanceKpiResolver.php app/Services/Kpi/KpiDashboardService.php resources/views/admin/pages/attendance/settings.blade.php resources/views/admin/layouts/header.blade.php routes/web.php database/migrations/2026_09_18_193505_create_attendance_import_tables.php database/migrations/2026_09_19_115449_change_attendance_import_date_to_range.php database/migrations/2026_09_19_122050_change_selected_date_to_attendance_date_range.php database/migrations/2026_09_19_130000_create_attendance_shift_policy_tables.php tests/Unit/AttendanceImportDateRangeMigrationTest.php tests/Unit/AttendanceSettingsViewTest.php tests/Feature/Attendance/AttendanceFeatureTestCase.php tests/Feature/Attendance/AttendanceShiftPolicyModelTest.php tests/Feature/Attendance/AttendanceShiftResolverTest.php tests/Feature/Attendance/AttendanceSettingsControllerTest.php tests/Feature/Kpi/KpiFeatureTestCase.php tests/Feature/Kpi/SalesAttendanceKpiResolverShiftPolicyTest.php
git commit -m "fix: polish attendance shift policy integration"
```

If no fixes were needed, do not create an empty commit.
