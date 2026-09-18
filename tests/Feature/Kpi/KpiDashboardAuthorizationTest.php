<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KpiDashboardAuthorizationTest extends KpiFeatureTestCase
{
    public function test_sales_executive_cannot_request_another_executive_kpi_details(): void
    {
        $sales = $this->createUserWithRole(
            'Authorized Sales Executive',
            UserType::SALES_EXECUTIVE
        );
        $otherSales = $this->createUserWithRole(
            'Other Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $this->actingAs($sales)
            ->get('/admin/kpi/details/work_done?user_id=' . $otherSales->id)
            ->assertForbidden();
    }

    public function test_accounts_user_can_open_daily_activity_but_cannot_use_sales_outreach_actions(): void
    {
        $accounts = $this->createUserWithRole(
            'Accounts Executive',
            UserType::ACCOUNTS_EXECUTIVE
        );
        $assignmentId = (string) Str::uuid();

        DB::table('kpi_outreach_assignments')->insert([
            'id' => $assignmentId,
            'user_id' => $accounts->id,
            'allocation_type' => 'standard',
            'normalized_phone' => '9876543210',
            'status' => 'pending',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($accounts)
            ->get('/admin/kpi/outreach')
            ->assertOk()
            ->assertSee('Accounts Daily Activity');

        $this->actingAs($accounts)
            ->withSession(['_token' => 'kpi-test'])
            ->post('/admin/kpi/outreach/get-more', [
                '_token' => 'kpi-test',
            ])
            ->assertForbidden();

        $this->actingAs($accounts)
            ->withSession(['_token' => 'kpi-test'])
            ->post("/admin/kpi/outreach/{$assignmentId}/dnp", [
                '_token' => 'kpi-test',
                'dnp' => 'on',
            ])
            ->assertForbidden();

        $this->actingAs($accounts)
            ->withSession(['_token' => 'kpi-test'])
            ->post("/admin/kpi/outreach/{$assignmentId}/remark", [
                '_token' => 'kpi-test',
                'remark' => 'Connected',
            ])
            ->assertForbidden();
    }

public function test_operations_user_can_open_daily_activity_but_cannot_use_sales_outreach_actions(): void
{
    $operations =
        $this->createUserWithRole(
            'Operations Executive',
            \App\Models\UserType::OPERATIONS_EXECUTIVE
        );

    /*
     * Operations can open the shared Daily Activity page.
     */
    $this->actingAs($operations)
        ->get('/admin/kpi/outreach')
        ->assertOk()
        ->assertSee(
            'Operations Daily Activity'
        );


    /*
     * Get More has no route-model binding.
     *
     * Therefore this is the cleanest exact authorization
     * assertion and MUST return 403 for Operations.
     */
    $this->actingAs($operations)
        ->withSession([
            '_token' => 'kpi-test',
        ])
        ->post(
            '/admin/kpi/outreach/get-more',
            [
                '_token' => 'kpi-test',
            ]
        )
        ->assertForbidden();


    /*
     * DNP and Remark use an {assignment} UUID.
     *
     * The UUID below intentionally does not exist.
     * Laravel may return:
     *
     * 403 = role middleware denied access first
     * 404 = route/model binding hid the nonexistent resource
     *
     * Both mean Operations cannot execute the Sales action.
     */
    $fakeAssignment =
        (string) \Illuminate\Support\Str::uuid();


    $dnpResponse =
        $this->actingAs($operations)
            ->withSession([
                '_token' => 'kpi-test',
            ])
            ->post(
                "/admin/kpi/outreach/{$fakeAssignment}/dnp",
                [
                    '_token' => 'kpi-test',
                ]
            );

    $this->assertContains(
        $dnpResponse->status(),
        [403, 404],
        'Operations must not be allowed to execute DNP.'
    );


    $remarkResponse =
        $this->actingAs($operations)
            ->withSession([
                '_token' => 'kpi-test',
            ])
            ->post(
                "/admin/kpi/outreach/{$fakeAssignment}/remark",
                [
                    '_token' => 'kpi-test',

                    'remark' =>
                        'Operations should not be allowed.',
                ]
            );

    $this->assertContains(
        $remarkResponse->status(),
        [403, 404],
        'Operations must not be allowed to execute Remark.'
    );
}

public function test_accounts_user_can_see_daily_outreach_menu_link(): void
{
    $accounts =
        $this->createUserWithRole(
            'Accounts Executive',
            \App\Models\UserType::ACCOUNTS_EXECUTIVE
        );

    $this->actingAs($accounts)
        ->get('/admin/kpi')
        ->assertOk()
        ->assertSee(
            'Daily Outreach'
        );
}
public function test_operations_user_can_see_daily_outreach_menu_link(): void
{
    $operations =
        $this->createUserWithRole(
            'Operations Executive',
            \App\Models\UserType::OPERATIONS_EXECUTIVE
        );

    $this->actingAs($operations)
        ->get('/admin/kpi')
        ->assertOk()
        ->assertSee(
            'Daily Outreach'
        );
}
}
