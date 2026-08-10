<?php

namespace App\Filament\Resources\MedicalRegistrations;

use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Filament\Resources\MedicalRegistrations\Schemas\MedicalRegistrationForm;
use App\Filament\Resources\MedicalRegistrations\Tables\MedicalRegistrationsTable;
use App\Filament\Widgets\RegistrationStatsOverview;
use App\Models\MedicalRegistration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MedicalRegistrationResource extends Resource
{
    protected static ?string $model = MedicalRegistration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'الطلبات';

    protected static ?string $modelLabel = 'طلب تسجيل';

    protected static ?string $pluralModelLabel = 'الطلبات';

    protected static string|\UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['reviewer', 'employee']);
    }

    public static function getWidgets(): array
    {
        return [
            RegistrationStatsOverview::class,
        ];
    }

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalRegistrations::route('/'),
            'view' => ViewMedicalRegistration::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
