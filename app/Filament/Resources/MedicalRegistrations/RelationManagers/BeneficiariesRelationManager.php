<?php

namespace App\Filament\Resources\MedicalRegistrations\RelationManagers;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use App\Support\RegistrationDocuments;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BeneficiariesRelationManager extends RelationManager
{
    protected static string $relationship = 'beneficiaries';

    protected static ?string $title = 'المستفيدون';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')->label('الاسم')->required(),
            Select::make('relationship')
                ->label('القرابة')
                ->options(collect(BeneficiaryRelationship::cases())->mapWithKeys(
                    fn (BeneficiaryRelationship $r) => [$r->value => $r->label()]
                ))
                ->required(),
            TextInput::make('national_id')->label('الرقم الوطني'),
            DatePicker::make('date_of_birth')->label('تاريخ الميلاد'),
            Select::make('blood_type')
                ->label('فصيلة الدم')
                ->options(collect(BloodType::cases())->mapWithKeys(
                    fn (BloodType $b) => [$b->value => $b->label()]
                )),
            FileUpload::make('photo_path')
                ->label('الصورة')
                ->disk(RegistrationDocuments::diskName())
                ->directory('registrations/beneficiaries')
                ->image()
                ->visibility('private'),
            Section::make('السجل الطبي')
                ->schema([
                    Toggle::make('has_chronic_conditions')
                        ->label('أمراض مزمنة')
                        ->live(),
                    CheckboxList::make('chronic_conditions')
                        ->label('تفاصيل الأمراض')
                        ->options(config('registration.chronic_conditions'))
                        ->columns(2)
                        ->visible(fn (Get $get): bool => (bool) $get('has_chronic_conditions')),
                    Toggle::make('has_tumor')->label('أورام'),
                    Toggle::make('has_surgery_history')->label('عمليات جراحية'),
                    Toggle::make('uses_medical_devices')->label('أجهزة طبية'),
                    Toggle::make('hospitalized_recently')->label('إقامة مستشفى'),
                    Toggle::make('traveled_for_treatment')->label('علاج بالخارج'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('الصورة')
                    ->circular()
                    ->getStateUsing(fn ($record): ?string => $record->medicalRegistration
                        ? RegistrationDocuments::beneficiaryUrl($record->medicalRegistration, $record)
                        : null),
                TextColumn::make('full_name')->label('الاسم')->searchable(),
                TextColumn::make('relationship')
                    ->label('القرابة')
                    ->formatStateUsing(fn (BeneficiaryRelationship $state): string => $state->label()),
                TextColumn::make('national_id')->label('الرقم الوطني'),
                TextColumn::make('date_of_birth')->label('تاريخ الميلاد')->date(),
                TextColumn::make('blood_type')
                    ->label('فصيلة الدم')
                    ->formatStateUsing(fn (?BloodType $state): string => $state?->label() ?? '—'),
                IconColumn::make('has_chronic_conditions')->label('مزمن')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
