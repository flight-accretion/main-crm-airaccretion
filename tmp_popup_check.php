<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('email', 'sales3@accretion.in')->first();
if (!$user) {
    fwrite(STDERR, "User not found\n");
    exit(1);
}

$user->last_login = now();
$user->save();

$service = app(App\Services\LeadAllocationService::class);
$data = $service->getPopupData($user, now());

echo json_encode([
    'show_popup' => $data['show_popup'],
    'reason' => $data['popup_reason'],
    'office_open' => $data['office_open'],
    'last_login' => $user->last_login,
], JSON_PRETTY_PRINT), PHP_EOL;
