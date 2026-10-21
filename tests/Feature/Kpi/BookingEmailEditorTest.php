<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\BookingEmailTemplateController;
use App\Models\BookingEmailTemplate;
use App\Models\BookingEmailTemplateRevision;
use App\Models\UserType;
use App\Services\BookingEmailTemplateHistory;
use App\Mail\BookingTemplateTestMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingEmailEditorTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('booking_email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('subject', 500); $table->text('body');
            $table->uuid('updated_by')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_10_21_190000_create_booking_email_template_revisions_table.php'))->up();
    }

    public function test_saves_and_restores_append_versions_without_losing_history(): void
    {
        $actor = $this->createUserWithRole('Template Admin', UserType::SUPER_ADMIN);
        $template = BookingEmailTemplate::create(['subject'=>'Original','body'=>'Hello {{customer_name}}']);
        $history = new BookingEmailTemplateHistory();
        $history->save($template, 'Changed', 'New body', $actor->id, 0, 'Updated subject and body');
        $this->assertSame(2, BookingEmailTemplateRevision::count());
        $original = BookingEmailTemplateRevision::where('version', 1)->first();
        $history->restore($template, $original, $actor->id, 2);
        $this->assertSame('Original', $template->fresh()->subject);
        $this->assertSame(3, BookingEmailTemplateRevision::count());
        $this->assertSame('Changed', BookingEmailTemplateRevision::where('version', 2)->first()->subject);
        $this->assertSame($actor->id, BookingEmailTemplateRevision::where('version', 3)->first()->created_by);
        try {
            $history->save($template, 'Stale edit', 'Stale', $actor->id, 2);
            throw new \RuntimeException('Stale edit was accepted');
        } catch (ValidationException $expected) {
            $this->assertSame('Original', $template->fresh()->subject);
            $this->assertSame(3, BookingEmailTemplateRevision::count());
        }
    }

    public function test_test_send_targets_only_the_actor_and_does_not_save_or_cc(): void
    {
        $actor = $this->createUserWithRole('Test Recipient', UserType::SUPER_ADMIN);
        Auth::setUser($actor);
        Mail::fake();
        $template = BookingEmailTemplate::create(['subject'=>'Saved','body'=>'Saved body']);
        $request = Request::create('/admin/booking-email-template/test', 'POST', [
            'subject'=>'Hi {{customer_name}}', 'body'=>'Hello {{ customer_name }} <script>alert(1)</script>',
            'to'=>'customer@unapproved.test', 'cc'=>'other@unapproved.test',
        ]);
        (new BookingEmailTemplateController())->sendTest($request);
        $mails = Mail::sent(BookingTemplateTestMail::class);
        $this->assertSame(1, $mails->count());
        $mail = $mails->first();
        $mail->build();
        $this->assertSame($actor->email, $mail->to[0]['address']);
        $this->assertSame([], $mail->cc);
        $this->assertSame([], $mail->bcc);
        $this->assertSame('[TEST] Hi Ankit Goel', $mail->subject);
        $this->assertSame('Saved', $template->fresh()->subject);
        $this->assertSame(0, BookingEmailTemplateRevision::count());
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $mail->render());
    }

    public function test_sales_cannot_send_template_tests(): void
    {
        Auth::setUser($this->createUserWithRole('Sales User', UserType::SALES_EXECUTIVE));
        Mail::fake();
        try {
            (new BookingEmailTemplateController())->sendTest(Request::create('/', 'POST', ['subject'=>'Test','body'=>'Body']));
            throw new \RuntimeException('Sales could send template test');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_controller_save_restore_and_migration_baseline(): void
    {
        $actor = $this->createUserWithRole('Editor Admin', UserType::SUPER_ADMIN);
        Auth::setUser($actor);
        $template = BookingEmailTemplate::create(['subject'=>'Live original','body'=>'Original body']);
        Schema::drop('booking_email_template_revisions');
        (require database_path('migrations/2026_10_21_190000_create_booking_email_template_revisions_table.php'))->up();
        $original = BookingEmailTemplateRevision::first();
        $this->assertSame('Live original', $original->subject);
        $this->assertSame('Original body', $template->fresh()->body);
        $controller = new BookingEmailTemplateController();
        $response = $controller->update(Request::create('/', 'PUT', ['subject'=>'New subject','body'=>'New body','expected_version'=>1,'change_note'=>'Changed content']));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('New subject', $template->fresh()->subject);
        $this->assertSame('Changed content', BookingEmailTemplateRevision::where('version',2)->first()->change_note);
        $controller->restore(Request::create('/', 'POST', ['expected_version'=>2]), $original);
        $this->assertSame('Live original', $template->fresh()->subject);
        $this->assertSame(3, BookingEmailTemplateRevision::count());
        $other = BookingEmailTemplate::create(['subject'=>'Other','body'=>'Other']);
        $foreign = BookingEmailTemplateRevision::create(['booking_email_template_id'=>$other->id,'version'=>1,'subject'=>'Other','body'=>'Other']);
        try {
            $controller->restore(Request::create('/', 'POST', ['expected_version'=>3]), $foreign);
            throw new \RuntimeException('Restored unrelated template');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
