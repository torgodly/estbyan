<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ReviewerWorkingHours;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReviewerWorkingHours
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isReviewer() || ReviewerWorkingHours::isOpen()) {
            return $next($request);
        }

        Filament::auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->to(Filament::getLoginUrl() ?? url('/admin/login'))
            ->with('reviewer_hours_ended', true);
    }
}
