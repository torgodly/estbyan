<?php

namespace App\Filament\Widgets;

use App\Enums\CardDeliveryRecipient;
use App\Models\Employee;
use App\Models\User;
use App\Support\PrintedEmployeesPeriodExport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeliveredCardsStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    protected function getStats(): array
    {
        $delivered = Employee::query()->cardsDelivered();

        $total = (clone $delivered)->count();
        $toEmployee = (clone $delivered)
            ->where('cards_delivered_to', CardDeliveryRecipient::Employee)
            ->count();
        $toAdministration = (clone $delivered)
            ->where('cards_delivered_to', CardDeliveryRecipient::Administration)
            ->count();
        $todayDate = now(PrintedEmployeesPeriodExport::TIMEZONE)->toDateString();
        [$todayStart, $todayEnd] = PrintedEmployeesPeriodExport::periodBounds($todayDate, $todayDate);
        $today = (clone $delivered)
            ->whereBetween('cards_delivered_at', [$todayStart, $todayEnd])
            ->count();

        return [
            Stat::make('عائلات مُسلَّمة', (string) $total)
                ->description('إجمالي العائلات التي سُلّمت بطاقاتها')
                ->color('success')
                ->icon('heroicon-o-hand-raised'),
            Stat::make('إلى الموظف', (string) $toEmployee)
                ->description('سُلّمت للموظف شخصياً')
                ->color('info')
                ->icon('heroicon-o-user'),
            Stat::make('إلى الإدارة', (string) $toAdministration)
                ->description('سُلّمت لإدارة العمل')
                ->color('primary')
                ->icon('heroicon-o-building-office-2'),
            Stat::make('اليوم', (string) $today)
                ->description('تسليمات اليوم (توقيت طرابلس)')
                ->color('warning')
                ->icon('heroicon-o-calendar-days'),
        ];
    }
}
