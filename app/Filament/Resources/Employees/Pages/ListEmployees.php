<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Support\EmployeesFamilyExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportEmployeesAndFamily')
                ->label('تصدير الموظفين والعائلة')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (EmployeesFamilyExport $export): StreamedResponse {
                    $employees = $this->getTableQueryForExport()
                        ->with(['latestSubmittedRegistration.beneficiaries'])
                        ->get();

                    return $export->download($employees);
                }),
            CreateAction::make()
                ->label('إضافة موظف'),
        ];
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
