<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    /**
     * @return array<string | int, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge(fn (): string => (string) Employee::query()->count()),
            'submitted' => Tab::make('أرسلوا النموذج')
                ->badge(fn (): string => (string) Employee::query()->submittedForm()->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->submittedForm()),
            'not_submitted' => Tab::make('لم يرسلوا')
                ->badge(fn (): string => (string) Employee::query()->notSubmittedForm()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->notSubmittedForm()),
        ];
    }
}
