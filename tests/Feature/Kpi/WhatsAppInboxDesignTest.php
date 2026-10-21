<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use App\Http\Controllers\WhatsAppInboxController;
use App\Services\WhatsAppConversationVisibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class WhatsAppInboxDesignTest extends KpiFeatureTestCase
{
    private $sales;
    private $other;
    private string $conversation;
    private string $second;
    private string $hidden;
    private string $lead;
    private string $compiled;

    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        Schema::create('whatsapp_contacts', function (Blueprint $t) {$t->uuid('id')->primary();$t->string('name');$t->string('normalized_phone');$t->string('raw_phone');$t->timestamps();});
        Schema::create('whatsapp_conversations', function (Blueprint $t) {$t->uuid('id')->primary();$t->uuid('contact_id');$t->uuid('lead_id')->nullable();$t->uuid('assigned_user_id');$t->integer('unread_count');$t->string('status')->default('open');$t->text('last_message');$t->timestamp('last_message_at');$t->timestamps();});
        Schema::create('whatsapp_messages', function (Blueprint $t) {$t->uuid('id')->primary();$t->uuid('conversation_id');$t->string('direction',20);$t->timestamp('message_at')->nullable();$t->timestamps();});
        config(['cache.default'=>'array']);
        $this->sales = $this->createUserWithRole('Inbox Sales', UserType::SALES_EXECUTIVE);
        $this->other = $this->createUserWithRole('Other Inbox Sales', UserType::SALES_EXECUTIVE);
        $this->lead = $this->createLeadFor($this->sales);
        DB::table('operation_cases')->insert(['id'=>(string)Str::uuid(),'lead_id'=>$this->lead,'type'=>'review','status'=>'pending','opened_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->conversation = $this->conversation($this->sales->id, 'Aditi Sharma', 2, $this->lead, now()->subDays(2));
        $this->second = $this->conversation($this->sales->id, 'Second Client', 0, null, now());
        $this->hidden = $this->conversation($this->other->id, 'Hidden Customer', 9, null, now());
        config(['session.driver'=>'array']);auth()->setUser($this->sales);
        $this->compiled = sys_get_temp_dir().'/wa-design-'.Str::uuid();mkdir($this->compiled);
        config(['view.compiled'=>$this->compiled]);app()->forgetInstance('blade.compiler');app('view.engine.resolver')->forget('blade');
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));view()->share('errors',new \Illuminate\Support\ViewErrorBag());
    }

    protected function tearDown(): void {(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->compiled);parent::tearDown();}

    private function conversation(string $user, string $name, int $unread, ?string $lead, $date): string
    {
        $id=(string)Str::uuid();$contact=(string)Str::uuid();
        DB::table('whatsapp_contacts')->insert(['id'=>$contact,'name'=>$name,'normalized_phone'=>'919876543210','raw_phone'=>'9876543210']);
        DB::table('whatsapp_conversations')->insert(['id'=>$id,'contact_id'=>$contact,'lead_id'=>$lead,'assigned_user_id'=>$user,'unread_count'=>$unread,'status'=>'open','last_message'=>'Please confirm the gate pass','last_message_at'=>$date,'created_at'=>now(),'updated_at'=>now()]);return $id;
    }

    private function request(array $filters=[]): Request
    {
        $request=Request::create('/admin/whatsapp','GET',$filters);$request->setLaravelSession(app('session.store'));app()->instance('request',$request);return $request;
    }

    public function test_summary_and_filters_use_only_visible_conversations(): void
    {
        $controller=app(WhatsAppInboxController::class);$visibility=app(WhatsAppConversationVisibilityService::class);
        $payload=$controller->conversations($this->request(['status'=>'unread']),$visibility)->getData(true);
        $this->assertSame(['unread_chats'=>1,'unread_messages'=>2,'on_case'=>1,'old_unread'=>1],array_intersect_key($payload['summary'],array_flip(['unread_chats','unread_messages','on_case','old_unread'])));
        $this->assertSame(null,$payload['summary']['avg_first_response_minutes']);
        $this->assertSame(1,count($payload['data']));$this->assertSame($this->conversation,$payload['data'][0]['id']);
        $case=$controller->conversations($this->request(['status'=>'on_case']),$visibility)->getData(true);
        $this->assertSame(1,count($case['data']));$this->assertSame(true,$case['data'][0]['on_case']);
        $this->assertSame(route('admin.leads.view',$this->lead),$case['data'][0]['lead_view_url']);
        $read=$controller->conversations($this->request(['status'=>'read']),$visibility)->getData(true);$this->assertSame(1,count($read['data']));
        $denied=$controller->conversations($this->request(['agent_id'=>$this->other->id]),$visibility)->getData(true);$this->assertSame(0,$denied['summary']['unread_messages']);
        $ops=$this->createUserWithRole('Inbox Operations',UserType::OPERATIONS_EXECUTIVE);auth()->setUser($ops);
        $all=$controller->conversations($this->request(),$visibility)->getData(true);$this->assertSame(11,$all['summary']['unread_messages']);
    }

    public function test_redesign_keeps_composer_and_has_open_lead_without_case_panel(): void
    {
        $this->request();$controller=app(WhatsAppInboxController::class);$visibility=app(WhatsAppConversationVisibilityService::class);
        $html=$controller->index($visibility)->render();
        foreach(['wa-summary','wa-open-lead','wa-media-fields','wa-contact-fields','wa-location-fields','wa-send-form','wa-status-chips'] as $id)$this->assertSame(true,str_contains($html,$id));
        $this->assertSame(false,str_contains($html,'Case context'));
        $this->assertSame(true,str_contains($html,'wa-summary-response'));
        $this->assertSame(false,str_contains($html,'Not tracked'));
        $dir=getenv('CRM_WA_PREVIEW_DIR');if($dir){file_put_contents($dir.'/inbox.html',$html);file_put_contents($dir.'/conversations.json',json_encode($controller->conversations($this->request(),$visibility)->getData(true)));}
    }

    private function message(string $conversation, string $direction, $at): void
    {
        DB::table('whatsapp_messages')->insert(['id'=>(string)Str::uuid(),'conversation_id'=>$conversation,'direction'=>$direction,'message_at'=>$at]);
    }

    public function test_first_response_time_uses_first_message_of_each_customer_turn_and_visible_chats_only(): void
    {
        $base=now()->subHours(3);
        // Turn 1: two customer messages, reply 10 minutes after the first. Turn 2: reply after 5 minutes.
        $this->message($this->conversation,'incoming',$base);$this->message($this->conversation,'incoming',$base->copy()->addMinutes(2));
        $this->message($this->conversation,'outgoing',$base->copy()->addMinutes(10));$this->message($this->conversation,'outgoing',$base->copy()->addMinutes(11));
        $this->message($this->conversation,'incoming',$base->copy()->addMinutes(20));$this->message($this->conversation,'outgoing',$base->copy()->addMinutes(25));
        $this->message($this->second,'incoming',$base->copy()->addMinutes(30));
        $this->message($this->second,'incoming',now()->subDays(9));$this->message($this->second,'outgoing',now()->subDays(9)->addHours(5));
        $this->message($this->hidden,'incoming',$base);$this->message($this->hidden,'outgoing',$base->copy()->addHours(2));
        $controller=app(WhatsAppInboxController::class);$visibility=app(WhatsAppConversationVisibilityService::class);
        // A search narrows the list but not the response metric, which covers every visible chat.
        $summary=$controller->conversations($this->request(['search'=>'Aditi']),$visibility)->getData(true)['summary'];
        $this->assertSame(7.5,$summary['avg_first_response_minutes']);
        $this->assertSame(2,$summary['responded_turns']);
        $this->assertSame(1,$summary['awaiting_reply']);
    }
}
