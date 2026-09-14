<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use App\Support\EmployeeNumber;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات الموظف')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('employee_number')
                            ->label('الرقم الوظيفي')
                            ->numeric()
                            ->maxLength(50)
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'create'
                                ? 'اتركه فارغاً ليُولَّد تلقائياً'
                                : null)
                            ->rule(static function (TextInput $component): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $employeeNumber = EmployeeNumber::normalize((string) $value) ?? (string) $value;

                                    $query = Employee::query()->where('employee_number', $employeeNumber);

                                    $record = $component->getRecord();

                                    if ($record instanceof Model) {
                                        $query->whereKeyNot($record->getKey());
                                    }

                                    if ($query->exists()) {
                                        $fail(__('validation.unique', ['attribute' => $attribute]));
                                    }
                                };
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => EmployeeNumber::normalize($state)),
                        TextInput::make('national_id')
                            ->label('الرقم الوطني')
                            ->required()
                            ->rule('digits:12')
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : $state),
                        Select::make('workplace')
                            ->label('الإدارة')
                            ->options(config('registration.workplaces'))
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(1),
            ]);
    }
}
