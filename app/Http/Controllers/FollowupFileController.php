<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FollowupFileController extends Controller
{
    public function show(Request $request, string $filename)
    {
        $safeFilename = basename(str_replace('\\', '/', $filename));

        if ($safeFilename === '' || $safeFilename !== $filename) {
            abort(404, 'Follow-up file not found.');
        }

        $path = 'followups/' . $safeFilename;
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
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
