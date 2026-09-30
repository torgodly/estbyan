<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\PrintedEmployeesPeriodExport;
use App\Support\PrintedEmployeesPeriodPdf;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ExportPrintedCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'تصدير المطبوع';

    protected static ?string $title = 'تصدير البطاقات المطبوعة';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'export-printed-cards';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageInsuranceCards();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $today = now(PrintedEmployeesPeriodExport::TIMEZONE)->toDateString();

        $this->form->fill([
            'printed_from' => $today,
            'printed_until' => $today,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('فترة الطباعة')
                ->description('اختر تاريخ البداية والنهاية. يمكن أن يكونا نفس اليوم.')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    DatePicker::make('printed_from')
                        ->label('من تاريخ')
                        ->required()
                        ->native(false)
                        ->live(),
                    DatePicker::make('printed_until')
                        ->label('إلى تاريخ')
                        ->required()
                        ->native(false)
                        ->minDate(fn (Get $get): ?string => $get('printed_from'))
                        ->live(),
                ])
                ->columns(2),
        ]);
    }

    public function export(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        return PrintedEmployeesPeriodExport::download(
            $data['printed_from'],
            $data['printed_until'],
        );
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        return PrintedEmployeesPeriodPdf::download(
            $data['printed_from'],
            $data['printed_until'],
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('printed-cards-export-form')
                ->livewireSubmitHandler('export')
                ->footer([
                    Actions::make([
                        Action::make('export')
                            ->label('تصدير Excel')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->submit('export'),
                        Action::make('exportPdf')
                            ->label('تحميل PDF')
                            ->icon(Heroicon::OutlinedDocumentArrowDown)
                            ->color('gray')
                            ->action('exportPdf'),
                    ])->key('form-actions'),
                ]),
        ]);
    }
}
