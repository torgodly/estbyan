<?php

namespace App\Filament\Resources\MedicalRegistrations\Tables;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MedicalRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                ImageColumn::make('employee_photo_path')
                    ->label('الصورة')
                    ->circular()
                    ->imageSize(40)
                    ->getStateUsing(fn (MedicalRegistration $record): ?string => RegistrationDocuments::url(
                        $record,
                        RegistrationDocuments::EMPLOYEE_PHOTO,
                    ))
                    ->defaultImageUrl(url('/images/brand/tax-authority.png')),
                TextColumn::make('reference_number')
                    ->label('رقم المرجع')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_number')
                    ->label('الرقم الوظيفي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('workplace')
                    ->label('مكان العمل')
                    ->formatStateUsing(fn (?string $state, MedicalRegistration $record): string => $record->workplaceLabel() ?? '—')
                    ->toggleable(),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->formatStateUsing(fn (?string $state, MedicalRegistration $record): string => $record->cityLabel() ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),
                TextColumn::make('beneficiaries_count')
                    ->label('المستفيدون')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reviewer.name')
                    ->label('المراجع')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('pending_age')
                    ->label('عمر الانتظار')
                    ->state(function (MedicalRegistration $record): ?string {
                        if (! $record->isPendingReview() || $record->submitted_at === null) {
                            return null;
                        }

                        return $record->submitted_at->diffForHumans(syntax: true);
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(collect(RegistrationStatus::cases())->mapWithKeys(
                        fn (RegistrationStatus $status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('workplace')
                    ->label('مكان العمل')
                    ->options(fn (): array => config('registration.workplaces', [])),
                SelectFilter::make('city')
                    ->label('المدينة')
                    ->options(fn (): array => config('registration.cities', [])),
                Filter::make('submitted_at')
                    ->label('تاريخ الإرسال')
                    ->schema([
                        DatePicker::make('submitted_from')
                            ->label('من تاريخ'),
                        DatePicker::make('submitted_until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['submitted_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                $data['submitted_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('الملف'),
            ])
            ->toolbarActions([]);
    }
}
