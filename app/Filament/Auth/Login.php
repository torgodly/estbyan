<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\ReviewerWorkingHours;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        $user = Filament::auth()->user();

        if (! $user instanceof User || ! $user->isReviewer() || ReviewerWorkingHours::isOpen()) {
            return $response;
        }

        Filament::auth()->logout();

        throw ValidationException::withMessages([
            'data.email' => ReviewerWorkingHours::loginBlockedMessage(),
        ]);
    }
}
