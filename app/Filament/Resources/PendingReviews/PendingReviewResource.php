<?php

namespace App\Filament\Resources\PendingReviews;

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\Tables\MedicalRegistrationsTable;
use App\Filament\Resources\PendingReviews\Pages\ListPendingReviews;
use App\Filament\Resources\PendingReviews\Pages\ViewPendingReview;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\ReviewerQueueSplitter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PendingReviewResource extends Resource
{
    protected static ?string $model = MedicalRegistration::class;

    protected static ?string $slug = 'pending-reviews';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'طلبات بانتظار المراجعة';

    protected static ?string $modelLabel = 'طلب';

    protected static ?string $pluralModelLabel = 'طلبات بانتظار المراجعة';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['reviewer', 'employee'])
            ->where('status', RegistrationStatus::Submitted);

        $user = Auth::user();

        if ($user instanceof User && $user->isReviewer()) {
            ReviewerQueueSplitter::constrain($query, $user);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return MedicalRegistrationsTable::configureReviewQueue($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendingReviews::route('/'),
            'view' => ViewPendingReview::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return User::authenticatedIsReviewer();
    }

    public static function canView(Model $record): bool
    {
        $user = Auth::user();

        return $record instanceof MedicalRegistration
            && $record->isPendingReview()
            && $user instanceof User
            && ReviewerQueueSplitter::owns($user, $record);
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
