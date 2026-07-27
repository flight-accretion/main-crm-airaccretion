<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
