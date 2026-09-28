<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TDD §8.3: KYC documents live outside the public webroot and are only
 * ever reachable through a signed, time-limited URL — never a stable,
 * guessable path.
 */
class KycDocumentController extends Controller
{
    public function downloadLink(Request $request, KycDocument $document): JsonResponse
    {
        $this->authorize('viewKycDocuments', $document->seller);

        $url = URL::temporarySignedRoute(
            'api.v1.kyc-documents.download',
            now()->addMinutes(15),
            ['document' => $document->id],
        );

        return response()->json(['data' => ['url' => $url, 'expires_in_minutes' => 15]]);
    }

    public function download(Request $request, KycDocument $document): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired download link.');

        return Storage::disk('kyc')->download($document->file_path, $document->original_filename);
    }
}
