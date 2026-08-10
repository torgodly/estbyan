<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الموظف')
                    ->description('تحديث بيانات أساسية فقط. الاستيراد الجماعي يتم عبر الأمر: php artisan employees:import')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('employee_number')
                            ->label('الرقم الوظيفي')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('national_id')
                            ->label('الرقم الوطني')
                            ->required()
                            ->maxLength(20),
                        Select::make('workplace')
                            ->label('مكان العمل')
                            ->options(fn (): array => config('registration.workplaces', []))
                            ->searchable()
                            ->nullable(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->required()
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }
}
