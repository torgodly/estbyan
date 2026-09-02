<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use Closure;
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
                            ->required()
                            ->numeric()
                            ->maxLength(50)
                            ->rule(static function (TextInput $component): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $employeeNumber = ctype_digit((string) $value)
                                        ? str_pad((string) $value, 6, '0', STR_PAD_LEFT)
                                        : (string) $value;

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
                            ->dehydrateStateUsing(function (?string $state): ?string {
                                if ($state === null || $state === '' || ! ctype_digit($state)) {
                                    return $state;
                                }

                                return str_pad($state, 6, '0', STR_PAD_LEFT);
                            }),
                        TextInput::make('national_id')
                            ->label('الرقم الوطني')
                            ->required()
                            ->rule('digits:12')
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : $state),
                    ])
                    ->columns(1),
            ]);
    }
}
