<?php

namespace App\Filament\Resources\MedicalRegistrations\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Models\User;
use App\Services\ReferenceCardGenerator;
use App\Services\RegistrationReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewMedicalRegistration extends ViewRecord
{
    protected static string $resource = MedicalRegistrationResource::class;

    protected string $view = 'filament.resources.medical-registrations.pages.view-registration';

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name ?: 'ملف الطلب';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->record->reference_number
            ? 'ملف الطلب '.$this->record->reference_number
            : 'ملف الطلب';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['employee', 'beneficiaries', 'reviewer']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        $review = app(RegistrationReviewService::class);

        return [
            Action::make('approve')
                ->label('اعتماد')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (): bool => $review->canApprove($this->record))
                ->requiresConfirmation()
                ->modalHeading('اعتماد الطلب')
                ->modalDescription('سيُقفل الطلب أمام الموظف بعد الاعتماد.')
                ->schema([
                    Textarea::make('review_note')
                        ->label('ملاحظة (اختياري)')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($review): void {
                    $reviewer = Auth::user();
                    assert($reviewer instanceof User);

                    $review->approve(
                        $this->record,
                        $reviewer,
                        $data['review_note'] ?? null,
                    );

                    $this->record->refresh()->loadMissing(['employee', 'beneficiaries', 'reviewer']);

                    Notification::make()->title('تم اعتماد الطلب')->success()->send();
                }),
            Action::make('decline')
                ->label('رفض')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => $review->canDecline($this->record))
                ->requiresConfirmation()
                ->modalHeading('رفض الطلب')
                ->modalDescription('سيتمكن الموظف من تعديل الطلب وإعادة إرساله.')
                ->schema([
                    Textarea::make('review_note')
                        ->label('سبب الرفض')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($review): void {
                    $reviewer = Auth::user();
                    assert($reviewer instanceof User);

                    $review->decline(
                        $this->record,
                        $reviewer,
                        (string) ($data['review_note'] ?? ''),
                    );

                    $this->record->refresh()->loadMissing(['employee', 'beneficiaries', 'reviewer']);

                    Notification::make()->title('تم رفض الطلب')->danger()->send();
                }),
            Action::make('viewEmployee')
                ->label('ملف الموظف')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->url(fn (): ?string => $this->record->employee_id
                    ? EmployeeResource::getUrl('view', ['record' => $this->record->employee_id])
                    : null)
                ->visible(fn (): bool => filled($this->record->employee_id)),
            Action::make('downloadReferenceCard')
                ->label('تحميل بطاقة المراجعة')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->reference_number))
                ->action(function (ReferenceCardGenerator $generator): StreamedResponse {
                    $png = $generator->png($this->record);
                    $filename = 'tax-'.$this->record->reference_number.'.png';

                    return response()->streamDownload(
                        function () use ($png): void {
                            echo $png;
                        },
                        $filename,
                        ['Content-Type' => 'image/png'],
                    );
                }),
        ];
    }
}
