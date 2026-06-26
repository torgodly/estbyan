<?php

namespace App\Filament\Resources\MedicalRegistrations\Schemas;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\RegistrationStatus;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MedicalRegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('الهوية')
                    ->columns(2)
                    ->schema([
                        TextInput::make('full_name')->label('الاسم')->required(),
                        TextInput::make('employee_number')->label('الرقم الوظيفي')->required(),
                        TextInput::make('national_id')->label('الرقم الوطني')->required(),
                        DatePicker::make('date_of_birth')->label('تاريخ الميلاد')->required(),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(collect(RegistrationStatus::cases())->mapWithKeys(
                                fn (RegistrationStatus $s) => [$s->value => $s->label()]
                            ))
                            ->required(),
                    ]),
                Section::make('بيانات الموظف')
                    ->columns(2)
                    ->schema([
                        Select::make('workplace')
                            ->label('مكان العمل')
                            ->options(config('registration.workplaces')),
                        Select::make('job_title')
                            ->label('المسمى الوظيفي')
                            ->options(config('registration.job_titles')),
                        Select::make('gender')
                            ->label('الجنس')
                            ->options(collect(Gender::cases())->mapWithKeys(
                                fn (Gender $g) => [$g->value => $g->label()]
                            )),
                        Select::make('marital_status')
                            ->label('الحالة الاجتماعية')
                            ->options(collect(MaritalStatus::cases())->mapWithKeys(
                                fn (MaritalStatus $s) => [$s->value => $s->label()]
                            )),
                        TextInput::make('beneficiaries_count')->label('عدد المستفيدين')->numeric(),
                        TextInput::make('phone')->label('الهاتف')->tel(),
                        TextInput::make('whatsapp')->label('واتساب')->tel(),
                        TextInput::make('email')->label('البريد')->email(),
                        Select::make('city')->label('المدينة')->options(config('registration.cities')),
                        TextInput::make('address')->label('العنوان')->columnSpanFull(),
                    ]),
                Section::make('السجل الطبي')
                    ->columns(2)
                    ->schema([
                        Toggle::make('has_chronic_conditions')->label('أمراض مزمنة'),
                        CheckboxList::make('chronic_conditions')
                            ->label('تفاصيل الأمراض')
                            ->options(config('registration.chronic_conditions'))
                            ->columns(2)
                            ->columnSpanFull(),
                        Toggle::make('has_tumor')->label('أورام'),
                        Toggle::make('has_surgery_history')->label('عمليات جراحية'),
                        Toggle::make('uses_medical_devices')->label('أجهزة طبية'),
                        Toggle::make('hospitalized_recently')->label('إقامة مستشفى'),
                        Toggle::make('traveled_for_treatment')->label('علاج بالخارج'),
                    ]),
                Section::make('المستندات')
                    ->schema([
                        FileUpload::make('family_status_document_path')
                            ->label('شهادة الوضع العائلي')
                            ->disk('public')
                            ->directory('registrations')
                            ->acceptedFileTypes(['application/pdf'])
                            ->downloadable()
                            ->openable(),
                        FileUpload::make('employee_photo_path')
                            ->label('صورة الموظف')
                            ->disk('public')
                            ->directory('registrations')
                            ->image()
                            ->downloadable()
                            ->openable(),
                    ]),
            ]);
    }
}
