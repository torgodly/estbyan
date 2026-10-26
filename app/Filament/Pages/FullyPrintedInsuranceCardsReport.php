<?php

namespace App\Filament\Pages;

use App\Models\Employee;
use App\Models\User;
use App\Support\FullyPrintedEmployeesExport;
use App\Support\InsuranceCardNumber;
use App\Support\PrintedEmployeesPeriodExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class FullyPrintedInsuranceCardsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $navigationLabel = 'تقرير المطبوع بالكامل';

    protected static ?string $title = 'تقرير العائلات المطبوعة بالكامل';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'fully-printed-insurance-cards-report';

    protected string $view = 'filament.pages.fully-printed-insurance-cards-report';

    public static function canAccess(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'عائلات اكتملت طباعة بطاقة الموظف وكل أفراد العائلة.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('تصدير Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action(fn (): StreamedResponse => $this->exportExcel()),
        ];
    }

    public function exportExcel(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        return FullyPrintedEmployeesExport::download(
            $this->getFilteredSortedTableQuery() ?? Employee::query()->cardsFullyPrinted(),
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Employee::query())
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->cardsFullyPrinted()
                    ->with([
                        'latestSubmittedRegistration' => fn ($registrationQuery) => $registrationQuery->withCount('beneficiaries'),
                    ]);
            })
            ->defaultSort('card_printed_at', 'desc')
            ->modelLabel('عائلة مطبوعة')
            ->pluralModelLabel('عائلات مطبوعة')
            ->emptyStateHeading('لا توجد عائلات مطبوعة بالكامل بعد')
            ->emptyStateDescription('تظهر هنا العائلات بعد طباعة بطاقة الموظف وكل أفراد العائلة.')
            ->columns([
                TextColumn::make('full_name')
                    ->label('الموظف')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Employee $record): string => $record->employee_number),
                TextColumn::make('card_number')
                    ->label('رقم البطاقة')
                    ->formatStateUsing(fn (?string $state): string => InsuranceCardNumber::display($state))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('workplace')
                    ->label('الإدارة')
                    ->formatStateUsing(fn (?string $state, Employee $record): string => $record->workplaceLabel() ?? '—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('office')
                    ->label('المكتب')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('family_cards_count')
                    ->label('عدد البطاقات')
                    ->state(fn (Employee $record): int => $record->familyCardsCount())
                    ->numeric()
                    ->alignCenter()
                    ->sortable(false),
                TextColumn::make('print_status')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (): string => 'طُبعت بالكامل')
                    ->color('success'),
                TextColumn::make('card_printed_at')
                    ->label('تاريخ طباعة الموظف')
                    ->dateTime('Y-m-d H:i', PrintedEmployeesPeriodExport::TIMEZONE)
                    ->sortable(),
                TextColumn::make('latestSubmittedRegistration.reference_number')
                    ->label('المرجع')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('workplace')
                    ->label('الإدارة')
                    ->options(fn (): array => config('registration.workplaces', [])),
                Filter::make('card_printed_at')
                    ->label('تاريخ الطباعة')
                    ->schema([
                        DateTimePicker::make('printed_from')
                            ->label('من تاريخ ووقت')
                            ->native(false)
                            ->seconds(false)
                            ->timezone(PrintedEmployeesPeriodExport::TIMEZONE)
                            ->displayFormat('Y-m-d H:i'),
                        DateTimePicker::make('printed_until')
                            ->label('إلى تاريخ ووقت')
                            ->native(false)
                            ->seconds(false)
                            ->timezone(PrintedEmployeesPeriodExport::TIMEZONE)
                            ->displayFormat('Y-m-d H:i'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['printed_from'] ?? null;
                        $until = $data['printed_until'] ?? null;

                        if (filled($from) && filled($until)) {
                            [$start, $end] = PrintedEmployeesPeriodExport::periodBounds($from, $until);

                            return $query->whereBetween('card_printed_at', [$start, $end]);
                        }

                        if (filled($from)) {
                            [$start] = PrintedEmployeesPeriodExport::periodBounds($from, $from);

                            return $query->where('card_printed_at', '>=', $start);
                        }

                        if (filled($until)) {
                            [, $end] = PrintedEmployeesPeriodExport::periodBounds($until, $until);

                            return $query->where('card_printed_at', '<=', $end);
                        }

                        return $query;
                    }),
            ])
            ->deferFilters(false)
            ->paginated([10, 25, 50, 100]);
    }
}
