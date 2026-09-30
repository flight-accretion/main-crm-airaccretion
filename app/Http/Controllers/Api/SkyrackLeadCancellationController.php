<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelSkyrackLeadRequest;
use App\Services\Skyrack\ExistingCrmLeadCancellation;
use App\Services\Skyrack\SkyrackLeadCancellationService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class SkyrackLeadCancellationController extends Controller
{
    public function store(
        CancelSkyrackLeadRequest $request,
        ExistingCrmLeadCancellation $crm,
        SkyrackLeadCancellationService $service
    ): JsonResponse {
        $actor = $crm->actor($request);
        if (!$actor) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated Skyrack actor could not be resolved.',
            ], 403);
        }

        try {
            [$body, $code] = $service->execute($request->validated(), $actor);
            return response()->json($body, $code);
        } catch (RuntimeException $e) {
            // Deployment safety: do not allow the API to alter leads until adapter is mapped.
            if ($e->getMessage() === 'CRM_CANCEL_INTEGRATION_NOT_CONFIGURED') {
                report($e);
                return response()->json([
                    'success' => false,
                    'message' => 'Cancellation integration has not been configured.',
                ], 503);
            }
            throw $e;
        }
    }
}
