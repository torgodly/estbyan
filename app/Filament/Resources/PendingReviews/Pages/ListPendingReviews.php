<?php

namespace App\Filament\Resources\PendingReviews\Pages;

use App\Filament\Resources\PendingReviews\PendingReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingReviews extends ListRecords
{
    protected static string $resource = PendingReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
