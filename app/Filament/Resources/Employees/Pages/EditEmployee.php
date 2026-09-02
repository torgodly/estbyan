<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'تعديل موظف';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('الملف'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['employee_number']) && is_numeric($data['employee_number'])) {
            $data['employee_number'] = str_pad((string) $data['employee_number'], 6, '0', STR_PAD_LEFT);
        }

        if (isset($data['national_id'])) {
            $data['national_id'] = trim((string) $data['national_id']);
        }

        if (isset($data['full_name'])) {
            $data['full_name'] = trim((string) $data['full_name']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EmployeeResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
