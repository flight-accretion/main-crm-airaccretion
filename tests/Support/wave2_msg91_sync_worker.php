<?php
$app = require __DIR__.'/wave1_qa_bootstrap.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = $app['db']->connection();
$identity = $db->selectOne('SELECT current_database() AS db, current_user AS role');
if ($identity->db !== 'accretion_wave1_qa' || $identity->role !== 'accretion_wave1_qa_role') throw new RuntimeException('QA identity mismatch');
$db->statement("SET application_name = 'msg91-sync-concurrency'");
$db->statement("SET lock_timeout = '20s'");
$sender = App\Models\Msg91WhatsAppSender::findOrFail($argv[1]);
$template = App\Models\Msg91WhatsAppTemplate::findOrFail($argv[2]);
config(['services.msg91_campaigns.template_sync_enabled' => true,
    'services.msg91_campaigns.control_url' => 'https://control.msg91.com', 'services.msg91_campaigns.auth_key' => 'qa-concurrent-fake-key']);
Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory());
Illuminate\Support\Facades\Http::fake(function ($request) use ($sender, $template) {
    if ($request->method() !== 'GET' || $request->url() !== 'https://control.msg91.com/api/v5/whatsapp/get-template-client/'.$sender->integrated_number) throw new RuntimeException('Unexpected HTTP blocked');
    return Illuminate\Support\Facades\Http::response(['data' => ['templates' => [[
        'template_name' => $template->name, 'language' => $template->language, 'category' => $template->category,
        'id' => 'qa-concurrent-provider-id', 'status' => 'approved', 'components' => $template->components,
    ]]]]);
});
$count = app(App\Services\WhatsApp\Msg91\Msg91TemplateSyncService::class)->syncSender($sender);
echo json_encode(['pid' => $db->selectOne('SELECT pg_backend_pid() AS pid')->pid, 'count' => $count]);
