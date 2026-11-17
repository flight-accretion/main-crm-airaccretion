<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Client;
use App\Models\KpiTeamMembership;
use Illuminate\Support\Str;
use Tests\Support\IsolatedWave2PostgresTestCase;

class Wave2AccountsTeamPostgresAcceptanceTest extends IsolatedWave2PostgresTestCase
{
    private function person(string $name, string $role = UserType::ACCOUNTS_EXECUTIVE): User
    {
        $type = UserType::firstOrCreate(['user_type' => $role], ['id' => (string) Str::uuid(), 'status' => 1]);
        $person = User::where('email', 'accounts@wave1.invalid')->sole()->replicate();
        $person->forceFill(['id' => (string) Str::uuid(), 'name' => $name,
            'email' => Str::uuid().'@wave1.invalid', 'user_type_id' => $type->id])->saveQuietly();
        $client = Client::forceCreate(['id' => (string) Str::uuid(), 'name' => $name.' customer', 'contact_number' => '9999990010']);
        $lead = Lead::forceCreate(['id' => (string) Str::uuid(), 'client_id' => $client->id,
            'representative_user_id' => $person->id]);
        $lead->forceFill(['created_at' => '2044-01-05 10:00:00'])->saveQuietly();
        LeadFollowup::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id,
            'followed_by' => $person->id, 'status' => 1]);
        return $person;
    }

    public function test_existing_and_new_executives_get_only_explicit_team_scope_and_scoped_exports(): void
    {
        $admin = User::where('email', 'super-admin@wave1.invalid')->sole();
        $member = $this->person('QA Team Member');
        $outsider = $this->person('QA Foreign Accounts');
        $sales = User::where('email', 'sales-executive@wave1.invalid')->sole();
        foreach (['QA Existing Executive', 'QA New Executive'] as $name) {
            $manager = $this->person($name);
            $roleId = $manager->user_type_id;
            $url = route('admin.kpi.index', ['preset' => 'custom', 'from_date' => '2044-01-01', 'to_date' => '2044-01-31']);
            $own = $this->actingAs($manager)->get($url)->assertOk();
            $this->assertSame([$manager->id], $own->viewData('team')->pluck('user.id')->all());
            $this->actingAs($admin)->get(route('admin.kpi.manage'))->assertOk()->assertSee($name);
            $this->post(route('admin.kpi.team-memberships.store'), ['department' => 'accounts',
                'manager_user_id' => $manager->id, 'member_user_id' => $member->id])->assertRedirect()->assertSessionHasNoErrors();
            $page = $this->actingAs($manager)->get($url)->assertOk();
            $this->assertEqualsCanonicalizing([$manager->id, $member->id], $page->viewData('team')->pluck('user.id')->all());
            $this->assertSame('accounts', $page->viewData('department'));
            $this->assertFalse($page->viewData('canSwitchDepartment'));
            $this->assertSame('2044-01-01', $page->viewData('filter')['from']->toDateString());
            $this->assertSame($roleId, $manager->fresh()->user_type_id);
            $this->get($url.'&user_id='.$outsider->id)->assertForbidden();
            $this->get($url.'&user_id='.$sales->id)->assertForbidden();
            foreach (['admin.users.store', 'admin.targets.store', 'admin.kpi.team-memberships.store'] as $route) {
                $this->postJson(route($route), [])->assertForbidden();
            }
            $this->get(route('admin.report.kpi.export.individual', ['representative_id' => $outsider->id, 'month' => '2044-01']))->assertForbidden();
            $export = $this->get(route('admin.report.kpi.export', ['month' => '2044-01']))->assertOk();
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($export->baseResponse->getFile()->getPathname());
            $cells = [];
            foreach ($book->getAllSheets() as $sheet) $cells = array_merge($cells, array_merge(...$sheet->toArray()));
            $this->assertContains($manager->name, $cells);
            $this->assertContains($member->name, $cells);
            $this->assertNotContains($outsider->name, $cells);
            $book->disconnectWorksheets();
        }
    }

    public function test_legacy_managers_keep_team_scope_and_financial_permissions(): void
    {
        $admin = User::where('email', 'super-admin@wave1.invalid')->sole();
        $member = $this->person('QA Shared Accounts Member');
        foreach ([UserType::ACCOUNTS_MANAGER, UserType::SENIOR_ACCOUNTS_MANAGER] as $role) {
            $manager = $this->person('QA '.$role, $role);
            $this->actingAs($admin)->post(route('admin.kpi.team-memberships.store'), [
                'department' => 'accounts', 'manager_user_id' => $manager->id, 'member_user_id' => $member->id,
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertEqualsCanonicalizing([$manager->id, $member->id],
                app(\App\Services\Kpi\KpiDataScopeService::class)->allowedUserIds($manager, 'accounts'));
            $this->actingAs($manager)->get(route('admin.refunds.index'))->assertOk();
            $this->assertTrue(\App\Support\RefundApproval::canDecide($manager));
        }
    }
}
