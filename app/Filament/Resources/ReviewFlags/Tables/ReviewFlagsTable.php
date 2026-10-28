<?php

namespace App\Filament\Resources\ReviewFlags\Tables;

use App\Enums\ReviewFlagReason;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Models\Beneficiary;
use App\Models\ReviewFlag;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReviewFlagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['flaggable', 'resolvedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('person')
                    ->label('الاسم')
                    ->state(fn (ReviewFlag $record): string => $record->personName())
                    ->description(fn (ReviewFlag $record): string => self::personDescription($record)),
                TextColumn::make('reason')
                    ->label('السبب')
                    ->badge(),
                TextColumn::make('note')
                    ->label('التفاصيل')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->state(fn (ReviewFlag $record): string => $record->isResolved() ? 'تمت المراجعة' : 'بانتظار المراجعة')
                    ->color(fn (ReviewFlag $record): string => $record->isResolved() ? 'success' : 'danger')
                    ->description(fn (ReviewFlag $record): ?string => $record->isResolved()
                        ? trim(($record->resolvedBy?->name ?? '').' '.($record->resolution_note ?? ''))
                        : null),
                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('resolved')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('تمت المراجعة')
                    ->falseLabel('بانتظار المراجعة')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('reason')
                    ->label('السبب')
                    ->options(ReviewFlagReason::class),
            ])
            ->recordActions([
                Action::make('openRegistration')
                    ->label('فتح الطلب')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (ReviewFlag $record): ?string => ($registration = $record->registration())
                        ? MedicalRegistrationResource::getUrl('view', ['record' => $registration])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (ReviewFlag $record): bool => $record->registration() !== null),
                Action::make('resolve')
                    ->label('تمت المراجعة')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (ReviewFlag $record): bool => ! $record->isResolved())
                    ->schema([
                        Textarea::make('resolution_note')
                            ->label('ماذا وجدت / ماذا فعلت؟')
                            ->rows(3),
                    ])
                    ->action(function (ReviewFlag $record, array $data): void {
                        $user = Auth::user();
                        assert($user instanceof User);

                        $record->resolve($user, filled($data['resolution_note'] ?? null) ? $data['resolution_note'] : null);

                        Notification::make()
                            ->title('تم تسجيل المراجعة')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    private static function personDescription(ReviewFlag $record): string
    {
        $person = $record->flaggable;
        $employee = $record->employee();

        if ($person instanceof Beneficiary) {
            $relationship = $person->relationship?->label($person->medicalRegistration?->gender) ?? 'فرد عائلة';

            return sprintf('%s · ملف الموظف %s · %s', $relationship, $employee?->employee_number ?? '—', $person->identityLabel());
        }

        return sprintf('موظف · %s · %s', $employee?->employee_number ?? '—', $employee?->national_id ?? '—');
    }
}
