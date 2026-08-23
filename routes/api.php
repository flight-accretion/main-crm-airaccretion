<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatCrmMessageController;
use App\Http\Controllers\Api\WhatCrmSendMessageController;
use App\Http\Controllers\Api\WhatsAppLeadController;
use App\Http\Controllers\Api\CallSummaryController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\Api\LeadApiController;

// Secure endpoints for external AI workflows (n8n, etc.)
Route::middleware(['verify.lead.key', 'throttle:60,1'])->group(function () {
    Route::get('/leads', [LeadApiController::class, 'index']);
    Route::post('/leads', [LeadApiController::class, 'store']);
});

Route::post(
    '/whatsapp-leads',
    [
        WhatsAppLeadController::class,
        'store'
    ]
)
->middleware('whatcrm.auth')
->name('api.whatsapp-leads.store');

Route::post(
    '/whatcrm/messages',
    [
        WhatCrmMessageController::class,
        'store'
    ]
)
->middleware('whatcrm.auth')
->name('api.whatcrm.messages.store');

Route::post(
    '/whatcrm/send-message',
    [
        WhatCrmSendMessageController::class,
        'store'
    ]
)
->middleware('whatcrm.auth')
->name('api.whatcrm.send-message.store');

Route::post(
    '/call-summaries',
    [
        CallSummaryController::class,
        'store'
    ]
)
->middleware(
    'call.summary.auth'
)
->name(
    'api.call-summaries.store'
);
