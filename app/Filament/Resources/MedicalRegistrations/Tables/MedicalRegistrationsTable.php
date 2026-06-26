<?php

namespace App\Filament\Resources\MedicalRegistrations\Tables;

use App\Enums\RegistrationStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MedicalRegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_number')
                    ->label('رقم المرجع')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_number')
                    ->label('الرقم الوظيفي')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => $state->color()),
                TextColumn::make('beneficiaries_count')
                    ->label('المستفيدون')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('—'),
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
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
