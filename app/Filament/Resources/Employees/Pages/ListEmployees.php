<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importHint')
                ->label('استيراد عبر الأمر')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->disabled()
                ->tooltip('php artisan employees:import database/data/employees.xlsx'),
        ];
    }
}
