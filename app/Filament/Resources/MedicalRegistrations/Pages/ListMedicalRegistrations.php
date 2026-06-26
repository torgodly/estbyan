<?php

namespace App\Filament\Resources\MedicalRegistrations\Pages;

use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use Filament\Resources\Pages\ListRecords;

class ListMedicalRegistrations extends ListRecords
{
    protected static string $resource = MedicalRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
