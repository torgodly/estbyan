<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected string $view = 'filament.resources.employees.pages.view-employee';

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name;
    }

    public function getHeading(): string|Htmlable
    {
        return 'ملف الموظف';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing([
            'latestSubmittedRegistration',
            'medicalRegistrations' => fn ($query) => $query->latest('created_at'),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function hasSubmittedForm(): bool
    {
        return $this->record->hasSubmittedForm();
    }

    public function hasPendingRegistration(): bool
    {
        return $this->record->medicalRegistrations
            ->contains(fn ($registration) => $registration->status === RegistrationStatus::Submitted);
    }

    public function hasApprovedRegistration(): bool
    {
        return $this->record->medicalRegistrations
            ->contains(fn ($registration) => $registration->status === RegistrationStatus::Approved);
    }
}
