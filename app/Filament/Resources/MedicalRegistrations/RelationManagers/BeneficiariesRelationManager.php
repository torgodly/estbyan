<?php

namespace App\Filament\Resources\MedicalRegistrations\RelationManagers;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
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
            Toggle::make('has_chronic_condition')->label('مرض مزمن'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')->label('الاسم')->searchable(),
                TextColumn::make('relationship')
                    ->label('القرابة')
                    ->formatStateUsing(fn (BeneficiaryRelationship $state): string => $state->label()),
                TextColumn::make('national_id')->label('الرقم الوطني'),
                TextColumn::make('date_of_birth')->label('تاريخ الميلاد')->date(),
                TextColumn::make('blood_type')
                    ->label('فصيلة الدم')
                    ->formatStateUsing(fn (?BloodType $state): string => $state?->label() ?? '—'),
                IconColumn::make('has_chronic_condition')->label('مزمن')->boolean(),
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
