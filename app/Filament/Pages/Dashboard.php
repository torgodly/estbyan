<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function shouldRegisterNavigation(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    public function mount(): void
    {
        if (User::authenticatedIsReviewer()) {
            $this->redirect(PendingReviewResource::getUrl());
        }
    }
}
