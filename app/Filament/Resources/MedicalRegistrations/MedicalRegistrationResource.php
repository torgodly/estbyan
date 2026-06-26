<?php

namespace App\Filament\Resources\MedicalRegistrations;

use App\Filament\Resources\MedicalRegistrations\Pages\EditMedicalRegistration;
use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Filament\Resources\MedicalRegistrations\RelationManagers\BeneficiariesRelationManager;
use App\Filament\Resources\MedicalRegistrations\Schemas\MedicalRegistrationForm;
use App\Filament\Resources\MedicalRegistrations\Tables\MedicalRegistrationsTable;
use App\Models\MedicalRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MedicalRegistrationResource extends Resource
{
    protected static ?string $model = MedicalRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'طلبات التسجيل';

    protected static ?string $modelLabel = 'طلب تسجيل';

    protected static ?string $pluralModelLabel = 'طلبات التسجيل';

    protected static string|\UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return MedicalRegistrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalRegistrationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BeneficiariesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalRegistrations::route('/'),
            'view' => ViewMedicalRegistration::route('/{record}'),
            'edit' => EditMedicalRegistration::route('/{record}/edit'),
        ];
    }
}
