<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\RegistrationStatus;
use App\Models\Employee;
use App\Models\User;
use App\Support\InsuranceCardNumber;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        $canManageInsuranceCards = self::canManageInsuranceCards();

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
                TextColumn::make('card_number')
                    ->label('رقم البطاقة')
                    ->formatStateUsing(fn (?string $state): string => InsuranceCardNumber::display($state))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('card_printed_at')
                    ->label('طباعة البطاقة')
                    ->badge()
                    ->getStateUsing(fn (Employee $record): bool => $record->cardIsPrinted())
                    ->formatStateUsing(fn (bool $state): string => $state ? 'طُبعت' : 'لم تُطبع')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->sortable()
                    ->toggleable()
                    ->visible($canManageInsuranceCards),
                TextColumn::make('national_id')
                    ->label('الرقم الوطني')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('workplace')
                    ->label('الإدارة')
                    ->formatStateUsing(fn (?string $state, Employee $record): string => $record->workplaceLabel() ?? '—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('office')
                    ->label('المكتب')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
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
                    ->label('الإدارة')
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
                TernaryFilter::make('card_printed_at')
                    ->label('طباعة البطاقة')
                    ->placeholder('الكل')
                    ->trueLabel('طُبعت')
                    ->falseLabel('لم تُطبع')
                    ->visible($canManageInsuranceCards)
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('card_printed_at'),
                        false: fn ($query) => $query->whereNull('card_printed_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('الملف'),
                EditAction::make()
                    ->label('تعديل'),
            ])
            ->toolbarActions([]);
    }

    private static function canManageInsuranceCards(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageInsuranceCards();
    }
}
