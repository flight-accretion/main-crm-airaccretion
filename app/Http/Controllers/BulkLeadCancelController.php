<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkLeadCancelRequest;
use App\Services\BulkLeadCancellationService;
use Illuminate\Http\JsonResponse;

class BulkLeadCancelController extends Controller
{
    public function __invoke(
        BulkLeadCancelRequest $request,
        BulkLeadCancellationService $service
    ): JsonResponse {
        $data = $request->validated();

        $result = $service->run(
            $data['lead_ids'],
            trim($data['reason']),
            $data['operation_id'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk cancellation processed.',
            'counts' => [
                'cancelled' => count($result['cancelled']),
                'skipped' => count($result['skipped']),
                'failed' => count($result['failed']),
            ],
            'details' => $result,
        ]);
    }
}
