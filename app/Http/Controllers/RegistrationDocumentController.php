<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationDocumentController extends Controller
{
    public function show(Request $request, MedicalRegistration $registration, string $document): Response
    {
        abort_unless(in_array($document, [
            RegistrationDocuments::FAMILY_STATUS,
            RegistrationDocuments::EMPLOYEE_PHOTO,
        ], true), 404);

        $this->authorizeAccess($request, $registration);

        $path = RegistrationDocuments::pathFor($registration, $document);

        abort_unless(filled($path) && RegistrationDocuments::disk()->exists($path), 404);

        return RegistrationDocuments::disk()->response(
            $path,
            headers: self::documentHeaders($path),
        );
    }

    public function beneficiary(Request $request, MedicalRegistration $registration, Beneficiary $beneficiary): Response
    {
        abort_unless($beneficiary->medical_registration_id === $registration->id, 404);

        $this->authorizeAccess($request, $registration);

        abort_unless(
            filled($beneficiary->photo_path) && RegistrationDocuments::disk()->exists($beneficiary->photo_path),
            404,
        );

        return RegistrationDocuments::disk()->response(
            $beneficiary->photo_path,
            headers: self::documentHeaders($beneficiary->photo_path),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function documentHeaders(string $path): array
    {
        return [
            'Content-Type' => RegistrationDocuments::mimeType($path),
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    protected function authorizeAccess(Request $request, MedicalRegistration $registration): void
    {
        $isOwner = (int) $request->session()->get('registration_id') === (int) $registration->id
            || (int) $request->session()->get('reference_download_id') === (int) $registration->id;

        $isAdmin = $request->user() !== null;

        abort_unless($isOwner || $isAdmin, 403);
    }
}
