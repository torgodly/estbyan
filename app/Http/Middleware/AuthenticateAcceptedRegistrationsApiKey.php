<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAcceptedRegistrationsApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.accepted_registrations.key');
        $provided = (string) $request->header('X-Api-Key', '');

        if ($configured === '' || $provided === '' || ! hash_equals($configured, $provided)) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
