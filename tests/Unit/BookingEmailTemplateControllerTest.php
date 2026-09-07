<?php

namespace Tests\Unit;

use App\Models\BookingEmailTemplate;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingEmailTemplateControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_super_admin_can_update_booking_email_template(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        $admin = $this->createUser(UserType::SUPER_ADMIN);

        $this
            ->actingAs($admin)
            ->put(route('admin.booking-email-template.update'), [
                'subject' => 'Booking for {{customer_name}}',
                'body' => 'Hello {{customer_name}}, register here {{registration_link}}.',
            ])
            ->assertRedirect(route('admin.booking-email-template.edit'));

        $template = BookingEmailTemplate::query()->first();

        $this->assertNotNull($template);
        $this->assertSame(
            'Booking for {{customer_name}}',
            $template->subject
        );
        $this->assertSame(
            'Hello {{customer_name}}, register here {{registration_link}}.',
            $template->body
        );
        $this->assertSame($admin->id, $template->updated_by);
    }

    public function test_super_admin_can_open_booking_email_template_editor(): void
    {
        $admin = $this->createUser(UserType::SUPER_ADMIN);

        $this
            ->actingAs($admin)
            ->get(route('admin.booking-email-template.edit'))
            ->assertOk()
            ->assertSee('{{customer_name}}', false)
            ->assertSee('{{registration_link}}', false);
    }

    private function createUser(string $role): User
    {
        $type = UserType::firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'status' => 1,
            ]
        );

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $role . ' User',
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->integer('status')->default(1);
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

        Schema::create('booking_email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->text('body');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
