<?php

namespace Tests\Feature\Operations;

use App\Http\Controllers\OperationsFollowupController;
use App\Models\Lead;
use App\Models\OperationCase;
use App\Models\UserType;
use App\Services\Operations\OperationsActivityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Kpi\KpiFeatureTestCase;

class OperationsFollowupPageTest extends KpiFeatureTestCase
{
    private $operationsUser;
    private OperationCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        (require base_path('database/migrations/2025_11_04_100000_add_parent_followup_id_to_lead_followups_table.php'))->up();
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->string('contact_outcome', 30)->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
        });
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        (require base_path('database/migrations/2026_09_24_120000_add_operations_followup_fields.php'))->up();

        $this->operationsUser = $this->createUserWithRole('Operations Follow-up User', UserType::OPERATIONS_EXECUTIVE);
        $lead = Lead::findOrFail($this->createLeadFor($this->operationsUser, 'Operations Customer'));
        $this->case = OperationCase::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'type' => OperationCase::TYPE_REVIEW,
            'status' => OperationCase::STATUS_IN_PROGRESS,
            'assigned_to' => $this->operationsUser->id,
            'opened_at' => now(),
            'metadata' => [
                'review_status' => 'done',
                'image_collection_status' => 'done',
            ],
        ]);

        config(['session.driver' => 'array']);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    private function request(string $method = 'GET', array $data = []): Request
    {
        $request = Request::create('/admin/operations/case/'.$this->case->id.'/follow-up', $method, $data);
        auth()->setUser($this->operationsUser);
        $request->setUserResolver(fn () => $this->operationsUser);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);

        return $request;
    }

    public function test_operations_followup_page_is_isolated_from_the_sales_lead_workspace(): void
    {
        $html = app(OperationsFollowupController::class)
            ->create($this->case->fresh())
            ->render();

        foreach (['Add Follow-up', 'Case Summary', 'Follow-up Details', 'name="notes"', 'name="operation_status"', 'name="next_followup_at"'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        $this->assertStringNotContainsString('client-details-editor', $html);
        $this->assertStringNotContainsString('contact_country_code', $html);
    }

    public function test_operations_followup_saves_through_the_existing_activity_service(): void
    {
        $response = app(OperationsFollowupController::class)->store(
            $this->request('POST', [
                'notes' => 'Customer confirmed that images will be shared today.',
                'operation_status' => 'completed',
            ]),
            $this->case,
            app(OperationsActivityService::class)
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertDatabaseHas('operation_cases', [
            'id' => $this->case->id,
            'status' => 'completed',
            'completed_by' => $this->operationsUser->id,
        ]);
        $this->assertDatabaseHas('operation_case_activities', [
            'operation_case_id' => $this->case->id,
            'action' => 'followup_saved',
            'note' => 'Customer confirmed that images will be shared today.',
        ]);
    }
}
