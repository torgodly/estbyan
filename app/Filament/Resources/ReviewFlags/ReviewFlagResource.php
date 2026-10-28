<?php

namespace App\Filament\Resources\ReviewFlags;

use App\Filament\Resources\ReviewFlags\Pages\ListReviewFlags;
use App\Filament\Resources\ReviewFlags\Tables\ReviewFlagsTable;
use App\Models\ReviewFlag;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * People the system was not sure about (possible duplicates).
 *
 * @see docs/cancelled-family-members.md
 */
class ReviewFlagResource extends Resource
{
    protected static ?string $model = ReviewFlag::class;

    protected static ?string $slug = 'manual-review';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'مراجعة يدوية';

    protected static ?string $modelLabel = 'حالة مراجعة';

    protected static ?string $pluralModelLabel = 'حالات تحتاج مراجعة يدوية';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 7;

    public static function getNavigationBadge(): ?string
    {
        $open = ReviewFlag::query()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return ReviewFlagsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewFlags::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
