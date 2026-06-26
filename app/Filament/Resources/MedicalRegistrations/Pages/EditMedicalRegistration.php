<?php

namespace App\Filament\Resources\MedicalRegistrations\Pages;

use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicalRegistration extends EditRecord
{
    protected static string $resource = MedicalRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
