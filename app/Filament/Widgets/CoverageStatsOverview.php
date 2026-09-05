<?php

namespace App\Filament\Widgets;

use App\Enums\RegistrationStatus;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class CoverageStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $registeredEmployees = (int) MedicalRegistration::query()
            ->whereNotNull('employee_id')
            ->where('status', '!=', RegistrationStatus::Draft)
            ->selectRaw('count(distinct employee_id) as aggregate')
            ->value('aggregate');

        $familyMembers = Beneficiary::query()
            ->whereHas(
                'medicalRegistration',
                fn (Builder $query): Builder => $query->where('status', '!=', RegistrationStatus::Draft),
            )
            ->count();

        return [
            Stat::make('الموظفون المسجّلون', (string) $registeredEmployees)
                ->description('عدد الموظفين الذين أكملوا التسجيل فقط')
                ->color('success')
                ->icon('heroicon-o-user-group'),
            Stat::make('أفراد العائلة', (string) $familyMembers)
                ->description('عدد أفراد العائلة المسجّلين فقط')
                ->color('info')
                ->icon('heroicon-o-home'),
            Stat::make('الإجمالي', (string) ($registeredEmployees + $familyMembers))
                ->description('الموظفون المسجّلون + أفراد العائلة')
                ->color('primary')
                ->icon('heroicon-o-users'),
        ];
    }
}
