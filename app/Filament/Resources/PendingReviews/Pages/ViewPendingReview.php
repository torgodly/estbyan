<?php

namespace App\Filament\Resources\PendingReviews\Pages;

use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Filament\Resources\PendingReviews\PendingReviewResource;

class ViewPendingReview extends ViewMedicalRegistration
{
    protected static string $resource = PendingReviewResource::class;
}
