<?php

namespace App\Http\Controllers;

use App\Models\LeadFollowup;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use function App\Helpers\getRepresentativeIds;

class FollowupFileController extends Controller
{
    public function show(Request $request, string $filename)
    {
        $safeFilename = basename(str_replace('\\', '/', $filename));

        if ($safeFilename === '' || $safeFilename !== $filename) {
            abort(404, 'Follow-up file not found.');
        }

        $path = 'followups/' . $safeFilename;

        $followup = LeadFollowup::with('enquiry')
            ->where('file', $path)
            ->orWhere('file', $safeFilename)
            ->first();

        if (!$followup || !$followup->enquiry) {
            abort(404, 'Follow-up file not found.');
        }

        $user = auth()->user();
        $userType = optional($user?->userType)->user_type;
        $isPaymentReviewRole = in_array(
            $userType,
            array_merge(
                UserType::ADMIN_ROLES,
                UserType::ACCOUNTS_ROLES,
                UserType::OPERATIONS_ROLES
            ),
            true
        );
        $isPaymentReceipt = (float) ($followup->received_amount ?? 0) > 0
            && !empty($followup->file);

        $representatives = ($isPaymentReviewRole && $isPaymentReceipt)
            ? null
            : getRepresentativeIds($user);

        if ($representatives !== null) {
            if ($representatives instanceof \Illuminate\Support\Collection) {
                $representatives = $representatives->toArray();
            }

            $representatives = array_map('strval', (array) $representatives);

            if (!in_array((string) $followup->enquiry->representative_user_id, $representatives, true)) {
                abort(403, 'You are not allowed to access this file.');
            }
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            abort(404, 'Follow-up file not found.');
        }

        $absolutePath = $disk->path($path);
        $mimeType = $disk->mimeType($path) ?: 'application/octet-stream';
        $disposition = $request->boolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $response = response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
        ]);

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition($disposition, $safeFilename)
        );

        return $response;
    }
}
