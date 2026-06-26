<?php

namespace App\Http\Middleware;

use App\Settings\RegistrationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationFormIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(RegistrationSettings::class);

        if ($settings->form_enabled) {
            return $next($request);
        }

        return response()->view('registration.closed', [
            'messageAr' => $settings->disabled_message_ar,
            'messageEn' => $settings->disabled_message_en,
        ], 503);
    }
}
