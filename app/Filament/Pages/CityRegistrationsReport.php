<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\CityRegistrationsReport as CityRegistrationsReportBuilder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class CityRegistrationsReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'حسب المدينة';

    protected static ?string $title = 'التسجيل حسب المدينة';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'city-registrations-report';

    protected string $view = 'filament.pages.city-registrations-report';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public static function canAccess(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    public function mount(): void
    {
        $this->report = CityRegistrationsReportBuilder::build();
    }
}
