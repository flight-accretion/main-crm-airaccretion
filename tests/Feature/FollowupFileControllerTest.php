<?php

namespace Tests\Feature;

use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Kpi\KpiFeatureTestCase;

class FollowupFileControllerTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array', 'session.driver'=>'array']);
        Schema::table('lead_followups', function (Blueprint $table) { $table->string('file')->nullable(); });
        $this->app->useStoragePath(sys_get_temp_dir().'/crm-receipt-tests-'.Str::uuid());
        Storage::fake('public');
    }

    private function receipt(string $file = 'followups/receipt.pdf', float $amount = 100): string
    {
        $owner = $this->createUserWithRole('Sales owner', UserType::SALES_EXECUTIVE);
        $lead = $this->createLeadFor($owner);
        DB::table('lead_followups')->insert(['id'=>(string)Str::uuid(), 'lead_id'=>$lead, 'status'=>3, 'received_amount'=>$amount, 'file'=>$file, 'created_at'=>now(), 'updated_at'=>now()]);
        Storage::disk('public')->put('followups/'.basename($file), '%PDF-1.4 receipt fixture');
        return $owner->id;
    }

    public static function accountsRoles(): array
    {
        return array_map(fn($role)=>[$role], UserType::ACCOUNTS_ROLES);
    }

    /** @dataProvider accountsRoles */
    public function test_all_accounts_roles_can_view_and_download_other_sales_receipts(string $role): void
    {
        $this->receipt();
        $actor=$this->createUserWithRole('Accounts viewer', $role);
        $this->actingAs($actor);
        foreach (['admin.followups.file','admin.followups.storage-fallback'] as $route) {
            $response=$this->get(route($route,['filename'=>'receipt.pdf']));
            $response->assertOk();
            $this->assertInstanceOf(BinaryFileResponse::class,$response->baseResponse);
            $this->assertStringContainsString('inline;', $response->headers->get('Content-Disposition'));
            $this->get(route($route,['filename'=>'receipt.pdf','download'=>1]))->assertOk()
                ->assertHeader('Content-Disposition','attachment; filename=receipt.pdf');
        }
    }

    public function test_legacy_filename_and_existing_operations_admin_access_remain(): void
    {
        $this->receipt('legacy.pdf');
        foreach ([UserType::SUPER_ADMIN, UserType::OPERATIONS_EXECUTIVE, UserType::ACCOUNTS_EXECUTIVE] as $role) {
            $this->actingAs($this->createUserWithRole('Receipt viewer', $role))
                ->get(route('admin.followups.file',['filename'=>'legacy.pdf']))->assertOk();
        }
    }

    public function test_receipts_do_not_become_public_or_accessible_to_unrelated_sales(): void
    {
        $this->receipt();
        $url=route('admin.followups.file',['filename'=>'receipt.pdf']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->createUserWithRole('Unrelated sales',UserType::SALES_EXECUTIVE))->get($url)->assertForbidden();
        $this->get(route('admin.followups.file',['filename'=>'missing.pdf']))->assertNotFound();
        $this->get('/admin/followups/files/..%2Freceipt.pdf')->assertNotFound();
    }

    public function test_nonpayment_attachments_are_not_granted_accounts_global_access(): void
    {
        $this->receipt('followups/note.pdf',0);
        $this->actingAs($this->createUserWithRole('Accounts viewer',UserType::ACCOUNTS_EXECUTIVE))
            ->get(route('admin.followups.file',['filename'=>'note.pdf']))->assertForbidden();
    }

    public function test_receipt_views_use_same_origin_protected_named_route(): void
    {
        foreach (['rides/ride-status','payment-review/payment-review','vendors/vendor-payments'] as $page) {
            $contents=file_get_contents(resource_path('views/admin/account/'.$page.'.blade.php'));
            $this->assertStringContainsString("route('admin.followups.file'", $contents);
            $this->assertStringContainsString("'__file__'], false)", $contents);
        }
    }
}
