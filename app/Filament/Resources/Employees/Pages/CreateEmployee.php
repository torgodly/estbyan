<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'إضافة موظف';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->normalizeEmployeeData($data);
        $data['is_active'] = true;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return EmployeeResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeEmployeeData(array $data): array
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
}
