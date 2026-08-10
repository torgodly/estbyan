<?php

namespace App\Filament\Widgets;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RegistrationStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $counts = MedicalRegistration::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            Stat::make('بانتظار المراجعة', (string) ($counts[RegistrationStatus::Submitted->value] ?? 0))
                ->description('طلبات مُرسلة تحتاج مراجعة')
                ->color('info')
                ->icon('heroicon-o-clock'),
            Stat::make('قيد التعديل', (string) ($counts[RegistrationStatus::Editing->value] ?? 0))
                ->description('الموظف يعدّل طلبه حالياً')
                ->color('warning')
                ->icon('heroicon-o-pencil-square'),
            Stat::make('مقبول', (string) ($counts[RegistrationStatus::Approved->value] ?? 0))
                ->description('طلبات معتمدة')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            Stat::make('مرفوض', (string) ($counts[RegistrationStatus::Declined->value] ?? 0))
                ->description('تحتاج تعديلاً من الموظف')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
