<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isSmartCare(): bool
    {
        return $this->role === UserRole::SmartCare;
    }

    public function isHr(): bool
    {
        return $this->role === UserRole::Hr;
    }

    public function isReviewer(): bool
    {
        return $this->role === UserRole::Reviewer;
    }

    public function canAccessFullAdmin(): bool
    {
        return ! $this->isReviewer();
    }

    public function canManageInsuranceCards(): bool
    {
        return $this->isSmartCare();
    }

    public static function authenticatedCanAccessFullAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof self && $user->canAccessFullAdmin();
    }

    public static function authenticatedIsReviewer(): bool
    {
        $user = Auth::user();

        return $user instanceof self && $user->isReviewer();
    }
}
