<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\RegistrationStatus;
use App\Models\Employee;
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
                    ->sortable()
                    ->description(fn (Employee $record): string => $record->employee_number),
                TextColumn::make('employee_number')
                    ->label('الرقم الوظيفي')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('workplace')
                    ->label('مكان العمل')
                    ->formatStateUsing(fn (?string $state, Employee $record): string => $record->workplaceLabel() ?? '—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('has_submitted_form')
                    ->label('تعبئة النموذج')
                    ->badge()
                    ->getStateUsing(fn (Employee $record): bool => $record->hasSubmittedForm())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'أرسل النموذج' : 'لم يرسل')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('has_submitted_form', $direction);
                    }),
                TextColumn::make('latestSubmittedRegistration.status')
                    ->label('حالة الطلب')
                    ->badge()
                    ->formatStateUsing(fn (?RegistrationStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?RegistrationStatus $state): string => $state?->color() ?? 'gray')
                    ->placeholder('—'),
                TextColumn::make('latestSubmittedRegistration.submitted_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('latestSubmittedRegistration.reference_number')
                    ->label('المرجع')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('medical_registrations_count')
                    ->label('عدد الطلبات')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('workplace')
                    ->label('مكان العمل')
                    ->options(fn (): array => config('registration.workplaces', [])),
                TernaryFilter::make('has_submitted_form')
                    ->label('تعبئة النموذج')
                    ->placeholder('الكل')
                    ->trueLabel('أرسلوا النموذج')
                    ->falseLabel('لم يرسلوا')
                    ->queries(
                        true: fn ($query) => $query->submittedForm(),
                        false: fn ($query) => $query->notSubmittedForm(),
                        blank: fn ($query) => $query,
                    ),
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('الملف'),
            ])
            ->toolbarActions([]);
    }
}
