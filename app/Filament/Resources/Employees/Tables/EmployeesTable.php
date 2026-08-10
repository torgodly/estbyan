<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\RegistrationStatus;
use App\Models\Employee;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('full_name')
            ->columns([
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
                    ->searchable(),
                TextColumn::make('workplace')
                    ->label('مكان العمل')
                    ->formatStateUsing(fn (?string $state, Employee $record): string => $record->workplaceLabel() ?? '—')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                TextColumn::make('medical_registrations_count')
                    ->label('الطلبات')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('latestMedicalRegistration.status')
                    ->label('آخر حالة')
                    ->badge()
                    ->formatStateUsing(fn (?RegistrationStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?RegistrationStatus $state): string => $state?->color() ?? 'gray')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('workplace')
                    ->label('مكان العمل')
                    ->options(fn (): array => config('registration.workplaces', [])),
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('الملف'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }
}
