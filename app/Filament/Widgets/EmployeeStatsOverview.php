<?php

namespace App\Filament\Widgets;

use App\Enums\RegistrationStatus;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmployeeStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $totalEmployees = Employee::query()->count();
        $activeEmployees = Employee::query()->where('is_active', true)->count();
        $employeesWithSubmissions = MedicalRegistration::query()
            ->whereNotNull('employee_id')
            ->where('status', '!=', RegistrationStatus::Draft)
            ->distinct('employee_id')
            ->count('employee_id');
        $employeesWithPending = MedicalRegistration::query()
            ->whereNotNull('employee_id')
            ->where('status', RegistrationStatus::Submitted)
            ->distinct('employee_id')
            ->count('employee_id');

        return [
            Stat::make('الموظفون', (string) $totalEmployees)
                ->description('إجمالي سجل الموظفين')
                ->color('primary')
                ->icon('heroicon-o-users'),
            Stat::make('نشطون', (string) $activeEmployees)
                ->description('يمكنهم تقديم الطلب')
                ->color('success')
                ->icon('heroicon-o-check-badge'),
            Stat::make('قدّموا طلباً', (string) $employeesWithSubmissions)
                ->description('غير المسودات')
                ->color('info')
                ->icon('heroicon-o-clipboard-document-check'),
            Stat::make('لديهم طلب معلّق', (string) $employeesWithPending)
                ->description('بانتظار مراجعة الموارد البشرية')
                ->color('warning')
                ->icon('heroicon-o-clock'),
        ];
    }
}
