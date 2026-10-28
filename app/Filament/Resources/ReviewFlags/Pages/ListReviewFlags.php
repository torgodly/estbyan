<?php

namespace App\Filament\Resources\ReviewFlags\Pages;

use App\Filament\Resources\ReviewFlags\ReviewFlagResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListReviewFlags extends ListRecords
{
    protected static string $resource = ReviewFlagResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'أشخاص لم يتأكد النظام منهم. لا تطبع أو تسلّم بطاقاتهم قبل التحقق من الوثائق الأصلية.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
