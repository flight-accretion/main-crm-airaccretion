<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelSkyrackLeadRequest;
use App\Services\Skyrack\ExistingCrmLeadCancellation;
use App\Services\Skyrack\SkyrackLeadCancellationService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SkyrackLeadCancellationController extends Controller
{
    public function store(
        CancelSkyrackLeadRequest $request,
        ExistingCrmLeadCancellation $crm,
        SkyrackLeadCancellationService $service
    ): JsonResponse {
       // Validate request before resolving agent identity.
        $data = $request->validated();

        // Resolve CRM user using agent_number.
        // Existing Skyrack authentication middleware must
        // already have authenticated the API token.
        $actor = $crm->actor($request);

        if (!$actor) {

            return $this->skyrackResponse([
                'message' => 'Agent not found or agent number is ambiguous.',
            ]);

        }

        try {
           [$body] = $service->execute(
        $data,
        $actor
    );
            return $this->skyrackResponse($body);
        } catch (HttpExceptionInterface $e) {
            return $this->skyrackResponse([
                'message' => $e->getMessage(),
            ]);
        } catch (RuntimeException $e) {
            // Deployment safety: do not allow the API to alter leads until adapter is mapped.
            if ($e->getMessage() === 'CRM_CANCEL_INTEGRATION_NOT_CONFIGURED') {
                report($e);
                return $this->skyrackResponse([
                    'message' => 'Cancellation integration has not been configured.',
                ]);
            }
            throw $e;
        }
    }

    private function skyrackResponse(array $body): JsonResponse
    {
        $body['success'] = true;
        $body['status'] = true;

        return response()->json($body, 200);
    }
}
