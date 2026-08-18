<?php

namespace App\Http\Controllers;

use App\Models\MedicalRegistration;
use App\Services\ReferenceCardGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReferenceCardController extends Controller
{
    public function __invoke(Request $request, MedicalRegistration $registration, ReferenceCardGenerator $generator): Response
    {
        $sessionId = $request->session()->get('registration_id');

        abort_unless(
            $sessionId === $registration->id || $request->session()->get('reference_download_id') === $registration->id,
            403,
        );

        abort_unless(filled($registration->reference_number), 404);

        $png = $generator->png($registration);
        $filename = 'tax-'.$registration->reference_number.'.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
