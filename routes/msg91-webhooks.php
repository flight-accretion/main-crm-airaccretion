<?php

use App\Http\Controllers\Msg91WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/msg91/whatsapp/campaigns', [Msg91WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.msg91.whatsapp.campaigns');
