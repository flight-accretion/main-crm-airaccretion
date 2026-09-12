<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class FollowupFileControllerTest extends TestCase
{
    public function test_authenticated_user_can_view_followup_receipt_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('followups/receipt.jpg', 'receipt-bytes');

        $user = new User([
            'name' => 'Receipt Viewer',
            'email' => 'receipt-viewer@example.test',
        ]);
        $user->id = (string) Str::uuid();
        $user->exists = true;

        $response = $this
            ->actingAs($user)
            ->get(route('admin.followups.file', [
                'filename' => 'receipt.jpg',
            ]));

        $response->assertOk();
        $this->assertInstanceOf(
            BinaryFileResponse::class,
            $response->baseResponse
        );
        $this->assertStringContainsString(
            'receipt.jpg',
            $response->headers->get('content-disposition')
        );
    }

    public function test_receipt_views_use_followup_file_route_not_public_storage_path(): void
    {
        $views = [
            resource_path('views/admin/account/payment-review/payment-review.blade.php'),
            resource_path('views/admin/account/rides/ride-status.blade.php'),
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($view);

            $this->assertStringContainsString(
                '/admin/followups/files',
                $contents,
                basename($view) . ' should use the protected follow-up file route.'
            );
            $this->assertStringNotContainsString(
                '/storage/followups/',
                $contents,
                basename($view) . ' should not bypass the follow-up file controller.'
            );
        }
    }
}
