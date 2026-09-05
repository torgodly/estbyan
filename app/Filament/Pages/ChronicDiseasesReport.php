<?php

namespace App\Filament\Pages;

use App\Support\ChronicDiseasesReport as ChronicDiseasesReportBuilder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ChronicDiseasesReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'الأمراض المزمنة';

    protected static ?string $title = 'تقرير الأمراض المزمنة';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'chronic-diseases-report';

    protected string $view = 'filament.pages.chronic-diseases-report';

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public function mount(): void
    {
        $this->report = ChronicDiseasesReportBuilder::build();
    }
}
