<?php

namespace App\Filament\Pages;

use App\Enums\CardDeliveryRecipient;
use App\Filament\Widgets\DeliveredCardsStatsOverview;
use App\Models\Employee;
use App\Models\User;
use App\Support\InsuranceCardNumber;
use App\Support\PrintedEmployeesPeriodExport;
use BackedEnum;
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
use UnitEnum;

class DeliveredInsuranceCardsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'تقرير التسليم';

    protected static ?string $title = 'تقرير العائلات المُسلَّمة';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'delivered-insurance-cards-report';

    protected string $view = 'filament.pages.delivered-insurance-cards-report';

    public static function canAccess(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'عرض العائلات التي تم تسليم بطاقاتها، مع العدد والحالة وجهة الاستلام والمُسلِّم.';
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            DeliveredCardsStatsOverview::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Employee::query())
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->cardsDelivered()
                    ->with([
                        'cardsDeliveredBy',
                        'latestSubmittedRegistration' => fn ($registrationQuery) => $registrationQuery->withCount('beneficiaries'),
                    ]);
            })
            ->defaultSort('cards_delivered_at', 'desc')
            ->modelLabel('عائلة مُسلَّمة')
            ->pluralModelLabel('عائلات مُسلَّمة')
            ->emptyStateHeading('لا توجد عائلات مُسلَّمة بعد')
            ->emptyStateDescription('بعد تأكيد التسليم من صفحة «تسليم البطاقات» تظهر العائلات هنا مع العدد وجهة الاستلام والمُسلِّم.')
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
                TextColumn::make('delivery_status')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (): string => 'مُسلَّمة')
                    ->color('success'),
                TextColumn::make('cards_delivered_to')
                    ->label('جهة الاستلام')
                    ->badge()
                    ->formatStateUsing(fn (?CardDeliveryRecipient $state): string => $state?->getLabel() ?? '—')
                    ->color(fn (?CardDeliveryRecipient $state): string => match ($state) {
                        CardDeliveryRecipient::Employee => 'info',
                        CardDeliveryRecipient::Administration => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('cardsDeliveredBy.name')
                    ->label('بواسطة')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cards_delivered_at')
                    ->label('تاريخ التسليم')
                    ->dateTime('Y-m-d H:i', PrintedEmployeesPeriodExport::TIMEZONE)
                    ->sortable(),
                TextColumn::make('latestSubmittedRegistration.reference_number')
                    ->label('المرجع')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('cards_delivered_to')
                    ->label('جهة الاستلام')
                    ->options(CardDeliveryRecipient::class),
                SelectFilter::make('cards_delivered_by')
                    ->label('بواسطة')
                    ->relationship(
                        name: 'cardsDeliveredBy',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereHas('deliveredEmployees')
                            ->orderBy('name'),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('workplace')
                    ->label('الإدارة')
                    ->options(fn (): array => config('registration.workplaces', [])),
                Filter::make('cards_delivered_at')
                    ->label('تاريخ التسليم')
                    ->schema([
                        DateTimePicker::make('delivered_from')
                            ->label('من تاريخ ووقت')
                            ->native(false)
                            ->seconds(false)
                            ->timezone(PrintedEmployeesPeriodExport::TIMEZONE)
                            ->displayFormat('Y-m-d H:i'),
                        DateTimePicker::make('delivered_until')
                            ->label('إلى تاريخ ووقت')
                            ->native(false)
                            ->seconds(false)
                            ->timezone(PrintedEmployeesPeriodExport::TIMEZONE)
                            ->displayFormat('Y-m-d H:i'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['delivered_from'] ?? null;
                        $until = $data['delivered_until'] ?? null;

                        if (filled($from) && filled($until)) {
                            [$start, $end] = PrintedEmployeesPeriodExport::periodBounds($from, $until);

                            return $query->whereBetween('cards_delivered_at', [$start, $end]);
                        }

                        if (filled($from)) {
                            [$start] = PrintedEmployeesPeriodExport::periodBounds($from, $from);

                            return $query->where('cards_delivered_at', '>=', $start);
                        }

                        if (filled($until)) {
                            [, $end] = PrintedEmployeesPeriodExport::periodBounds($until, $until);

                            return $query->where('cards_delivered_at', '<=', $end);
                        }

                        return $query;
                    }),
            ])
            ->deferFilters(false)
            ->paginated([10, 25, 50, 100]);
    }
}
